<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Models\Asset;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share the currently authenticated user with all views, but guard against DB errors
        try {
            $user = Auth::user();
        } catch (\Throwable $e) {
            $user = null;
        }

        View::share('currentUser', $user);

        // Auto-transition assets from 'Acquired' to 'Active' after 1 day
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('assets')) {
                $threshold = Carbon::now()->subDay();

                $updated = Asset::where('Lifecycle_Status', 'Acquired')
                    ->where(function ($q) use ($threshold) {
                        $q->whereNotNull('accusion_date')
                          ->where('accusion_date', '<=', $threshold->toDateString())
                          ->orWhere('created_at', '<=', $threshold);
                    })
                    ->update(['Lifecycle_Status' => 'Active']);

                if ($updated) {
                    Log::info("Auto-updated {$updated} asset(s) from Acquired to Active");
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to auto-update asset lifecycle statuses: ' . $e->getMessage());
        }
    }
}
