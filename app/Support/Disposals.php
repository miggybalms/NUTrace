<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Disposal records and the request-driven disposal workflow.
 *
 * The request is the source of the disposal transaction: approving a Disposal
 * request creates the record, marks the asset Disposed and leaves the asset row
 * — and everything hanging off it — in place.
 *
 * Archiving is the reversible-feeling step: the record leaves the main list, the
 * asset leaves the inventory, both rows stay. Deleting is the one deliberate
 * exception, and it is only ever available on an already-archived record:
 * disposal id records itself plus the asset row it names are removed from the
 * database, together with the rows that cannot outlive the asset (see
 * destroy()). Everything else — the audit trail, records of other assets —
 * survives.
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

        // Whether the asset has left the inventory (it no longer appears in the
        // Assets list, but the row is kept for this record and for history).
        if (Inventory::columnsReady()) {
            $columns[] = 'assets.' . Inventory::REMOVED_AT . ' as asset_removed_from_inventory';
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
        $row->inventory_removed  = ! empty($row->asset_removed_from_inventory ?? null);

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
     * Archiving is the point at which the asset leaves the inventory: its
     * disposal is complete and recorded, so it stops being counted as an asset
     * the institution holds. Archiving itself never deletes the row — the
     * archived record has to be able to name the asset, and the asset's own
     * history stays readable. Deleting is a separate, explicit action on an
     * archived record (see destroy()).
     *
     * A record can only be archived once, and the record plus the inventory
     * change are one transaction so they can never disagree.
     *
     * @return array{archived: bool, message: string, asset_removed: bool, asset_code: ?string}
     */
    public static function archive(int $disposalId, ?int $adminId): array
    {
        $nothing = static fn (string $message): array => [
            'archived'     => false,
            'message'      => $message,
            'asset_removed' => false,
            'asset_code'   => null,
        ];

        if (! self::archiveColumnsReady()) {
            return $nothing('Archiving is not available yet — the archive database migration has not been run.');
        }

        $disposal = DB::table('disposals')->where('Disposal_ID', $disposalId)->first();

        if (! $disposal) {
            return $nothing('Disposal record not found.');
        }

        if (! empty($disposal->is_archived)) {
            return $nothing('This disposal record is already archived.');
        }

        $assetCode = $disposal->Asset_id
            ? (DB::table('assets')->where('id', $disposal->Asset_id)->value('Asset_code') ?? null)
            : null;

        // Whether the asset actually left the inventory is reported honestly: on
        // a server that has not run the inventory migration yet the archive still
        // works, it just cannot take the asset out of the listings.
        $removed = false;

        try {
            DB::transaction(function () use ($disposal, $disposalId, $adminId, &$removed) {
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

                if ($disposal->Asset_id) {
                    $removed = Inventory::remove((int) $disposal->Asset_id, $adminId);
                }
            });
        } catch (Throwable $e) {
            \Log::error('Archiving disposal #' . $disposalId . ' failed: ' . $e->getMessage());

            return $nothing('The disposal record could not be archived. Nothing was changed — please try again.');
        }

        return [
            'archived'      => true,
            'message'       => 'Disposal record archived successfully.',
            'asset_removed' => $removed,
            'asset_code'    => $assetCode,
        ];
    }

    /**
     * What permanently deleting an archived record is about to remove.
     *
     * Deleting an archived record is the only destructive action in the disposal
     * workflow, so it is never a surprise: the Archived Disposal Assets page asks
     * this first and shows the Admin exactly which rows — and how many — will go,
     * and why the action is refused when it is not allowed yet.
     *
     * Returns null when the record does not exist.
     *
     * @return array{
     *     disposal_id: int,
     *     is_archived: bool,
     *     asset_id: ?int,
     *     asset_code: ?string,
     *     asset_name: ?string,
     *     asset_exists: bool,
     *     blockers: array<int, string>,
     *     removes: array<string, int>,
     *     keeps: array<string, int>,
     *     preserved_requests: array<int, int>,
     *     files: int
     * }|null
     */
    public static function deletionImpact(int $disposalId): ?array
    {
        $disposal = DB::table('disposals')->where('Disposal_ID', $disposalId)->first();

        if (! $disposal) {
            return null;
        }

        $assetId = $disposal->Asset_id ? (int) $disposal->Asset_id : null;
        $asset   = $assetId ? DB::table('assets')->where('id', $assetId)->first() : null;

        $blockers = [];

        if (! self::archiveColumnsReady()) {
            $blockers[] = 'The archive database migration has not been run on this server.';
        } elseif (empty($disposal->is_archived)) {
            $blockers[] = 'Only archived disposal records can be permanently deleted — archive this record first.';
        }

        if ($assetId && self::activeDisposalsFor($assetId) > 0) {
            $blockers[] = 'This asset still has an active disposal record on the Disposal page. Archive that record first.';
        }

        $removes = [];

        if ($assetId && $asset) {
            $requestIds = self::requestsOfAsset($assetId);

            // Requests that also cover another asset are kept, so only the rest
            // of this asset's own requests are part of the delete.
            $preserved = array_values(array_filter(
                $requestIds,
                static fn (int $requestId): bool => self::requestLinksAnotherAsset($requestId, $assetId)
            ));

            $removes['Disposal record'] = DB::table('disposals')->where('Asset_id', $assetId)->count();
            $removes['Asset record']    = 1;
            $removes['Request line item'] = DB::table('request_items')->where('asset_id', $assetId)->count();
            $removes['Repair record']   = DB::table('repairs')->where('Assets_id', $assetId)->count();
            $removes['Repair evaluation'] = DB::table('repair_evaluations')
                ->join('repairs', 'repair_evaluations.repair_id', '=', 'repairs.Repair_id')
                ->where('repairs.Assets_id', $assetId)
                ->count();
            $removes['Replacement record'] = DB::table('replacements')
                ->where('old_assets_id', $assetId)->orWhere('new_assets_id', $assetId)->count();
            $removes['Pullout record']  = DB::table('pullouts')->where('asset_id', $assetId)->count();
            $removes['Accountability record'] = DB::table('asset_accountability')->where('asset_id', $assetId)->count();
            $removes['Asset file']      = DB::table('asset_files')->where('Asset_id', $assetId)->count();
            $removes['Request']         = count(array_diff($requestIds, $preserved));
        } else {
            $removes['Disposal record'] = 1;
            $preserved = [];
        }

        return [
            'disposal_id'        => $disposalId,
            'is_archived'        => ! empty($disposal->is_archived),
            'asset_id'           => $assetId,
            'asset_code'         => $asset?->Asset_code,
            'asset_name'         => $asset?->Asset_name ?? ($disposal->Description ?: null),
            'asset_exists'       => (bool) $asset,
            'blockers'           => $blockers,
            'removes'            => array_filter($removes, static fn (int $count): bool => $count > 0),
            // The audit trail deliberately stays: it is the only record of who
            // removed the asset, and its asset_id is cleared by the database.
            'keeps'              => array_filter([
                'Audit entry' => $assetId ? DB::table('audit_logs')->where('asset_id', $assetId)->count() : 0,
            ], static fn (int $count): bool => $count > 0),
            'preserved_requests' => $preserved,
            'files'              => $assetId ? count(self::storedFiles($assetId)) : 0,
        ];
    }

    /**
     * Permanently delete an archived disposal record and the asset it names.
     *
     * This is the one destructive action in the disposal workflow, and the only
     * way an asset ever leaves the database. It is deliberately narrow:
     *
     *   - the record has to be archived already;
     *   - the asset may not still have an active disposal record waiting;
     *   - a request shared with another asset is kept (its asset link is cleared
     *     instead), so deleting one asset cannot strip another asset's history.
     *
     * The asset's own rows exist only because the asset does, and PostgreSQL
     * refuses to remove the asset while they reference it
     * (request_items / repairs / replacements / disposals are RESTRICT), so they
     * are cleared in dependency order first: disposal records, replacements,
     * repairs, request line items, then the asset itself — whose request, pullout,
     * file and accountability rows the database then cascades away. All of it is
     * one transaction: either the record and its asset are gone, or nothing
     * changed at all.
     *
     * The audit entry that records who did this is written by the caller, after
     * this has committed and the rows it names are gone.
     *
     * @return array{deleted: bool, message: string, asset_deleted: bool, asset_code: ?string, removed: array<string, int>, preserved_requests: array<int, int>, files: int}
     */
    public static function destroy(int $disposalId): array
    {
        $fail = static fn (string $message): array => [
            'deleted'            => false,
            'message'            => $message,
            'asset_deleted'      => false,
            'asset_code'         => null,
            'removed'            => [],
            'preserved_requests' => [],
            'files'              => 0,
        ];

        if (! self::archiveColumnsReady()) {
            return $fail('Permanent deletion is not available yet — the archive database migration has not been run.');
        }

        $disposal = DB::table('disposals')->where('Disposal_ID', $disposalId)->first();

        if (! $disposal) {
            return $fail('Disposal record not found.');
        }

        if (empty($disposal->is_archived)) {
            return $fail('Only archived disposal records can be permanently deleted. Archive this record first.');
        }

        $assetId = $disposal->Asset_id ? (int) $disposal->Asset_id : null;
        $asset   = $assetId ? DB::table('assets')->where('id', $assetId)->first() : null;

        // The asset is the thing being retired; if it still has a live disposal
        // record the Admin has not finished the workflow for it yet.
        if ($assetId && self::activeDisposalsFor($assetId) > 0) {
            return $fail('This asset still has an active disposal record on the Disposal page. Archive that record first, then delete it.');
        }

        $assetCode = $asset?->Asset_code;
        $removed   = [];
        $preserved = [];
        $files     = [];

        try {
            DB::transaction(function () use ($assetId, $disposalId, &$removed, &$preserved, &$files) {
                if (! $assetId || ! DB::table('assets')->where('id', $assetId)->exists()) {
                    // A legacy record whose asset row is long gone: only the
                    // record itself is left to remove.
                    $removed['Disposal record'] = DB::table('disposals')->where('Disposal_ID', $disposalId)->delete();

                    return;
                }

                // Every request this asset is part of, and the ones among them
                // that also cover another asset (those are kept, the rest go with
                // the asset).
                $requestIds = self::requestsOfAsset($assetId);
                $preserved  = self::preserveSharedRequests($assetId);

                // Stored photos and the QR image go with the row they belong to;
                // the files are removed from the media disk after this commits.
                $files = self::storedFiles($assetId);

                // Dependency order, forced by the RESTRICT / NO ACTION keys that
                // point at assets and requests. Every row is removed explicitly
                // instead of leaning on ON DELETE CASCADE, so the order is this
                // code's decision rather than the schema's, and a database whose
                // keys were declared differently still gets the same result.
                $removed['Disposal record']    = DB::table('disposals')->where('Asset_id', $assetId)->delete();
                $removed['Replacement record'] = DB::table('replacements')
                    ->where('old_assets_id', $assetId)->orWhere('new_assets_id', $assetId)->delete();
                $removed['Repair record']      = DB::table('repairs')->where('Assets_id', $assetId)->delete();
                $removed['Request line item']  = DB::table('request_items')->where('asset_id', $assetId)->delete();
                $removed['Request']            = DB::table('requests')
                    ->whereIn('id', array_values(array_diff($requestIds, $preserved)))
                    ->delete();
                $removed['Asset file']         = DB::table('asset_files')->where('Asset_id', $assetId)->delete();
                $removed['Pullout record']     = DB::table('pullouts')->where('asset_id', $assetId)->delete();
                $removed['Accountability record'] = DB::table('asset_accountability')->where('asset_id', $assetId)->delete();
                $removed['Asset record']       = DB::table('assets')->where('id', $assetId)->delete();
            });
        } catch (Throwable $e) {
            Log::error('Permanently deleting archived disposal #' . $disposalId . ' failed: ' . $e->getMessage());

            return $fail('The archived record could not be deleted — nothing was removed. The asset is still referenced by records that have to be cleared first; try again or ask the system administrator.');
        }

        // Media is cleaned up only after the database has committed, so a
        // storage outage can never leave a half-deleted record behind.
        foreach ($files as $path) {
            Media::delete($path);
        }

        $message = $assetCode
            ? 'Archived disposal record for asset ' . $assetCode . ' and the asset record itself were permanently deleted.'
            : 'Archived disposal record permanently deleted.';

        if ($preserved) {
            $message .= ' Request #' . implode(', #', $preserved)
                . ' was kept because it also covers other assets; it no longer names this one.';
        }

        return [
            'deleted'            => true,
            'message'            => $message,
            'asset_deleted'      => ! empty($removed['Asset record']),
            'asset_code'         => $assetCode,
            'removed'            => array_filter($removed, static fn (int $count): bool => $count > 0),
            'preserved_requests' => $preserved,
            'files'              => count($files),
        ];
    }

    /**
     * Permanently delete every archived disposal record and, with each of them,
     * the asset it names.
     *
     * Each record is deleted in its own transaction through destroy(), so one
     * record that cannot be removed (a shared request the database still needs, a
     * record already gone) never takes the rest of the batch down with it. The
     * outcome reports both sides honestly.
     *
     * @return array{deleted: bool, message: string, deleted_count: int, asset_count: int, failed: array<int, string>, destroyed: array<int, int>}
     */
    public static function destroyAllArchived(): array
    {
        if (! self::archiveColumnsReady()) {
            return [
                'deleted'       => false,
                'message'       => 'Permanent deletion is not available yet — the archive database migration has not been run.',
                'deleted_count' => 0,
                'asset_count'   => 0,
                'failed'        => [],
                'destroyed'     => [],
            ];
        }

        $rows = DB::table('disposals')->where('is_archived', true)
            ->orderBy('Disposal_ID')
            ->get(['Disposal_ID', 'Asset_id']);

        $destroyed = [];
        $assetCount = 0;
        $failed = [];
        $handledAssets = [];

        foreach ($rows as $row) {
            // All records of one asset are removed together by the first of
            // them, so the rest of that asset's records are already accounted
            // for.
            if ($row->Asset_id) {
                if (isset($handledAssets[$row->Asset_id])) {
                    continue;
                }

                $handledAssets[$row->Asset_id] = true;
            }

            $result = self::destroy((int) $row->Disposal_ID);

            if (! $result['deleted']) {
                $failed[] = '#Disposal ' . $row->Disposal_ID . ': ' . $result['message'];

                continue;
            }

            $destroyed[] = (int) $row->Disposal_ID;
            $assetCount += $result['asset_deleted'] ? 1 : 0;
        }

        if (! $destroyed) {
            return [
                'deleted'       => false,
                'message'       => $failed
                    ? 'No archived records could be deleted. ' . implode(' ', $failed)
                    : 'There are no archived disposal records to delete.',
                'deleted_count' => 0,
                'asset_count'   => 0,
                'failed'        => $failed,
                'destroyed'     => [],
            ];
        }

        $message = count($destroyed) . ' archived disposal record' . (count($destroyed) === 1 ? '' : 's')
            . ' permanently deleted';

        if ($assetCount > 0) {
            $message .= ', together with ' . $assetCount . ' asset record' . ($assetCount === 1 ? '' : 's');
        }

        $message .= '.';

        if ($failed) {
            $message .= ' ' . count($failed) . ' could not be deleted: ' . implode(' ', $failed);
        }

        return [
            'deleted'       => true,
            'message'       => $message,
            'deleted_count' => count($destroyed),
            'asset_count'   => $assetCount,
            'failed'        => $failed,
            'destroyed'     => $destroyed,
        ];
    }

    /** How many disposal records for this asset are still on the Disposal page. */
    private static function activeDisposalsFor(int $assetId): int
    {
        return DB::table('disposals')
            ->where('Asset_id', $assetId)
            ->where(function (Builder $query) {
                $query->where('is_archived', false)->orWhereNull('is_archived');
            })
            ->count();
    }

    /**
     * Every request this asset is part of.
     *
     * A disposal request does not have to name its asset in requests.asset_id —
     * the asset is held in request_items, and a repair / replacement / pullout
     * request reaches its asset through its own record. Any of those links makes
     * the request part of this asset's history.
     *
     * @return array<int, int>
     */
    public static function requestsOfAsset(int $assetId): array
    {
        if ($assetId <= 0) {
            return [];
        }

        return collect()
            ->merge(DB::table('requests')->where('asset_id', $assetId)->pluck('id'))
            ->merge(DB::table('request_items')->where('asset_id', $assetId)->pluck('request_id'))
            ->merge(DB::table('pullouts')->where('asset_id', $assetId)->pluck('request_id'))
            ->merge(DB::table('repairs')->where('Assets_id', $assetId)->pluck('Request_id'))
            ->merge(DB::table('replacements')->where('old_assets_id', $assetId)->pluck('Request_id'))
            ->merge(DB::table('replacements')->where('new_assets_id', $assetId)->pluck('Request_id'))
            ->merge(DB::table('disposals')->where('Asset_id', $assetId)->pluck('Request_id'))
            ->merge(DB::table('asset_accountability')->where('asset_id', $assetId)->pluck('request_id'))
            ->filter(static fn ($id): bool => ! empty($id))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Does this request still mean something to a different asset?
     *
     * Everything that belongs to the asset being deleted is excluded from the
     * test, so only links the delete would not remove count — the other assets'
     * request lines, pullouts, repairs, replacements, accountability rows and
     * even legacy disposal records with no asset at all.
     */
    private static function requestLinksAnotherAsset(int $requestId, int $assetId): bool
    {
        return DB::table('requests')->where('id', $requestId)
                ->whereNotNull('asset_id')->where('asset_id', '!=', $assetId)->exists()
            || DB::table('request_items')->where('request_id', $requestId)
                ->where('asset_id', '!=', $assetId)->exists()
            || DB::table('repairs')->where('Request_id', $requestId)
                ->where('Assets_id', '!=', $assetId)->exists()
            || DB::table('replacements')->where('Request_id', $requestId)
                ->where('old_assets_id', '!=', $assetId)->where('new_assets_id', '!=', $assetId)->exists()
            || DB::table('disposals')->where('Request_id', $requestId)
                ->where(function (Builder $query) use ($assetId) {
                    $query->whereNull('Asset_id')->orWhere('Asset_id', '!=', $assetId);
                })->exists()
            || DB::table('asset_accountability')->where('request_id', $requestId)
                ->where('asset_id', '!=', $assetId)->exists()
            || DB::table('pullouts')
                ->leftJoin('pullout_items', 'pullout_items.pullout_id', '=', 'pullouts.id')
                ->where('pullouts.request_id', $requestId)
                ->where(function (Builder $query) use ($assetId) {
                    $query->where(function (Builder $inner) use ($assetId) {
                        $inner->whereNotNull('pullouts.asset_id')->where('pullouts.asset_id', '!=', $assetId);
                    })->orWhere(function (Builder $inner) use ($assetId) {
                        $inner->whereNotNull('pullout_items.asset_id')->where('pullout_items.asset_id', '!=', $assetId);
                    });
                })->exists();
    }

    /**
     * The requests of this asset that also cover other assets.
     *
     * Deleting the asset would take its requests away through the database's own
     * cascade, and with them the other assets' line items, pullouts and
     * evaluations. Those requests are kept instead, and their asset link is
     * cleared so they no longer claim an asset that is gone.
     *
     * @return array<int, int> request ids that are shared with another asset
     */
    private static function preserveSharedRequests(int $assetId): array
    {
        $shared = array_values(array_filter(
            self::requestsOfAsset($assetId),
            static fn (int $requestId): bool => self::requestLinksAnotherAsset($requestId, $assetId)
        ));

        if ($shared) {
            DB::table('requests')->whereIn('id', $shared)->where('asset_id', $assetId)->update([
                'asset_id'   => null,
                'updated_at' => now(),
            ]);
        }

        return $shared;
    }

    /**
     * Files on the media disk that belong to an asset: its uploaded photos and
     * its stored QR image.
     *
     * @return array<int, string>
     */
    private static function storedFiles(int $assetId): array
    {
        $paths = DB::table('asset_files')->where('Asset_id', $assetId)
            ->whereNotNull('url')
            ->pluck('url')
            ->all();

        if ($qr = DB::table('assets')->where('id', $assetId)->value('qr_code_path')) {
            $paths[] = $qr;
        }

        return array_values(array_filter(array_map(
            static fn ($path): ?string => is_string($path) && trim($path) !== '' ? $path : null,
            $paths
        )));
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
