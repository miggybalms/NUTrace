<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three-year waiting period between approving a disposal and disposing.
 *
 * One rule, in one place: a disposal request approved by the Asset Management
 * Office becomes eligible for final disposal `config('disposal.hold_years')`
 * years after it was approved. Until then the official form may be prepared,
 * printed and filed, but nothing may be retired.
 *
 * The clock is the approval timestamp (`requests.approved_at`), not the form's
 * preparation date and not any date typed into the form. Those move when
 * somebody edits or prints a form; the approval does not.
 *
 * Two things this class deliberately does NOT do:
 *
 *   1. It never disposes of anything. Reaching the eligibility date makes a
 *      request *eligible*; an Admin still has to confirm the finalization.
 *   2. It never invents an approval date. A request approved before the
 *      timestamp existed (a legacy record) is reported as unknown and is left
 *      exactly as it was, rather than being given a made-up start date.
 *
 * Calendar arithmetic uses Carbon's addYears(), which is what the rest of the
 * application uses for date maths. A request approved on 29 February 2028 is
 * therefore eligible on 1 March 2031: three years later is not a leap year, so
 * the anniversary overflows to the following day instead of silently becoming
 * 28 February.
 */
class DisposalEligibility
{
    /** Not a disposal request: no waiting period applies. */
    public const STATE_NOT_APPLICABLE = 'not-applicable';

    /** Approved before the approval timestamp existed - nothing is assumed. */
    public const STATE_UNKNOWN = 'unknown';

    /** Approved, and the waiting period is still running. */
    public const STATE_WAITING = 'waiting';

    /** The waiting period has ended; final disposal may be confirmed. */
    public const STATE_ELIGIBLE = 'eligible';

    /** Cached answer for "has the eligibility migration been run?". */
    private static ?bool $ready = null;

    /**
     * Are the columns this rule needs actually present?
     *
     * The code ships before `php artisan migrate` runs on the server, so every
     * read and write is guarded instead of blowing up on a column that does not
     * exist yet. Until the migration runs, approval behaves exactly as it did
     * before (no timestamp is written) and nothing is refused.
     */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            self::$ready = Schema::hasColumn('requests', 'approved_at')
                && Schema::hasColumn('requests', 'disposal_eligibility_notified_at');
        }

        return self::$ready;
    }

    /** How many years the office has to wait. Never below one. */
    public static function years(): int
    {
        return max(1, (int) config('disposal.hold_years', 3));
    }

    /** "3 years" - the length itself, for a table cell or a log line. */
    public static function periodLabel(): string
    {
        $years = self::years();

        return $years . ' ' . ($years === 1 ? 'year' : 'years');
    }

    /** "three-year" - the same number as an adjective before "waiting period". */
    public static function periodAdjective(): string
    {
        $words = [
            1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
            6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
        ];

        $years = self::years();

        return ($words[$years] ?? (string) $years) . '-year';
    }

    /* ─────────────────────────── dates ─────────────────────────── */

    /**
     * The authoritative approval moment, or null when it was never recorded.
     *
     * Accepts the row either as it comes from `requests` (approved_at) or as the
     * disposal-form queries alias it (request_approved_at).
     */
    public static function approvalTime(?object $row): ?Carbon
    {
        if (! $row) {
            return null;
        }

        $value = $row->request_approved_at ?? $row->approved_at ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    /** The moment the request becomes eligible, or null when unknown. */
    public static function eligibleAt(?object $row): ?Carbon
    {
        $approved = self::approvalTime($row);

        return $approved ? $approved->copy()->addYears(self::years()) : null;
    }

    /** Is this a disposal request at all? */
    public static function isDisposal(?object $request): bool
    {
        return $request !== null
            && strtolower((string) ($request->request_type ?? '')) === 'disposal';
    }

    /**
     * Everything a screen needs to explain where a disposal stands.
     *
     * `finalizable` is about the waiting period only; the form's own checks
     * (signed copy, confirmation) still apply on top of it.
     *
     * @return array{state: string, label: string, hint: string, approval_at: ?Carbon, eligible_at: ?Carbon, remaining_days: int, notified_at: ?Carbon, finalizable: bool}
     */
    public static function state(?object $request, ?Carbon $now = null): array
    {
        $now = $now ?: now();

        $blank = [
            'state'         => self::STATE_NOT_APPLICABLE,
            'label'         => 'No waiting period',
            'hint'          => '',
            'approval_at'   => null,
            'eligible_at'   => null,
            'remaining_days' => 0,
            'notified_at'   => null,
            'finalizable'   => true,
        ];

        if (! self::isDisposal($request)) {
            return $blank;
        }

        $approved = self::approvalTime($request);
        $eligible = self::eligibleAt($request);

        $notified = $request->disposal_eligibility_notified_at ?? null;

        // No approval timestamp: this request predates the rule. Nothing is
        // assumed, and nothing is blocked - a historical record must stay
        // usable and must not be given a made-up start date.
        if (! $approved || ! $eligible) {
            return array_merge($blank, [
                'state'       => self::STATE_UNKNOWN,
                'label'       => 'Approval date not recorded',
                'hint'        => 'This request was approved before the waiting period was introduced, so no eligibility date is known. The disposal can still be finalized as before.',
                'notified_at' => $notified ? Carbon::parse($notified) : null,
            ]);
        }

        // Whole days left, rounded up, so "2 days" never reads "1 day" on the
        // morning it is still two sleeps away.
        $seconds = $eligible->getTimestamp() - $now->getTimestamp();
        $days = $seconds > 0 ? (int) ceil($seconds / 86400) : 0;

        if ($now->greaterThanOrEqualTo($eligible)) {
            return [
                'state'          => self::STATE_ELIGIBLE,
                'label'          => 'Eligible for Final Disposal',
                'hint'           => 'The ' . self::periodAdjective() . ' waiting period has ended. Final disposal still has to be confirmed here by the Asset Management Office.',
                'approval_at'    => $approved,
                'eligible_at'    => $eligible,
                'remaining_days' => 0,
                'notified_at'    => $notified ? Carbon::parse($notified) : null,
                'finalizable'    => true,
            ];
        }

        return [
            'state'          => self::STATE_WAITING,
            'label'          => 'Awaiting Eligibility Date',
            'hint'           => 'The ' . self::periodAdjective() . ' waiting period started when this request was approved and ends on '
                . $eligible->format('F j, Y g:i A') . '.',
            'approval_at'    => $approved,
            'eligible_at'    => $eligible,
            'remaining_days' => max(0, $days),
            'notified_at'    => $notified ? Carbon::parse($notified) : null,
            'finalizable'    => false,
        ];
    }

    /* ─────────────────────── the refusal ─────────────────────── */

    /**
     * Why this request may not be finalized yet, or null when it may.
     *
     * This is the message every disposal path shows; it names the request, the
     * approval date and the date final disposal becomes available.
     */
    public static function refusal(?object $request, ?Carbon $now = null): ?string
    {
        $state = self::state($request, $now);

        if ($state['state'] !== self::STATE_WAITING) {
            return null;
        }

        return 'This disposal cannot be finalized yet: the ' . self::periodAdjective()
            . ' waiting period for disposal request #' . (int) ($request->id ?? 0)
            . ' has not ended. It was approved on ' . $state['approval_at']->format('F j, Y g:i A')
            . ', so final disposal becomes available on ' . $state['eligible_at']->format('F j, Y g:i A')
            . '. Nothing was changed.';
    }

    /**
     * The approved disposal request that is holding one of these assets back.
     *
     * Used by every path that can retire an asset without the official form
     * (repair, lifespan evaluation, pullout, the legacy record entry): if the
     * asset sits on an approved disposal request whose waiting period is still
     * running, that path must not retire it either. Otherwise the three-year
     * rule would only apply to the screen that honours it.
     *
     * @param  array<int, int>  $assetIds
     * @param  int|null  $exceptRequestId  the request being finalized, which must not block itself
     */
    public static function holdForAssets(array $assetIds, ?int $exceptRequestId = null, ?Carbon $now = null): ?object
    {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $assetIds))));

        if (! $assetIds || ! self::ready()) {
            return null;
        }

        $now = $now ?: now();
        $cutoff = $now->copy()->subYears(self::years());

        $candidates = DB::table('requests')
            ->whereRaw('LOWER(request_type) = ?', ['disposal'])
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->whereNotNull('approved_at')
            ->where('approved_at', '>', $cutoff)
            ->where(function ($query) use ($assetIds) {
                $query->whereIn('requests.id', function ($sub) use ($assetIds) {
                    $sub->select('request_id')
                        ->from('request_items')
                        ->whereIn('asset_id', $assetIds);
                })->orWhereIn('requests.asset_id', $assetIds);
            })
            ->when($exceptRequestId, fn ($query) => $query->where('id', '!=', $exceptRequestId))
            ->orderBy('id')
            ->get(['id', 'approved_at', 'request_type', 'status']);

        // The SQL cutoff is deliberately generous; the exact boundary is
        // decided here, by the same date arithmetic the form uses.
        foreach ($candidates as $candidate) {
            if (self::state($candidate, $now)['state'] === self::STATE_WAITING) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The refusal message for an alternative disposal path, or null when the
     * assets are not held by a waiting disposal request.
     *
     * @param  array<int, int>  $assetIds
     */
    public static function refusalForAssets(array $assetIds, ?int $exceptRequestId = null, ?Carbon $now = null): ?string
    {
        $holding = self::holdForAssets($assetIds, $exceptRequestId, $now);

        if (! $holding) {
            return null;
        }

        $state = self::state($holding, $now);

        $codes = DB::table('assets')->whereIn('id', $assetIds)->pluck('Asset_code')->filter()->values()->all();
        $named = $codes ? implode(', ', $codes) : 'The asset';

        return $named . (count($codes) === 1 ? ' is' : ' are')
            . ' covered by approved disposal request #' . (int) $holding->id
            . ', whose ' . self::periodAdjective() . ' waiting period ends on '
            . $state['eligible_at']->format('F j, Y g:i A')
            . '. It cannot be retired through this screen before then. Nothing was changed.';
    }

    /* ─────────────────────── notifications ─────────────────────── */

    /**
     * The notice the Asset Management Office receives when the period ends.
     *
     * @param  array<int, string>  $codes  the asset codes the request covers
     * @return array{title: string, message: string}
     */
    public static function notice(object $request, array $codes = [], ?Carbon $now = null): array
    {
        $state = self::state($request, $now);

        $label = $codes
            ? implode(', ', $codes)
            : 'the asset(s) on the request';

        $message = 'The ' . self::periodAdjective() . ' waiting period for disposal request #'
            . (int) $request->id . ' (' . $label . ') has ended.'
            . ' Approved on ' . ($state['approval_at'] ? $state['approval_at']->format('F j, Y') : '—')
            . '; eligible for final disposal from '
            . ($state['eligible_at'] ? $state['eligible_at']->format('F j, Y') : '—') . '.'
            . ' The request is now eligible for final disposal — it has NOT been disposed of.'
            . ' Review the official disposal form and complete the existing finalization process when appropriate.'
            . ' Form: ' . rtrim((string) config('app.url'), '/') . '/admin/disposal?tab=forms';

        return [
            'title'   => 'Disposal Eligibility Reached',
            'message' => $message,
        ];
    }

    /** Record that the office has been told about this request's eligibility. */
    public static function markNotified(int $requestId, ?Carbon $at = null): void
    {
        if ($requestId <= 0 || ! self::ready()) {
            return;
        }

        DB::table('requests')->where('id', $requestId)->update([
            'disposal_eligibility_notified_at' => $at ?: now(),
            'updated_at'                       => now(),
        ]);
    }
}
