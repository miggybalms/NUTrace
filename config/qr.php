<?php

/**
 * Signing keys for asset QR codes.
 *
 * A sticker's payload is signed with a private key that never leaves the
 * server: the keyring below is the only place the app reads it from, and
 * nothing derived from it is ever put in a page, a response, or the payload
 * itself (see App\Support\QrSigner and App\Support\QrPayload).
 *
 * Keys are supplied as one env var so that rotating or retiring a key is a
 * single edit in the deployment platform's variables:
 *
 *     QR_SIGNING_KEYS="v2:ed25519:<base64 secret>,v1:ed25519-public:<base64 public>"
 *
 * Each entry is `<key id>:<algorithm>:<base64 key material>`. The key id is
 * printed inside every signed payload, which is how an old sticker keeps
 * verifying after the active key has been rotated: keep the retired key in the
 * list as `ed25519-public` / `ecdsa-p256-public` and it can still verify, but
 * can no longer sign.
 *
 * `php artisan qr:keygen` prints env-ready values for a new key.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Signing algorithm
    |--------------------------------------------------------------------------
    |
    | `auto` prefers Ed25519 through libsodium (ext-sodium) and falls back to
    | ECDSA P-256 through OpenSSL when libsodium is not loaded, so a deployment
    | that cannot provide sodium still signs and verifies. Pin it to a single
    | algorithm with QR_SIGNING_ALGORITHM once the platform's extension set is
    | settled. Keys carry their own algorithm, so changing this never
    | invalidates stickers that are already printed.
    |
    */
    'algorithm' => env('QR_SIGNING_ALGORITHM', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Active key
    |--------------------------------------------------------------------------
    |
    | The key id new stickers are signed with. Defaults to the first signing
    | key in QR_SIGNING_KEYS.
    |
    */
    'active_key_id' => env('QR_SIGNING_ACTIVE_KEY_ID'),

    /*
    |--------------------------------------------------------------------------
    | Keyring
    |--------------------------------------------------------------------------
    |
    | Comma-separated `<key id>:<algorithm>:<base64 material>` entries. Secret
    | entries can sign and verify; `-public` entries can only verify. When the
    | keyring is empty the app keeps printing plain asset codes and every scan
    | reports "verification unavailable" - never "fake".
    |
    */
    'keys' => env('QR_SIGNING_KEYS'),

    /*
    |--------------------------------------------------------------------------
    | Payload key
    |--------------------------------------------------------------------------
    |
    | A sticker's asset code is sealed (AES-256-GCM) before it is signed, so the
    | text on the paper is opaque: no code for a camera or a QR-generator
    | website to read. This is the key that opens it again.
    |
    | Set the same value on every deployment that has to read the same stickers
    | (for example your laptop and Railway), otherwise one cannot open the
    | other's payloads and those scans report "verification unavailable".
    | Generate one with `php artisan qr:keygen --payload`;
    |
    |     QR_PAYLOAD_KEY="<base64, 32 bytes>"
    |
    | Left empty, the key is derived from APP_KEY - fine for a single install,
    | but it ties your stickers to that one application key.
    |
    */
    'payload_key' => env('QR_PAYLOAD_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Centre mark
    |--------------------------------------------------------------------------
    |
    | Every sticker the app draws or prints carries the official NU Trace logo
    | in the middle of its QR code. When this is on, a scan whose centre mark
    | cannot be confirmed is refused before any asset information is shown, so
    | a code recreated on a QR-generator website - which has the payload but not
    | the mark - does not work in the app.
    |
    | Stickers printed *before* the mark existed cannot pass that check. They
    | stay scannable, with a warning, only while this is turned off by setting
    | QR_REQUIRE_SEAL=false on the deployment. Re-enable it once the office has
    | reprinted its labels: the logo is a visual marker, and the signature and
    | the registered asset are what actually prove a payload.
    |
    */
    'require_seal' => (bool) env('QR_REQUIRE_SEAL', true),

    /*
    |--------------------------------------------------------------------------
    | Centre mark artwork
    |--------------------------------------------------------------------------
    |
    | The official logo, kept inside the app so no QR, label or download depends
    | on an external host. Its navy plate is part of the artwork, which is what
    | makes the mark readable at sticker size.
    |
    */
    'seal_logo' => 'images/nu-trace-mark.jpg',

    /*
    |--------------------------------------------------------------------------
    | Tag key (NUTAG stickers)
    |--------------------------------------------------------------------------
    |
    | The HMAC key inside a NUTAG sticker's check value. The key never leaves
    | the server: the browser samples the cells, and the server alone decides
    | whether the token and check value in them are genuine.
    |
    | Set the same value on every deployment that has to read the same tags;
    | otherwise a sticker printed elsewhere reports "failed authenticity
    | check" here. Left empty, the QR payload key is used, and failing that a
    | value derived from APP_KEY.
    |
    |     QR_TAG_KEY="<long random string>"
    |
    */
    'tag_key' => env('QR_TAG_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Copy detection window
    |--------------------------------------------------------------------------
    |
    | Every verified sticker is recorded by a fingerprint of its payload. When
    | the same sticker shows up from a different account or network inside this
    | window, the scan is flagged as a possible copy: a reprinted sticker, a
    | photocopy, or the same sticker scanned by a second person.
    |
    | This is a warning, not a guarantee. A copy carries the same text as the
    | original, so no scan can tell the two apart on its own - only that one
    | sticker is being used from two places at once.
    |
    */
    'duplicate_window_minutes' => (int) env('QR_DUPLICATE_WINDOW_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Mobile resolution: /qr/resolve and /qr/session
    |--------------------------------------------------------------------------
    |
    | The phone has no Laravel session (it signs in against the users table with
    | a password RPC), so it cannot use the browser's cookie. It therefore asks
    | /qr/session once for a signed bearer token and presents that token when it
    | needs a sealed sticker opened.
    |
    | The token is HMAC-SHA256 under a key derived from APP_KEY, holds no secret
    | of its own, and cannot be minted without a valid password. Rotating
    | APP_KEY invalidates every token (users sign in again); the tokens are
    | stateless, so an individual one cannot be revoked early - that is the
    | trade-off for not adding a sessions table.
    |
    | 'limiter_store' is a cache store name, not a database table. The app's
    | default cache store is `database`, and this deployment has no `cache`
    | table, so a rate limiter on the default store would answer 500. `file` is
    | the one store that exists on every deployment without a migration; tests
    | set QR_LIMITER_STORE=array so counters cannot leak between tests.
    |
    */
    'resolve' => [

        'limiter_store' => env('QR_LIMITER_STORE', 'file'),

        // Per bearer token, per minute. A burst is a scanner retrying; a flood
        // is abuse, and a real inventory session stays far below this.
        'per_minute' => (int) env('QR_RESOLVE_PER_MINUTE', 60),

        // Per bearer token, per day. Bounded so one leaked token cannot fill the
        // scan ledger for weeks.
        'per_day' => (int) env('QR_RESOLVE_PER_DAY', 2000),

        // How long a phone stays signed in before it asks for a password again.
        'token_ttl_days' => (int) env('QR_MOBILE_TOKEN_DAYS', 30),

        // Password attempts per (email, address) per minute on /qr/session.
        'login_per_minute' => (int) env('QR_MOBILE_LOGIN_PER_MINUTE', 10),

    ],

];
