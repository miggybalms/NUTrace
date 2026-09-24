<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Employee relocation: one employee hands their assigned assets to another.
 *
 * The assets themselves never change — the same Asset ID, Asset Code, QR Code,
 * acquisition data, lifespan and repair/replacement history stay with the asset.
 * Only `assets.user_id` and the assignment history in `asset_accountability`
 * move, so the transfer stays traceable in both directions.
 *
 * A transfer is stored as a normal `requests` row with request_type = Transfer
 * and status = Approved (a relocation already carries the Asset Management
 * Office's approval), the assets go into `request_items`, and the reason is
 * kept in `requests.Note` — no new tables and no schema change.
 */
class AssetTransfer
{
    /** Reasons the Admin can pick from on the confirmation screen. */
    public const REASONS = [
        'Employee Relocation',
        'Position Reassignment',
        'Employee Replacement',
        'Department Transfer',
        'Other',
    ];

    /**
     * Lifecycle states in which the asset is no longer in the employee's
     * custody, so it must not travel with them.
     */
    public const NOT_CUSTODY = ['Disposal', 'Pullout'];

    /**
     * One query for every asset still in someone's custody, grouped by employee.
     * The transfer page shows these, so it loads them once instead of per row.
     */
    public static function custodyByUser(): Collection
    {
        return DB::table('assets')
            ->whereNotIn('Lifecycle_Status', self::NOT_CUSTODY)
            ->orderBy('Asset_name')
            ->orderBy('Asset_code')
            ->get([
                'id', 'user_id', 'Asset_code', 'Asset_name', 'Category',
                'Condition', 'Lifecycle_Status', 'asset_location', 'accusion_date',
            ])
            ->groupBy('user_id');
    }

    /** How many assets each employee holds, including the ones that can't move. */
    public static function heldCounts(): Collection
    {
        return DB::table('assets')
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');
    }

    /**
     * Employees who have handed assets over in a completed transfer.
     *
     * "Has no assets left" is not the same as "was relocated" — an employee can
     * still hold other equipment. The transfer history is what proves it, so the
     * status is read from the requests the Admin has completed (and from the
     * closed accountability rows those transfers leave behind).
     *
     * @return array<int, int>
     */
    public static function reassignedUserIds(): array
    {
        $transferRequestIds = DB::table('requests')
            ->where('request_type', 'Transfer')
            ->pluck('id');

        $fromRequests = DB::table('requests')
            ->where('request_type', 'Transfer')
            ->where('status', 'Approved')
            ->pluck('user_id');

        $fromAccountability = DB::table('asset_accountability')
            ->where('Is_Current', 0)
            ->whereIn('request_id', $transferRequestIds)
            ->pluck('user_id');

        return $fromRequests
            ->merge($fromAccountability)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every completed/cancelled transfer, newest first, with both employees,
     * the assets involved and who recorded it.
     */
    public static function history(): Collection
    {
        $transfers = DB::table('requests')
            ->where('request_type', 'Transfer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        if ($transfers->isEmpty()) {
            return collect();
        }

        $requestIds = $transfers->pluck('id')->all();

        $assetsByRequest = DB::table('request_items')
            ->join('assets', 'request_items.asset_id', '=', 'assets.id')
            ->whereIn('request_items.request_id', $requestIds)
            ->orderBy('assets.Asset_name')
            ->get([
                'request_items.request_id',
                'assets.id',
                'assets.Asset_code',
                'assets.Asset_name',
            ])
            ->groupBy('request_id');

        // Older single-asset transfers predate request_items.
        $fallbackAssets = DB::table('assets')
            ->whereIn('id', $transfers->pluck('asset_id')->filter()->all())
            ->get(['id', 'Asset_code', 'Asset_name'])
            ->keyBy('id');

        $names = self::userLabels();

        $recordedBy = DB::table('audit_logs')
            ->whereIn('request_id', $requestIds)
            ->where('action_type', 'TRANSFER')
            ->orderBy('id')
            ->get(['request_id', 'user_id'])
            ->keyBy('request_id');

        return $transfers->map(function ($transfer) use ($assetsByRequest, $fallbackAssets, $names, $recordedBy) {
            $items = $assetsByRequest->get($transfer->id);

            if ($items === null && $transfer->asset_id && $fallbackAssets->has($transfer->asset_id)) {
                $fallback = $fallbackAssets->get($transfer->asset_id);
                $items = collect([(object) [
                    'id'         => $fallback->id,
                    'Asset_code' => $fallback->Asset_code,
                    'Asset_name' => $fallback->Asset_name,
                ]]);
            }

            $items = ($items ?? collect())->map(fn ($item) => [
                'id'   => (int) $item->id,
                'code' => $item->Asset_code,
                'name' => $item->Asset_name,
            ])->values();

            $actorId = optional($recordedBy->get($transfer->id))->user_id;

            return [
                'id'           => (int) $transfer->id,
                'reference'    => 'TR-' . str_pad((string) $transfer->id, 4, '0', STR_PAD_LEFT),
                'status'       => (string) $transfer->status,
                'from'         => $names[(int) $transfer->user_id] ?? null,
                'to'           => $names[(int) $transfer->assign_to_user_id] ?? null,
                'reason'       => $transfer->Note,
                'notes'        => $transfer->admin_remarks,
                'assets'       => $items->all(),
                'asset_count'  => $items->count(),
                'date'         => $transfer->created_at,
                'recorded_by'  => $actorId ? ($names[(int) $actorId]['name'] ?? null) : null,
            ];
        });
    }

    /**
     * Names, employee numbers and departments for every user, keyed by user id.
     */
    public static function userLabels(): array
    {
        $rows = DB::table('users')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('departments as employee_department', 'employee_numbers.Department_id', '=', 'employee_department.id')
            ->orderBy('employee_numbers.Full_Name')
            ->get([
                'users.id',
                'users.email',
                'users.role',
                'users.department_id',
                'employee_numbers.Full_Name as full_name',
                'employee_numbers.Employee_number as employee_number',
                'departments.Name as department',
                'employee_department.Name as employee_department',
            ]);

        $labels = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row->full_name ?: $row->email));

            $labels[(int) $row->id] = [
                'id'              => (int) $row->id,
                'name'            => $name !== '' ? $name : ('User #' . $row->id),
                'email'           => $row->email,
                'role'            => $row->role,
                'employee_number' => $row->employee_number,
                'department'      => $row->department ?: $row->employee_department,
                'department_id'   => $row->department_id,
            ];
        }

        return $labels;
    }

    /**
     * Perform the relocation.
     *
     * Everything happens in one transaction: the transfer request, its assets,
     * the closed/open accountability rows, the assets' new custodian, the audit
     * trail and the two notifications. Returns the transfer's request id.
     *
     * @param  array<int, int>  $assetIds
     *
     * @throws RuntimeException when an asset is no longer the sender's to give away
     */
    public static function create(
        int $fromUserId,
        int $toUserId,
        array $assetIds,
        string $reason,
        ?string $notes = null,
        ?int $adminId = null
    ): int {
        if ($fromUserId === $toUserId) {
            throw new RuntimeException('Choose a different employee to receive the assets.');
        }

        $assetIds = array_values(array_unique(array_map('intval', $assetIds)));

        if (empty($assetIds)) {
            throw new RuntimeException('Select at least one asset to transfer.');
        }

        return DB::transaction(function () use ($fromUserId, $toUserId, $assetIds, $reason, $notes, $adminId) {
            $assets = DB::table('assets')
                ->whereIn('id', $assetIds)
                ->lockForUpdate()
                ->get(['id', 'user_id', 'Asset_code', 'Asset_name', 'Lifecycle_Status']);

            if ($assets->count() !== count($assetIds)) {
                throw new RuntimeException('One or more of the selected assets no longer exist.');
            }

            $foreign = $assets->where('user_id', '!=', $fromUserId);

            if ($foreign->isNotEmpty()) {
                throw new RuntimeException(
                    'These assets are no longer assigned to the employee you are transferring from: '
                    . $foreign->pluck('Asset_code')->implode(', ')
                    . '. Reload the page and try again.'
                );
            }

            $blocked = $assets->whereIn('Lifecycle_Status', self::NOT_CUSTODY);

            if ($blocked->isNotEmpty()) {
                throw new RuntimeException(
                    'These assets have left the employee’s custody and cannot be transferred: '
                    . $blocked->pluck('Asset_code')->implode(', ') . '.'
                );
            }

            $names = self::userLabels();
            $fromName = $names[$fromUserId]['name'] ?? ('User #' . $fromUserId);
            $toName = $names[$toUserId]['name'] ?? ('User #' . $toUserId);

            // The transfer transaction itself: an approved Transfer request whose
            // assets live in request_items, because it carries more than one.
            $requestId = DB::table('requests')->insertGetId([
                'user_id'            => $fromUserId,
                'asset_id'           => null,
                'request_type'       => 'Transfer',
                'status'             => 'Approved',
                'Note'               => $reason,
                'assign_to_user_id'  => $toUserId,
                'admin_remarks'      => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'admin_remarks_by'   => $adminId,
                'admin_remarks_at'   => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            foreach ($assets as $asset) {
                DB::table('request_items')->insert([
                    'request_id' => $requestId,
                    'asset_id'   => $asset->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            self::applyAccountability(
                $requestId,
                $fromUserId,
                $toUserId,
                $assets->pluck('id')->all(),
                $reason,
                $notes
            );

            DB::table('assets')->whereIn('id', $assets->pluck('id')->all())->update([
                'user_id'    => $toUserId,
                'updated_at' => now(),
            ]);

            // Per-asset audit rows: the asset's own history is where anyone looks
            // for "who was accountable for this before me".
            foreach ($assets as $asset) {
                DB::table('audit_logs')->insert([
                    'user_id'            => $adminId,
                    'request_id'         => $requestId,
                    'asset_id'           => $asset->id,
                    'action_type'        => 'TRANSFER',
                    'notes'              => 'Transferred ' . $asset->Asset_code . ' from ' . $fromName
                        . ' to ' . $toName . ' (' . $reason . ')',
                    'action_description' => 'Transfer ' . $requestId . ': ' . $asset->Asset_code
                        . ' — ' . $asset->Asset_name . ' reassigned to ' . $toName,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            $count = $assets->count();
            $label = $count . ' asset' . ($count === 1 ? '' : 's');

            // Both sides of the relocation hear about it.
            Notifications::create(
                $toUserId,
                'Assets Transferred To You',
                $label . ' from ' . $fromName . ' ' . ($count === 1 ? 'is' : 'are') . ' now assigned to you.',
                'TRANSFER',
                $requestId,
                'request'
            );

            Notifications::create(
                $fromUserId,
                'Assets Transferred Out',
                $label . ' you held ' . ($count === 1 ? 'was' : 'were') . ' reassigned to ' . $toName . '.',
                'TRANSFER',
                $requestId,
                'request'
            );

            return (int) $requestId;
        });
    }

    /**
     * Close the previous assignment row and open the new one for each asset.
     *
     * `asset_accountability` may still be empty on an install that predates this
     * module, so when there is no open row for an asset the previous holder is
     * recovered from `assets.user_id` and written down first — otherwise the
     * history would silently start with the new employee and the previous
     * accountability would be lost.
     *
     * @param  array<int, int>  $assetIds
     */
    public static function applyAccountability(
        int $requestId,
        ?int $fromUserId,
        int $toUserId,
        array $assetIds,
        string $reason,
        ?string $notes = null
    ): void {
        foreach ($assetIds as $assetId) {
            $current = DB::table('asset_accountability')
                ->where('asset_id', $assetId)
                ->where('Is_Current', 1)
                ->get();

            if ($current->isNotEmpty()) {
                DB::table('asset_accountability')
                    ->whereIn('id', $current->pluck('id')->all())
                    ->update(['Is_Current' => 0, 'updated_at' => now()]);
            } else {
                $previousOwner = $fromUserId
                    ?: DB::table('assets')->where('id', $assetId)->value('user_id');

                if ($previousOwner) {
                    $assignedAt = DB::table('assets')->where('id', $assetId)->value('created_at');

                    DB::table('asset_accountability')->insert([
                        'asset_id'        => $assetId,
                        'user_id'         => (int) $previousOwner,
                        'request_id'      => $requestId,
                        'Assign_date'     => $assignedAt ?: now(),
                        'Is_Current'      => 0,
                        'transfer_reason' => mb_substr($reason, 0, 100),
                        'notes'           => 'Closed by the transfer recorded in request #' . $requestId . '.',
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            DB::table('asset_accountability')->insert([
                'asset_id'        => $assetId,
                'user_id'         => $toUserId,
                'request_id'      => $requestId,
                'Assign_date'     => now(),
                'Is_Current'      => 1,
                'transfer_reason' => mb_substr($reason, 0, 100),
                'notes'           => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }
    }
}
