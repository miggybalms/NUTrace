<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;

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
    }
}
