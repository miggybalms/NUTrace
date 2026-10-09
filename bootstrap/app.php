<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Response;

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

        // There is no route named `login`; without this, `auth` sends a guest to
        // route('login') and the request dies with "Route [login] not defined."
        // Guests belong on the landing page.
        $middleware->redirectGuestsTo('/');

        // Copied admin/user URLs must not work in another browser. This runs
        // before the controllers so no private page is ever rendered for a
        // request that carries no session.
        $middleware->web(append: \App\Http\Middleware\RedirectGuestToLanding::class);

        // Log user login events
        $middleware->web(append: \App\Http\Middleware\LogUserLogin::class);

        // Security response headers (HSTS, CSP, X-Frame-Options, nosniff,
        // Referrer-Policy, Permissions-Policy) on every response the app
        // produces. Global, not part of the web group, so downloads and JSON
        // endpoints carry them too.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An error response never travels back through the middleware stack, so
        // the same headers are attached here — a 404 or 500 must not be the one
        // page on the site that is missing them.
        $exceptions->respond(function (Response $response) {
            return \App\Http\Middleware\SecurityHeaders::apply($response, request()->isSecure());
        });
    })
    ->withSchedule(function ($schedule) {
        // Check for expired assets daily at 2 AM
        $schedule->command('assets:check-expiration')
            ->daily()
            ->at('02:00')
            ->onOneServer();
    })
    ->create();
