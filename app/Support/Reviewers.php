<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Telling the right people that something is waiting for them.
 *
 * Extends the notification bell that already exists (notifications table +
 * Support\Notifications), so a submission reaches the accounts that can act on
 * it without introducing a second notification system.
 *
 * Two rules, both from the panel recommendations:
 *
 *   1. Only the Asset Management Office is notified as a reviewer, because only
 *      the Asset Management Office can decide a request. The Department Head of
 *      the submitter's own department also gets a notice - their own page lists
 *      that department's requests - but a notice is not authority: nothing here
 *      lets them decide anything.
 *   2. A deactivated account is never notified. An Inactive account is not
 *      coming back to a page it cannot open, and a bell that keeps ringing for
 *      it is noise the office has to read past.
 *
 * Every notice is de-duplicated on (account, request, title) so a retried POST
 * or a double-clicked Submit cannot ring twice for the same request.
 */
class Reviewers
{
    public const TYPE = 'REQUEST';

    /**
     * Who hears about this submission.
     *
     * @return array<int, User>
     */
    public static function forNewRequest(User $submitter): array
    {
        $office = User::query()
            ->whereIn('role', Access::ASSET_OFFICE_ROLES)
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->where('id', '!=', $submitter->id)
            ->orderBy('id')
            ->get();

        $recipients = [];
        foreach ($office as $account) {
            $recipients[(int) $account->id] = $account;
        }

        // The head of the submitter's own department, when there is one and it
        // is somebody else. Their request page is scoped to this department.
        if ($submitter->department_id) {
            $heads = User::query()
                ->where('role', 'Department Head')
                ->where('department_id', $submitter->department_id)
                ->whereRaw('LOWER(status) = ?', ['active'])
                ->where('id', '!=', $submitter->id)
                ->orderBy('id')
                ->get();

            foreach ($heads as $head) {
                $recipients[(int) $head->id] = $head;
            }
        }

        return array_values($recipients);
    }

    /**
     * Announce a newly submitted request.
     *
     * Called after the request row is written, so the notice can never describe
     * a request that does not exist - and if the notice fails, the request is
     * still there (Support\Notifications swallows and logs its own failures).
     */
    public static function notifyNewRequest(int $requestId, User $submitter, string $requestType, string $assetLabel): int
    {
        $type  = ucfirst(strtolower($requestType));
        $title = 'New ' . $type . ' Request';

        $message = $submitter->display_name . ' submitted a ' . $type . ' request for ' . $assetLabel . '.';

        $sent = 0;

        foreach (self::forNewRequest($submitter) as $recipient) {
            if (self::notifyOnce((int) $recipient->id, $title, $message, self::TYPE, $requestId, 'request')) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Create the notice unless this account already has it for this record.
     *
     * @return bool true when a notification was written
     */
    public static function notifyOnce(
        int $userId,
        string $title,
        string $message,
        string $type,
        ?int $referenceId = null,
        ?string $referenceType = null
    ): bool {
        if (! $userId) {
            return false;
        }

        $alreadySent = DB::table('notifications')
            ->where('user_id', $userId)
            ->where('title', $title)
            ->where('type', strtoupper($type))
            ->when($referenceId !== null, fn ($q) => $q->where('reference_id', $referenceId))
            ->when($referenceType !== null, fn ($q) => $q->where('reference_type', strtolower($referenceType)))
            ->exists();

        if ($alreadySent) {
            return false;
        }

        Notifications::create($userId, $title, $message, $type, $referenceId, $referenceType);

        return true;
    }
}
