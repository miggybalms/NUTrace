<?php

namespace App\Support;

/**
 * The sealed body of a version 2 sticker.
 *
 * A version 1 payload carried the asset code in the open:
 *
 *     NUT1-AST-0001-V1-<signature>
 *
 * which meant a camera, a phone app or a QR-generator website could read the
 * code straight off the paper. Version 2 wraps the code in AES-256-GCM before
 * it is signed, so the text on the sticker is an opaque blob: no code, no key
 * id alone, nothing to retype into a generator and nothing to learn from.
 *
 * What this does *not* do - and no scheme can do - is stop a copy: a QR code is
 * text drawn as squares, so whoever reads the blob can print the same blob.
 * That copy still scans as genuine; Support\QrScans is what notices the same
 * blob being used from two places.
 *
 * The key never travels: it is taken from `QR_PAYLOAD_KEY` when the deployment
 * sets one, and otherwise derived from `APP_KEY` with HKDF. Every deployment
 * that has to read the same stickers must therefore share that value - two
 * installs with different keys simply cannot open each other's payloads, and a
 * scan of one of them reports "verification unavailable" rather than "fake".
 *
 * The nonce is derived from the plaintext (HMAC of the code) instead of being
 * random, which is what makes a sticker's text stable: the label sheet, the
 * stored PNG and a reprint all carry exactly the same payload. Asset codes are
 * unique, so no nonce is ever reused for two different plaintexts.
 */
class QrSeal
{
    /** 12-byte nonce + 16-byte tag + at least one byte of code. */
    private const MIN_BYTES = 29;

    private const NONCE_BYTES = 12;

    private const TAG_BYTES = 16;

    private const CIPHER = 'aes-256-gcm';

    /** Bound into the tag, so a body cannot be moved to another payload type. */
    private const AAD = 'NUT2';

    private const HKDF_INFO = 'nutrace-qr-payload-v2';

    private const NONCE_INFO = 'nutrace-qr-payload-v2-nonce';

    /** The derived encryption key, built once per request. */
    private static ?string $key = null;

    /** Forget the derived key (tests, and anything that changes config). */
    public static function flush(): void
    {
        self::$key = null;
    }

    /**
     * Can this install seal and open payloads? False when neither
     * `QR_PAYLOAD_KEY` nor `APP_KEY` yields usable key material.
     */
    public static function available(): bool
    {
        return self::key() !== null;
    }

    /**
     * Seal one asset code into the opaque body of a sticker.
     *
     * Returns the raw bytes (`nonce || ciphertext || tag`), or null when no
     * key is configured - callers then fall back to the readable v1 format
     * rather than printing something this server cannot open again.
     */
    public static function seal(string $plain): ?string
    {
        $key = self::key();

        if ($key === null || $plain === '') {
            return null;
        }

        $nonce = self::nonceFor($plain);
        $tag = '';

        $ciphertext = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD, self::TAG_BYTES);

        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            return null;
        }

        return $nonce . $ciphertext . $tag;
    }

    /**
     * Open a sealed body back into its asset code, or null when this server
     * cannot: a different payload key, a damaged body, or bytes that were
     * never sealed by us.
     */
    public static function open(string $sealed): ?string
    {
        $key = self::key();

        if ($key === null || strlen($sealed) < self::MIN_BYTES) {
            return null;
        }

        $nonce = substr($sealed, 0, self::NONCE_BYTES);
        $tag = substr($sealed, -self::TAG_BYTES);
        $ciphertext = substr($sealed, self::NONCE_BYTES, -self::TAG_BYTES);

        if ($ciphertext === '') {
            return null;
        }

        $plain = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, self::AAD);

        if ($plain === false || trim($plain) === '') {
            return null;
        }

        return $plain;
    }

    /** Deterministic nonce: HMAC of the plaintext, truncated to 12 bytes. */
    private static function nonceFor(string $plain): string
    {
        $nonceKey = hash_hkdf('sha256', (string) self::key(), 32, self::NONCE_INFO);

        return substr(hash_hmac('sha256', $plain, $nonceKey, true), 0, self::NONCE_BYTES);
    }

    private static function key(): ?string
    {
        if (self::$key !== null) {
            return self::$key;
        }

        $material = self::material();

        if ($material === '') {
            return null;
        }

        self::$key = hash_hkdf('sha256', $material, 32, self::HKDF_INFO);

        return self::$key;
    }

    /** `QR_PAYLOAD_KEY` when set, otherwise the application key. */
    private static function material(): string
    {
        $configured = trim((string) config('qr.payload_key', ''));

        if ($configured !== '') {
            // `qr:keygen --payload` prints Laravel's `base64:` form. The marker
            // is understood here so the value can be pasted exactly as printed,
            // and so the same key written with or without it (the base64 body
            // alone) still derives the same seal - a mismatch between two
            // installs would make their stickers unreadable to each other.
            $material = str_starts_with($configured, 'base64:')
                ? substr($configured, 7)
                : $configured;

            $decoded = base64_decode($material, true);

            return $decoded !== false && strlen($decoded) >= 16 ? $decoded : $configured;
        }

        $appKey = (string) config('app.key', '');

        if ($appKey === '') {
            return '';
        }

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            return $decoded === false ? '' : $decoded;
        }

        return $appKey;
    }
}
