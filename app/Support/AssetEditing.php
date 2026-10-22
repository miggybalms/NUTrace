<?php

namespace App\Support;

use App\Models\User;
use App\Support\Import\AssetImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Correcting an asset's record after it exists.
 *
 * The importer deliberately writes only what the office's registry said. The
 * rest - a missing serial number, the right room, the person who is actually
 * accountable - is filled in later from the asset's own page, which is where a
 * scanned sticker lands.
 *
 * Two things this must never do:
 *
 *  - change `Asset_code`, because that code is on a printed sticker and in the
 *    QR payload; a new code is a new sticker, which is a separate decision.
 *  - put an employee id, or a typed name, into `assets.user_id`. Only a real,
 *    active NU Trace account can be assigned; otherwise the record keeps the
 *    accountable name and stays unassigned.
 */
class AssetEditing
{
    public const COMPLETION_STATUSES = ['Complete', 'Needs completion', 'Under review'];

    public const CONDITIONS = ['New', 'Excellent', 'Good', 'Fair', 'Existing'];

    /** Departments an asset may belong to. */
    public static function departments()
    {
        return DB::table('departments')->orderBy('Name')->get(['id', 'Name', 'status']);
    }

    /**
     * Accounts an asset may be assigned to: an active account whose employee
     * record is also active. That is the same eligibility the importer uses.
     */
    public static function people()
    {
        return DB::table('users')
            ->join('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('users.status', 'Active')
            ->where('employee_numbers.status', 'Active')
            ->orderBy('employee_numbers.Full_Name')
            ->get([
                'users.id',
                'employee_numbers.Full_Name as name',
                'employee_numbers.Employee_number as number',
            ]);
    }

    public static function rules(): array
    {
        return [
            'Asset_name'          => ['required', 'string', 'max:150'],
            'Category'            => ['required', 'in:' . implode(',', AssetImport::CATEGORIES)],
            'Condition'           => ['required', 'in:' . implode(',', self::CONDITIONS)],
            'asset_location'      => ['nullable', 'string', 'max:255'],
            'accusion_date'       => ['nullable', 'date'],
            'purchase_Price'      => ['nullable', 'numeric', 'min:0'],
            'property_id_no'      => ['nullable', 'string', 'max:100'],
            'inventory_id_no'     => ['nullable', 'string', 'max:100'],
            'po_no'               => ['nullable', 'string', 'max:100'],
            'department_id'       => ['nullable', 'integer', 'exists:departments,id'],
            'accountable_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'accountable_name'    => ['nullable', 'string', 'max:150'],
            'completion_status'   => ['required', 'in:' . implode(',', self::COMPLETION_STATUSES)],
        ];
    }

    /**
     * @return array{ok: bool, message?: string, errors?: array<string, string>}
     */
    public static function update(int $assetId, array $input, ?User $actor): array
    {
        $asset = DB::table('assets')->where('id', $assetId)->first();

        if (! $asset) {
            return ['ok' => false, 'errors' => ['Asset_name' => 'That asset no longer exists.']];
        }

        $data = [
            'Asset_name'          => trim((string) ($input['Asset_name'] ?? '')),
            'Category'            => trim((string) ($input['Category'] ?? '')),
            'Condition'           => trim((string) ($input['Condition'] ?? '')),
            'asset_location'      => trim((string) ($input['asset_location'] ?? '')),
            'accusion_date'       => trim((string) ($input['accusion_date'] ?? '')),
            'purchase_Price'      => trim((string) ($input['purchase_Price'] ?? '')),
            'property_id_no'      => trim((string) ($input['property_id_no'] ?? '')),
            'inventory_id_no'     => trim((string) ($input['inventory_id_no'] ?? '')),
            'po_no'               => trim((string) ($input['po_no'] ?? '')),
            'department_id'       => $input['department_id'] ?? null,
            'accountable_user_id' => $input['accountable_user_id'] ?? null,
            'accountable_name'    => trim((string) ($input['accountable_name'] ?? '')),
            'completion_status'   => trim((string) ($input['completion_status'] ?? 'Complete')),
        ];

        $validator = Validator::make($data, self::rules(), [], [
            'Asset_name' => 'asset name',
            'accusion_date' => 'acquisition date',
            'purchase_Price' => 'purchase price',
            'property_id_no' => 'Property ID No.',
            'accountable_user_id' => 'accountable person',
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'errors' => $validator->errors()->toArray()];
        }

        // The property number identifies the same physical asset; two rows may
        // not answer to it. The database index says the same thing, but the
        // office gets a sentence instead of a 500.
        if ($data['property_id_no'] !== '') {
            $clash = DB::table('assets')
                ->whereRaw('LOWER(property_id_no) = ?', [mb_strtolower($data['property_id_no'])])
                ->where('id', '<>', $assetId)
                ->first(['id', 'Asset_code']);

            if ($clash) {
                return ['ok' => false, 'errors' => [
                    'property_id_no' => 'Property ID No. ' . $data['property_id_no'] . ' is already on ' . $clash->Asset_code . '.',
                ]];
            }
        }

        $user = null;

        if ($data['accountable_user_id']) {
            $user = DB::table('users')
                ->join('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
                ->where('users.id', (int) $data['accountable_user_id'])
                ->first(['users.id', 'users.status as user_status', 'employee_numbers.Full_Name', 'employee_numbers.Employee_number', 'employee_numbers.status as employee_status']);

            if (! $user || strtolower((string) $user->user_status) !== 'active' || strtolower((string) $user->employee_status) !== 'active') {
                return ['ok' => false, 'errors' => [
                    'accountable_user_id' => 'That account is not active, or its employee record is not active, so the asset cannot be assigned to it.',
                ]];
            }
        }

        $updates = [
            'Asset_name'        => $data['Asset_name'],
            'Category'          => $data['Category'],
            'Condition'         => $data['Condition'],
            'asset_location'    => $data['asset_location'] !== '' ? $data['asset_location'] : null,
            'accusion_date'     => $data['accusion_date'] !== '' ? $data['accusion_date'] : null,
            'purchase_Price'    => $data['purchase_Price'] !== '' ? $data['purchase_Price'] : null,
            'property_id_no'    => $data['property_id_no'] !== '' ? $data['property_id_no'] : null,
            'inventory_id_no'   => $data['inventory_id_no'] !== '' ? $data['inventory_id_no'] : null,
            'po_no'             => $data['po_no'] !== '' ? $data['po_no'] : null,
            'department_id'     => $data['department_id'] ? (int) $data['department_id'] : null,
            'accountable_name'  => $data['accountable_name'] !== ''
                ? $data['accountable_name']
                : ($user->Full_Name ?? null),
            'completion_status' => $data['completion_status'],
            'user_id'           => $user?->id,
            'updated_at'        => now(),
        ];

        // What actually changed, for the audit trail. A note that names the
        // fields is worth more than "asset updated".
        $changed = [];

        foreach ($updates as $column => $value) {
            if ($column === 'updated_at') {
                continue;
            }

            $before = $asset->{$column} ?? null;
            $after = $value;

            if ((string) $before !== (string) $after && ! (is_null($before) && $after === null)) {
                $changed[] = $column;
            }
        }

        if (in_array($asset->Lifecycle_Status, [null, ''], true) && empty($asset->Lifecycle_Status)) {
            $updates['Lifecycle_Status'] = 'Acquired';
        }

        try {
            DB::transaction(function () use ($assetId, $asset, $updates, $user, $actor, $changed) {
                DB::table('assets')->where('id', $assetId)->update($updates);

                // Assignment history is the accountability table's job. Moving
                // the holder closes the old row and opens a new one; it never
                // rewrites the past.
                if ((int) ($asset->user_id ?? 0) !== (int) ($user->id ?? 0)) {
                    DB::table('asset_accountability')
                        ->where('asset_id', $assetId)
                        ->where('Is_Current', 1)
                        ->update(['Is_Current' => 0, 'updated_at' => now()]);

                    if ($user) {
                        DB::table('asset_accountability')->insert([
                            'asset_id'    => $assetId,
                            'user_id'     => $user->id,
                            'request_id'  => null,
                            'Assign_date' => now(),
                            'Is_Current'  => 1,
                            'notes'       => 'Assigned while completing the asset record',
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                    }
                }

                self::audit($actor, $assetId, $asset->Asset_code, $user, $changed);
            });
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'errors' => ['Asset_name' => 'That change could not be saved: ' . mb_substr($e->getMessage(), 0, 200)]];
        }

        return ['ok' => true, 'message' => $changed === []
            ? 'Nothing changed.'
            : 'Asset record updated (' . implode(', ', $changed) . ').'];
    }

    private static function audit(?User $actor, int $assetId, ?string $code, ?object $user, array $changed): void
    {
        try {
            DB::table('audit_logs')->insert([
                'user_id'            => $actor?->id,
                'request_id'         => null,
                'asset_id'           => $assetId,
                'action_type'        => 'UPDATE',
                'notes'              => 'Updated ' . ($code ?: 'asset') . ': ' . (empty($changed) ? 'no field changed' : implode(', ', $changed))
                    . ($user ? ' · accountable person set to ' . $user->Full_Name . ' (' . $user->Employee_number . ')' : ''),
                'action_description' => 'Asset record corrected',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Asset edit audit entry failed: ' . $e->getMessage());
        }
    }
}
