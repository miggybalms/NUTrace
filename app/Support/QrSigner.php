<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The server's signing keys for asset QR codes.
 *
 * Stickers are signed with Ed25519 (libsodium, PHP's bundled `sodium`
 * extension) and verified with the matching public key. When libsodium is not
 * loaded the signer falls back to ECDSA P-256 through OpenSSL, which every PHP
 * build ships - same payload shape, same keyring, different signature bytes.
 *
 * The private key is read from configuration (an environment variable on the
 * deployment platform) and is used here and nowhere else. No part of this
 * class is reachable from a page: the only thing that ever leaves the server is
 * the 64-byte signature inside the QR payload.
 *
 * Verifying with a retired key matters as much as signing with the active one.
 * A key entry may therefore be `ed25519` / `ecdsa-p256` (signing, secret
 * material) or `ed25519-public` / `ecdsa-p256-public` (verifying only, public
 * material), so rotation can demote the old key instead of keeping its secret
 * around.
 */
class QrSigner
{
    /** Signing algorithms: hold a secret, can sign and verify. */
    public const ED25519 = 'ed25519';

    public const ECDSA_P256 = 'ecdsa-p256';

    /** Verifying-only algorithms: hold a public key, can only verify. */
    public const ED25519_PUBLIC = 'ed25519-public';

    public const ECDSA_P256_PUBLIC = 'ecdsa-p256-public';

    /** Every algorithm the keyring understands. */
    public const ALGORITHMS = [
        self::ED25519,
        self::ECDSA_P256,
        self::ED25519_PUBLIC,
        self::ECDSA_P256_PUBLIC,
    ];

    /** A key id is printed in the payload, so it stays short and simple. */
    public const KEY_ID_PATTERN = '/^[A-Z0-9]{1,16}$/';

    /**
     * The parsed keyring, resolved once per request: `id => ['algorithm',
     * 'signing', 'material']`.
     *
     * @var array<string, array{algorithm: string, signing: bool, material: string}>|null
     */
    private static ?array $keys = null;

    /**
     * Is a given algorithm usable on this PHP build?
     */
    public static function supports(string $algorithm): bool
    {
        return match ($algorithm) {
            self::ED25519, self::ED25519_PUBLIC => function_exists('sodium_crypto_sign_detached'),
            self::ECDSA_P256, self::ECDSA_P256_PUBLIC => function_exists('openssl_sign'),
            default => false,
        };
    }

    /**
     * The algorithm this install signs with.
     *
     * Configured explicitly, or picked automatically: Ed25519 first, OpenSSL
     * ECDSA P-256 when libsodium is missing. Null when neither is available,
     * which is the "verification unavailable" case rather than a failure.
     */
    public static function algorithm(): ?string
    {
        $configured = strtolower(trim((string) config('qr.algorithm', 'auto')));

        if ($configured !== '' && $configured !== 'auto') {
            return self::supports($configured) ? $configured : null;
        }

        foreach ([self::ED25519, self::ECDSA_P256] as $candidate) {
            if (self::supports($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Forget the parsed keyring, so the next caller reads the configuration
     * again. Used by tests and by anything that rotates a key in-process.
     */
    public static function flush(): void
    {
        self::$keys = null;
    }

    /**
     * The parsed keyring. Malformed entries are skipped (and reported to the
     * log) rather than taken down the whole keyring: a typo in one retired key
     * must not stop new stickers from being signed.
     *
     * @return array<string, array{algorithm: string, signing: bool, material: string}>
     */
    public static function keys(): array
    {
        if (self::$keys !== null) {
            return self::$keys;
        }

        self::$keys = [];
        $raw = (string) config('qr.keys', '');

        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            // <key id>:<algorithm>:<base64 material>
            $parts = explode(':', $entry, 3);

            if (count($parts) !== 3) {
                self::reject($entry, 'expected "<id>:<algorithm>:<base64 key material>"');

                continue;
            }

            [$id, $algorithm, $material] = array_map('trim', $parts);
            $id = strtoupper($id);
            $algorithm = strtolower($algorithm);

            if (! preg_match(self::KEY_ID_PATTERN, $id)) {
                self::reject($id, 'key id must be 1-16 characters of A-Z and 0-9');

                continue;
            }

            if (! in_array($algorithm, self::ALGORITHMS, true)) {
                self::reject($id, 'unknown algorithm "'.$algorithm.'"');

                continue;
            }

            if (! self::supports($algorithm)) {
                self::reject($id, 'this PHP build cannot use '.$algorithm);

                continue;
            }

            $decoded = base64_decode($material, true);

            if ($decoded === false || strlen($decoded) < 16) {
                self::reject($id, 'key material is not valid base64');

                continue;
            }

            self::$keys[$id] = [
                'algorithm' => $algorithm,
                'signing' => in_array($algorithm, [self::ED25519, self::ECDSA_P256], true),
                'material' => $material,
            ];
        }

        return self::$keys;
    }

    /**
     * Is a key with this id in the keyring - active, retired, or verifying
     * only?
     */
    public static function known(string $keyId): bool
    {
        return isset(self::keys()[strtoupper(trim($keyId))]);
    }

    /**
     * The key new stickers are signed with: the configured active key when it
     * is a signing key, otherwise the first signing key in the ring.
     */
    public static function activeKeyId(): ?string
    {
        $keys = self::keys();
        $configured = strtoupper(trim((string) config('qr.active_key_id', '')));

        if ($configured !== '' && isset($keys[$configured]) && $keys[$configured]['signing']) {
            return $configured;
        }

        foreach ($keys as $id => $key) {
            if ($key['signing']) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Is this install able to sign? False on a deployment without a keyring
     * (the app then prints plain asset codes and reports signatures as
     * unavailable).
     */
    public static function isConfigured(): bool
    {
        return self::activeKeyId() !== null && self::algorithm() !== null;
    }

    /**
     * Sign a canonical payload message.
     *
     * @return array{key_id: string, algorithm: string, signature: string}|null
     *         Raw signature bytes, or null when no key can sign.
     */
    public static function sign(string $message): ?array
    {
        $keyId = self::activeKeyId();

        if ($keyId === null) {
            return null;
        }

        $key = self::keys()[$keyId];
        $signature = self::rawSign($key['algorithm'], $key['material'], $message);

        if ($signature === null) {
            return null;
        }

        return [
            'key_id' => $keyId,
            'algorithm' => $key['algorithm'],
            'signature' => $signature,
        ];
    }

    /**
     * Verify a signature against the key named in the payload.
     *
     * Returns null - not false - when the key is unknown or unusable, so a
     * caller can tell "this sticker was not signed by any key we know" from
     * "this signature does not match that key".
     */
    public static function verify(string $message, string $signature, string $keyId): ?bool
    {
        $key = self::keys()[strtoupper(trim($keyId))] ?? null;

        if ($key === null || $signature === '') {
            return null;
        }

        return self::rawVerify($key['algorithm'], $key['material'], $message, $signature);
    }

    /**
     * Create a key for the keyring.
     *
     * @return array{algorithm: string, id: string, secret: string, public: string, env_keys: string, env_active: string}
     *         `secret` / `public` are base64 key material; `env_keys` /
     *         `env_active` are the two environment lines to paste into the
     *         deployment platform.
     */
    public static function generate(string $algorithm = self::ED25519, string $id = 'v1'): array
    {
        $algorithm = strtolower(trim($algorithm));
        $id = strtoupper(trim($id)) ?: 'v1';

        if (! in_array($algorithm, [self::ED25519, self::ECDSA_P256], true)) {
            throw new \InvalidArgumentException('qr:keygen signs with ed25519 or ecdsa-p256, not '.$algorithm.'.');
        }

        if (! self::supports($algorithm)) {
            throw new \RuntimeException('This PHP build cannot use '.$algorithm.'.');
        }

        if (! preg_match(self::KEY_ID_PATTERN, $id)) {
            throw new \InvalidArgumentException('Key id must be 1-16 characters of A-Z and 0-9.');
        }

        [$secret, $public] = $algorithm === self::ED25519
            ? self::generateEd25519()
            : self::generateEcdsaP256();

        $publicAlgorithm = $algorithm.'-public';

        return [
            'algorithm' => $algorithm,
            'id' => $id,
            'secret' => $secret,
            'public' => $public,
            'env_keys' => 'QR_SIGNING_KEYS="'.self::envKeysValue($id, $algorithm, $secret).'"',
            'env_active' => 'QR_SIGNING_ACTIVE_KEY_ID='.$id,
        ];
    }

    /**
     * The `QR_SIGNING_KEYS` value for a fresh key on its own.
     */
    private static function envKeysValue(string $id, string $algorithm, string $secret): string
    {
        return $id.':'.$algorithm.':'.$secret;
    }

    public static function publicAlgorithm(string $algorithm): string
    {
        return in_array($algorithm, [self::ED25519, self::ECDSA_P256], true)
            ? $algorithm.'-public'
            : $algorithm;
    }

    /**
     * The verifying half of every key in the ring, in a shape a client that is
     * not PHP can check: `id => ['algorithm' => 'ed25519'|'ecdsa-p256',
     * 'public' => base64]`.
     *
     * This exists for the mobile app, which scans stickers offline of any PHP
     * session and therefore cannot call `/qr/verify`. It gets only what a
     * verifier needs - never a seed, never anything that can sign:
     *
     *  - Ed25519 entries carry the 32-byte public key.
     *  - ECDSA P-256 entries carry the uncompressed point `04 || X || Y`, so the
     *    client does not need an X.509 parser to make sense of an SPKI blob.
     *
     * A key whose public half cannot be derived (bad material, missing
     * extension) is left out rather than reported as usable: a sticker signed
     * with it must fall through to "unknown signing key" on the client, not
     * "failed authenticity check".
     *
     * @return array<string, array{algorithm: string, public: string}>
     */
    public static function publicKeyring(): array
    {
        $ring = [];

        foreach (self::keys() as $id => $key) {
            $material = self::publicMaterial($key['algorithm'], $key['material']);

            if ($material === null) {
                continue;
            }

            $ring[$id] = [
                'algorithm' => str_replace('-public', '', $key['algorithm']),
                'public' => base64_encode($material),
            ];
        }

        return $ring;
    }

    /** Raw public material for one key entry, or null when it cannot be read. */
    private static function publicMaterial(string $algorithm, string $material): ?string
    {
        try {
            return match ($algorithm) {
                self::ED25519_PUBLIC => self::ed25519PublicBytes($material),
                self::ED25519 => self::ed25519PublicFromSeed($material),
                self::ECDSA_P256_PUBLIC => self::ecdsaPoint(openssl_pkey_get_public(self::pem('PUBLIC KEY', $material))),
                self::ECDSA_P256 => self::ecdsaPoint(self::privateKey($material)),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function ed25519PublicBytes(string $material): ?string
    {
        $public = self::decode($material);

        return strlen($public) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $public : null;
    }

    private static function ed25519PublicFromSeed(string $material): ?string
    {
        $seed = self::decode($material);

        if (strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            return null;
        }

        return sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed));
    }

    /**
     * `04 || X || Y` from an OpenSSL key, or null when it is not a usable EC key.
     *
     * @param  \OpenSSLAsymmetricKey|false  $key
     */
    private static function ecdsaPoint($key): ?string
    {
        if ($key === false) {
            return null;
        }

        $details = openssl_pkey_get_details($key);
        $x = (string) ($details['ec']['x'] ?? '');
        $y = (string) ($details['ec']['y'] ?? '');

        if ($x === '' || $y === '') {
            return null;
        }

        return "\x04"
            . str_pad($x, 32, "\x00", STR_PAD_LEFT)
            . str_pad($y, 32, "\x00", STR_PAD_LEFT);
    }

    /** Report a keyring entry that cannot be used, without the material. */
    private static function reject(string $id, string $reason): void
    {
        Log::warning('QR keyring entry ignored: '.$reason, ['key_id' => $id]);
    }

    /* ── Ed25519 (libsodium) ────────────────────────────────────────────── */

    /** @return array{0: string, 1: string} base64 seed, base64 public key */
    private static function generateEd25519(): array
    {
        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keypair = sodium_crypto_sign_seed_keypair($seed);

        return [
            base64_encode($seed),
            base64_encode(sodium_crypto_sign_publickey($keypair)),
        ];
    }

    private static function rawSign(string $algorithm, string $material, string $message): ?string
    {
        try {
            return match ($algorithm) {
                self::ED25519 => self::ed25519Sign($material, $message),
                self::ECDSA_P256 => self::ecdsaSign($material, $message),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function rawVerify(string $algorithm, string $material, string $message, string $signature): ?bool
    {
        try {
            return match ($algorithm) {
                self::ED25519, self::ED25519_PUBLIC => self::ed25519Verify($algorithm, $material, $message, $signature),
                self::ECDSA_P256, self::ECDSA_P256_PUBLIC => self::ecdsaVerify($algorithm, $material, $message, $signature),
                default => null,
            };
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function ed25519Sign(string $seedBase64, string $message): ?string
    {
        // The keyring stores the 32-byte seed; libsodium signs with the 64-byte
        // secret key (seed + public half), which is what the keypair holds.
        $keypair = sodium_crypto_sign_seed_keypair(self::decode($seedBase64));

        return sodium_crypto_sign_detached($message, sodium_crypto_sign_secretkey($keypair));
    }

    private static function ed25519Verify(string $algorithm, string $material, string $message, string $signature): ?bool
    {
        $public = $algorithm === self::ED25519_PUBLIC
            ? self::decode($material)
            // A seeding key: derive the public half from the seed itself.
            : sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(self::decode($material)));

        if (strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $message, $public);
    }

    /* ── ECDSA P-256 (OpenSSL) ──────────────────────────────────────────── */

    /** @return array{0: string, 1: string} base64 PKCS#8 private key, base64 SPKI public key */
    private static function generateEcdsaP256(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        if ($key === false) {
            throw new \RuntimeException('OpenSSL could not create a P-256 key: '.openssl_error_string());
        }

        openssl_pkey_export($key, $privatePem);
        $details = openssl_pkey_get_details($key);

        return [
            self::pemToBase64($privatePem),
            self::pemToBase64((string) ($details['key'] ?? '')),
        ];
    }

    private static function ecdsaSign(string $privateBase64, string $message): ?string
    {
        $key = self::privateKey($privateBase64);

        if ($key === false) {
            return null;
        }

        $signature = '';

        return openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256) ? $signature : null;
    }

    private static function ecdsaVerify(string $algorithm, string $material, string $message, string $signature): ?bool
    {
        $publicPem = $algorithm === self::ECDSA_P256_PUBLIC
            ? self::publicKeyPem($material)
            : self::publicKeyPemFromPrivate($material);

        if ($publicPem === null) {
            return false;
        }

        $result = openssl_verify($message, $signature, $publicPem, OPENSSL_ALGO_SHA256);

        return $result === 1 ? true : ($result === 0 ? false : null);
    }

    private static function privateKey(string $privateBase64)
    {
        return openssl_pkey_get_private(self::pem('PRIVATE KEY', $privateBase64));
    }

    private static function publicKeyPemFromPrivate(string $privateBase64): ?string
    {
        $key = self::privateKey($privateBase64);

        if ($key === false) {
            return null;
        }

        $details = openssl_pkey_get_details($key);

        return isset($details['key']) ? (string) $details['key'] : null;
    }

    private static function publicKeyPem(string $publicBase64): string
    {
        return self::pem('PUBLIC KEY', $publicBase64);
    }

    /** Wrap base64 DER in the PEM armour OpenSSL expects. */
    private static function pem(string $label, string $base64): string
    {
        return '-----BEGIN '.$label."-----\n"
            .chunk_split(trim($base64), 64, "\n")
            .'-----END '.$label."-----\n";
    }

    /** PEM (public or private) to the single-line base64 DER stored in env. */
    private static function pemToBase64(string $pem): string
    {
        $body = preg_replace('/-----[^-]+-----|\s+/', '', $pem) ?? '';

        return $body;
    }

    /** Strict base64 decode; malformed material becomes an empty string. */
    private static function decode(string $base64): string
    {
        return base64_decode(trim($base64), true) ?: '';
    }
}
