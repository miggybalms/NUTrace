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

];
