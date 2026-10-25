<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * NUTAG-1 stickers: the token behind one, the text form the app passes around,
 * and the server-side verdict for a scan.
 *
 * The point of this format is what a *foreign* reader sees: nothing. The
 * symbol is a frame and a grid of cells, not a QR code, so a camera app or a
 * scanning website finds no code at all. Only NU Trace knows how to sample the
 * cells, and only the server can say whether the token and MAC in them are
 * genuine - the key never reaches the browser.
 *
 * What this does NOT stop: a photograph of a genuine tag can be printed again,
 * and that copy produces the same token, exactly like a photocopied QR. The
 * MAC proves this system minted the token, never which piece of paper is in
 * front of the camera. That is why QrScans still records every scan: the same
 * tag arriving from two places is the signal worth acting on, and revoking the
 * sticker is what makes every copy dead at once.
 */
class AssetTag
{
    /** Text form memo for one request: a label sheet asks for a code many times. */
    private static array $texts = [];

    /**
     * The HMAC key for the tag's check value.
     *
     * `QR_TAG_KEY` is its own secret so rotating it does not touch the QR
     * payload key; when it is not set the QR payload key is used (same
     * deployment, same trust boundary), and failing both a value derived from
     * APP_KEY keeps a single install working without configuration.
     */
    public static function key(): string
    {
        foreach (['qr.tag_key', 'qr.payload_key'] as $path) {
            $value = trim((string) config($path, ''));

            if ($value !== '') {
                return $value;
            }
        }

        return hash('sha256', 'nutag:'.(string) config('app.key'), true);
    }

    /**
     * The tag's text form for an asset code, issuing a token the first time the
     * code is seen. Null when the code cannot carry a sticker at all.
     *
     * The token is stored rather than derived: a derived token could not be
     * revoked or replaced without changing the code, and looking one up would
     * mean testing every asset on every scan.
     */
    public static function textFor(?string $code): ?string
    {
        $code = AssetCode::normalize((string) $code);

        if ($code === '' || ! AssetCode::isValid($code)) {
            return null;
        }

        $stored = mb_strtoupper($code);

        if (isset(self::$texts[$stored])) {
            return self::$texts[$stored];
        }

        $token = DB::table('asset_tags')->where('asset_code', $stored)->value('token');

        if ($token === null) {
            $candidate = strtoupper(bin2hex(random_bytes(NutTag::TOKEN_BYTES)));

            try {
                DB::table('asset_tags')->insert([
                    'token'      => $candidate,
                    'asset_code' => $stored,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $token = $candidate;
            } catch (Throwable $e) {
                // Two printers asked for the same new code at once: the unique
                // index kept one token, and both stickers use it.
                $token = DB::table('asset_tags')->where('asset_code', $stored)->value('token');

                if ($token === null) {
                    report($e);

                    return null;
                }
            }
        }

        $binary = hex2bin((string) $token);

        if ($binary === false || strlen($binary) !== NutTag::TOKEN_BYTES) {
            return null;
        }

        return self::$texts[$stored] = NutTag::text($binary, NutTag::mac($binary, self::key()));
    }

    /** The tag for a code drawn as SVG, or null when the code cannot carry one. */
    public static function svgFor(?string $code, string $label = 'NU Trace asset tag'): ?string
    {
        $text = self::textFor($code);

        return $text === null ? null : NutTag::svgFromText($text, $label);
    }

    /**
     * Resolve one scanned tag.
     *
     * The order is deliberate and mirrors App\Support\QrVerification: check
     * the MAC first (an altered tag is never looked up), then the token, then
     * the asset itself, then revocation. The asset's live row is the only
     * source of truth for anything shown.
     *
     * @return array{status: string, label: string, tone: string, detail: string, signed: bool, sealed: bool, key_id: string|null, code: string|null, permitted: bool, asset: array|null, duplicate?: array}
     */
    public static function verify(string $scanned, ?User $viewer = null): array
    {
        $parsed = NutTag::fromText($scanned);

        if ($parsed === null) {
            return self::verdict(QrVerification::MALFORMED, 'That is not a NU Trace tag.', null, $viewer, $scanned);
        }

        if (! hash_equals(NutTag::mac($parsed['token'], self::key()), $parsed['mac'])) {
            return self::verdict(
                QrVerification::INVALID_SIGNATURE,
                'The check value in this tag does not match. It was altered, or it was not made by this system.',
                null,
                $viewer,
                $scanned
            );
        }

        $tag = DB::table('asset_tags')->where('token', strtoupper(bin2hex($parsed['token'])))->first();

        if ($tag === null) {
            return self::verdict(
                QrVerification::UNKNOWN_ASSET,
                'This tag is genuine, but no asset in this database claims it.',
                null,
                $viewer,
                $scanned
            );
        }

        $row = AssetQr::findByCode((string) $tag->asset_code);

        if ($row === null) {
            return self::verdict(
                QrVerification::UNKNOWN_ASSET,
                'The code behind this tag does not belong to any asset in this database.',
                null,
                $viewer,
                $scanned
            );
        }

        if (! empty($row->qr_revoked_at)) {
            return self::verdict(
                QrVerification::REVOKED,
                'The Asset Management Office revoked this asset\'s sticker. Do not rely on it.',
                $row,
                $viewer,
                $scanned
            );
        }

        return self::verdict(
            QrVerification::VALID,
            'The tag\'s check value is genuine and matches the asset in the database.',
            $row,
            $viewer,
            $scanned
        );
    }

    /** Forget the text forms minted in this process (tests, after a key change). */
    public static function clearCache(): void
    {
        self::$texts = [];
    }

    /**
     * One verdict in the same shape QrVerification returns, so every screen
     * that already shows a scan result can show this one without a second
     * code path.
     */
    private static function verdict(string $status, ?string $detail, ?object $row, ?User $viewer, ?string $scanned = null): array
    {
        $state = QrVerification::STATES[$status] ?? QrVerification::STATES[QrVerification::MALFORMED];
        $permitted = $row !== null && QrVerification::permitted($row, $viewer);

        $result = [
            'status'    => $status,
            'label'     => $state['label'],
            'tone'      => $state['tone'],
            'detail'    => $detail ?? $state['detail'],
            'signed'    => true,
            'sealed'    => true,
            'key_id'    => NutTag::TEXT_PREFIX,
            'code'      => $row->Asset_code ?? null,
            'permitted' => $permitted,
            'asset'     => $permitted && $row !== null ? AssetQr::summary($row) : null,
        ];

        if (in_array($status, [QrVerification::INVALID_SIGNATURE, QrVerification::REVOKED], true)) {
            Log::warning('tag.verification_failed', [
                'status'    => $status,
                'asset_id'  => $row->id ?? null,
                'viewer_id' => $viewer?->id,
            ]);
        }

        if ($scanned !== null) {
            // The same tag turning up from a second account or network is the
            // copy signal - the tag's own bytes cannot tell a copy from the
            // original, so this is what the office acts on.
            $result['duplicate'] = QrScans::record($scanned, $result, $viewer);
        }

        return $result;
    }
}
