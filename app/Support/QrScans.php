<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The scan ledger: what notices a reprinted sticker.
 *
 * A QR code is text drawn as squares. Whoever can read a sticker's text can
 * print the same text again, and that copy verifies exactly like the original -
 * there is no cryptographic way to tell them apart, because the bytes are the
 * bytes. What a system *can* notice is the consequence: one sticker, which is
 * one physical label on one object, being used from two different places.
 *
 * So every verified scan is recorded against an HMAC fingerprint of its
 * payload (never the payload, never the code), together with who presented it
 * and when. When the same fingerprint arrives from a different account or
 * network inside the configured window, the scan is reported as a suspected
 * duplicate and written to the log. The scan still works - a genuine sticker
 * scanned by two people in an audit is a normal thing - so this is a warning
 * to check the object physically, not a verdict.
 *
 * The kill switch for a real copy is revocation (Support\AssetQr::revoke):
 * the sticker's status changes for every copy at once, and reissuing prints a
 * fresh payload.
 */
class QrScans
{
    public const TABLE = 'qr_scan_events';

    /**
     * Record one scan and say whether the same sticker was seen from another
     * source recently.
     *
     * Never throws: a missing table (a deployment that has not migrated yet)
     * or a database hiccup must not break scanning.
     *
     * @param  array{status: string, code: string|null, asset: array|null}  $result
     * @return array{suspected: bool, others: int, window_minutes: int}
     */
    public static function record(string $scanned, array $result, ?User $viewer = null, ?string $ip = null): array
    {
        $window = max(1, (int) config('qr.duplicate_window_minutes', 30));
        $quiet = ['suspected' => false, 'others' => 0, 'window_minutes' => $window];

        // Only stickers that actually resolved are worth tracking: a forged or
        // revoked one is already refused and logged on its own.
        if (! in_array($result['status'], [QrVerification::VALID, QrVerification::LEGACY, QrVerification::UNAVAILABLE], true)) {
            return $quiet;
        }

        try {
            $fingerprint = self::fingerprint($scanned);
            $source = self::sourceKey($viewer, $ip);

            $others = DB::table(self::TABLE)
                ->where('fingerprint', $fingerprint)
                ->where('seen_at', '>=', now()->subMinutes($window))
                ->where('source_key', '!=', $source)
                ->distinct()
                ->count('source_key');

            DB::table(self::TABLE)->insert([
                'fingerprint' => $fingerprint,
                'asset_id' => $result['asset']['id'] ?? null,
                'asset_code' => $result['code'] ?? null,
                'viewer_id' => $viewer?->id,
                'source_key' => $source,
                'status' => $result['status'],
                'seen_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($others > 0) {
                Log::warning('qr.duplicate_suspected', [
                    'asset_id' => $result['asset']['id'] ?? null,
                    'code' => $result['code'] ?? null,
                    'other_sources' => $others,
                    'window_minutes' => $window,
                    'viewer_id' => $viewer?->id,
                    'fingerprint' => substr($fingerprint, 0, 16),
                ]);
            }

            return ['suspected' => $others > 0, 'others' => $others, 'window_minutes' => $window];
        } catch (Throwable $e) {
            report($e);

            return $quiet;
        }
    }

    /**
     * A stable, non-reversible fingerprint of a sticker's text.
     *
     * Keyed with the application key, so the same payload produces the same
     * fingerprint on this deployment and a different one elsewhere - the table
     * then cannot be used to recognise (or rebuild) a sticker from a database
     * dump.
     */
    public static function fingerprint(string $scanned): string
    {
        return hash_hmac('sha256', trim($scanned), (string) config('app.key', 'nutrace'));
    }

    /**
     * Who presented this sticker: the account when there is a session, and a
     * hash of the address when there is not (the phone app).
     *
     * The address itself is never stored - two different networks still count
     * as two sources, without keeping a record of where anyone was.
     */
    public static function sourceKey(?User $viewer = null, ?string $ip = null): string
    {
        if ($viewer !== null) {
            return 'user:' . $viewer->id;
        }

        $address = trim((string) ($ip ?? request()?->ip() ?? ''));

        return $address === '' ? 'unknown' : 'net:' . substr(hash('sha256', $address), 0, 32);
    }
}
