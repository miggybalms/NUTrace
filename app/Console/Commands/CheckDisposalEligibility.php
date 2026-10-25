<?php

namespace App\Console\Commands;

use App\Support\Access;
use App\Support\DisposalEligibility;
use App\Support\Reviewers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tell the office when a disposal has finished its three-year waiting period.
 *
 * This command notifies and nothing else. It never finalizes a disposal, never
 * writes a disposal record and never changes an asset's lifecycle status: an
 * eligible asset still has to be confirmed by an Admin on the Disposal page.
 *
 * It is safe to run as often as you like. A request is handled once - the
 * notification is de-duplicated per account, and the request is stamped so the
 * next run does not look at it again. `--request=` narrows it to one request
 * for testing, and `--dry-run` shows what would be sent without writing.
 */
class CheckDisposalEligibility extends Command
{
    protected $signature = 'disposal:check-eligibility
                            {--request=0 : Only consider this disposal request id}
                            {--dry-run : List what would be notified without writing anything}';

    protected $description = 'Notify the Asset Management Office when an approved disposal request has completed its waiting period';

    public function handle(): int
    {
        // The code ships before the migration runs on a server: without the
        // columns there is nothing to measure, and approval is still writing no
        // timestamp. Say so once instead of failing every night.
        if (! DisposalEligibility::ready()) {
            $this->warn('The disposal eligibility migration has not been run yet — nothing to check. Run `php artisan migrate`.');

            return self::SUCCESS;
        }

        $now    = now();
        $cutoff = $now->copy()->subYears(DisposalEligibility::years());
        $only   = (int) $this->option('request');
        $dryRun = (bool) $this->option('dry-run');

        $requests = DB::table('requests')
            ->whereRaw('LOWER(request_type) = ?', ['disposal'])
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $cutoff)
            ->whereNull('disposal_eligibility_notified_at')
            ->when($only > 0, fn ($query) => $query->where('id', $only))
            ->orderBy('id')
            ->get(['id', 'user_id', 'approved_at', 'request_type', 'status', 'disposal_eligibility_notified_at', 'Note']);

        if ($requests->isEmpty()) {
            $this->info('No disposal request has reached the end of its ' . DisposalEligibility::periodAdjective() . ' waiting period.');

            return self::SUCCESS;
        }

        $notified = 0;
        $skipped  = 0;

        foreach ($requests as $request) {
            // The SQL cutoff is deliberately generous; the exact boundary is
            // decided by the same arithmetic the finalization uses.
            $state = DisposalEligibility::state($request, $now);

            if ($state['state'] !== DisposalEligibility::STATE_ELIGIBLE) {
                $skipped++;

                continue;
            }

            $codes  = $this->assetCodes((int) $request->id);
            $notice = DisposalEligibility::notice($request, $codes, $now);

            if ($dryRun) {
                $this->line('Would notify: request #' . (int) $request->id
                    . ' · approved ' . $state['approval_at']->toDateString()
                    . ' · eligible ' . $state['eligible_at']->toDateString()
                    . ' · ' . ($codes ? implode(', ', $codes) : 'no asset codes'));
                $notified++;

                continue;
            }

            $recipients = $this->recipients();

            if ($recipients->isEmpty()) {
                // Left unstamped on purpose: a later run, once an active Admin
                // account exists, should still be able to send it.
                Log::warning('Disposal eligibility notice not sent: no active Asset Management Office account.', [
                    'request_id' => (int) $request->id,
                ]);

                $this->warn('Request #' . (int) $request->id . ' is eligible, but there is no active Admin account to notify.');
                $skipped++;

                continue;
            }

            $written = 0;

            foreach ($recipients as $account) {
                try {
                    // Same one-per-account rule the rest of the notification
                    // code uses, so a re-run (or a retry after a timeout) cannot
                    // ring twice for the same request.
                    if (Reviewers::notifyOnce(
                        (int) $account->id,
                        $notice['title'],
                        $notice['message'],
                        'DISPOSAL',
                        (int) $request->id,
                        'disposal'
                    )) {
                        $written++;
                    }
                } catch (\Throwable $e) {
                    Log::error('Disposal eligibility notification failed: ' . $e->getMessage(), [
                        'request_id' => (int) $request->id,
                        'user_id'    => (int) $account->id,
                    ]);
                }
            }

            // Notifications\create() swallows its own failures, so "we called it"
            // is not the same as "somebody was told". The row is what counts: if
            // none exists, the request stays unstamped and the next run tries
            // again, rather than the office being silently left uninformed.
            $delivered = DB::table('notifications')
                ->where('title', $notice['title'])
                ->where('type', 'DISPOSAL')
                ->where('reference_id', (int) $request->id)
                ->where('reference_type', 'disposal')
                ->count();

            if ($delivered === 0) {
                Log::error('Disposal eligibility notice could not be written; the request stays un-notified.', [
                    'request_id' => (int) $request->id,
                ]);

                $this->error('Request #' . (int) $request->id . ' is eligible, but the notice could not be written. It will be retried on the next run.');
                $skipped++;

                continue;
            }

            // Stamped even when every notice already existed: the request has
            // been handled, and the stamp is what the disposal form shows as
            // "office notified on …".
            DisposalEligibility::markNotified((int) $request->id, $now);

            $this->audit($request, $codes, $state, $written, $recipients->count());

            $notified++;
            $this->info('Request #' . (int) $request->id . ' is eligible — notified ' . $written . ' account(s).');
        }

        $this->info('Done: ' . $notified . ' eligible request(s) handled, ' . $skipped . ' skipped.');

        return self::SUCCESS;
    }

    /**
     * The accounts that may finalize a disposal: the Asset Management Office.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function recipients()
    {
        return DB::table('users')
            ->whereIn('role', Access::ASSET_OFFICE_ROLES)
            ->whereRaw('LOWER(status) = ?', [strtolower(Access::STATUS_ACTIVE)])
            ->orderBy('id')
            ->get(['id', 'email']);
    }

    /**
     * The asset codes a request covers, for a message the office can act on.
     *
     * @return array<int, string>
     */
    private function assetCodes(int $requestId): array
    {
        $codes = DB::table('request_items')
            ->join('assets', 'request_items.asset_id', '=', 'assets.id')
            ->where('request_items.request_id', $requestId)
            ->orderBy('assets.id')
            ->pluck('assets.Asset_code')
            ->filter()
            ->values()
            ->all();

        if ($codes) {
            return $codes;
        }

        // Legacy single-asset requests keep the asset on requests.asset_id.
        $fallback = DB::table('requests')
            ->join('assets', 'requests.asset_id', '=', 'assets.id')
            ->where('requests.id', $requestId)
            ->value('assets.Asset_code');

        return $fallback ? [(string) $fallback] : [];
    }

    /**
     * One audit entry per handled request - never one per daily run.
     */
    private function audit(object $request, array $codes, array $state, int $written, int $recipients): void
    {
        try {
            DB::table('audit_logs')->insert([
                'user_id'            => null,
                'request_id'         => (int) $request->id,
                'asset_id'           => null,
                'action_type'        => 'UPDATE',
                'notes'              => 'Disposal eligibility reached: ' . DisposalEligibility::periodAdjective()
                    . ' waiting period ended (approved ' . $state['approval_at']->toDateTimeString()
                    . ', eligible ' . $state['eligible_at']->toDateTimeString() . ')',
                'action_description' => 'Eligibility notice for disposal request #' . (int) $request->id
                    . ($codes ? ' (' . implode(', ', $codes) . ')' : '')
                    . ' was generated for ' . $written . ' of ' . $recipients . ' Asset Management Office account(s).'
                    . ' The assets are eligible for final disposal; nothing was disposed of.',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (\Throwable $e) {
            // A missing audit row must not stop the office being told.
            Log::warning('Disposal eligibility audit entry skipped: ' . $e->getMessage(), [
                'request_id' => (int) $request->id,
            ]);
        }
    }
}
