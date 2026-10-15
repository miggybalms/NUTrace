<?php

namespace App\Support;

/**
 * The text inside an asset's QR code.
 *
 * Version 1 of the payload is a compact, canonical, uppercased token:
 *
 *     NUT1-<asset code>-<key id>-<signature>
 *
 * for example (fictitious asset and key):
 *
 *     NUT1-AST-0001-V1-3JQ7...2QKU
 *
 * Why this shape:
 *
 *  - `NUT1` names the format and its version, so a future format can be
 *    recognised and refused honestly instead of being reported as fake.
 *  - The asset code is the authoritative identifier: verification looks the
 *    asset up by that code rather than trusting anything else in the payload.
 *  - The key id travels with the sticker, which is what lets a retired key
 *    still verify a sticker printed with it.
 *  - Everything is A-Z, 0-9 and `-`, so the code can use QR's *alphanumeric*
 *    mode: the same information in roughly half the modules of a byte-mode
 *    code, which keeps the sticker's 20mm square readable.
 *  - The signature is base32 (not base64) for exactly that reason: `-` and
 *    `_` from base64url would push the whole payload into byte mode.
 *
 * The signed message is `NUT1-<asset code>-<key id>` - the prefix, code and
 * key id, and nothing else. Changing any of them breaks the signature; the
 * signature bytes themselves are not part of the message.
 *
 * A payload is never secret: anyone can read it off the sticker. What it
 * proves is only that *this system* minted that exact code, not who is
 * holding the sticker (see App\Support\QrVerification for that distinction).
 */
class QrPayload
{
    /** Marker and version of the current format. */
    public const PREFIX = 'NUT1';

    /** Version of the format this class writes. */
    public const VERSION = 1;

    /**
     * Longest scanned text we will even parse.
     *
     * A v1 payload with a 50-character code is around 170 characters; the cap
     * is generous but finite, so a QR full of garbage cannot make the server
     * do unbounded work or fill the log with megabytes.
     */
    public const MAX_TEXT_LENGTH = 512;

    /** Base32 (RFC 4648, no padding) - QR alphanumeric mode, case-free. */
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** `NUT` + up to two version digits + separator. */
    private const FAMILY_PATTERN = '/^NUT([0-9]{1,2})[-:]/i';

    /** Raw signature lengths we accept, in bytes (Ed25519 is 64, DER P-256 ~70). */
    private const MIN_SIGNATURE_BYTES = 32;

    private const MAX_SIGNATURE_BYTES = 160;

    /**
     * Build a payload from its three parts. The signature is raw bytes.
     */
    public static function build(string $code, string $keyId, string $signature): string
    {
        return self::PREFIX.'-'.AssetCode::normalize($code).'-'.strtoupper($keyId).'-'.self::encodeSignature($signature);
    }

    /**
     * The exact bytes a signature covers: the payload's first three fields.
     */
    public static function message(string $code, string $keyId): string
    {
        return self::PREFIX.'-'.AssetCode::normalize($code).'-'.strtoupper($keyId);
    }

    /**
     * Does this text claim to be one of our payloads (any version)?
     */
    public static function isPayload(string $text): bool
    {
        return (bool) preg_match(self::FAMILY_PATTERN, ltrim($text));
    }

    /**
     * Read scanned text.
     *
     * Returns one of four outcomes, and never throws:
     *
     *  - `signed`      a well-formed v1 payload (signature not checked here)
     *  - `legacy`      a plain asset code - a sticker printed before signing
     *  - `unsupported` text that is not a NU Trace sticker at all, or a payload
     *                  version this build does not know
     *  - `malformed`   a payload of ours that cannot be read (damaged, cut off,
     *                  wrong charset, oversized)
     *
     * @return array{type: string, version: int|null, code: string|null, key_id: string|null, signature: string|null, detail: string}
     */
    public static function parse(string $scanned): array
    {
        $text = trim($scanned);
        $blank = ['version' => null, 'code' => null, 'key_id' => null, 'signature' => null];

        if ($text === '') {
            return ['type' => 'malformed', 'detail' => 'Nothing was scanned.'] + $blank;
        }

        if (strlen($text) > self::MAX_TEXT_LENGTH) {
            return ['type' => 'malformed', 'detail' => 'The scanned text is too long to be a NU Trace sticker (' . strlen($text) . ' characters).'] + $blank;
        }

        // Not one of ours: it still has to look like an asset code to be
        // treated as a pre-signature sticker. Anything else (a poster, a
        // website, a shipping label) is simply not a NU Trace code, and must
        // never be reported as a fake one.
        if (! self::isPayload($text)) {
            if (AssetCode::isValid($text)) {
                // Keys are written before `+ $blank`, never after: `$blank`
                // already carries a null `code`, and a union with it first would
                // silently drop the very code this whole file exists to read.
                return [
                    'type' => 'legacy',
                    'code' => AssetCode::normalize($text),
                    'detail' => 'Plain asset code, no signature.',
                ] + $blank;
            }

            return ['type' => 'unsupported', 'detail' => 'This is not a NU Trace sticker.'] + $blank;
        }

        $matches = [];
        preg_match(self::FAMILY_PATTERN, $text, $matches);
        $version = (int) ($matches[1] ?? 0);

        if ($version !== self::VERSION) {
            return [
                'type' => 'unsupported',
                'version' => $version,
                'detail' => 'This sticker uses payload version ' . $version . ', which this system does not know.',
            ] + $blank;
        }

        $fields = explode('-', $text);

        // NUT1, at least one code field, key id, signature.
        if (count($fields) < 4 || strcasecmp($fields[0], self::PREFIX) !== 0) {
            return [
                'type' => 'malformed',
                'version' => $version,
                'detail' => 'The sticker payload is incomplete.',
            ] + $blank;
        }

        $signatureEncoded = array_pop($fields);
        $keyId = strtoupper(array_pop($fields));
        $code = AssetCode::normalize(implode('-', array_slice($fields, 1)));

        if ($code === '' || ! AssetCode::isValid($code)) {
            return [
                'type' => 'malformed',
                'version' => $version,
                'detail' => 'The sticker payload does not carry a usable asset code.',
            ] + $blank;
        }

        if (! preg_match(QrSigner::KEY_ID_PATTERN, $keyId)) {
            return [
                'type' => 'malformed',
                'version' => $version,
                'detail' => 'The sticker payload does not name a usable signing key.',
            ] + $blank;
        }

        $signature = self::decodeSignature($signatureEncoded);

        if ($signature === null) {
            return [
                'type' => 'malformed',
                'version' => $version,
                'detail' => 'The sticker signature cannot be read.',
            ] + $blank;
        }

        return [
            'type' => 'signed',
            'version' => $version,
            'code' => $code,
            'key_id' => $keyId,
            'signature' => $signature,
            'detail' => 'Signed sticker.',
        ];
    }

    /**
     * Raw signature bytes as uppercase base32 (QR alphanumeric mode).
     */
    public static function encodeSignature(string $signature): string
    {
        $binary = '';
        $bits = 0;
        $value = 0;

        foreach (str_split($signature) as $byte) {
            $value = ($value << 8) | ord($byte);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $binary .= self::BASE32[($value >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $binary .= self::BASE32[($value << (5 - $bits)) & 0x1F];
        }

        return $binary;
    }

    /**
     * Decode a base32 signature, or null when it is not a canonical encoding
     * of an acceptable signature. Re-encoding what was decoded and comparing
     * keeps one signature from having several spellings, so a tampered payload
     * cannot hide behind a different-but-equivalent string.
     */
    public static function decodeSignature(string $encoded): ?string
    {
        $encoded = strtoupper(trim($encoded));

        if ($encoded === '' || $encoded === '0' || strlen($encoded) > 260) {
            return null;
        }

        $decoded = '';
        $bits = 0;
        $value = 0;

        foreach (str_split($encoded) as $character) {
            $index = strpos(self::BASE32, $character);

            if ($index === false) {
                return null;
            }

            $value = ($value << 5) | $index;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($value >> $bits) & 0xFF);
            }
        }

        // Leftover bits are only valid when they are zero, which is exactly
        // what re-encoding catches.
        if ($decoded === '' || self::encodeSignature($decoded) !== $encoded) {
            return null;
        }

        if (strlen($decoded) < self::MIN_SIGNATURE_BYTES || strlen($decoded) > self::MAX_SIGNATURE_BYTES) {
            return null;
        }

        return $decoded;
    }
}
