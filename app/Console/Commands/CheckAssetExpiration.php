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
    protected $description = 'Check for assets with expired lifespan or overdue maintenance and update their lifecycle status to "For Checking"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired assets and overdue maintenance...');

        try {
            $today = Carbon::now()->toDateString();
            $count = 0;

            // 1. Find assets that have reached or exceeded their expiration date
            // and are currently in "Active" status
            $this->info('Checking for expired lifespan assets...');
            $expiredAssets = DB::table('assets')
                ->whereNotNull('expiration_date')
                ->where('expiration_date', '<=', $today)
                ->where('Lifecycle_Status', '=', 'Active')
                ->get();

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
                        'action_type' => 'UPDATE',
                        'action_description' => 'Automatic status change: Asset lifespan expired',
                        'notes' => 'Asset lifespan expired on ' . $asset->expiration_date . '. Lifecycle status automatically changed to "For Checking" for evaluation.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $count++;
                    $this->line("✓ Lifespan expired: Asset #{$asset->id} ({$asset->Asset_code}) → For Checking");

                } catch (\Exception $e) {
                    $this->error("Failed to update asset #{$asset->id}: " . $e->getMessage());
                    Log::error('Failed to update expired asset', ['asset_id' => $asset->id, 'error' => $e->getMessage()]);
                }
            }

            // 2. Find assets with overdue maintenance (maintenance due today or earlier)
            // and are currently in "Active" status
            $this->info('Checking for overdue maintenance assets...');
            $maintenanceOverdueAssets = DB::table('assets')
                ->whereNotNull('next_maintenance_date')
                ->where('next_maintenance_date', '<=', $today)
                ->where('Lifecycle_Status', '=', 'Active')
                ->get();

            foreach ($maintenanceOverdueAssets as $asset) {
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
                        'action_type' => 'UPDATE',
                        'action_description' => 'Automatic status change: Maintenance overdue',
                        'notes' => 'Asset maintenance is overdue (due date: ' . $asset->next_maintenance_date . '). Lifecycle status automatically changed to "For Checking" for maintenance evaluation.',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $count++;
                    $this->line("✓ Maintenance overdue: Asset #{$asset->id} ({$asset->Asset_code}) → For Checking");

                } catch (\Exception $e) {
                    $this->error("Failed to update asset #{$asset->id}: " . $e->getMessage());
                    Log::error('Failed to update maintenance overdue asset', ['asset_id' => $asset->id, 'error' => $e->getMessage()]);
                }
            }

            if ($count === 0) {
                $this->info('No assets requiring evaluation found.');
            } else {
                $this->info("✓ Successfully updated {$count} asset(s) to 'For Checking' status");
            }

            Log::info('Asset evaluation check completed', ['checked_assets_count' => $count]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error checking assets: ' . $e->getMessage());
            Log::error('Asset evaluation check error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}
