<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Disposal records and the request-driven disposal workflow.
 *
 * The request is the source of the disposal transaction: approving a Disposal
 * request creates the record, marks the asset Disposed and leaves the asset row
 * — and everything hanging off it — in place. Nothing here ever deletes an
 * asset or a disposal record; records are archived, never removed.
 */
class Disposals
{
    /**
     * Lifecycle value written when an asset is retired.
     *
     * The UI labels it "Disposed", but the CHECK constraint on
     * assets.Lifecycle_Status only accepts 'Disposal' — the value the rest of
     * the system filters on (counts, transfer rules, visibility).
     */
    public const DISPOSED_STATUS = 'Disposal';

    /** Cached answer for "has the archive migration been run?" */
    private static ?bool $archiveColumnsReady = null;

    /**
     * Whether disposals carries the archive columns.
     *
     * The code ships before `php artisan migrate` runs on the server, so every
     * archive read/write checks this first instead of blowing up on a column
     * that does not exist yet. Once the migration has run the answer is cached
     * and this costs nothing.
     */
    public static function archiveColumnsReady(): bool
    {
        if (self::$archiveColumnsReady === null) {
            self::$archiveColumnsReady = Schema::hasColumn('disposals', 'is_archived')
                && Schema::hasColumn('disposals', 'archived_at')
                && Schema::hasColumn('disposals', 'archived_by');
        }

        return self::$archiveColumnsReady;
    }

    /**
     * Disposal records joined to their asset, request and requester.
     *
     * @param  bool  $archived  true => Archived Disposal Assets, false => main Disposal list
     */
    public static function query(bool $archived = false): Builder
    {
        $query = DB::table('disposals')
            ->leftJoin('assets', 'disposals.Asset_id', '=', 'assets.id')
            ->leftJoin('requests', 'disposals.Request_id', '=', 'requests.id')
            ->leftJoin('users as requester', 'requests.user_id', '=', 'requester.id')
            ->leftJoin('employee_numbers as requester_emp', 'requester.employee_numbers_id', '=', 'requester_emp.id');

        if (self::archiveColumnsReady()) {
            $query
                ->leftJoin('users as archiver', 'disposals.archived_by', '=', 'archiver.id')
                ->leftJoin('employee_numbers as archiver_emp', 'archiver.employee_numbers_id', '=', 'archiver_emp.id');

            if ($archived) {
                $query->where('disposals.is_archived', true);
            } else {
                // Rows written before the archive migration have no value at all.
                $query->where(function (Builder $q) {
                    $q->where('disposals.is_archived', false)->orWhereNull('disposals.is_archived');
                });
            }
        } elseif ($archived) {
            // Nothing is archived until the migration has been run.
            $query->whereRaw('1 = 0');
        }

        $columns = [
            'disposals.*',
            DB::raw('COALESCE(assets."Asset_name", disposals."Description") as asset_name'),
            DB::raw('COALESCE(assets."Asset_code", \'N/A\') as asset_code'),
            DB::raw('assets."purchase_Price" as original_value'),
            DB::raw('assets."Category" as asset_category'),
            DB::raw('assets."Condition" as asset_condition'),
            DB::raw('assets."serial_Number" as asset_serial'),
            DB::raw('assets."asset_location" as asset_location'),
            DB::raw('CASE WHEN assets.id IS NULL THEN 0 ELSE 1 END as asset_still_exists'),
            'requests.user_id as requester_id',
            'requests.Note as request_note',
            'requests.status as request_status',
            'requests.created_at as request_date',
            'requests.url as request_file_url',
            'requester.email as requester_email',
            DB::raw('requester_emp."Full_Name" as requester_name'),
        ];

        if (self::archiveColumnsReady()) {
            $columns[] = 'archiver.email as archived_by_email';
            $columns[] = DB::raw('archiver_emp."Full_Name" as archived_by_name');
        }

        return $query->select($columns);
    }

    /**
     * Normalise a row from query() into what the Blade views read.
     */
    public static function present(object $row): object
    {
        // PostgreSQL keeps the case of `disposals.*` columns, so the lowercase
        // names the views read are filled in here.
        $row->id                 = $row->Disposal_ID ?? null;
        $row->request_id         = $row->Request_id ?? null;
        $row->asset_still_exists = (bool) ($row->asset_still_exists ?? 0);
        $row->disposed_by        = $row->Approve_by ?: '-';
        $row->reason             = $row->disposal_reason ?: ($row->Description ?: ($row->notes ?: '-'));
        $row->is_archived        = (bool) ($row->is_archived ?? false);
        $row->archived_by_name   = $row->archived_by_name ?? ($row->archived_by_email ?? null);

        return $row;
    }

    /**
     * Create the disposal record for an approved request.
     *
     * Returns the new Disposal_ID, or null when a record already exists for
     * that request — an Admin clicking Approve twice must not duplicate the
     * disposal history.
     */
    public static function createFromRequest(
        int $requestId,
        int $assetId,
        string $approvedBy,
        string $reason,
        ?string $note = null,
        ?string $description = null,
        ?string $disposalDate = null
    ): ?int {
        $alreadyRecorded = DB::table('disposals')
            ->where('Request_id', $requestId)
            ->where('Asset_id', $assetId)
            ->exists();

        if ($alreadyRecorded) {
            return null;
        }

        $row = [
            'Request_id'      => $requestId,
            'Asset_id'        => $assetId,
            'Approve_by'      => $approvedBy,
            'Description'     => $description ?: ('Disposal request #' . $requestId . ' approved'),
            'disposal_reason' => $reason,
            'disposal_date'   => $disposalDate ?: now()->toDateString(),
            'notes'           => $note,
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        if (self::archiveColumnsReady()) {
            $row['is_archived'] = false;
        }

        return (int) DB::table('disposals')->insertGetId($row, 'Disposal_ID');
    }

    /**
     * Move a disposal record to Archived Disposal Assets.
     *
     * The row itself is kept (plus the archive stamp) so the history of the
     * disposed asset stays readable. A record can only be archived once.
     *
     * @return array{archived: bool, message: string}
     */
    public static function archive(int $disposalId, ?int $adminId): array
    {
        if (! self::archiveColumnsReady()) {
            return [
                'archived' => false,
                'message'  => 'Archiving is not available yet — the archive database migration has not been run.',
            ];
        }

        $disposal = DB::table('disposals')->where('Disposal_ID', $disposalId)->first();

        if (! $disposal) {
            return ['archived' => false, 'message' => 'Disposal record not found.'];
        }

        if (! empty($disposal->is_archived)) {
            return ['archived' => false, 'message' => 'This disposal record is already archived.'];
        }

        DB::table('disposals')
            ->where('Disposal_ID', $disposalId)
            ->where(function (Builder $query) {
                $query->where('is_archived', false)->orWhereNull('is_archived');
            })
            ->update([
                'is_archived' => true,
                'archived_at' => now(),
                'archived_by' => $adminId,
                'updated_at'  => now(),
            ]);

        return ['archived' => true, 'message' => 'Disposal record archived successfully.'];
    }

    /**
     * Full lifecycle history of an asset, for the View Details panel.
     *
     * @return array{accountability: array, repairs: array, replacements: array, disposals: array, audit: array}
     */
    public static function history(?int $assetId): array
    {
        $empty = ['accountability' => [], 'repairs' => [], 'replacements' => [], 'disposals' => [], 'audit' => []];

        if (! $assetId) {
            return $empty;
        }

        $accountability = DB::table('asset_accountability')
            ->leftJoin('users', 'asset_accountability.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('asset_accountability.asset_id', $assetId)
            ->orderByDesc('asset_accountability.Assign_date')
            ->get([
                'asset_accountability.id',
                'asset_accountability.Assign_date',
                'asset_accountability.Is_Current',
                'asset_accountability.transfer_reason',
                'asset_accountability.notes',
                'employee_numbers.Full_Name as holder',
                'users.email as holder_email',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        $repairs = DB::table('repairs')
            ->where('Assets_id', $assetId)
            ->orderByDesc('Repair_Date')
            ->get(['Repair_id', 'Repair_Description', 'Repair_Date', 'status', 'Repair_result', 'Repair_Cost', 'Approve_by', 'notes'])
            ->map(fn ($row) => (array) $row)
            ->all();

        $replacements = DB::table('replacements')
            ->leftJoin('assets as new_asset', 'replacements.new_assets_id', '=', 'new_asset.id')
            ->where('replacements.old_assets_id', $assetId)
            ->orderByDesc('replacements.Replacement_Date')
            ->get([
                'replacements.Replacement_id',
                'replacements.reason',
                'replacements.replacement_reason',
                'replacements.status',
                'replacements.Replacement_Date',
                'replacements.Approve_by',
                'replacements.notes',
                'new_asset.Asset_name as new_asset_name',
                'new_asset.Asset_code as new_asset_code',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        $disposals = DB::table('disposals')
            ->where('Asset_id', $assetId)
            ->orderByDesc('disposal_date')
            ->get(['Disposal_ID', 'disposal_date', 'disposal_reason', 'Approve_by', 'Description', 'notes', 'Request_id'])
            ->map(fn ($row) => (array) $row)
            ->all();

        $audit = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            ->where('audit_logs.asset_id', $assetId)
            ->orderByDesc('audit_logs.created_at')
            ->limit(25)
            ->get([
                'audit_logs.id',
                'audit_logs.action_type',
                'audit_logs.action_description',
                'audit_logs.notes',
                'audit_logs.created_at',
                'employee_numbers.Full_Name as actor',
                'users.email as actor_email',
            ])
            ->map(fn ($row) => (array) $row)
            ->all();

        return [
            'accountability' => $accountability,
            'repairs'        => $repairs,
            'replacements'   => $replacements,
            'disposals'      => $disposals,
            'audit'          => $audit,
        ];
    }
}
