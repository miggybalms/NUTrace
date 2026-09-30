<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A private-area URL only works for the browser that signed in.
 *
 * Pasting an admin or user URL (e.g. https://…/admin/disposal) into another
 * browser, or into a fresh profile, carries no session cookie — so the request
 * is not that account's to make. Instead of rendering the page (or 500ing on a
 * missing `login` route) the visitor is sent to the NU TRACE landing page,
 * which is the only page that reads correctly without a session.
 *
 * Fetch calls from an already-open page get a 401 JSON answer so the page can
 * explain what happened instead of silently rendering the landing page.
 */
class RedirectGuestToLanding
{
    /** URL prefixes that belong to a signed-in account. */
    private const PRIVATE_AREAS = [
        'admin', 'admin/*',
        'users', 'users/*',
        'user', 'user/*',
        'department-head', 'department-head/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() || ! $request->is(...self::PRIVATE_AREAS)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Your session has ended. Please sign in again.',
            ], 401);
        }

        return redirect('/');
    }
}
