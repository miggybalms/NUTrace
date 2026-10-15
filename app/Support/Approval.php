<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Refused decisions, written down.
 *
 * Requirement 3.9 asks for denied self-approval attempts and relevant
 * unauthorized operations to reach the audit trail. A refusal is silent
 * otherwise: the page shows nothing, the request stays Pending, and nobody
 * finds out that somebody tried. The entry uses the audit table that already
 * exists (action_type APPROVAL, the request linked through request_id), so it
 * sits next to the approval it was trying to be.
 *
 * The entries are deliberately short and factual - who, which request, and why
 * it was refused - and never contain anything the actor typed.
 */
class Approval
{
    /**
     * @param string $decision 'approve' or 'reject'
     */
    public static function recordRefusal(?User $actor, $requestRecord, string $decision): void
    {
        $verb = $decision === 'reject' ? 'reject' : 'approve';
        $past = $verb === 'reject' ? 'reject' : 'approve';

        $who = $actor
            ? (trim((string) ($actor->display_name ?? '')) ?: (string) $actor->email)
            : 'A visitor with no session';

        $requestId = (int) ($requestRecord->id ?? 0);

        // Two different refusals, told apart so the log reads correctly: the
        // submitter trying to decide their own request, and an account that
        // simply is not the Asset Management Office.
        $notes = Access::isSubmitter($actor, $requestRecord)
            ? 'Denied: ' . $who . ' tried to ' . $verb . ' request #' . $requestId . ', which they submitted themselves.'
            : 'Denied: ' . $who . ' (role: ' . (Access::role($actor) ?: 'unknown') . ') tried to '
                . $verb . ' request #' . $requestId . ' without the authority to decide requests.';

        $description = 'Request #' . $requestId . ' was not ' . $past . 'ed: '
            . (Access::isSubmitter($actor, $requestRecord)
                ? 'nobody may decide their own request.'
                : 'only the Asset Management Office may decide requests.');

        try {
            DB::table('audit_logs')->insert([
                'user_id'            => $actor?->id,
                'request_id'         => $requestId > 0 ? $requestId : null,
                'asset_id'           => null,
                'action_type'        => 'APPROVAL',
                'notes'              => $notes,
                'action_description' => $description,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            // The request is refused either way: losing the note must not
            // change the answer the caller gets.
            \Log::warning('Approval refusal could not be audited: ' . $e->getMessage());
        }
    }
}
