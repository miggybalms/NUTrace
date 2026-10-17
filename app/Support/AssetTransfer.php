<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Why these assets cannot be handed over, or null when they all can.
     *
     * A transfer changes who is accountable for an asset that is still in
     * service. One that has left the employee's custody has no custody left to
     * hand over: it is retired ('Disposal') or pulled out ('Pullout'), it is
     * kept out of every Assets listing on purpose, and the disposal or pullout
     * record that removed it still names it. Handing it over would write it
     * back to 'Active' — undoing the disposal — so the handover stops before it
     * starts and says which assets stopped it.
     *
     * One implementation for every door that executes a transfer: the Transfer
     * page, the office approving a Transfer request and the Executive Director
     * approving a forwarded one. A request is only ever submitted for assets
     * that were in custody at the time, so the assets it names can have been
     * retired by another approved request while it waited.
     *
     * Only the assets that would really move count: one the receiving employee
     * already holds has nothing to change hands for.
     *
     * @param  array<int, int>  $assetIds
     */
    public static function custodyProblem(array $assetIds, int $targetUserId): ?string
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));

        if (! $assetIds) {
            return null;
        }

        $blocked = DB::table('assets')
            ->whereIn('id', $assetIds)
            ->whereIn('Lifecycle_Status', self::NOT_CUSTODY)
            ->where(function ($query) use ($targetUserId) {
                // An asset nobody holds has even less custody to hand over than
                // one held by somebody, and `user_id != ?` never matches NULL.
                $query->whereNull('user_id')->orWhere('user_id', '!=', $targetUserId);
            })
            ->orderBy('Asset_code')
            ->get(['id', 'Asset_code', 'Lifecycle_Status']);

        if ($blocked->isEmpty()) {
            return null;
        }

        $labels = $blocked
            ->map(fn ($asset) => ($asset->Asset_code ?: ('asset #' . $asset->id))
                . ' (' . $asset->Lifecycle_Status . ')')
            ->all();

        return 'These assets have left the employee’s custody and cannot be transferred: '
            . implode(', ', $labels)
            . '. A transfer hands over an asset that is still in service, so nothing was changed.';
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

        // A request that was forwarded to the Executive Director is Approved
        // while the assets are still where they were: the office approved the
        // request, nobody has handed anything over yet. Counting it here would
        // announce a relocation that has not happened, so requests with an
        // invitation that has not been applied are left out until the decision
        // lands.
        $fromRequests = DB::table('requests')
            ->where('request_type', 'Transfer')
            ->where('status', 'Approved')
            ->when(Schema::hasTable('transfer_reviews'), function ($query) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('transfer_reviews')
                        ->whereColumn('transfer_reviews.request_id', 'requests.id')
                        ->whereNull('transfer_reviews.executed_at');
                });
            })
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
            // A transfer the Executive Director has been asked about has not
            // happened yet, and one they rejected, or an invitation that was
            // withdrawn or left to expire, never will: those assets are still in
            // the sending employee's name. Listing one here — and counting its
            // assets in the page's own totals — would report a handover that the
            // Transfer page's Executive Review tab can still withdraw, and that
            // the assets themselves contradict. The review is where that
            // transfer lives until a decision is recorded.
            ->when(TransferReviews::tablesReady(), function ($query) {
                $query->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('transfer_reviews')
                        ->whereColumn('transfer_reviews.request_id', 'requests.id')
                        ->where(function ($inner) {
                            $inner->where('transfer_reviews.status', '!=', TransferReviews::STATUS_DECIDED)
                                ->orWhereNull('transfer_reviews.decision')
                                ->orWhere('transfer_reviews.decision', TransferReviews::DECISION_REJECTED);
                        });
                });
            })
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
     * How far back a completed transfer still counts as "the confirmation the
     * Admin just sent again" rather than a genuine conflict. A replayed POST
     * (double click, browser retry, a tab put aside and submitted later) always
     * lands moments to minutes after the transfer that did the work.
     */
    public const REPLAY_WINDOW_MINUTES = 120;

    /**
     * The transfer that already moved this exact batch, if there is one.
     *
     * Only answers when the assets are *already* sitting with the receiving
     * employee and a recent A → B transfer covers every one of them — the
     * unmistakable fingerprint of the same confirmation arriving twice. A
     * genuine conflict (the assets were moved somewhere else, or by someone
     * else's transfer) returns null and is reported as an error.
     *
     * @param  array<int, int>  $assetIds
     */
    protected static function replayedTransferId(int $fromUserId, int $toUserId, array $assetIds): ?int
    {
        $withReceiver = DB::table('assets')
            ->whereIn('id', $assetIds)
            ->where('user_id', $toUserId)
            ->count();

        if ($withReceiver !== count($assetIds)) {
            return null;
        }

        $candidates = DB::table('requests')
            ->where('request_type', 'Transfer')
            ->where('status', 'Approved')
            ->where('user_id', $fromUserId)
            ->where('assign_to_user_id', $toUserId)
            ->where('created_at', '>=', now()->subMinutes(self::REPLAY_WINDOW_MINUTES))
            ->orderByDesc('id')
            ->pluck('id');

        foreach ($candidates as $candidateId) {
            $covered = DB::table('request_items')
                ->where('request_id', $candidateId)
                ->whereIn('asset_id', $assetIds)
                ->count();

            if ($covered === count($assetIds)) {
                return (int) $candidateId;
            }
        }

        return null;
    }

    /**
     * Perform the relocation.
     *
     * Everything happens in one transaction: the transfer request, its assets,
     * the closed/open accountability rows, the assets' new custodian, the audit
     * trail and the two notifications.
     *
     * The same confirmation can reach the server more than once (a double
     * click, a browser re-sending the POST, a tab submitted twice). The second
     * copy is not an error — the requested end state is already in place — so
     * it is answered with the transfer that did the work and `replayed = true`
     * instead of an error the Admin cannot act on.
     *
     * @param  array<int, int>  $assetIds
     * @return array{id: int, replayed: bool}
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
    ): array {
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
                $replay = self::replayedTransferId($fromUserId, $toUserId, $assetIds);

                if ($replay !== null) {
                    Log::info('Duplicate transfer confirmation ignored; the transfer was already completed.', [
                        'transfer_id'  => $replay,
                        'from_user_id' => $fromUserId,
                        'to_user_id'   => $toUserId,
                        'assets'       => count($assetIds),
                    ]);

                    return ['id' => $replay, 'replayed' => true];
                }

                throw new RuntimeException(
                    'These assets are no longer assigned to the employee you are transferring from: '
                    . $foreign->pluck('Asset_code')->implode(', ')
                    . '. Reload the page and try again.'
                );
            }

            // Assets the Executive Director is already deciding on are on hold.
            // They are still the sending employee's, and this door must not do
            // what that review is still weighing up: moving them here would turn
            // the invitation into a link to assets that had already gone.
            if ($hold = TransferReviews::assetsOnHold($assetIds)) {
                throw new RuntimeException(TransferReviews::holdRefusal($hold));
            }

            if ($problem = self::custodyProblem($assetIds, $toUserId)) {
                throw new RuntimeException($problem);
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

            return ['id' => (int) $requestId, 'replayed' => false];
        });
    }

    /**
     * Stage a relocation for the Executive Director's decision.
     *
     * The relocation the Transfer page performs, held one step earlier: the same
     * approved Transfer request with its assets in `request_items`, the same
     * reason in `requests.Note`, the office's own approval in the audit trail —
     * and nothing changes hands. The invitation is issued by the caller, after
     * this has committed, so a mail server that refuses the message cannot roll
     * back a decision the office has already made.
     *
     * Everything the Executive Director's review promises is kept here: the
     * assets stay in the sending employee's custody, they stay in that
     * employee's listings, and no other door may move them while the invitation
     * is live — because `TransferReviews::assetsOnHold()` answers for exactly
     * this kind of approved but not-yet-executed transfer.
     *
     * A second submit of the same confirmation (a double click, a retried POST)
     * is not an error and must not ask the Executive Director twice: the
     * outstanding invitation covering this same batch, from this same employee
     * to this same one, is answered with the request that already holds it.
     *
     * @param  array<int, int>  $assetIds
     * @return array{id: int, replayed: bool}
     *
     * @throws RuntimeException when an asset is no longer the sender's to give away, or is on hold
     */
    public static function stageForReview(
        int $fromUserId,
        int $toUserId,
        array $assetIds,
        string $reason,
        ?string $notes = null,
        ?int $adminId = null
    ): array {
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

            // Asked before anything else, because a replayed confirmation leaves
            // the assets exactly where they started — still the sending
            // employee's — and would otherwise read as a genuine second request
            // and send the Executive Director a second invitation for one
            // handover.
            if (($replay = self::stagedReplayId($fromUserId, $toUserId, $assetIds)) !== null) {
                Log::info('Duplicate review request ignored; the transfer is already waiting on the Executive Director.', [
                    'transfer_id'  => $replay,
                    'from_user_id' => $fromUserId,
                    'to_user_id'   => $toUserId,
                    'assets'       => count($assetIds),
                ]);

                return ['id' => $replay, 'replayed' => true];
            }

            $foreign = $assets->where('user_id', '!=', $fromUserId);

            if ($foreign->isNotEmpty()) {
                throw new RuntimeException(
                    'These assets are no longer assigned to the employee you are transferring from: '
                    . $foreign->pluck('Asset_code')->implode(', ')
                    . '. Reload the page and try again.'
                );
            }

            // An asset another review is already holding cannot enter a second
            // one: two live invitations for one asset would let the Executive
            // Director answer a question that has already moved on.
            if ($hold = TransferReviews::assetsOnHold($assetIds)) {
                throw new RuntimeException(TransferReviews::holdRefusal($hold));
            }

            if ($problem = self::custodyProblem($assetIds, $toUserId)) {
                throw new RuntimeException($problem);
            }

            $names = self::userLabels();
            $fromName = $names[$fromUserId]['name'] ?? ('User #' . $fromUserId);
            $toName = $names[$toUserId]['name'] ?? ('User #' . $toUserId);

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

            // The office's approval, recorded the way the Requests page records
            // it — the Executive Director's review page reads this entry back to
            // say who released the transfer. The marker inside the note is what
            // tells the Transfer page that this transfer was prepared here
            // rather than asked for by an employee; no column has to be added
            // for it.
            DB::table('audit_logs')->insert([
                'user_id'            => $adminId,
                'request_id'         => $requestId,
                'asset_id'           => null,
                'action_type'        => 'APPROVAL',
                'notes'              => 'Approved bulk Transfer request with ' . count($assetIds)
                    . ' asset(s) (' . TransferReviews::ORIGIN_TRANSFER_PAGE . ')',
                'action_description' => 'Transfer request #' . $requestId . ' was prepared by the Asset Management Office'
                    . ' on the Transfer page and sent to the Executive Director for the final decision. '
                    . 'No asset has changed hands — ' . $fromName . ' still holds every asset named on it.',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // The employee whose assets are being handed over is told, because
            // the transfer waits on somebody else now and their custody is
            // unchanged until it is decided — not a goodbye, a "not yet".
            Notifications::create(
                $fromUserId,
                'Transfer Awaiting the Executive Director',
                count($assetIds) . ' asset' . (count($assetIds) === 1 ? '' : 's') . ' of yours '
                    . (count($assetIds) === 1 ? 'is' : 'are') . ' being handed over to ' . $toName
                    . '. The Asset Management Office approved the transfer and sent it to the Executive Director, who makes the final decision. '
                    . 'The assets stay with you until that decision is recorded.',
                'TRANSFER',
                $requestId,
                'request'
            );

            return ['id' => (int) $requestId, 'replayed' => false];
        });
    }

    /**
     * The same handover already waiting on the Executive Director, if there is one.
     *
     * Only answers when an outstanding invitation covers every one of these
     * assets for this same from → to pair and was sent moments ago: the
     * unmistakable fingerprint of the same confirmation arriving twice. A
     * genuinely different request — another employee, another batch, another
     * destination — returns null and is decided on its own merits.
     *
     * @param  array<int, int>  $assetIds
     */
    protected static function stagedReplayId(int $fromUserId, int $toUserId, array $assetIds): ?int
    {
        if (! TransferReviews::tablesReady()) {
            return null;
        }

        $candidates = DB::table('transfer_reviews')
            ->join('requests', 'transfer_reviews.request_id', '=', 'requests.id')
            ->whereIn('transfer_reviews.status', [TransferReviews::STATUS_AWAITING, TransferReviews::STATUS_VERIFIED])
            ->where('transfer_reviews.last_sent_at', '>=', now()->subMinutes(self::REPLAY_WINDOW_MINUTES))
            ->where('requests.user_id', $fromUserId)
            ->where('requests.assign_to_user_id', $toUserId)
            ->orderByDesc('transfer_reviews.id')
            ->pluck('transfer_reviews.request_id');

        foreach ($candidates as $candidateId) {
            $covered = DB::table('request_items')
                ->where('request_id', $candidateId)
                ->whereIn('asset_id', $assetIds)
                ->count();

            if ($covered === count($assetIds)) {
                return (int) $candidateId;
            }
        }

        return null;
    }

    /**
     * Apply an approved Transfer *request*: the assets change hands.
     *
     * This is the step the Requests page used to run inline when an Admin
     * approved a transfer request, and it is the same step the Executive
     * Director's approval runs when a request was forwarded for review — one
     * implementation, so an approved transfer is recorded identically whoever
     * released it, and the audit trail cannot drift between the two doors.
     *
     * Whoever releases the transfer, the handover is recorded the same way:
     * the assets' custodian changes, the previous holder's accountability is
     * closed and the receiving employee's is opened, one TRANSFER entry lands in
     * the asset's own history, and the receiving employee is told they now hold
     * it. The asset's identity, code, QR sticker, acquisition data and history
     * stay with it. Assets that already sit with the receiving employee are left
     * alone, so a replayed decision cannot write a second set of entries.
     *
     * The accountability rows are the point of this method. `assets.user_id`
     * alone says who is supposed to have the asset; `asset_accountability` is
     * what the official disposal form, the asset's lifecycle history and the
     * "who held what, when" question all read. A transfer that moved the first
     * without the second left the employee who gave the asset away as its
     * current accountable holder for good.
     *
     * @param  array<int, int>  $assetIds
     * @return int  how many assets actually moved
     *
     * @throws RuntimeException when one of the assets has left the employee's custody
     */
    public static function applyRequestTransfer(
        object $requestRecord,
        array $assetIds,
        ?int $actorId = null,
        ?string $context = null
    ): int {
        $targetUserId = (int) ($requestRecord->assign_to_user_id ?? 0);

        if ($targetUserId <= 0 || ! $assetIds) {
            return 0;
        }

        $names = self::userLabels();
        $assigneeName = $names[$targetUserId]['name'] ?? ('User #' . $targetUserId);

        // Why the handover happened, as the accountability history records it:
        // the requester's own words where there are any, and the request itself
        // otherwise.
        $reason = trim((string) ($requestRecord->Note ?? ''));

        if ($reason === '') {
            $reason = 'Transfer request #' . $requestRecord->id;
        }

        // An asset that has left the employee's custody is not part of a
        // handover, however the request reads by now: it is retired or pulled
        // out, and moving it would write it back into service under the
        // receiving employee while the disposal record still names it. Refused
        // before anything moves, so the batch cannot half-happen around it.
        if ($problem = self::custodyProblem($assetIds, $targetUserId)) {
            throw new RuntimeException($problem);
        }

        $moved = 0;

        foreach (array_values(array_unique(array_map('intval', $assetIds))) as $assetId) {
            if ($assetId <= 0) {
                continue;
            }

            $asset = DB::table('assets')->where('id', $assetId)->first(['id', 'user_id', 'Asset_code', 'Asset_name']);

            if (! $asset || (int) $asset->user_id === $targetUserId) {
                continue;
            }

            // The handover itself, recorded one asset at a time so the closing
            // row names whoever really held *this* asset — a department head may
            // request a transfer for a colleague's asset, and the history must
            // not credit the requester with holding it.
            self::applyAccountability(
                (int) $requestRecord->id,
                (int) $asset->user_id,
                $targetUserId,
                [$assetId],
                $reason,
                $context !== null && trim($context) !== ''
                    ? 'Recorded when ' . trim($context) . '.'
                    : null
            );

            DB::table('assets')->where('id', $assetId)->update([
                'user_id'          => $targetUserId,
                'Lifecycle_Status' => 'Active',
                'updated_at'       => now(),
            ]);

            $notes = 'Transferred asset to ' . $assigneeName;

            if ($context) {
                $notes .= ' (' . $context . ')';
            }

            DB::table('audit_logs')->insert([
                'user_id'            => $actorId,
                'request_id'         => $requestRecord->id,
                'asset_id'           => $assetId,
                'action_type'        => 'TRANSFER',
                'notes'              => $notes,
                'action_description' => 'Asset ownership transferred via approved bulk transfer request #' . $requestRecord->id,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            $moved++;
        }

        // The receiving employee is told, once, what they are now accountable
        // for. A handover nobody hears about is how an asset ends up in the
        // "who is responsible for this?" conversation with no answer.
        if ($moved > 0) {
            Notifications::create(
                $targetUserId,
                'Assets Transferred To You',
                $moved . ' asset' . ($moved === 1 ? '' : 's') . ' from transfer request #' . $requestRecord->id
                    . ' ' . ($moved === 1 ? 'is' : 'are') . ' now assigned to you and recorded as yours to account for.',
                'TRANSFER',
                (int) $requestRecord->id,
                'request'
            );
        }

        return $moved;
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
