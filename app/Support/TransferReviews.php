<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * The Executive Director's review of a transfer request.
 *
 * The Executive Director holds no NU Trace account and must never get one: they
 * receive one temporary link, prove they hold the mailbox it was sent to, decide
 * on exactly that transfer, and the link is spent. Nothing in this class ever
 * signs anybody in, so a forwarded link cannot become a session on the rest of
 * the system — the review page it opens knows one request and nothing else.
 *
 * What the review does NOT do is decide for the office. Before this feature an
 * Admin approving a Transfer request moved the assets immediately. A request
 * that is forwarded for review is approved by the office and then *waits*: the
 * Executive Director's decision is what applies the transfer for the assets they
 * approved, and a rejection applies nothing at all. Both paths run the same
 * execution code (AssetTransfer::applyRequestTransfer), so an approved transfer
 * is recorded identically whoever released it.
 *
 * Stages (transfer_reviews.status):
 *
 *   awaiting_verification  Invitation sent; the recipient has not proved the mailbox yet
 *   verified               The recipient proved it; the decision is open
 *   decided                A decision was recorded (approved / partially / rejected)
 *   revoked                Withdrawn by the office, or replaced by a newer invitation
 *
 * Expiry is a date, not a stage: `expires_at` passes and an outstanding
 * invitation stops working, whether or not anybody acted on it. A decided
 * invitation is never re-openable.
 *
 * The code ships before `php artisan migrate` runs on the server, so every read
 * and write checks tablesReady() first instead of failing on a missing table.
 */
class TransferReviews
{
    public const STATUS_AWAITING = 'awaiting_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_DECIDED  = 'decided';
    public const STATUS_REVOKED  = 'revoked';

    /** The decision recorded on the review. */
    public const DECISION_APPROVED = 'approved';
    public const DECISION_PARTIAL  = 'partially_approved';
    public const DECISION_REJECTED = 'rejected';

    /** The decision recorded against one asset of the transfer. */
    public const ITEM_APPROVED = 'approved';
    public const ITEM_DECLINED = 'declined';

    /**
     * Where a transfer was prepared, written into the office's own APPROVAL
     * audit entry and read back out of it.
     *
     * The Transfer page can ask the Executive Director about a handover the
     * office set up itself, and the Requests page about one an employee asked
     * for. Both create the same kind of approved Transfer request, so the trail
     * says which door it came through rather than the schema carrying a column
     * for it — the same way the approval's author and time are read back.
     */
    public const ORIGIN_TRANSFER_PAGE = 'prepared-on-transfer-page';

    private static ?bool $tablesReady = null;

    /* ───────────────────────── configuration ───────────────────────── */

    public static function tablesReady(): bool
    {
        if (self::$tablesReady === null) {
            self::$tablesReady = Schema::hasTable('transfer_reviews')
                && Schema::hasTable('transfer_review_items');
        }

        return self::$tablesReady;
    }

    /**
     * The mailbox the office usually sends to, if the deployment names one.
     *
     * This is a *default*, not a rule. The Executive Director is a person, and
     * people change: the office types the address on each invitation, so a new
     * director needs no deployment, no environment variable and no developer.
     * What this setting does is save the office from re-typing the usual address
     * and give the dialog something sensible to start with.
     */
    public static function defaultRecipientEmail(): string
    {
        return trim((string) config('transfer_review.email'));
    }

    /** How the recipient is addressed when no name has been configured. */
    public static function recipientName(): string
    {
        $name = trim((string) config('transfer_review.name'));

        return $name !== '' ? $name : 'Executive Director';
    }

    /** Is this something a message can be sent to? */
    public static function validEmail(?string $email): bool
    {
        $email = trim((string) $email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Where an invitation for this request should be sent by default.
     *
     * The address used last for this request comes first, then the deployment's
     * usual address. Re-sending therefore goes back to the same person unless
     * the office deliberately types somebody else — which is exactly what has to
     * happen when the director changes.
     */
    public static function suggestedRecipient(int $requestId): string
    {
        $last = self::latestForRequest($requestId);

        if ($last && self::validEmail($last->recipient_email ?? null)) {
            return (string) $last->recipient_email;
        }

        return self::defaultRecipientEmail();
    }

    /**
     * Who the office should be told to contact.
     *
     * A page that cannot open a review has to point the reader somewhere, and
     * the Asset Management Office is the somebody. Falls back to the app's own
     * sending address, and to nothing at all when neither is set — a wrong
     * address on that page would be worse than no address.
     */
    public static function officeContactEmail(): string
    {
        foreach ([config('transfer_review.office_email'), self::defaultRecipientEmail(), config('mail.from.address')] as $candidate) {
            if (self::validEmail($candidate)) {
                return trim((string) $candidate);
            }
        }

        return '';
    }

    public static function forwardByDefault(): bool
    {
        return (bool) config('transfer_review.forward_by_default', true);
    }

    public static function linkTtlHours(): int
    {
        return max(1, (int) config('transfer_review.link_ttl_hours', 168));
    }

    public static function codeTtlMinutes(): int
    {
        return max(1, (int) config('transfer_review.code_ttl_minutes', 15));
    }

    public static function maxCodeAttempts(): int
    {
        return max(1, (int) config('transfer_review.code_attempts', 5));
    }

    public static function maxCodeRequests(): int
    {
        return max(1, (int) config('transfer_review.code_requests_per_window', 3));
    }

    public static function codeWindowMinutes(): int
    {
        return max(1, (int) config('transfer_review.code_window_minutes', 60));
    }

    public static function codeResendCooldown(): int
    {
        return max(0, (int) config('transfer_review.code_resend_cooldown', 60));
    }

    public static function verifiedSessionHours(): int
    {
        return max(1, (int) config('transfer_review.verified_session_hours', 4));
    }

    /* ──────────────────────────── stages ──────────────────────────── */

    public static function label(?string $status): string
    {
        return match ($status) {
            self::STATUS_AWAITING => 'Awaiting the Executive Director’s verification',
            self::STATUS_VERIFIED => 'Verified — decision not submitted yet',
            self::STATUS_DECIDED  => 'Reviewed — decision recorded',
            self::STATUS_REVOKED  => 'Invitation withdrawn',
            default               => 'Unknown',
        };
    }

    public static function hint(?string $status): string
    {
        return match ($status) {
            self::STATUS_AWAITING => 'The email was sent. Nothing is decided until the recipient verifies the mailbox and submits a decision.',
            self::STATUS_VERIFIED => 'The recipient proved they hold the official mailbox. The transfer still waits for their decision.',
            self::STATUS_DECIDED  => 'The decision has been recorded and the invitation cannot be used again.',
            self::STATUS_REVOKED  => 'This invitation no longer works. Issue a new one to ask again.',
            default               => '',
        };
    }

    public static function decisionLabel(?string $decision): string
    {
        return match ($decision) {
            self::DECISION_APPROVED => 'Approved in full',
            self::DECISION_PARTIAL  => 'Approved in part',
            self::DECISION_REJECTED => 'Rejected',
            default                 => 'No decision yet',
        };
    }

    public static function itemLabel(?string $decision): string
    {
        return match ($decision) {
            self::ITEM_APPROVED => 'Approved',
            self::ITEM_DECLINED => 'Not approved',
            default             => 'Awaiting decision',
        };
    }

    /** An invitation the office is still waiting on. */
    public static function isOutstanding(?string $status): bool
    {
        return in_array($status, [self::STATUS_AWAITING, self::STATUS_VERIFIED], true);
    }

    public static function isExpired(?object $review): bool
    {
        if (! $review || empty($review->expires_at)) {
            return false;
        }

        return \Illuminate\Support\Carbon::parse($review->expires_at)->isPast();
    }

    /** Can this invitation still take a decision? */
    public static function canDecide(?object $review): bool
    {
        if (! $review) {
            return false;
        }

        return $review->status === self::STATUS_VERIFIED && ! self::isExpired($review);
    }

    /* ──────────────────────────── reading ─────────────────────────── */

    /** Every invitation ever issued for a request, newest first. */
    public static function forRequest(int $requestId): Collection
    {
        if (! self::tablesReady() || $requestId <= 0) {
            return new Collection();
        }

        return DB::table('transfer_reviews')
            ->where('request_id', $requestId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => self::present($row));
    }

    /** The invitation the office is currently waiting on, if any. */
    public static function outstandingForRequest(int $requestId): ?object
    {
        if (! self::tablesReady() || $requestId <= 0) {
            return null;
        }

        $row = DB::table('transfer_reviews')
            ->where('request_id', $requestId)
            ->whereIn('status', [self::STATUS_AWAITING, self::STATUS_VERIFIED])
            ->orderByDesc('id')
            ->first();

        return $row ? self::present($row) : null;
    }

    /** The newest invitation for a request, whatever became of it. */
    public static function latestForRequest(int $requestId): ?object
    {
        if (! self::tablesReady() || $requestId <= 0) {
            return null;
        }

        $row = DB::table('transfer_reviews')
            ->where('request_id', $requestId)
            ->orderByDesc('id')
            ->first();

        return $row ? self::present($row) : null;
    }

    /**
     * The invitation behind a link.
     *
     * The token is looked up by its hash: the raw value is never stored, so a
     * dump of this table cannot be replayed as a link. The hash is a plain
     * SHA-256 because the token is 64 random characters — there is nothing to
     * guess, so there is nothing for a slow hash to protect.
     */
    public static function findByToken(string $token): ?object
    {
        $token = trim($token);

        if (! self::tablesReady() || $token === '' || strlen($token) > 128) {
            return null;
        }

        $row = DB::table('transfer_reviews')->where('token_hash', hash('sha256', $token))->first();

        return $row ? self::present($row) : null;
    }

    /** Fill in the names and derived state the views read. */
    public static function present(object $review): object
    {
        $review->status_label   = self::label($review->status);
        $review->status_hint    = self::hint($review->status);
        $review->decision_label = self::decisionLabel($review->decision ?? null);
        $review->is_expired     = self::isExpired($review);
        $review->is_revoked     = $review->status === self::STATUS_REVOKED;
        $review->is_decided     = $review->status === self::STATUS_DECIDED;
        $review->is_outstanding = self::isOutstanding($review->status);
        $review->can_decide     = self::canDecide($review);
        $review->request_id     = (int) ($review->request_id ?? 0);

        return $review;
    }

    /**
     * What the Admin's Requests page needs to know about one transfer request.
     *
     * Everything the review panel and the forward checkbox need, in the shape
     * the page already speaks in (a plain array inside the requests payload),
     * so no route has to be taught a second vocabulary. It never contains a
     * token: the link exists only in the email that carried it.
     *
     * @return array<string, mixed>
     */
    public static function panel(int $requestId): array
    {
        // The page reads the same keys whatever the state of the server, so the
        // answer to "can this be forwarded?" is a sentence either way rather
        // than a missing field the dialog has to guess at.
        $notMigrated = 'The transfer review tables have not been created on this server yet, so a transfer cannot be sent to the Executive Director. Run the database migration, then try again.';

        $panel = [
            'tables_ready'      => self::tablesReady(),
            'recipient'         => self::recipientName(),
            'default_recipient' => self::defaultRecipientEmail(),
            'recipient_email'   => self::suggestedRecipient($requestId),
            'forward_default'   => self::forwardByDefault(),
            'outstanding'       => null,
            'eligible'          => false,
            'problem'           => null,
            'can_send'          => false,
            'can_forward'       => false,
            'forward_problem'   => self::tablesReady() ? null : $notMigrated,
            'can_revoke'        => false,
            'history'           => [],
        ];

        if (! self::tablesReady()) {
            $panel['problem'] = $notMigrated;

            return $panel;
        }

        if ($requestId <= 0) {
            return $panel;
        }

        $request = DB::table('requests')->where('id', $requestId)->first();
        $problem = self::eligibilityProblem($request);
        $outstanding = self::outstandingForRequest($requestId);

        // Asking at all: this is the question the approve dialog's checkbox
        // depends on, and it is asked with the approval still pending.
        $forwardProblem = self::eligibilityProblem($request, false);

        $panel['eligible']        = $problem === null;
        $panel['problem']         = $problem;
        // Forwarding no longer depends on a deployment variable: the office
        // types the address, so the only question left is whether the request
        // itself can be reviewed.
        $panel['can_send']        = $problem === null;
        $panel['can_forward']     = $forwardProblem === null;
        $panel['forward_problem'] = $forwardProblem;
        $panel['can_revoke']      = (bool) $outstanding;

        // The address the dialog should start with: the one used for this request
        // last time if there was one, otherwise the deployment's usual address,
        // otherwise empty — and empty means the office has to type it.
        $panel['recipient_email'] = self::suggestedRecipient($requestId);

        if ($outstanding) {
            $panel['outstanding'] = [
                'id'              => (int) $outstanding->id,
                'status'          => $outstanding->status,
                'status_label'    => $outstanding->status_label,
                'status_hint'     => $outstanding->status_hint,
                'recipient_email' => $outstanding->recipient_email,
                'sent_at'         => self::stamp($outstanding->last_sent_at),
                'sent_count'      => (int) $outstanding->sent_count,
                'expires_at'      => self::stamp($outstanding->expires_at),
                'verified_at'     => self::stamp($outstanding->verified_at),
            ];
        }

        foreach (self::forRequest($requestId)->take(5) as $past) {
            $panel['history'][] = [
                'id'              => (int) $past->id,
                'status_label'    => $past->status_label,
                'decision_label'  => $past->decision_label,
                'recipient_email' => $past->recipient_email,
                'sent_at'         => self::stamp($past->last_sent_at),
                'decided_at'      => self::stamp($past->decided_at),
                'revoked_reason'  => $past->revoked_reason,
            ];
        }

        return $panel;
    }

    /**
     * Every transfer the Executive Director has been asked about, for the
     * Transfer page's own tab.
     *
     * The Requests page can answer this one transfer request at a time, and the
     * office can send a transfer from the Transfer page itself — so a link the
     * office can only find by going back to Requests is a link it will lose
     * track of. One row per invitation, newest first, carrying the same facts
     * the invitation email put in front of the Executive Director: who the
     * transfer is from and to, which assets, why, where the email went, and what
     * came back. The office can answer "what exactly did we send, and what is
     * still unanswered?" without leaving this page.
     *
     * Read-only, and no token: the link exists only in the email that carried
     * it. `can_revoke` and `can_resend` are the two actions the page may offer,
     * and both are answered by the same rules the endpoints themselves apply.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function board(int $limit = 60): array
    {
        if (! self::tablesReady()) {
            return [];
        }

        $reviews = DB::table('transfer_reviews')->orderByDesc('id')->limit(max(1, min($limit, 200)))->get();

        if ($reviews->isEmpty()) {
            return [];
        }

        $requestIds = $reviews->pluck('request_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $requests  = DB::table('requests')->whereIn('id', $requestIds)->get()->keyBy('id');
        $assets    = self::assetsForRequests($requestIds);
        $items     = DB::table('transfer_review_items')
            ->whereIn('review_id', $reviews->pluck('id')->map(fn ($id) => (int) $id)->all())
            ->get();
        $approvals = DB::table('audit_logs')
            ->whereIn('request_id', $requestIds)
            ->where('action_type', 'APPROVAL')
            ->orderBy('id')
            ->get(['request_id', 'user_id', 'notes', 'created_at'])
            ->groupBy('request_id');
        $names     = AssetTransfer::userLabels();

        $board = [];

        foreach ($reviews as $review) {
            $request = $requests->get((int) $review->request_id);

            // A review whose request is gone describes nothing; the foreign key
            // cascades on delete, so this is belt and braces.
            if (! $request) {
                continue;
            }

            $reviewId  = (int) $review->id;
            $requestId = (int) $review->request_id;

            $rowAssets = $assets->get($requestId, new Collection());
            $decisions = $items
                ->where('review_id', $reviewId)
                ->filter(fn ($item) => (int) ($item->asset_id ?? 0) > 0)
                ->keyBy('asset_id');

            $from   = $names[(int) $request->user_id] ?? null;
            $to     = $names[(int) ($request->assign_to_user_id ?? 0)] ?? null;
            $toName = $to['name'] ?? ('user #' . (int) ($request->assign_to_user_id ?? 0));

            // The office's release, read back out of the trail: the newest
            // APPROVAL entry that is not the Executive Director's own decision.
            $release = ($approvals->get($requestId) ?? new Collection())
                ->last(fn ($entry) => ! str_starts_with((string) $entry->notes, 'Executive Director'));

            $status      = (string) $review->status;
            $outstanding = self::isOutstanding($status);
            $decided     = $status === self::STATUS_DECIDED;
            $decision    = $review->decision ? (string) $review->decision : null;

            $assetRows = [];

            foreach ($rowAssets as $asset) {
                $item = $decisions->get((int) $asset->id);

                $assetRows[] = [
                    'id'             => (int) $asset->id,
                    'code'           => $asset->Asset_code,
                    'name'           => $asset->Asset_name,
                    'category'       => $asset->Category,
                    'condition'      => $asset->Condition,
                    'lifecycle'      => $asset->Lifecycle_Status,
                    'location'       => $asset->asset_location,
                    'decision'       => $item->decision ?? null,
                    'decision_label' => self::itemLabel($item->decision ?? null),
                ];
            }

            $board[] = [
                'id'              => $reviewId,
                'request_id'      => $requestId,
                'reference'       => 'TR-' . str_pad((string) $requestId, 4, '0', STR_PAD_LEFT),
                'origin'          => $release && str_contains((string) $release->notes, self::ORIGIN_TRANSFER_PAGE)
                                        ? 'transfer_page'
                                        : 'request',
                'status'          => $status,
                'status_label'    => self::label($status),
                'status_hint'     => self::hint($status),
                'expired'         => self::isExpired($review),
                'awaiting'        => $outstanding && ! self::isExpired($review),
                'decided'         => $decided,
                'decision'        => $decision,
                'decision_label'  => self::decisionLabel($decision),
                'outcome'         => self::boardOutcome($decision, $review->rejection_reason ?? null, $decisions, $toName),
                'recipient_email' => $review->recipient_email,
                'sent_at'         => self::stamp($review->last_sent_at),
                'sent_count'      => (int) ($review->sent_count ?? 0),
                'expires_at'      => self::stamp($review->expires_at),
                'verified_at'     => self::stamp($review->verified_at),
                'decided_at'      => self::stamp($review->decided_at),
                'revoked_reason'  => $review->revoked_reason,
                'reason'          => $request->Note,
                'notes'           => $request->admin_remarks,
                'request_status'  => $request->status,
                'from'            => $from,
                'to'              => $to,
                'assets'          => $assetRows,
                'asset_count'     => count($assetRows),
                'released_by'     => $release && ! empty($release->user_id)
                                        ? ($names[(int) $release->user_id]['name'] ?? null)
                                        : null,
                'released_at'     => self::stamp($release->created_at ?? null),
                'can_revoke'      => $outstanding,
                'can_resend'      => $outstanding || self::eligibilityProblem($request) === null,
            ];
        }

        return $board;
    }

    /** What a recorded decision meant, in one sentence, for the page's records. */
    private static function boardOutcome(?string $decision, ?string $reason, Collection $decisions, string $toName): ?string
    {
        if ($decision === self::DECISION_REJECTED) {
            $reason = trim((string) $reason);

            return 'Rejected — nothing was transferred.' . ($reason !== '' ? ' Reason: ' . $reason : '');
        }

        if ($decision !== self::DECISION_APPROVED && $decision !== self::DECISION_PARTIAL) {
            return null;
        }

        $total    = $decisions->count();
        $approved = $decisions->where('decision', self::ITEM_APPROVED)->count();
        $declined = $decisions->where('decision', self::ITEM_DECLINED)->count();

        $sentence = $approved . ' of ' . $total . ' asset' . ($total === 1 ? '' : 's') . ' approved';

        if ($approved > 0) {
            $sentence .= ' and now assigned to ' . $toName;
        }

        if ($declined > 0) {
            $sentence .= '; ' . $declined . ' not approved and left where they were';
        }

        return $sentence . '.';
    }

    /** A stored timestamp, formatted for the pages that read it. */
    public static function stamp($value, string $format = 'M j, Y g:i A'): ?string
    {
        return empty($value) ? null : \Illuminate\Support\Carbon::parse($value)->format($format);
    }

    /**
     * The per-asset decisions recorded for a review.
     *
     * The asset's tag comes along so the page can name what was decided rather
     * than showing a row id, and it is read with a left join: an asset deleted
     * after the decision leaves the decision itself intact.
     */
    public static function items(int $reviewId): Collection
    {
        if (! self::tablesReady() || $reviewId <= 0) {
            return new Collection();
        }

        return DB::table('transfer_review_items')
            ->leftJoin('assets', 'transfer_review_items.asset_id', '=', 'assets.id')
            ->where('transfer_review_items.review_id', $reviewId)
            ->orderBy('transfer_review_items.id')
            ->get([
                'transfer_review_items.*',
                'assets.Asset_code as asset_code',
                'assets.Asset_name as asset_name',
            ])
            ->map(function ($item) {
                $item->decision_label = self::itemLabel($item->decision ?? null);
                $item->label = $item->asset_code ?: ($item->asset_id ? ('asset #' . $item->asset_id) : 'asset');

                return $item;
            });
    }

    /**
     * The assets a transfer request covers, with everything the review page has
     * to show about each one: the recorded detail, never an invented field.
     *
     * @return Collection<int, object>
     */
    public static function transferAssets(int $requestId): Collection
    {
        if ($requestId <= 0) {
            return new Collection();
        }

        return self::assetsForRequests([$requestId])->get($requestId, new Collection());
    }

    /**
     * The assets of several transfer requests at once, keyed by request id.
     *
     * One implementation for one request and for a page that lists many: the
     * Transfer page's Executive Review tab shows what each invitation sent, and
     * asking the database once per row is how a listing quietly becomes slow.
     * The shape each request's assets arrive in is exactly what
     * `transferAssets()` has always returned.
     *
     * @param  array<int, int>  $requestIds
     * @return Collection<int, Collection<int, object>>
     */
    public static function assetsForRequests(array $requestIds): Collection
    {
        $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds))));

        if (! $requestIds) {
            return new Collection();
        }

        $items = DB::table('request_items')
            ->whereIn('request_id', $requestIds)
            ->orderBy('id')
            ->get(['request_id', 'asset_id']);

        // Older single-asset requests keep the asset on the request row, and only
        // when there are no items for them at all.
        $singles = DB::table('requests')
            ->whereIn('id', $requestIds)
            ->whereNotNull('asset_id')
            ->pluck('asset_id', 'id');

        $ordered = [];

        foreach ($requestIds as $requestId) {
            $ordered[$requestId] = [];
        }

        foreach ($items as $item) {
            $assetId = (int) $item->asset_id;

            if ($assetId > 0) {
                $ordered[(int) $item->request_id][] = $assetId;
            }
        }

        foreach ($singles as $requestId => $assetId) {
            if (empty($ordered[(int) $requestId]) && (int) $assetId > 0) {
                $ordered[(int) $requestId] = [(int) $assetId];
            }
        }

        $assetIds = array_values(array_unique(array_merge(...array_values($ordered))));

        $grouped = new Collection();

        if (! $assetIds) {
            foreach ($ordered as $requestId => $ids) {
                $grouped->put((int) $requestId, new Collection());
            }

            return $grouped;
        }

        $rows = DB::table('assets')
            ->leftJoin('users as holder', 'assets.user_id', '=', 'holder.id')
            ->leftJoin('employee_numbers as holder_emp', 'holder.employee_numbers_id', '=', 'holder_emp.id')
            ->whereIn('assets.id', $assetIds)
            ->orderBy('assets.Asset_name')
            ->get([
                'assets.id',
                'assets.Asset_code',
                'assets.Asset_name',
                'assets.Category',
                'assets.Condition',
                'assets.Lifecycle_Status',
                'assets.serial_Number',
                'assets.asset_location',
                'assets.accusion_date',
                DB::raw('holder.email as holder_email'),
                DB::raw('holder_emp."Full_Name" as holder_name'),
            ]);

        $byId = $rows->keyBy('id');

        foreach ($ordered as $requestId => $ids) {
            $forRequest = new Collection();

            // Keep the request's own order, and keep an asset that has since
            // been deleted: the request still lists it.
            foreach ($ids as $assetId) {
                if (isset($byId[$assetId])) {
                    $forRequest->push($byId[$assetId]);
                }
            }

            $grouped->put((int) $requestId, $forRequest);
        }

        return $grouped;
    }

    /**
     * Everything the review page shows, in one place, so the view never has to
     * guess which column means what.
     *
     * @return array<string, mixed>
     */
    public static function summary(object $review): array
    {
        $request = DB::table('requests')->where('id', $review->request_id)->first();

        if (! $request) {
            return [
                'reference' => null,
                'request'   => null,
                'assets'    => new Collection(),
                'from'      => null,
                'to'        => null,
                'handover'  => ['warning' => null, 'note' => null],
            ];
        }

        $names = AssetTransfer::userLabels();

        $fromId = (int) ($request->user_id ?? 0);
        $toId   = (int) ($request->assign_to_user_id ?? 0);

        $from = $names[$fromId] ?? null;
        $to   = $names[$toId] ?? null;

        // The office's approval is recorded as an APPROVAL entry in the audit
        // log. Reading it back is how this page can say when the request was
        // released for review, and by whom, without inventing a column the
        // schema does not have.
        $approval = DB::table('audit_logs')
            ->where('request_id', $request->id)
            ->where('action_type', 'APPROVAL')
            ->orderByDesc('id')
            ->first();

        $approvedBy = null;

        if ($approval && ! empty($approval->user_id)) {
            $approvedBy = $names[(int) $approval->user_id] ?? null;
        }

        $assets = self::transferAssets((int) $request->id);

        // Where each asset stands now, so the page can say what is really going
        // to happen instead of showing a custodian column that contradicts the
        // request. Read here, in the one place the page takes its facts from.
        $states = self::handoverStates($review, $request, $assets);

        foreach ($assets as $asset) {
            $state = $states[(int) $asset->id] ?? ['state' => 'giver', 'holder' => null, 'holder_name' => null];

            $asset->handover_state       = $state['state'];
            $asset->handover_holder      = $state['holder'];
            $asset->handover_holder_name = $state['holder_name'];
        }

        return [
            'reference'   => 'TR-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT),
            'request'     => $request,
            'from'        => $from,
            'to'          => $to,
            'approved_by' => $approvedBy,
            'approved_at' => $approval->created_at ?? null,
            'assets'      => $assets,
            'handover'    => self::handoverBriefing($request, $assets, $states),
            'attachment'  => (! empty($request->url) && str_starts_with((string) $request->url, 'http'))
                ? (string) $request->url
                : null,
        ];
    }

    /**
     * What the person deciding should be told about the handover before they
     * press anything.
     *
     * Two different things, said differently, because they mean opposite things:
     * an asset the receiving employee already holds is not a problem (there is
     * nothing left to move for it), while an asset that has gone to a *third*
     * employee makes the decision unanswerable — and the person reading the page
     * deserves to know that before they choose, not after.
     *
     * @param  Collection<int, object>  $assets
     * @param  array<int, array{state: string, holder: int|null, holder_name: string|null}>  $states
     * @return array{warning: string|null, note: string|null}
     */
    public static function handoverBriefing(object $request, Collection $assets, array $states): array
    {
        $codes = [];

        foreach ($assets as $asset) {
            $codes[(int) $asset->id] = $asset->Asset_code ?: ('asset #' . $asset->id);
        }

        $toName    = self::userName((int) ($request->assign_to_user_id ?? 0));
        $already   = [];
        $elsewhere = [];

        foreach ($states as $assetId => $state) {
            $label = $codes[(int) $assetId] ?? ('asset #' . $assetId);

            if ($state['state'] === 'recipient') {
                $already[] = $label;
            }

            if ($state['state'] === 'elsewhere') {
                $elsewhere[] = $label . ' is now held by ' . ($state['holder_name'] ?? 'another employee');
            }
        }

        $note = null;

        if ($already) {
            $them = count($already) === 1 ? 'it' : 'them';

            $note = self::nameList($already) . (count($already) === 1 ? ' is' : ' are')
                . ' already held by ' . $toName . ', so approving ' . $them . ' changes nothing for ' . $them . '.';
        }

        $warning = null;

        if ($elsewhere) {
            $warning = 'Somebody else has taken custody since this invitation was sent: ' . self::nameList($elsewhere)
                . '. A decision cannot be recorded on ' . (count($elsewhere) === 1 ? 'that asset' : 'those assets')
                . ' — approving would move an asset this transfer is no longer about. Ask the Asset Management Office to issue a new invitation.';
        }

        return ['warning' => $warning, 'note' => $note];
    }

    /* ──────────────────────── eligibility ──────────────────────── */

    /**
     * Why this request cannot be forwarded for review, or null when it can.
     *
     * Deliberately about the *request*, not the invitation: the same question is
     * asked when the office issues a link and again when a decision arrives.
     *
     * $requireApproved is what separates the two moments. Issuing a link and
     * deciding on one need the request to have passed the office already; the
     * office's own approve dialog is asked *before* that approval is recorded,
     * so it asks the same question with the approval still pending — otherwise
     * the answer would be "not yet" at the only moment the office can choose.
     */
    public static function eligibilityProblem(?object $request, bool $requireApproved = true): ?string
    {
        if (! $request) {
            return 'That transfer request no longer exists.';
        }

        if (strtolower((string) $request->request_type) !== 'transfer') {
            return 'Only a transfer request can go to the Executive Director.';
        }

        if (! $requireApproved && strtolower((string) $request->status) === 'rejected') {
            return 'This request has already been rejected, so there is nothing left to send.';
        }

        if ($requireApproved && strtolower((string) $request->status) !== 'approved') {
            return 'Only an administratively approved transfer request can go to the Executive Director. This request is ' . $request->status . '.';
        }

        if (empty($request->assign_to_user_id)) {
            return 'This transfer request does not name the employee who should receive the assets, so there is nothing for the Executive Director to approve — and the assets would have nowhere to go.';
        }

        if (! self::transferAssets((int) $request->id)->count()) {
            return 'This transfer request has no assets linked to it, so there is nothing to review.';
        }

        // A request whose transfer already went through has nothing to review.
        // "In full, or for the assets that were approved" because a partially
        // approved transfer is executed too — only some of its assets moved, and
        // saying they all did would be a lie.
        if (self::alreadyExecuted((int) $request->id)) {
            return 'This transfer has already been carried out — in full, or for the assets that were approved — so there is nothing left for the Executive Director to review.';
        }

        // One decision per asset. An asset another review is holding cannot be
        // put into a second one: the transfer waiting on it would have to be
        // decided first, and two invitations for the same asset would let the
        // Executive Director answer a question that has already moved on.
        if ($held = self::assetsOnHold(self::transferAssets((int) $request->id)->pluck('id')->all(), (int) $request->id)) {
            return self::holdRefusal($held);
        }

        return null;
    }

    /**
     * Has this request's transfer already been applied?
     *
     * The execution writes one TRANSFER audit entry per asset — the same
     * evidence the Transfer page reads — so the question asked here is whether
     * such an entry exists for this request. The review never writes one: an
     * invitation, a verification and a withdrawal are UPDATE entries on the
     * request, which is what stops an invitation from making its own request
     * look like a transfer that has already happened.
     */
    public static function alreadyExecuted(int $requestId): bool
    {
        return DB::table('audit_logs')
            ->where('request_id', $requestId)
            ->where('action_type', 'TRANSFER')
            ->whereNotNull('asset_id')
            ->exists();
    }

    /**
     * Assets whose transfer is waiting on the Executive Director, and the
     * request each one is held for.
     *
     * While an invitation is live the assets are on hold. They are still the
     * sending employee's, they are still listed in that employee's custody, and
     * no other door may move them — not the Transfer page, not a pullout being
     * settled, not an approval of some later request. Without this the promise
     * the review page makes ("nothing changes hands until the decision comes
     * back") would hold only for the one route that remembers to ask, and a
     * transfer recorded elsewhere would leave the Executive Director holding a
     * link to assets that had already moved.
     *
     * An invitation that has expired is not a hold: it can no longer take a
     * decision, so it cannot keep an asset out of everybody else's hands.
     *
     * $exceptRequestId leaves one request's own invitation out of the answer.
     * The review of a request is asked this question while deciding on exactly
     * that request, and its own invitation must not read as somebody else
     * holding the assets.
     *
     * @param  array<int, int>  $assetIds
     * @return array<int, array{request_id: int, reference: string}>  keyed by asset id
     */
    public static function assetsOnHold(array $assetIds, ?int $exceptRequestId = null): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));

        if (! $assetIds || ! self::tablesReady()) {
            return [];
        }

        $rows = DB::table('transfer_reviews')
            ->join('request_items', 'request_items.request_id', '=', 'transfer_reviews.request_id')
            ->whereIn('request_items.asset_id', $assetIds)
            ->whereIn('transfer_reviews.status', [self::STATUS_AWAITING, self::STATUS_VERIFIED])
            ->whereNull('transfer_reviews.executed_at')
            ->where(function ($query) {
                $query->whereNull('transfer_reviews.expires_at')
                    ->orWhere('transfer_reviews.expires_at', '>', now());
            })
            ->when($exceptRequestId, function ($query) use ($exceptRequestId) {
                $query->where('transfer_reviews.request_id', '!=', $exceptRequestId);
            })
            ->orderByDesc('transfer_reviews.id')
            ->get(['request_items.asset_id', 'transfer_reviews.request_id']);

        $hold = [];

        foreach ($rows as $row) {
            $assetId = (int) $row->asset_id;

            // Newest invitation first, and the newest one is the one holding
            // the asset: an older invitation for the same asset is superseded.
            if (isset($hold[$assetId])) {
                continue;
            }

            $hold[$assetId] = [
                'request_id' => (int) $row->request_id,
                'reference'  => 'TR-' . str_pad((string) $row->request_id, 4, '0', STR_PAD_LEFT),
            ];
        }

        return $hold;
    }

    /* ────────── the handover this review describes ────────── */

    private static ?bool $hasHandover = null;

    /**
     * Does this installation have the column that records the handover?
     *
     * Asked rather than assumed, for the same reason tablesReady() is: the code
     * ships before `php artisan migrate` runs on the server, and a missing
     * column must not turn a transfer into a 500.
     */
    public static function hasHandover(): bool
    {
        if (self::$hasHandover === null) {
            self::$hasHandover = self::tablesReady() && Schema::hasColumn('transfer_reviews', 'handover');
        }

        return self::$hasHandover;
    }

    /**
     * Who is holding each of these assets right now, keyed by asset id.
     *
     * A null holder is an asset still in the inventory — nobody's custody —
     * which is a legitimate thing for a transfer to take and hand to somebody.
     *
     * @param  array<int, int>  $assetIds
     * @return array<int, int|null>
     */
    public static function currentHolders(array $assetIds): array
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));

        if (! $assetIds) {
            return [];
        }

        $holders = [];

        foreach (DB::table('assets')->whereIn('id', $assetIds)->get(['id', 'user_id']) as $row) {
            $holders[(int) $row->id] = $row->user_id === null ? null : (int) $row->user_id;
        }

        return $holders;
    }

    /**
     * The handover this invitation describes, as it was written down when the
     * invitation was issued: {asset id: the user holding it then}.
     *
     * This is what makes a decision answerable. Judged against the employee who
     * *requested* the transfer it would be wrong about every Department Head
     * request — a head requests on behalf of their department, so the assets are
     * held by the department's employees and never by the head — and it could
     * not tell "the asset has since gone to somebody else" from "the request was
     * never about the requester's own asset".
     *
     * An invitation issued before this column existed has no record: the
     * decision then falls back to the requester, which is the rule that was
     * always true for an employee requesting their own asset.
     *
     * @return array<int, int|null>
     */
    public static function handedOverBy(?object $review): array
    {
        if (! $review || empty($review->handover)) {
            return [];
        }

        $decoded = is_array($review->handover)
            ? $review->handover
            : json_decode((string) $review->handover, true);

        if (! is_array($decoded)) {
            return [];
        }

        $recorded = [];

        foreach ($decoded as $assetId => $userId) {
            $recorded[(int) $assetId] = $userId === null ? null : (int) $userId;
        }

        return $recorded;
    }

    /**
     * Write down who holds each asset right now — the handover an invitation
     * about to be sent describes. Null when the column is not there yet.
     */
    public static function handoverSnapshot(int $requestId): ?string
    {
        if (! self::hasHandover()) {
            return null;
        }

        $holders = self::currentHolders(self::transferAssets($requestId)->pluck('id')->all());

        return $holders ? json_encode($holders) : null;
    }

    /**
     * What each asset of the transfer looks like now, against the request and
     * against the invitation that describes it.
     *
     * One of three answers per asset, and each one means something different to
     * the person deciding:
     *
     *   giver      Held by somebody this transfer may take it from — the
     *              employee who held it when the office asked, the employee who
     *              requested the transfer, or the inventory. It will move.
     *   recipient  Already held by the receiving employee. Nothing to move for
     *              it: approving it does not take it from anybody.
     *   elsewhere  In a third employee's hands now. Approving would take an asset
     *              from somebody this transfer was never about, so the decision
     *              is refused and says who holds it.
     *
     * @param  Collection<int, object>  $assets
     * @return array<int, array{state: string, holder: int|null, holder_name: string|null}>
     */
    public static function handoverStates(?object $review, object $request, Collection $assets): array
    {
        $holders   = self::currentHolders($assets->pluck('id')->all());
        $recorded  = self::handedOverBy($review);
        $requester = (int) ($request->user_id ?? 0);
        $recipient = (int) ($request->assign_to_user_id ?? 0);
        $names     = AssetTransfer::userLabels();

        $states = [];

        foreach ($assets as $asset) {
            $assetId = (int) $asset->id;
            $holder  = $holders[$assetId] ?? null;

            if ($recipient > 0 && $holder === $recipient) {
                $state = 'recipient';
            } elseif ($holder === null || $holder === $requester || $holder === ($recorded[$assetId] ?? null)) {
                $state = 'giver';
            } else {
                $state = 'elsewhere';
            }

            $states[$assetId] = [
                'state'       => $state,
                'holder'      => $holder,
                'holder_name' => $holder === null ? null : ($names[$holder]['name'] ?? ('user #' . $holder)),
            ];
        }

        return $states;
    }

    /**
     * Why a decision cannot be applied: the assets it would move are in a third
     * employee's hands now, and names who has them.
     *
     * Only the assets the decision actually covers are named. An asset the
     * Executive Director is not approving cannot make their decision stale.
     *
     * @param  Collection<int, object>  $assets
     * @param  array<int, array{state: string, holder: int|null, holder_name: string|null}>  $states
     * @param  array<int, int>  $deciding
     */
    public static function handoverRefusal(Collection $assets, array $states, array $deciding): string
    {
        $codes = [];

        foreach ($assets as $asset) {
            $codes[(int) $asset->id] = $asset->Asset_code ?: ('asset #' . $asset->id);
        }

        $labels = [];

        foreach ($deciding as $assetId) {
            if (($states[(int) $assetId]['state'] ?? null) !== 'elsewhere') {
                continue;
            }

            $labels[] = ($codes[(int) $assetId] ?? ('asset #' . $assetId))
                . ' is now held by ' . ($states[(int) $assetId]['holder_name'] ?? 'another employee');
        }

        if (! $labels) {
            return 'This review is out of date. Nothing was changed. Ask the Asset Management Office to issue a new invitation if the transfer still needs a decision.';
        }

        return 'This review is out of date: ' . self::nameList($labels)
            . '. Somebody else took custody while this invitation was open, so approving it would move an asset this transfer is no longer about. Nothing was changed. Ask the Asset Management Office to issue a new invitation if the transfer still needs a decision.';
    }

    /**
     * Why these assets cannot be moved yet — one sentence naming the assets and
     * the request that is holding each of them, and what to do about it.
     *
     * @param  array<int, array{request_id: int, reference: string}>  $hold
     */
    public static function holdRefusal(array $hold): string
    {
        if (! $hold) {
            return '';
        }

        $codes = DB::table('assets')->whereIn('id', array_keys($hold))->pluck('Asset_code', 'id');

        $labels = [];
        $references = [];

        foreach ($hold as $assetId => $waitingOn) {
            $code = $codes[$assetId] ?? null;

            $labels[] = $code ?: ('asset #' . $assetId);
            $references[$waitingOn['reference']] = true;
        }

        $references = array_keys($references);

        return self::nameList($labels)
            . (count($labels) === 1 ? ' is' : ' are')
            . ' on hold: the Executive Director has been asked to decide '
            . (count($references) === 1 ? 'transfer request ' : 'transfer requests ')
            . self::nameList($references)
            . ', and nothing can move until that decision is recorded. Withdraw the invitation if the transfer must not wait.';
    }

    /** "A", "A and B", "A, B and C" — so the refusal reads as a sentence. */
    private static function nameList(array $names): string
    {
        $names = array_values($names);

        if (count($names) < 2) {
            return (string) ($names[0] ?? '');
        }

        $last = array_pop($names);

        return implode(', ', $names) . ' and ' . $last;
    }

    /**
     * Why the invitation behind a link can no longer take a decision.
     *
     * Every reason is named rather than collapsed into one "invalid link": the
     * person reading it is the Executive Director, and the difference between
     * "expired" (ask for a new one) and "already decided" (nothing to do) is the
     * difference between an action and a dead end.
     */
    public static function unusableReason(object $review): ?string
    {
        if ($review->status === self::STATUS_REVOKED) {
            return 'This invitation was withdrawn by the Asset Management Office'
                . (! empty($review->revoked_reason) ? ' (' . $review->revoked_reason . ')' : '')
                . '. Ask them for a new invitation if the transfer still needs a decision.';
        }

        if ($review->status === self::STATUS_DECIDED) {
            return 'A decision has already been submitted on this invitation'
                . (! empty($review->decided_at) ? ' (' . \Illuminate\Support\Carbon::parse($review->decided_at)->format('F j, Y g:i A') . ')' : '')
                . '. It cannot be used again.';
        }

        if (self::isExpired($review)) {
            return 'This invitation expired on '
                . \Illuminate\Support\Carbon::parse($review->expires_at)->format('F j, Y g:i A')
                . '. Ask the Asset Management Office for a new invitation.';
        }

        return null;
    }

    /* ─────────────────────── issuing invitations ─────────────────────── */

    /**
     * Issue an invitation for an approved transfer request and email the link.
     *
     * Idempotent in the way that matters: any outstanding invitation for the
     * same request is withdrawn first, inside the same transaction and behind a
     * lock on the request row, so two Admins clicking at once cannot leave two
     * live links behind — and a resend cannot leave the previous one working.
     *
     * @return array{ok: bool, message: string, review_id?: int, delivered?: bool, errors?: array<string, string>}
     */
    public static function invite(
        int $requestId,
        ?User $actor = null,
        bool $replacement = false,
        ?string $recipientEmail = null
    ): array {
        if (! self::tablesReady()) {
            return ['ok' => false, 'message' => 'The transfer review tables have not been created yet — run the database migration.', 'errors' => ['review' => 'The transfer review tables have not been created yet — run the database migration.']];
        }

        // The address the office typed wins. A send that arrived without one
        // goes back to whoever the invitation went to last time, and failing
        // that to the deployment's usual address — never somewhere arbitrary.
        $to = trim((string) $recipientEmail) !== ''
            ? trim((string) $recipientEmail)
            : (self::suggestedRecipient($requestId) ?: self::defaultRecipientEmail());

        if (! self::validEmail($to)) {
            $message = 'Enter the email address the Executive Director actually reads — that mailbox is where the link and its verification code go, and nowhere else.';

            return ['ok' => false, 'message' => $message, 'errors' => ['review' => $message]];
        }

        $request = DB::table('requests')->where('id', $requestId)->first();

        if ($problem = self::eligibilityProblem($request)) {
            return ['ok' => false, 'message' => $problem, 'errors' => ['review' => $problem]];
        }

        // The token exists here, in this variable, and in the email. Only its
        // hash is written down.
        $token   = Str::random(64);
        $expires = now()->addHours(self::linkTtlHours());

        // The code travels with the invitation, so the recipient opens one email
        // and can act on it. Nothing about the mailbox proof is given up: the
        // link and the code go to the same address, and only that address can
        // use either. Where the code is written down, and what it allows, is the
        // same as a code asked for from the page — including the allowance, so
        // the invitation's own code counts as one of them.
        $fresh = self::freshCode(null);

        try {
            $reviewId = DB::transaction(function () use ($requestId, $token, $expires, $actor, $to, $fresh) {
                // One invitation at a time per request: the lock is what makes
                // that true even when two requests arrive together.
                DB::table('requests')->where('id', $requestId)->lockForUpdate()->first();

                self::withdrawOutstanding($requestId, 'Replaced by a newer invitation.', $actor);

                $row = [
                    'request_id'      => $requestId,
                    'token_hash'      => hash('sha256', $token),
                    'recipient_email' => $to,
                    'status'          => self::STATUS_AWAITING,
                    'expires_at'      => $expires,
                    'sent_count'      => 1,
                    'last_sent_at'    => now(),
                    'created_by'      => $actor?->id,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                // Who is holding each asset at the moment the office asks. The
                // decision is read back against this, not against the employee
                // who requested the transfer: a Department Head requests on
                // behalf of their department, and those assets are never the
                // head's own.
                if (($handover = self::handoverSnapshot($requestId)) !== null) {
                    $row['handover'] = $handover;
                }

                // The verification code the invitation email is about to carry.
                $row = array_merge($row, $fresh['columns']);

                return (int) DB::table('transfer_reviews')->insertGetId($row);
            });
        } catch (Throwable $e) {
            Log::error('Issuing a transfer review invitation for request #' . $requestId . ' failed: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'The invitation could not be created. Nothing was changed — please try again.', 'errors' => ['review' => 'The invitation could not be created. Nothing was changed — please try again.']];
        }

        $review = self::latestForRequest($requestId);

        // The email goes out after the row is committed: a transport that hangs
        // must not hold a database transaction open, and a failure here leaves a
        // usable invitation that can simply be sent again.
        $delivery = self::sendInvitation($review, $token, $fresh['code']);

        self::audit(
            actor: $actor,
            requestId: $requestId,
            actionType: 'UPDATE',
            notes: ($replacement ? 'Executive Director review invitation re-issued (resend)' : 'Executive Director review invitation issued')
                . ' for transfer request #' . $requestId,
            description: 'Invitation #' . $reviewId . ' for the Executive Director to review transfer request #' . $requestId
                . ' was created by ' . self::actorName($actor) . ' and sent to ' . $to . '.'
                . ($delivery['ok'] ? '' : ' The email could not be delivered: ' . $delivery['error'])
                . ' No decision has been recorded yet and no asset has changed hands.',
        );

        return [
            'ok'        => true,
            'review_id' => $reviewId,
            'delivered' => $delivery['ok'],
            'message'   => $delivery['ok']
                ? 'Invitation sent to ' . $to . '. The email carries the review link and the verification code the Executive Director needs. It expires on ' . $expires->format('F j, Y') . '.'
                : 'The invitation was created, but the email could not be delivered. ' . $delivery['error']
                    . ' Fix the mail settings and send it again.',
        ];
    }

    /**
     * Withdraw whatever the office is still waiting on and issue a fresh link.
     *
     * The address comes with it: re-sending after the director changes is the
     * whole reason a resend exists, and the previous link stops working in the
     * same breath.
     */
    public static function resend(int $requestId, ?User $actor = null, ?string $recipientEmail = null): array
    {
        return self::invite($requestId, $actor, true, $recipientEmail);
    }

    /** Take away the invitation the office is waiting on. */
    public static function revoke(int $requestId, ?User $actor, string $reason): array
    {
        if (! self::tablesReady()) {
            return ['ok' => false, 'message' => 'The transfer review tables have not been created yet — run the database migration.'];
        }

        $count = self::withdrawOutstanding($requestId, $reason !== '' ? $reason : 'Withdrawn by the Asset Management Office.', $actor);

        if ($count === 0) {
            return ['ok' => false, 'message' => 'There is no outstanding invitation for this request.'];
        }

        self::audit(
            actor: $actor,
            requestId: $requestId,
            actionType: 'UPDATE',
            notes: 'Executive Director review invitation withdrawn for transfer request #' . $requestId,
            description: 'The invitation for the Executive Director to review transfer request #' . $requestId
                . ' was withdrawn by ' . self::actorName($actor) . '. Reason: ' . $reason
                . '. The link no longer works and no decision can be submitted through it.',
        );

        return ['ok' => true, 'message' => 'The invitation was withdrawn. The link no longer works.'];
    }

    /**
     * Withdraw every outstanding invitation for a request.
     *
     * Shared by "resend", the explicit revoke action, and the hook that runs when
     * an office rejects or withdraws the request the link points at — a link that
     * outlives its request is exactly the stale decision this feature has to
     * prevent.
     */
    public static function withdrawOutstanding(int $requestId, string $reason, ?User $actor = null): int
    {
        if (! self::tablesReady() || $requestId <= 0) {
            return 0;
        }

        return DB::table('transfer_reviews')
            ->where('request_id', $requestId)
            ->whereIn('status', [self::STATUS_AWAITING, self::STATUS_VERIFIED])
            ->update([
                'status'         => self::STATUS_REVOKED,
                'token_hash'     => null,
                'code_hash'      => null,
                'revoked_at'     => now(),
                'revoked_by'     => $actor?->id,
                'revoked_reason' => mb_substr($reason, 0, 255),
                'updated_at'     => now(),
            ]);
    }

    /* ──────────────────────── verification ──────────────────────── */

    /**
     * Email a one-time verification code to the official mailbox.
     *
     * The code proves the person holding the link can read that mailbox; typing
     * an email address into a form proves nothing, which is why this step exists
     * before a single asset is shown.
     *
     * @return array{ok: bool, message: string, delivered?: bool, dev_code?: string}
     */
    public static function sendCode(object $review): array
    {
        if (! self::tablesReady()) {
            return ['ok' => false, 'message' => 'The transfer review tables have not been created yet.'];
        }

        if ($reason = self::unusableReason($review)) {
            return ['ok' => false, 'message' => $reason];
        }

        if ($review->status === self::STATUS_VERIFIED) {
            return ['ok' => true, 'message' => 'Verified already — no code is needed.'];
        }

        // Without an address on the invitation there is nowhere to send a code,
        // and this step cannot fall back to a guess: say so and let the office
        // re-issue the invitation.
        if (! self::validEmail($review->recipient_email ?? null) && ! self::validEmail(self::defaultRecipientEmail())) {
            return ['ok' => false, 'message' => 'This invitation has no email address recorded, so a code cannot be sent. Ask the Asset Management Office to send a new invitation.'];
        }

        $cooldown = self::codeResendCooldown();

        if ($cooldown > 0 && ! empty($review->code_sent_at)) {
            $waited = \Illuminate\Support\Carbon::parse($review->code_sent_at)->diffInSeconds(now());

            if ($waited < $cooldown) {
                $wait = max(1, $cooldown - (int) $waited);

                return ['ok' => false, 'message' => 'A code was just sent. Please wait ' . $wait . ' second' . ($wait === 1 ? '' : 's') . ' before asking for another one.'];
            }
        }

        // The window is kept on the row, so the limit survives a restart and
        // does not depend on a cache store being configured. The code the
        // invitation email itself carried counts as one of the allowance — the
        // email is not a way around the limit.
        if (self::codesSentInWindow($review) >= self::maxCodeRequests()) {
            return ['ok' => false, 'message' => 'This invitation has already been sent ' . self::maxCodeRequests() . ' verification codes in the last ' . self::codeWindowMinutes() . ' minutes. Please try again later, or ask the Asset Management Office to issue a new invitation.'];
        }

        $fresh = self::freshCode($review);
        $code  = $fresh['code'];

        DB::table('transfer_reviews')->where('id', $review->id)->update($fresh['columns'] + ['updated_at' => now()]);

        // The code goes to the address this invitation was sent to — the one the
        // office typed when it issued it — never to an address the person
        // holding the link happens to enter.
        $to = (string) ($review->recipient_email ?: self::defaultRecipientEmail());

        $delivery = self::sendCodeEmail($code, $to);

        $result = [
            'ok'      => $delivery['ok'],
            'message' => $delivery['ok']
                ? 'A 6-digit code has been sent to ' . $to
                    . '. It expires in ' . self::codeTtlMinutes() . ' minutes.'
                : 'The code could not be emailed: ' . $delivery['error'] . ' Ask the Asset Management Office to check the mail settings.',
            'delivered' => $delivery['ok'],
        ];

        // Local development: without a reachable mail server the flow would be
        // untestable, so the code is shown on the local environment only. It is
        // never shown anywhere else.
        if (app()->environment('local') && ! $delivery['ok']) {
            $result['dev_code'] = $code;
        }

        return $result;
    }

    /**
     * How many codes this invitation has been sent inside the current window.
     *
     * A window that has run out starts again, so the allowance is "per window",
     * not "per lifetime": an invitation that expires in a week is not silenced
     * by three codes sent on its first afternoon.
     */
    private static function codesSentInWindow(object $review): int
    {
        if (empty($review->code_window_started_at)) {
            return 0;
        }

        $started = \Illuminate\Support\Carbon::parse($review->code_window_started_at);

        return $started->diffInMinutes(now()) >= self::codeWindowMinutes()
            ? 0
            : (int) $review->code_sent_count;
    }

    /**
     * A fresh verification code, and the columns that record it.
     *
     * One place, so the code that travels with the invitation and the code a
     * replacement email carries are the same object: hashed the same way, with
     * the same deadline, counted in the same allowance, and never written down
     * in a usable form.
     *
     * @return array{code: string, columns: array<string, mixed>}
     */
    private static function freshCode(?object $review): array
    {
        $windowStart = $review && ! empty($review->code_window_started_at)
            ? \Illuminate\Support\Carbon::parse($review->code_window_started_at)
            : null;

        $windowExpired = $windowStart === null
            || $windowStart->diffInMinutes(now()) >= self::codeWindowMinutes();

        $sentInWindow = ($review === null || $windowExpired) ? 0 : (int) $review->code_sent_count;

        $code = (string) random_int(100000, 999999);

        return [
            'code'    => $code,
            'columns' => [
                'code_hash'              => Hash::make($code),
                'code_expires_at'        => now()->addMinutes(self::codeTtlMinutes()),
                'code_sent_at'           => now(),
                'code_attempts'          => 0,
                'code_sent_count'        => $sentInWindow + 1,
                'code_window_started_at' => $windowExpired ? now() : $review->code_window_started_at,
            ],
        ];
    }

    /**
     * Check a verification code against the invitation.
     *
     * Wrong codes are counted and the code is thrown away once the allowance is
     * spent, so guessing cannot continue indefinitely against one invitation.
     * A correct code clears itself: it is never stored in a usable form again.
     *
     * @return array{ok: bool, message: string}
     */
    public static function verify(object $review, string $code): array
    {
        if (! self::tablesReady()) {
            return ['ok' => false, 'message' => 'The transfer review tables have not been created yet.'];
        }

        if ($reason = self::unusableReason($review)) {
            return ['ok' => false, 'message' => $reason];
        }

        $code = trim($code);

        if ($code === '') {
            return ['ok' => false, 'message' => 'Enter the 6-digit code from the email.'];
        }

        if (empty($review->code_hash) || empty($review->code_expires_at)) {
            return ['ok' => false, 'message' => 'No code is waiting for this invitation. Ask for a new one.'];
        }

        if (\Illuminate\Support\Carbon::parse($review->code_expires_at)->isPast()) {
            DB::table('transfer_reviews')->where('id', $review->id)->update([
                'code_hash'  => null,
                'updated_at' => now(),
            ]);

            return ['ok' => false, 'message' => 'That code has expired. Ask for a new one.'];
        }

        if ((int) $review->code_attempts >= self::maxCodeAttempts()) {
            DB::table('transfer_reviews')->where('id', $review->id)->update([
                'code_hash'  => null,
                'updated_at' => now(),
            ]);

            return ['ok' => false, 'message' => 'Too many incorrect codes for this invitation. Ask for a new code.'];
        }

        if (! Hash::check($code, (string) $review->code_hash)) {
            $attempts = (int) $review->code_attempts + 1;
            $spent    = $attempts >= self::maxCodeAttempts();

            DB::table('transfer_reviews')->where('id', $review->id)->update([
                'code_attempts' => $attempts,
                'code_hash'     => $spent ? null : $review->code_hash,
                'updated_at'    => now(),
            ]);

            return [
                'ok'      => false,
                'message' => $spent
                    ? 'That code is not correct, and this invitation has used up its attempts. Ask for a new code.'
                    : 'That code is not correct. ' . (self::maxCodeAttempts() - $attempts) . ' attempt'
                        . ((self::maxCodeAttempts() - $attempts) === 1 ? '' : 's') . ' left.',
            ];
        }

        DB::table('transfer_reviews')->where('id', $review->id)->update([
            'status'         => self::STATUS_VERIFIED,
            'verified_at'    => now(),
            'code_hash'      => null,
            'code_expires_at' => null,
            'code_attempts'  => 0,
            'updated_at'     => now(),
        ]);

        self::audit(
            actor: null,
            requestId: (int) $review->request_id,
            actionType: 'UPDATE',
            notes: 'Executive Director verified the official mailbox for transfer request #' . $review->request_id,
            description: 'The recipient of invitation #' . $review->id . ' verified control of '
                . ($review->recipient_email ?: self::defaultRecipientEmail()) . '. The transfer details of request #'
                . $review->request_id . ' were unlocked for review. No decision has been recorded and no asset has changed hands.',
        );

        return ['ok' => true, 'message' => 'Verified. You can now review the transfer.'];
    }

    /* ──────────────────────── the decision ──────────────────────── */

    /**
     * Record the Executive Director's decision and apply it.
     *
     * The whole thing is one transaction behind a lock on the invitation: two
     * submissions of the same decision cannot both apply, and a decision that
     * arrives after the request was withdrawn is refused rather than applied to
     * a request that no longer means what it said.
     *
     * @param  array{decision?: string, asset_ids?: array<int, int>, rejection_reason?: string}  $input
     * @return array{ok: bool, message: string, decision?: string, approved?: array<int, string>, declined?: array<int, string>}
     */
    public static function decide(object $review, array $input, ?User $actor = null): array
    {
        if (! self::tablesReady()) {
            return ['ok' => false, 'message' => 'The transfer review tables have not been created yet.'];
        }

        $choice = (string) ($input['decision'] ?? '');

        if (! in_array($choice, ['approve_all', 'approve_selected', 'reject'], true)) {
            return ['ok' => false, 'message' => 'Choose whether to approve the transfer, approve selected assets, or reject it.'];
        }

        $reason = trim((string) ($input['rejection_reason'] ?? ''));

        if ($choice === 'reject' && $reason === '') {
            return ['ok' => false, 'message' => 'A rejection has to say why. Please give a reason.'];
        }

        try {
            return DB::transaction(function () use ($review, $input, $choice, $reason, $actor) {
                // Re-read behind a lock: this is the row every concurrent
                // submission is fighting over.
                $fresh = DB::table('transfer_reviews')->where('id', $review->id)->lockForUpdate()->first();

                if (! $fresh) {
                    return ['ok' => false, 'message' => 'That invitation no longer exists.'];
                }

                $fresh = self::present($fresh);

                // Named apart from $reason on purpose: $reason is the rejection
                // reason the decision arrived with, and it must not be
                // overwritten by this check.
                if ($blocked = self::unusableReason($fresh)) {
                    return ['ok' => false, 'message' => $blocked . ' If the transfer still needs a decision, the Asset Management Office has to issue a new invitation.'];
                }

                if ($fresh->status !== self::STATUS_VERIFIED) {
                    return ['ok' => false, 'message' => 'The recipient has not been verified yet, so no decision can be recorded.'];
                }

                $request = DB::table('requests')->where('id', $fresh->request_id)->first();

                if ($problem = self::eligibilityProblem($request)) {
                    return ['ok' => false, 'message' => 'This review is out of date. ' . $problem . ' Ask the Asset Management Office to issue a new invitation if the transfer still needs a decision.'];
                }

                $assets = self::transferAssets((int) $request->id);

                if ($assets->isEmpty()) {
                    return ['ok' => false, 'message' => 'This transfer request has no assets linked to it, so there is nothing to decide.'];
                }

                $allIds = $assets->pluck('id')->map(fn ($id) => (int) $id)->all();

                // A rejection changes nothing, so it stays recordable however
                // the assets have moved on since. Refusing it would leave the
                // office waiting on a decision that cannot be submitted at all —
                // the request stays Approved for ever and the assets stay on
                // hold for a review nobody can close.
                if ($choice === 'reject') {
                    return self::recordRejection($fresh, $request, $allIds, $reason, $actor);
                }

                if ($choice === 'approve_selected') {
                    $selected = array_values(array_unique(array_map('intval', (array) ($input['asset_ids'] ?? []))));
                    $selected = array_values(array_intersect($selected, $allIds));

                    if (! $selected) {
                        return ['ok' => false, 'message' => 'Select at least one asset to approve, or approve the whole transfer.'];
                    }
                } else {
                    $selected = $allIds;
                }

                // Where each asset stands, read against the handover the office
                // asked about rather than against whoever typed the request: a
                // Department Head requests on behalf of their department, so the
                // assets are held by the department's employees and never by the
                // head. Two of the three answers are decidable — the asset is
                // still held by somebody this transfer may take it from, or the
                // receiving employee already holds it (nothing to move for it).
                // Only an asset that has passed into a *third* employee's hands
                // since the office asked makes a decision unanswerable, and only
                // when it is one the Executive Director is deciding on.
                $states = self::handoverStates($fresh, $request, $assets);

                // An asset that has left the employee's custody since the office
                // asked is out of date for this review the same way a third
                // employee's hands are: it is retired or pulled out, it is kept
                // out of every listing on purpose, and the disposal record that
                // removed it still names it. Named here rather than left to the
                // execution, so the Executive Director reads why.
                if ($custodyProblem = AssetTransfer::custodyProblem($selected, (int) ($request->assign_to_user_id ?? 0))) {
                    return ['ok' => false, 'message' => 'This review is out of date. ' . $custodyProblem
                        . ' Ask the Asset Management Office to issue a new invitation if the transfer still needs a decision.'];
                }

                foreach ($selected as $assetId) {
                    if (($states[(int) $assetId]['state'] ?? 'giver') === 'elsewhere') {
                        return ['ok' => false, 'message' => self::handoverRefusal($assets, $states, $selected)];
                    }
                }

                return self::recordApproval($fresh, $request, $assets, $selected, $allIds, $actor, $states);
            });
        } catch (Throwable $e) {
            Log::error('Recording the Executive Director decision on invitation #' . $review->id . ' failed: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'The decision could not be recorded. Nothing was changed — please try again, or ask the Asset Management Office to issue a new invitation.'];
        }
    }

    /**
     * Approve everything or a selection: the assets change hands, the rest stay
     * where they are.
     *
     * "Change hands" is read from the handover as it stands, never assumed: an
     * approved asset the receiving employee already holds has nothing to move,
     * and telling the employee it was transferred would be a claim the history
     * (and the accountability record) would contradict.
     *
     * @param  Collection<int, object>  $assets
     * @param  array<int, int>  $selected
     * @param  array<int, int>  $allIds
     * @param  array<int, array{state: string, holder: int|null, holder_name: string|null}>  $states
     */
    private static function recordApproval(object $review, object $request, Collection $assets, array $selected, array $allIds, ?User $actor, array $states = []): array
    {
        $declined = array_values(array_diff($allIds, $selected));

        $codes = [];
        $names = [];

        foreach ($assets as $asset) {
            $codes[(int) $asset->id] = $asset->Asset_code ?: ('asset #' . $asset->id);
            $names[(int) $asset->id] = $asset->Asset_name ?: ($asset->Asset_code ?: ('asset #' . $asset->id));
        }

        // Which of the approved assets actually have to move, and which the
        // receiving employee already holds.
        $givers  = [];
        $already = [];

        foreach ($selected as $assetId) {
            if (($states[(int) $assetId]['state'] ?? 'giver') === 'recipient') {
                $already[] = (int) $assetId;
            } else {
                $givers[] = (int) $assetId;
            }
        }

        // The same execution the office's own approval runs: the assets move to
        // the receiving employee, one TRANSFER entry per asset, nothing else
        // about them changes. Only the assets somebody else is holding are
        // handed over — for one already with them there is nothing to do.
        $applied = AssetTransfer::applyRequestTransfer(
            $request,
            $givers,
            $actor?->id,
            'Executive Director approval via review invitation #' . $review->id
        );

        $approvedCodes = [];
        foreach ($selected as $assetId) {
            $approvedCodes[] = $codes[$assetId] ?? ('asset #' . $assetId);
        }

        $movedCodes = [];
        foreach ($givers as $assetId) {
            $movedCodes[] = $codes[$assetId] ?? ('asset #' . $assetId);
        }

        $alreadyCodes = [];
        foreach ($already as $assetId) {
            $alreadyCodes[] = $codes[$assetId] ?? ('asset #' . $assetId);
        }

        $declinedCodes = [];
        foreach ($declined as $assetId) {
            $declinedCodes[] = $codes[$assetId] ?? ('asset #' . $assetId);
        }

        $toName = self::userName((int) $request->assign_to_user_id);

        // What actually happened, in the order the reader needs it: what moved,
        // what was already there, and what was not approved.
        $outcome = [];

        if ($movedCodes) {
            $outcome[] = self::nameList($movedCodes) . ' ' . (count($movedCodes) === 1 ? 'is' : 'are') . ' now assigned to ' . $toName;
        }

        if ($alreadyCodes) {
            $outcome[] = self::nameList($alreadyCodes) . ' ' . (count($alreadyCodes) === 1 ? 'was' : 'were')
                . ' already held by ' . $toName . ', so nothing had to move for ' . (count($alreadyCodes) === 1 ? 'it' : 'them');
        }

        if ($declinedCodes) {
            $outcome[] = 'not approved: ' . self::nameList($declinedCodes) . ', which stay where they are';
        }

        $outcomeText = ucfirst(implode('; ', $outcome)) . '.';

        $decision = $declined ? self::DECISION_PARTIAL : self::DECISION_APPROVED;

        foreach ($allIds as $assetId) {
            DB::table('transfer_review_items')->updateOrInsert(
                ['review_id' => $review->id, 'asset_id' => $assetId],
                [
                    'decision'   => in_array($assetId, $selected, true) ? self::ITEM_APPROVED : self::ITEM_DECLINED,
                    'decided_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('transfer_reviews')->where('id', $review->id)->update([
            'status'         => self::STATUS_DECIDED,
            'decision'       => $decision,
            'approved_count' => count($selected),
            'declined_count' => count($declined),
            'decided_at'     => now(),
            'executed_at'    => $applied > 0 ? now() : null,
            'updated_at'     => now(),
        ]);

        self::audit(
            actor: $actor,
            requestId: (int) $request->id,
            actionType: 'APPROVAL',
            notes: 'Executive Director ' . ($declined ? 'partially approved' : 'approved') . ' transfer request #' . $request->id
                . ' (invitation #' . $review->id . ')',
            description: 'The Executive Director ' . ($declined ? 'approved part of' : 'approved') . ' transfer request #' . $request->id
                . ' through invitation #' . $review->id . ': ' . $outcomeText
                . ' The decision was recorded and the invitation can no longer be used.',
        );

        $reference = 'TR-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT);

        Notifications::create(
            (int) $request->user_id,
            $declined ? 'Transfer Partially Approved' : 'Transfer Approved',
            'The Executive Director ' . ($declined ? 'partially approved' : 'approved') . ' your transfer request ' . $reference . '. ' . $outcomeText,
            'TRANSFER',
            (int) $request->id,
            'request'
        );

        self::notifyOffice(
            (int) $request->id,
            $declined ? 'Transfer Partially Approved by the Executive Director' : 'Transfer Approved by the Executive Director',
            'Transfer request ' . $reference . ': ' . $outcomeText,
            $actor
        );

        return [
            'ok'            => true,
            'decision'      => $decision,
            // Every asset the decision covered, and — among those — the ones
            // that actually had to move and the ones the receiving employee
            // already held. The closing page says what happened; this is what
            // happened.
            'approved'      => $approvedCodes,
            'already_there' => $alreadyCodes,
            'declined'      => $declinedCodes,
            'message'       => 'Recorded. ' . $outcomeText,
        ];
    }

    /**
     * Reject: the request is closed with the reason, and nothing changes hands.
     *
     * @param  array<int, int>  $allIds
     */
    private static function recordRejection(object $review, object $request, array $allIds, string $reason, ?User $actor): array
    {
        // The existing status logic for a refused request, used unchanged: the
        // request is Rejected, the office's reason is kept where a rejection
        // reason has always been kept, and no asset row is touched.
        DB::table('requests')->where('id', $request->id)->update([
            'status'            => 'Rejected',
            'admin_remarks'     => $reason,
            'admin_remarks_by'  => $actor?->id,
            'admin_remarks_at'  => now(),
            'updated_at'        => now(),
        ]);

        foreach ($allIds as $assetId) {
            DB::table('transfer_review_items')->updateOrInsert(
                ['review_id' => $review->id, 'asset_id' => $assetId],
                [
                    'decision'   => self::ITEM_DECLINED,
                    'decided_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('transfer_reviews')->where('id', $review->id)->update([
            'status'           => self::STATUS_DECIDED,
            'decision'         => self::DECISION_REJECTED,
            'rejection_reason' => $reason,
            'approved_count'   => 0,
            'declined_count'   => count($allIds),
            'decided_at'       => now(),
            'updated_at'       => now(),
        ]);

        self::audit(
            actor: $actor,
            requestId: (int) $request->id,
            actionType: 'APPROVAL',
            notes: 'Executive Director rejected transfer request #' . $request->id . ' (invitation #' . $review->id . ')',
            description: 'The Executive Director rejected transfer request #' . $request->id . ' through invitation #' . $review->id
                . '. Reason: ' . $reason . '. No asset changed hands and the request is closed.',
        );

        $reference = 'TR-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT);

        Notifications::create(
            (int) $request->user_id,
            'Transfer Rejected',
            'The Executive Director rejected your transfer request ' . $reference . '. Reason: ' . $reason
                . ' The assets stay with their current custodian.',
            'TRANSFER',
            (int) $request->id,
            'request'
        );

        self::notifyOffice(
            (int) $request->id,
            'Transfer Rejected by the Executive Director',
            'Transfer request ' . $reference . ' was rejected. Reason: ' . $reason . ' No asset changed hands.',
            $actor
        );

        return [
            'ok'       => true,
            'decision' => self::DECISION_REJECTED,
            'approved' => [],
            'declined' => [],
            'message'  => 'Recorded. The transfer request is rejected and no asset changed hands.',
        ];
    }

    /* ──────────────────────────── email ──────────────────────────── */

    /**
     * The invitation email: the link, the verification code it needs, and nothing
     * else.
     *
     * No asset list, no custodian names, no request note: an email is not a safe
     * place to put inventory details. The code is here rather than in a second
     * message the recipient has to ask for — the link and the code go to the
     * same mailbox, so one email opens the review without weakening what the
     * code proves.
     *
     * @return array{ok: bool, error: string}
     */
    private static function sendInvitation(object $review, string $token, string $code): array
    {
        $url      = url('/executive-review/' . $token);
        $expires  = \Illuminate\Support\Carbon::parse($review->expires_at)->format('F j, Y g:i A');
        $reference = 'TR-' . str_pad((string) $review->request_id, 4, '0', STR_PAD_LEFT);

        $body = "Dear " . self::recipientName() . ",\n\n"
            . "The NU Lipa Asset Management Office has asked you to review an asset transfer request.\n\n"
            . "Transfer request: " . $reference . "\n"
            . "Open the review: " . $url . "\n\n"
            . "Your verification code is: " . $code . "\n\n"
            . "Enter that code on the page the link opens. It confirms this message reached this mailbox, and it\n"
            . "is valid for " . self::codeTtlMinutes() . " minutes. If it has expired, the same page can send a new\n"
            . "one to this address.\n\n"
            . "This link is personal and single-use. It expires on " . $expires . ".\n"
            . "Nothing is approved, and no asset changes hands, until you submit a decision through it.\n\n"
            . "If you were not expecting this, please contact the NU Lipa Asset Management Office.\n\n"
            . "— NU TRACE Asset Management System";

        return self::deliver(
            $body,
            'NU TRACE — Executive Director review of transfer request ' . $reference,
            (string) ($review->recipient_email ?: self::defaultRecipientEmail())
        );
    }

    /** The verification code, on its own, to the invitation's own address. */
    private static function sendCodeEmail(string $code, string $to): array
    {
        $body = "Dear " . self::recipientName() . ",\n\n"
            . "Your NU TRACE transfer review verification code is: " . $code . "\n\n"
            . "It is valid for " . self::codeTtlMinutes() . " minutes and can be used once.\n"
            . "Nobody from NU Lipa will ask you for this code.\n\n"
            . "— NU TRACE Asset Management System";

        return self::deliver($body, 'NU TRACE — Transfer review verification code', $to);
    }

    /**
     * Send one plain message through the configured mailer.
     *
     * A refused send is reported as a failure, never swallowed: "sent" is the
     * only thing the office can see, and an invitation that was never delivered
     * looks exactly like one that was ignored. The body is never logged — it
     * carries the link — and neither the token nor the code is written to the
     * application log.
     *
     * @return array{ok: bool, error: string}
     */
    private static function deliver(string $body, string $subject, string $to): array
    {
        if (! self::validEmail($to)) {
            return ['ok' => false, 'error' => 'This invitation has no valid email address recorded, so nothing could be sent.'];
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (Throwable $e) {
            $mailer = (string) config('mail.default');

            Log::warning('Could not send an Executive Director transfer review email to ' . $to . ': ' . $e->getMessage());

            if ($mailer === 'resend' && (string) config('services.resend.key') === '') {
                $hint = 'RESEND_API_KEY is missing on this server.';
            } elseif ($mailer === 'smtp' && (string) config('mail.mailers.smtp.password') === '') {
                $hint = 'MAIL_USERNAME and MAIL_PASSWORD are not set on this server.';
            } else {
                $hint = 'The mail provider refused the message (' . class_basename($e) . ').';
            }

            return ['ok' => false, 'error' => $hint];
        }

        return ['ok' => true, 'error' => ''];
    }

    /* ──────────────────────────── helpers ──────────────────────────── */

    /** Tell every active Asset Management Office account. */
    private static function notifyOffice(int $requestId, string $title, string $message, ?User $actor = null): void
    {
        $office = User::query()
            ->whereIn('role', Access::ASSET_OFFICE_ROLES)
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->when($actor, fn ($query) => $query->where('id', '!=', $actor->id))
            ->orderBy('id')
            ->get();

        foreach ($office as $account) {
            Notifications::create(
                (int) $account->id,
                $title,
                $message,
                'TRANSFER',
                $requestId,
                'request'
            );
        }
    }

    private static function userName(int $userId): string
    {
        if ($userId <= 0) {
            return 'the receiving employee';
        }

        $names = AssetTransfer::userLabels();

        return $names[$userId]['name'] ?? ('user #' . $userId);
    }

    /**
     * One audit entry, in the existing log.
     *
     * Call sites name their arguments: this helper has grown past the point
     * where a positional call is safe, and an argument landing in the wrong slot
     * writes a row that looks plausible.
     *
     * Only action types the audit log already accepts are used (the log keeps a
     * check constraint on PostgreSQL): the invitation's comings and goings are
     * UPDATE entries on the request, and the decision itself is an APPROVAL,
     * next to the office's own approvals.
     *
     * TRANSFER is deliberately not used for the invitation. A TRANSFER entry
     * with an asset on it is the evidence that an asset really changed hands —
     * the Transfer page reads it, and so does the check that refuses a decision
     * on a request whose assets have already moved — so an invitation must not
     * be able to look like one.
     *
     * Neither the link token nor a verification code is ever written here.
     */
    private static function audit(?User $actor, int $requestId, string $actionType, string $notes, string $description): void
    {
        try {
            DB::table('audit_logs')->insert([
                'user_id'            => $actor?->id,
                'request_id'         => $requestId > 0 ? $requestId : null,
                'asset_id'           => null,
                'action_type'        => $actionType,
                'notes'              => $notes,
                'action_description' => $description,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (Throwable $e) {
            // The step it describes has already happened; losing the note must
            // not undo it. Reported, never swallowed silently.
            Log::warning('Transfer review audit entry failed (action=' . $actionType . ', request=' . $requestId . '): ' . $e->getMessage());
        }
    }

    private static function actorName(?User $actor): string
    {
        if (! $actor) {
            return 'the Executive Director';
        }

        return trim((string) ($actor->display_name ?? '')) ?: (string) $actor->email;
    }
}
