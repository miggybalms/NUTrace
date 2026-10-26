<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The credential the phone uses to open a sealed sticker.
 *
 * The mobile app has no Laravel session: it signs in against the `users` table
 * with a Supabase password RPC and keeps its own copy of the account. The web's
 * resolver used to accept anonymous callers for that reason, which also meant
 * anyone who photographed a sticker could have its asset code opened for them.
 *
 * The phone now asks `POST /qr/session` once, with the same email and password
 * it already uses, and receives this token. The token:
 *
 *   - is HMAC-SHA256 signed under a key derived from APP_KEY, so no new secret
 *     has to exist in the environment or in the app bundle;
 *   - names one account and an expiry, and nothing else;
 *   - is verified here before a single byte of a sticker is parsed;
 *   - is stateless, so it can only be invalidated all at once by rotating
 *     APP_KEY. Nothing in this class writes to the database.
 *
 * The token is a bearer credential. It is not a signing key, cannot mint a
 * sticker, and grants only the resolver: the same account rules that decide
 * what a signed-in browser may see still run on every scan.
 */
class QrMobileToken
{
    /** Version of the token envelope. Bumping it invalidates every old token. */
    private const VERSION = 1;

    /** Bound into the derived key, so the same APP_KEY yields a separate secret. */
    private const HKDF_INFO = 'nutrace-qr-mobile-token';

    /** A token shorter than this cannot even hold the envelope. */
    private const MIN_LENGTH = 24;

    /**
     * Mint a token for one account.
     *
     * @return array{token: string, expires_at: string, user: array{id: int, role: string}}
     */
    public static function issue(User $user, ?Carbon $now = null): array
    {
        $now = $now ?: now();
        $days = max(1, (int) config('qr.resolve.token_ttl_days', 30));
        $expires = $now->copy()->addDays($days);

        $payload = self::encode(json_encode([
            'v'   => self::VERSION,
            'sub' => (int) $user->id,
            'iat' => $now->getTimestamp(),
            'exp' => $expires->getTimestamp(),
        ], JSON_THROW_ON_ERROR));

        return [
            'token'      => $payload . '.' . self::encode(self::sign($payload)),
            'expires_at' => $expires->toIso8601String(),
            'user'       => [
                'id'   => (int) $user->id,
                'role' => (string) ($user->role ?? ''),
            ],
        ];
    }

    /**
     * The account this token names, or null when it is malformed, unsigned,
     * expired, or belongs to an account that no longer exists or is inactive.
     */
    public static function user(?string $token, ?Carbon $now = null): ?User
    {
        $token = trim((string) $token);

        if ($token === '' || strlen($token) < self::MIN_LENGTH) {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $encodedSignature] = $parts;

        $signature = self::decode($encodedSignature);

        if ($signature === null || strlen($signature) !== 32) {
            return null;
        }

        if (! hash_equals(self::sign($payload), $signature)) {
            return null;
        }

        $json = self::decode($payload);

        if ($json === null) {
            return null;
        }

        try {
            $claims = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return null;
        }

        if (! is_array($claims) || (int) ($claims['v'] ?? 0) !== self::VERSION) {
            return null;
        }

        $userId = (int) ($claims['sub'] ?? 0);
        $expires = (int) ($claims['exp'] ?? 0);

        if ($userId <= 0 || $expires <= 0 || ($now ?: now())->getTimestamp() >= $expires) {
            return null;
        }

        $user = User::find($userId);

        if (! $user || Access::isInactive($user)) {
            return null;
        }

        return $user;
    }

    /**
     * The derived signing key, or null when APP_KEY is missing.
     *
     * Null means "no token can be issued or accepted" - fail closed rather than
     * fall back to a fixed string.
     */
    private static function key(): ?string
    {
        $appKey = (string) config('app.key', '');

        if ($appKey === '') {
            return null;
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded === false || $decoded === '') {
                return null;
            }

            $appKey = $decoded;
        }

        return hash_hkdf('sha256', $appKey, 32, self::HKDF_INFO);
    }

    private static function sign(string $payload): string
    {
        $key = self::key();

        return $key === null ? '' : hash_hmac('sha256', $payload, $key, true);
    }

    /** base64url without padding (safe inside a header and a JSON string). */
    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** Strict base64url decode; anything that is not canonical becomes null. */
    private static function decode(string $encoded): ?string
    {
        if ($encoded === '' || preg_match('/[^A-Za-z0-9\-_]/', $encoded)) {
            return null;
        }

        $padded = strtr($encoded, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }
}
