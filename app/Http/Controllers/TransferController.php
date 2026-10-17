<?php

namespace App\Http\Controllers;

use App\Support\AssetTransfer;
use App\Support\TransferReviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Transfer / Employee Relocation.
 *
 * The page answers one question: "Juan is leaving his post — which of his
 * assets go with him, and who takes over?" The Admin picks the employee, the
 * system lists everything currently assigned to them, the Admin picks the
 * receiving employee, confirms, and the assets change custodian without ever
 * becoming new assets.
 *
 * A confirmation can also be sent to the Executive Director instead: the same
 * transfer, held before anything changes hands until their decision comes back
 * through the emailed review link. The page's third tab is where the office
 * sees every one of those, whichever door sent it.
 */
class TransferController extends Controller
{
    /** The transfer page: employees on one tab, transfer history on the other. */
    public function index(Request $request)
    {
        $labels = AssetTransfer::userLabels();
        $custody = AssetTransfer::custodyByUser();
        $held = AssetTransfer::heldCounts();
        $reassigned = array_flip(AssetTransfer::reassignedUserIds());

        $employees = [];

        foreach ($labels as $userId => $label) {
            $assets = ($custody->get($userId) ?? collect())->map(fn ($asset) => [
                'id'        => (int) $asset->id,
                'code'      => $asset->Asset_code,
                'name'      => $asset->Asset_name,
                'category'  => $asset->Category,
                'condition' => $asset->Condition,
                'lifecycle' => $asset->Lifecycle_Status,
                'location'  => $asset->asset_location,
                'acquired'  => $asset->accusion_date,
            ])->values()->all();

            $totalHeld = (int) ($held[$userId] ?? 0);
            $assigned = count($assets);

            $employees[] = $label + [
                'status'                 => isset($reassigned[$userId]) ? 'reassigned' : 'current',
                'assigned_count'         => $assigned,
                'held_count'             => $totalHeld,
                'not_transferable_count' => max($totalHeld - $assigned, 0),
                'assets'                 => $assets,
            ];
        }

        $transfers = AssetTransfer::history()->all();

        $stats = [
            'employees_with_assets' => count(array_filter($employees, fn ($e) => $e['assigned_count'] > 0)),
            'assets_assigned'       => array_sum(array_column($employees, 'assigned_count')),
            'transfers_completed'   => count($transfers),
            'assets_transferred'    => array_sum(array_column($transfers, 'asset_count')),
        ];

        $departments = collect($employees)
            ->pluck('department')
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Every transfer the Executive Director has been asked about, including
        // the ones the Requests page forwarded: one list, on the page the office
        // sends from, so a live invitation is never hunted for.
        $executiveReviews = TransferReviews::board();

        return view('admin.transfer.transfer', [
            'employees'        => $employees,
            'transfers'        => $transfers,
            'departments'      => $departments,
            'reasons'          => AssetTransfer::REASONS,
            'stats'            => $stats,
            'openTransferId'   => (int) $request->query('transfer', 0),
            'executiveReviews' => $executiveReviews,
            'awaitingReviews'  => count(array_filter($executiveReviews, fn ($review) => $review['awaiting'])),
            'openReviewId'     => (int) $request->query('review', 0),
            'reviewDefaults'   => [
                'tables_ready'      => TransferReviews::tablesReady(),
                'recipient'         => TransferReviews::recipientName(),
                'default_recipient' => TransferReviews::defaultRecipientEmail(),
                'link_lifetime'     => self::linkLifetime(),
            ],
        ]);
    }

    /** How long an invitation stays usable, in words, for the page that offers to send one. */
    private static function linkLifetime(): string
    {
        $hours = max(1, TransferReviews::linkTtlHours());

        return $hours % 24 === 0
            ? ($hours / 24) . ' day' . ($hours === 24 ? '' : 's')
            : $hours . ' hour' . ($hours === 1 ? '' : 's');
    }

    /** Confirm the relocation. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_user_id' => 'required|integer|exists:users,id',
            'to_user_id'   => 'required|integer|exists:users,id|different:from_user_id',
            'asset_ids'    => 'required|array|min:1',
            'asset_ids.*'  => 'integer|exists:assets,id',
            'reason'       => 'required|string|max:100',
            'notes'        => 'nullable|string|max:1000',
            // Optional: the office may hand the confirmation to the Executive
            // Director instead of completing it here.
            'send_for_review'        => 'nullable|boolean',
            'review_recipient_email' => 'nullable|string|max:150',
        ], [
            'to_user_id.different' => 'The receiving employee must be someone other than the employee handing the assets over.',
            'asset_ids.required'   => 'Select at least one asset to transfer.',
        ]);

        if ($request->boolean('send_for_review')) {
            return $this->sendForReview($validated);
        }

        try {
            $result = AssetTransfer::create(
                (int) $validated['from_user_id'],
                (int) $validated['to_user_id'],
                $validated['asset_ids'],
                $validated['reason'],
                $validated['notes'] ?? null,
                Auth::id()
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            Log::error('Asset transfer failed: ' . $e->getMessage(), [
                'from_user_id' => $validated['from_user_id'],
                'to_user_id'   => $validated['to_user_id'],
                'assets'       => count($validated['asset_ids']),
            ]);

            return back()->withErrors([
                'transfer' => 'The transfer could not be completed. Please reload the page and try again.',
            ])->withInput();
        }

        // The same confirmation reached the server twice (a double click, a
        // retried POST, a tab submitted again). Nothing was changed the second
        // time because there was nothing left to change — say so plainly rather
        // than reporting a failure for work that is already done.
        if ($result['replayed']) {
            return redirect('/admin/transfer')
                ->with('notice', 'This transfer was already completed, so nothing was changed — the assets are already with the receiving employee.')
                ->with('transfer_receipt', ['id' => $result['id']]);
        }

        return redirect('/admin/transfer')
            ->with('success', 'Transfer completed. The assets now belong to the receiving employee — their asset codes, QR codes and history are unchanged.')
            ->with('transfer_receipt', ['id' => $result['id']]);
    }

    /**
     * Send a relocation to the Executive Director instead of completing it.
     *
     * The transfer is recorded and held — the same approved Transfer request the
     * Requests page produces, with its assets, its reason and the office's
     * approval in the trail — and the invitation is issued after that has
     * committed, so a mail server that refuses the message cannot lose a
     * decision the office has already made.
     *
     * @param  array<string, mixed>  $validated
     */
    private function sendForReview(array $validated)
    {
        if (! TransferReviews::tablesReady()) {
            return back()->withErrors([
                'transfer' => 'The transfer review tables have not been created on this server yet, so a transfer cannot be sent to the Executive Director. Run the database migration, then try again.',
            ])->withInput();
        }

        // The address is the whole delivery: it is where the link goes and the
        // only mailbox that can use it, so an address that cannot receive one is
        // refused before the transfer is recorded rather than after.
        $recipient = trim((string) ($validated['review_recipient_email'] ?? '')) ?: TransferReviews::defaultRecipientEmail();

        if (! TransferReviews::validEmail($recipient)) {
            return back()->withErrors([
                'review_recipient_email' => 'Enter the email address the Executive Director actually reads. That mailbox is where the review link and its verification code go, and the only place a decision can be submitted from.',
            ])->withInput();
        }

        try {
            $staged = AssetTransfer::stageForReview(
                (int) $validated['from_user_id'],
                (int) $validated['to_user_id'],
                $validated['asset_ids'],
                $validated['reason'],
                $validated['notes'] ?? null,
                Auth::id()
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()])->withInput();
        } catch (\Throwable $e) {
            Log::error('Sending a transfer to the Executive Director failed: ' . $e->getMessage(), [
                'from_user_id' => $validated['from_user_id'],
                'to_user_id'   => $validated['to_user_id'],
                'assets'       => count($validated['asset_ids']),
            ]);

            return back()->withErrors([
                'transfer' => 'The transfer could not be sent for review. Nothing was changed — please reload the page and try again.',
            ])->withInput();
        }

        // The same confirmation reached the server twice. The office asked once,
        // so the invitation that is already waiting on a decision is left exactly
        // as it is — re-sending it would replace the link the Executive Director
        // may already be looking at.
        if ($staged['replayed']) {
            $existing = TransferReviews::latestForRequest((int) $staged['id']);

            return redirect('/admin/transfer?review=' . ($existing->id ?? 0))
                ->with('notice', 'This transfer is already with the Executive Director, so nothing was sent twice — the invitation that is waiting on a decision was left exactly as it is.');
        }

        $invitation = TransferReviews::invite((int) $staged['id'], Auth::user(), false, $recipient);

        $redirect = redirect('/admin/transfer?review=' . (int) ($invitation['review_id'] ?? 0));

        $sender = AssetTransfer::userLabels()[(int) $validated['from_user_id']]['name'] ?? 'the employee';

        // Nothing has moved, and the page says so: the assets stay in the
        // sending employee's custody until a decision is recorded, which is the
        // promise the invitation email makes on the office's behalf.
        if (! $invitation['ok']) {
            return $redirect->with('notice',
                'The transfer was recorded and held, but the review invitation could not be sent. ' . $invitation['message']
                . ' Nothing has changed hands — ' . $sender . ' still holds every asset on it, and the invitation can be sent again from the Executive Review tab.'
            );
        }

        return $redirect->with('success',
            'Transfer sent to the Executive Director. ' . $invitation['message']
            . ($invitation['delivered'] ?? false ? '' : ' The mail server did not accept it — send it again from the Executive Review tab.')
            . ' Nothing has changed hands yet: ' . $sender . ' stays accountable for every asset on it until the decision is recorded on the review page.'
        );
    }
}
