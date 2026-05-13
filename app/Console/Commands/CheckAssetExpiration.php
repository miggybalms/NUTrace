<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CheckAssetExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assets:check-expiration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for assets that have reached their expiration date and update their lifecycle status to "For Checking"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired assets...');

        try {
            $today = Carbon::now()->toDateString();

            // Find assets that have reached or exceeded their expiration date
            // and are currently in "Active" status
            $expiredAssets = DB::table('assets')
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<=', $today)
                ->where('Lifecycle_Status', '=', 'Active')
                ->get();

            if ($expiredAssets->count() === 0) {
                $this->info('No expired assets found.');
                return Command::SUCCESS;
            }

            $count = 0;
            foreach ($expiredAssets as $asset) {
                try {
                    DB::table('assets')
                        ->where('id', $asset->id)
                        ->update([
                            'Lifecycle_Status' => 'For Checking',
                            'updated_at' => now(),
                        ]);

                    // Log the change in audit_logs
                    DB::table('audit_logs')->insert([
                        'asset_id' => $asset->id,
                        'notes' => 'Asset lifespan expired on ' . $asset->expiration_date . '. Lifecycle status automatically changed to "For Checking" for evaluation.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $count++;
                    $this->line("✓ Updated asset #{$asset->id} ({$asset->Asset_code}) to 'For Checking' status");

                } catch (\Exception $e) {
                    $this->error("Failed to update asset #{$asset->id}: " . $e->getMessage());
                    Log::error('Failed to update expired asset', ['asset_id' => $asset->id, 'error' => $e->getMessage()]);
                }
            }

            $this->info("✓ Successfully updated {$count} asset(s) to 'For Checking' status");
            Log::info('Asset expiration check completed', ['expired_assets_count' => $count]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error checking asset expiration: ' . $e->getMessage());
            Log::error('Asset expiration check error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
