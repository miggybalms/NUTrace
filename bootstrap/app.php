<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway terminates HTTPS in front of the app, so requests arrive as
        // plain HTTP. Trust the proxy's forwarded headers (X-Forwarded-Proto,
        // X-Forwarded-Host, ...) on every request: without this Laravel builds
        // http:// form actions and redirects on the https:// site, which trips
        // Chrome's "the information you're about to submit is not secure"
        // interstitial. Submitting past it loses the POST flash data (e.g. the
        // print-QR labels after registering assets) and resets the page.
        $middleware->trustProxies(at: '*');

        // Log user login events
        $middleware->web(append: \App\Http\Middleware\LogUserLogin::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function ($schedule) {
        // Check for expired assets daily at 2 AM
        $schedule->command('assets:check-expiration')
            ->daily()
            ->at('02:00')
            ->onOneServer();
    })
    ->create();
