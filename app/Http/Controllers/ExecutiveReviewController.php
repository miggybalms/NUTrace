<?php

namespace App\Http\Controllers;

use App\Support\TransferReviews;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The Executive Director's review, through the emailed link.
 *
 * This is the only part of NU Trace that answers a visitor who is not signed in
 * and must stay that way: there is no account behind the link, and nothing here
 * ever calls Auth::login. The session it uses holds one thing — "this browser
 * verified invitation #N at this time" — and that grant is checked against the
 * invitation on every request, so a link that is revoked, expired or already
 * decided stops working even for a browser that verified a minute ago.
 *
 * What the link can reach is one transfer request. There is no asset search, no
 * listing, no identifier in the URL other than the token, and no route here that
 * takes a request id the visitor could change: the request behind the page is
 * always the one the token points at.
 */
class ExecutiveReviewController extends Controller
{
    /** How long a verified browser stays verified, in minutes. */
    private const SESSION_KEY = 'executive_review.verified';

    /**
     * The review page: the verification gate, or the transfer itself.
     */
    public function show(Request $request, string $token)
    {
        $review = TransferReviews::findByToken($token);

        if (! $review) {
            return $this->unknownLink();
        }

        // A link that cannot take a decision says why, and says what to do. A
        // link whose decision is already in reads the outcome back to the person
        // who submitted it — the review is theirs, and it is finished.
        if ($reason = TransferReviews::unusableReason($review)) {
            return response()
                ->view('executive_review.unavailable', [
                    'review'  => $review,
                    'reason'  => $reason,
                    'contact' => TransferReviews::officeContactEmail(),
                    'items'   => TransferReviews::items((int) $review->id),
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        $summary = TransferReviews::summary($review);

        // Verified in this browser, and the invitation is still open: show the
        // transfer. Everything else goes back through the code step.
        if (! $this->isVerified($request, $review)) {
            return response()
                ->view('executive_review.verify', [
                    'review'  => $review,
                    'token'   => $token,
                    'status'  => session('executive_review_notice'),
                    'error'   => session('executive_review_error'),
                    'devCode' => session('executive_review_dev_code'),
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
        }

        return response()
            ->view('executive_review.show', [
                'review'  => $review,
                'token'   => $token,
                'summary' => $summary,
                'status'  => session('executive_review_notice'),
                'error'   => session('executive_review_error'),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    /** Email a fresh verification code to the official mailbox. */
    public function sendCode(Request $request, string $token)
    {
        $review = TransferReviews::findByToken($token);

        if (! $review) {
            return $this->unknownLink();
        }

        // A closed invitation is refused here too: asking for a code must not be
        // a way around an expired or withdrawn link.
        if ($reason = TransferReviews::unusableReason($review)) {
            return redirect('/executive-review/' . $token)->with('executive_review_error', $reason);
        }

        $result = TransferReviews::sendCode($review);

        $redirect = redirect('/executive-review/' . $token);

        if (! $result['ok']) {
            return $redirect->with('executive_review_error', $result['message']);
        }

        $redirect = $redirect->with('executive_review_notice', $result['message']);

        // Local development only: with no reachable mail server the flow would
        // be untestable. Never shown on any other environment.
        if (! empty($result['dev_code'])) {
            $redirect = $redirect->with('executive_review_dev_code', $result['dev_code']);
        }

        return $redirect;
    }

    /** Check the code and unlock this one review for this browser. */
    public function verify(Request $request, string $token)
    {
        $review = TransferReviews::findByToken($token);

        if (! $review) {
            return $this->unknownLink();
        }

        $validated = $request->validate([
            'code' => 'required|string|max:12',
        ], [
            'code.required' => 'Enter the 6-digit code from the email.',
        ]);

        $result = TransferReviews::verify($review, $validated['code']);

        if (! $result['ok']) {
            return redirect('/executive-review/' . $token)->with('executive_review_error', $result['message']);
        }

        // The grant is per invitation and per browser. The session is the
        // browser's own; the invitation still decides whether it means anything.
        $grants = (array) $request->session()->get(self::SESSION_KEY, []);
        $grants[(int) $review->id] = now()->toIso8601String();
        $request->session()->put(self::SESSION_KEY, $grants);

        return redirect('/executive-review/' . $token)->with('executive_review_notice', $result['message']);
    }

    /** Submit the decision. */
    public function decide(Request $request, string $token)
    {
        $review = TransferReviews::findByToken($token);

        if (! $review) {
            return $this->unknownLink();
        }

        if ($reason = TransferReviews::unusableReason($review)) {
            return redirect('/executive-review/' . $token)->with('executive_review_error', $reason);
        }

        if (! $this->isVerified($request, $review)) {
            return redirect('/executive-review/' . $token)
                ->with('executive_review_error', 'Verify the mailbox before submitting a decision.');
        }

        $validated = $request->validate([
            'decision'         => 'required|in:approve_all,approve_selected,reject',
            'asset_ids'        => 'nullable|array',
            'asset_ids.*'      => 'integer',
            'rejection_reason' => 'nullable|string|max:1000',
        ], [
            'decision.required' => 'Choose whether to approve the transfer, approve selected assets, or reject it.',
            'decision.in'       => 'Choose whether to approve the transfer, approve selected assets, or reject it.',
            'rejection_reason.max' => 'The reason cannot exceed 1000 characters.',
        ]);

        $result = TransferReviews::decide($review, $validated);

        if (! $result['ok']) {
            Log::info('Executive Director decision refused for invitation #' . $review->id . ': ' . $result['message']);

            return redirect('/executive-review/' . $token)->with('executive_review_error', $result['message']);
        }

        // The invitation is spent: drop this browser's grant for it so the page
        // cannot be reloaded into an editable-looking state.
        $grants = (array) $request->session()->get(self::SESSION_KEY, []);
        unset($grants[(int) $review->id]);
        $request->session()->put(self::SESSION_KEY, $grants);

        // Re-read: the decision just recorded is what the closing page reports.
        return redirect('/executive-review/' . $token)->with('executive_review_notice', $result['message']);
    }

    /**
     * Is this browser verified for this invitation?
     *
     * The stored grant is the browser's claim; the invitation is the authority.
     * Both are required, and the grant also has a deadline of its own so a
     * browser left open overnight does not stay unlocked.
     */
    private function isVerified(Request $request, object $review): bool
    {
        $grants = (array) $request->session()->get(self::SESSION_KEY, []);
        $at = $grants[(int) $review->id] ?? null;

        if (! $at) {
            return false;
        }

        if (! $review->is_outstanding) {
            return false;
        }

        if ($review->status !== TransferReviews::STATUS_VERIFIED) {
            return false;
        }

        try {
            $verifiedAt = \Illuminate\Support\Carbon::parse((string) $at);
        } catch (\Throwable $e) {
            return false;
        }

        return $verifiedAt->diffInMinutes(now()) < (TransferReviews::verifiedSessionHours() * 60);
    }

    /**
     * A link that is not one of ours.
     *
     * The same answer whether the token was mistyped, revoked, or belongs to an
     * installation that no longer has the row — one shape of page, and a 404, so
     * nobody can learn from the response that a token nearly exists.
     */
    private function unknownLink()
    {
        return response()
            ->view('executive_review.unavailable', [
                'review'  => null,
                'reason'  => 'This review link is not valid. It may have been mistyped, or it may have been replaced by a newer invitation. Ask the NU Lipa Asset Management Office for a new link if the transfer still needs a decision.',
                'contact' => TransferReviews::officeContactEmail(),
                'items'   => collect(),
            ], 404)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
