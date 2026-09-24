<?php

namespace App\Http\Middleware;

use App\Support\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogUserLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if user just authenticated during this request
        if (Auth::check() && !session()->has('login_logged')) {
            // Who signed in and when - never where from. Sign-outs are recorded
            // by the Logout event listener in AppServiceProvider.
            AuditTrail::login(Auth::user());

            // Mark that we've logged this login
            session()->put('login_logged', true);
        }

        return $response;
    }
}
