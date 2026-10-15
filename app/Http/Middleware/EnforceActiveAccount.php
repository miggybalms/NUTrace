<?php

namespace App\Http\Middleware;

use App\Support\Access;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account stops working immediately.
 *
 * The status column existed but nothing read it, so switching an account to
 * Inactive changed a label and no behaviour: the person kept using the session
 * they already had, and could sign in again tomorrow. Deactivating is meant to
 * be a lock, and this is where it is applied.
 *
 * The check runs on every request and reads the row from the database, so the
 * session that is open right now is closed by the next click - not at the next
 * sign-in. Nothing is deleted: the account, its requests and its audit history
 * are all preserved, and reactivating it restores access with the same
 * identity.
 */
class EnforceActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! Access::isInactive($user)) {
            return $next($request);
        }

        // Sign out first: whatever the request was going to do, the answer is
        // the same for a locked account.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This account has been deactivated. Please contact the Asset Management Office.',
            ], 403);
        }

        return redirect('/')->withErrors([
            'email' => 'This account has been deactivated. Please contact the Asset Management Office.',
        ]);
    }
}
