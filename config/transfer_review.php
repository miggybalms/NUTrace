<?php

/**
 * The Executive Director's transfer review.
 *
 * The Executive Director has no NU Trace account. They review one transfer
 * request through a temporary emailed link, verify they hold the mailbox it was
 * sent to, decide, and the link stops working. Everything configurable about
 * that flow lives here rather than in the code that runs it, so the office can
 * change the address or the deadlines without a deployment of new logic.
 *
 * Nobody is invited by configuration: the Asset Management Office types the
 * Executive Director's address when it sends each invitation. Only that address
 * can be invited — no browser can redirect an invitation somewhere else — but
 * choosing it is the office's decision rather than a deployment's, because a
 * change of Executive Director must not wait on an environment variable.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | The address the office usually sends to (optional)
    |--------------------------------------------------------------------------
    |
    | A convenience, not a rule. When it is set, the approve dialog starts with
    | this address already filled in and a finished invitation for a request is
    | re-sent to whoever it went to last time; the office can always type a
    | different one. Leave it empty and the office types the address every time —
    | nothing else changes.
    |
    */

    'email' => env('TRANSFER_REVIEW_EMAIL'),

    /**
     * Where the office is told to write when a link cannot be used.
     *
     * Falls back to the address above and then to the application's own sending
     * address, and to no address at all if none of those is set — a wrong
     * address on that page would be worse than none.
     */
    'office_email' => env('TRANSFER_REVIEW_OFFICE_EMAIL'),

    /**
     * How the recipient is addressed in the email and on the review page.
     *
     * Optional. Left empty, the emails open "Dear Executive Director", which
     * cannot go stale when the person in the office changes.
     */
    'name' => env('TRANSFER_REVIEW_NAME', ''),

    /*
    |--------------------------------------------------------------------------
    | Deadlines
    |--------------------------------------------------------------------------
    |
    | The link lives long enough to survive a weekend post, the way paper would.
    | The verification code is short-lived: it is typed minutes after it arrives,
    | and it is the second factor that stands between a forwarded link and the
    | transfer details.
    |
    */

    /** How long an invitation link stays usable, in hours. */
    'link_ttl_hours' => (int) env('TRANSFER_REVIEW_LINK_TTL_HOURS', 168),

    /** How long a verification code stays valid, in minutes. */
    'code_ttl_minutes' => (int) env('TRANSFER_REVIEW_CODE_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | Counted on the invitation row rather than in the cache, so they hold on a
    | server whose cache store is not configured: a limiter that needs the cache
    | is a limiter that disappears on the day the cache does.
    |
    */

    /** How many wrong codes may be tried before the code is thrown away. */
    'code_attempts' => (int) env('TRANSFER_REVIEW_CODE_ATTEMPTS', 5),

    /** How many codes may be requested inside one window. */
    'code_requests_per_window' => (int) env('TRANSFER_REVIEW_CODE_REQUESTS', 3),

    /** The window the code-request limit is counted over, in minutes. */
    'code_window_minutes' => (int) env('TRANSFER_REVIEW_CODE_WINDOW_MINUTES', 60),

    /** Seconds to wait between two code requests for the same invitation. */
    'code_resend_cooldown' => (int) env('TRANSFER_REVIEW_CODE_COOLDOWN', 60),

    /** How long the review page stays unlocked after a successful verification. */
    'verified_session_hours' => (int) env('TRANSFER_REVIEW_SESSION_HOURS', 4),

    /*
    |--------------------------------------------------------------------------
    | The office's default
    |--------------------------------------------------------------------------
    |
    | Whether the approve dialog offers "forward to the Executive Director" with
    | the box already ticked. It stays the Admin's decision on every individual
    | request - this only sets which way the box starts.
    |
    */

    'forward_by_default' => (bool) env('TRANSFER_REVIEW_FORWARD_BY_DEFAULT', true),

];
