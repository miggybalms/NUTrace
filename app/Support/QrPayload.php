<?php

namespace App\Support;

/**
 * The text inside an asset's QR code.
 *
 * The current format is version 2, a sealed token:
 *
 *     NUT2-<key id>-<sealed body>-<signature>
 *
 * for example (fictitious asset and key):
 *
 *     NUT2-V1-3JQ7...2QKU-5TXM...8LWA
 *
 * Why this shape:
 *
 *  - The asset code is no longer in the open. The body is the code sealed with
 *    App\Support\QrSeal (AES-256-GCM, key held only by the server), so a phone
 *    camera, a generic scanner app or a QR-generator website shows an opaque
 *    blob - no asset code, no owner, nothing to retype into a generator and
 *    nothing to learn. Only a NU Trace server can turn the blob back into a
 *    code.
 *  - `NUT2` names the format and its version, so the older readable format
 *    (`NUT1-<code>-<key id>-<signature>`, printed before this change) is still
 *    read and reported honestly instead of being called fake.
 *  - The key id travels with the sticker, which is what lets a retired key
 *    still verify a sticker printed with it.
 *  - Everything is A-Z, 0-9 and `-`, so the code can use QR's *alphanumeric*
 *    mode: the same information in roughly half the modules of a byte-mode
 *    code, which keeps the sticker's 20mm square readable.
 *  - The signature is base32 (not base64) for exactly that reason: `-` and
 *    `_` from base64url would push the whole payload into byte mode.
 *
 * The signed message is the payload up to and including the body - the prefix,
 * key id and sealed body, and nothing else. Changing any of them breaks the
 * signature; the signature bytes themselves are not part of the message.
 *
 * A payload is never secret from a copy: whoever can read the blob can print
 * the blob, and that copy will verify like the original - a QR code is only
 * text drawn as squares. What signature and seal prove is that *this system*
 * minted that exact sticker; Support\QrScans is what notices the same sticker
 * turning up in two places, and revoking it is what makes every copy dead.
 */
class QrPayload
{
    /** Marker and version of the format this class writes. */
    public const PREFIX = 'NUT2';

    /** Version of the format this class writes. */
    public const VERSION = 2;

    /** The readable format printed before version 2, still verified. */
    public const PREVIOUS_PREFIX = 'NUT1';

    public const PREVIOUS_VERSION = 1;

    /**
     * Longest scanned text we will even parse.
     *
     * A v2 payload is around 190 characters, a v1 payload around 170; the cap
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
     * Build a readable version 1 payload. Kept because the format is still
     * verified, and because an install with no sealing key falls back to it.
     *
     * The signature is raw bytes and covers `message()`.
     */
    public static function build(string $code, string $keyId, string $signature): string
    {
        return self::PREVIOUS_PREFIX.'-'.AssetCode::normalize($code).'-'.strtoupper($keyId).'-'.self::encodeSignature($signature);
    }

    /**
     * The exact bytes a version 1 signature covers: the payload's first three
     * fields.
     */
    public static function message(string $code, string $keyId): string
    {
        return self::PREVIOUS_PREFIX.'-'.AssetCode::normalize($code).'-'.strtoupper($keyId);
    }

    /**
     * Seal one asset code into a version 2 body (base32), or null when this
     * install has no payload key.
     */
    public static function sealFor(?string $code): ?string
    {
        $code = AssetCode::normalize((string) $code);

        if ($code === '' || ! AssetCode::isValid($code)) {
            return null;
        }

        $sealed = QrSeal::seal($code);

        return $sealed === null ? null : self::encodeBytes($sealed);
    }

    /**
     * The exact bytes a version 2 signature covers, from a body already in
     * base32.
     */
    public static function messageFor(string $keyId, string $body): string
    {
        return self::PREFIX.'-'.strtoupper($keyId).'-'.strtoupper($body);
    }

    /**
     * Compose a version 2 payload from its parts. The signature is raw bytes.
     */
    public static function compose(string $keyId, string $body, string $signature): string
    {
        return self::messageFor($keyId, $body).'-'.self::encodeSignature($signature);
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
     *  - `signed`      a well-formed payload (signature not checked here)
     *  - `legacy`      a plain asset code - a sticker printed before signing
     *  - `unsupported` text that is not a NU Trace sticker at all, or a payload
     *                  version this build does not know
     *  - `malformed`   a payload of ours that cannot be read (damaged, cut off,
     *                  wrong charset, oversized)
     *
     * A version 2 payload whose body this server cannot open is still reported
     * as `signed` with a null code: the signature can be judged without the
     * key, and the caller says "unavailable" rather than blaming the sticker.
     *
     * @return array{type: string, version: int|null, code: string|null, key_id: string|null, signature: string|null, message: string|null, sealed: bool, detail: string}
     */
    public static function parse(string $scanned): array
    {
        $text = trim($scanned);
        $blank = ['version' => null, 'code' => null, 'key_id' => null, 'signature' => null, 'message' => null, 'sealed' => false];

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

        if ($version === self::PREVIOUS_VERSION) {
            return self::parseReadable($text) + $blank;
        }

        if ($version !== self::VERSION) {
            return [
                'type' => 'unsupported',
                'version' => $version,
                'detail' => 'This sticker uses payload version ' . $version . ', which this system does not know.',
            ] + $blank;
        }

        return self::parseSealed($text) + $blank;
    }

    /**
     * A readable version 1 payload: `NUT1-<code>-<key id>-<signature>`.
     */
    private static function parseReadable(string $text): array
    {
        $fields = explode('-', $text);

        // NUT1, at least one code field, key id, signature.
        if (count($fields) < 4 || strcasecmp($fields[0], self::PREVIOUS_PREFIX) !== 0) {
            return [
                'type' => 'malformed',
                'version' => self::PREVIOUS_VERSION,
                'detail' => 'The sticker payload is incomplete.',
            ];
        }

        $signatureEncoded = array_pop($fields);
        $keyId = strtoupper(array_pop($fields));
        $code = AssetCode::normalize(implode('-', array_slice($fields, 1)));

        if ($code === '' || ! AssetCode::isValid($code)) {
            return [
                'type' => 'malformed',
                'version' => self::PREVIOUS_VERSION,
                'detail' => 'The sticker payload does not carry a usable asset code.',
            ];
        }

        if (! preg_match(QrSigner::KEY_ID_PATTERN, $keyId)) {
            return [
                'type' => 'malformed',
                'version' => self::PREVIOUS_VERSION,
                'detail' => 'The sticker payload does not name a usable signing key.',
            ];
        }

        $signature = self::decodeSignature($signatureEncoded);

        if ($signature === null) {
            return [
                'type' => 'malformed',
                'version' => self::PREVIOUS_VERSION,
                'detail' => 'The sticker signature cannot be read.',
            ];
        }

        return [
            'type' => 'signed',
            'version' => self::PREVIOUS_VERSION,
            'code' => $code,
            'key_id' => $keyId,
            'signature' => $signature,
            'message' => self::message($code, $keyId),
            'sealed' => false,
            'detail' => 'Signed sticker.',
        ];
    }

    /**
     * A sealed version 2 payload: `NUT2-<key id>-<body>-<signature>`.
     *
     * Exactly four fields: the body and the signature are base32, so neither
     * contains a dash.
     */
    private static function parseSealed(string $text): array
    {
        $fields = explode('-', $text);

        if (count($fields) !== 4 || strcasecmp($fields[0], self::PREFIX) !== 0) {
            return [
                'type' => 'malformed',
                'version' => self::VERSION,
                'detail' => 'The sticker payload is incomplete.',
            ];
        }

        [$prefix, $keyId, $body, $signatureEncoded] = $fields;
        $keyId = strtoupper($keyId);

        if (! preg_match(QrSigner::KEY_ID_PATTERN, $keyId)) {
            return [
                'type' => 'malformed',
                'version' => self::VERSION,
                'detail' => 'The sticker payload does not name a usable signing key.',
            ];
        }

        $sealed = self::decodeBytes($body);

        if ($sealed === null) {
            return [
                'type' => 'malformed',
                'version' => self::VERSION,
                'detail' => 'The sealed sticker body cannot be read.',
            ];
        }

        $signature = self::decodeSignature($signatureEncoded);

        if ($signature === null) {
            return [
                'type' => 'malformed',
                'version' => self::VERSION,
                'detail' => 'The sticker signature cannot be read.',
            ];
        }

        // The code is only known when this server holds the payload key. A
        // sticker sealed elsewhere still parses, so the signature can be
        // judged and the scan reported as unavailable instead of fake.
        $code = QrSeal::open($sealed);
        $code = $code === null ? null : AssetCode::normalize($code);

        if ($code !== null && ! AssetCode::isValid($code)) {
            return [
                'type' => 'malformed',
                'version' => self::VERSION,
                'detail' => 'The sticker payload does not carry a usable asset code.',
            ];
        }

        return [
            'type' => 'signed',
            'version' => self::VERSION,
            'code' => $code,
            'key_id' => $keyId,
            'signature' => $signature,
            'message' => self::messageFor($keyId, $body),
            'sealed' => true,
            'detail' => $code === null
                ? 'Sealed sticker this server cannot open.'
                : 'Sealed sticker.',
        ];
    }

    /** Raw signature bytes as uppercase base32 (QR alphanumeric mode). */
    public static function encodeSignature(string $signature): string
    {
        return self::encodeBytes($signature);
    }

    /**
     * Decode a base32 signature, or null when it is not a canonical encoding
     * of an acceptable signature. Re-encoding what was decoded and comparing
     * keeps one signature from having several spellings, so a tampered payload
     * cannot hide behind a different-but-equivalent string.
     */
    public static function decodeSignature(string $encoded): ?string
    {
        $decoded = self::decodeBytes($encoded);

        if ($decoded === null) {
            return null;
        }

        if (strlen($decoded) < self::MIN_SIGNATURE_BYTES || strlen($decoded) > self::MAX_SIGNATURE_BYTES) {
            return null;
        }

        return $decoded;
    }

    /** Raw bytes as uppercase base32 (QR alphanumeric mode). */
    private static function encodeBytes(string $bytes): string
    {
        $binary = '';
        $bits = 0;
        $value = 0;

        foreach (str_split($bytes) as $byte) {
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
     * Decode canonical base32, or null. Leftover bits are only valid when
     * they are zero, which re-encoding catches.
     */
    private static function decodeBytes(string $encoded): ?string
    {
        $encoded = strtoupper(trim($encoded));

        if ($encoded === '' || $encoded === '0' || strlen($encoded) > 600) {
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

        if ($decoded === '' || self::encodeBytes($decoded) !== $encoded) {
            return null;
        }

        return $decoded;
    }
}
