<?php

namespace App\Http\Middleware;

use App\Support\QrMobileToken;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The door in front of /qr/resolve.
 *
 * A sealed sticker carries an opaque blob. Opening it is the one operation that
 * turns paper into an asset code, so it is no longer offered anonymously: the
 * caller must present the bearer token the phone receives from /qr/session.
 *
 * The token is the only accepted credential. A Laravel session cookie is not
 * looked at here, which is also why the route can stay out of the CSRF group:
 * a request that carries a cookie but no token is answered 401 rather than
 * acted on, so there is nothing for a cross-site form to trigger.
 *
 * Limits are per account, not per address: two employees behind one office NAT
 * get their own budgets, and a copied token cannot escape the limit by changing
 * the payload it sends.
 */
class AuthenticateQrClient
{
    /** How long one minute's window lasts, in seconds. */
    private const MINUTE = 60;

    /** How long the daily window lasts, in seconds. */
    private const DAY = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $user = QrMobileToken::user($request->bearerToken());

        if ($user === null) {
            return response()->json([
                'message' => 'Sign in to NU Trace again to verify a sticker.',
            ], 401);
        }

        $limiter = $this->limiter();
        $perMinute = max(1, (int) config('qr.resolve.per_minute', 60));
        $perDay = max(1, (int) config('qr.resolve.per_day', 2000));

        $minuteKey = 'qr-resolve:minute:' . $user->id;
        $dayKey = 'qr-resolve:day:' . $user->id;

        if ($limiter->tooManyAttempts($dayKey, $perDay)) {
            return $this->refuse($limiter->availableIn($dayKey), 'The daily limit for verifying stickers has been reached.');
        }

        if ($limiter->tooManyAttempts($minuteKey, $perMinute)) {
            return $this->refuse($limiter->availableIn($minuteKey), 'Too many sticker verifications in a short time. Try again in a moment.');
        }

        $limiter->hit($minuteKey, self::MINUTE);
        $limiter->hit($dayKey, self::DAY);

        // The account, not a session: the route runs no auth middleware, so a
        // cookie cannot stand in for the token. QrVerification receives this
        // user and applies the same permission rule the rest of the app uses.
        $request->attributes->set('qrUser', $user);

        return $next($request);
    }

    /**
     * The limiter's cache store.
     *
     * The app's default store is `database`, and this deployment has no `cache`
     * table, so the store is chosen explicitly in config/qr.php. If even that is
     * unavailable, limiting falls back to the per-request array store and says
     * so in the log rather than turning a working scanner into a 500.
     */
    private function limiter(): RateLimiter
    {
        $store = (string) config('qr.resolve.limiter_store', 'file');

        try {
            return new RateLimiter(Cache::store($store));
        } catch (Throwable $e) {
            Log::warning('QR rate limiter store unavailable; falling back to a per-request store.', [
                'store' => $store,
                'error' => $e->getMessage(),
            ]);

            return new RateLimiter(Cache::store('array'));
        }
    }

    private function refuse(int $retryAfter, string $message): Response
    {
        return response()
            ->json([
                'message'     => $message,
                'retry_after' => max(1, $retryAfter),
            ], 429)
            ->header('Retry-After', (string) max(1, $retryAfter));
    }
}
