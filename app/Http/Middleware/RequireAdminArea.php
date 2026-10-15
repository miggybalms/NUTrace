<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * /admin/* is the Asset Management Office's area.
 *
 * Until now the only thing standing at that door was "is somebody signed in",
 * so an employee account — or a department head, whose own pages live under
 * /department-head — could open the asset registry, the audit log, the requests
 * queue and its approve endpoint by typing the URL. Hiding a menu entry is not
 * access control, and this is the server-side check that replaces it.
 *
 * Applied to the whole web group and matched on the path, the same way
 * RedirectGuestToLanding already works, so a new admin route is covered the
 * moment it is written instead of the day someone remembers to guard it.
 *
 * A guest is left alone on purpose: RedirectGuestToLanding sends them to the
 * landing page, which is a better answer than "forbidden" for a copied URL.
 */
class RequireAdminArea
{
    /** URL prefixes that belong to the Asset Management Office. */
    private const ADMIN_AREA = ['admin', 'admin/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $request->is(...self::ADMIN_AREA)) {
            return $next($request);
        }

        if (Access::canEnterAdminArea($user)) {
            return $next($request);
        }

        // A fetch from an open page gets a JSON refusal it can display; a
        // pasted URL gets an honest 403 rather than a page that pretends to
        // work.
        if ($request->expectsJson()) {
            return response()->json(['message' => Access::adminAreaMessage()], 403);
        }

        abort(403, Access::adminAreaMessage());
    }
}
