<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * What a scanned sticker actually is.
 *
 * One scan is resolved in a fixed order, and every outcome has a name:
 *
 *  1. read the payload (`QrPayload::parse`) - signed, plain code, unsupported
 *     foreign code, or a damaged payload of ours
 *  2. check the signature against the key named in the payload
 *  3. look the asset up in the database by the code - the encoded information
 *     is never trusted beyond the identifier
 *  4. check whether the sticker has been revoked
 *  5. check whether this viewer may see the asset at all
 *
 * The asset's live row is always the source of truth for anything shown: the
 * payload proves the code was minted here, not that the sticker is on the right
 * object. A copied genuine sticker still verifies - a QR code is only text
 * drawn as squares, so a copy carries the same bytes - which is why
 * Support\QrScans also records the scan and flags a sticker that turns up in
 * two places. A server with no signing key reports "unavailable", never
 * "fake"; the same is true of a sticker sealed with a payload key this
 * deployment does not hold.
 */
class QrVerification
{
    /** Signature matches, key is known, asset exists, sticker not revoked. */
    public const VALID = 'valid';

    /** A sticker printed before signing existed: a plain asset code. */
    public const LEGACY = 'legacy_unsigned';

    /** A payload of ours whose signature does not match - altered or forged. */
    public const INVALID_SIGNATURE = 'invalid_signature';

    /** Signed by a key id this system does not have in its keyring. */
    public const UNKNOWN_KEY = 'unknown_key';

    /** Signed/unsigned but no asset carries that code. */
    public const UNKNOWN_ASSET = 'unknown_asset';

    /** The asset's sticker was revoked by the office. */
    public const REVOKED = 'asset_revoked';

    /** One of our payloads that cannot be read at all. */
    public const MALFORMED = 'malformed';

    /** Not a NU Trace sticker, or a payload version we do not know. */
    public const UNSUPPORTED = 'unsupported';

    /** No signing keys on this server, so the signature could not be checked. */
    public const UNAVAILABLE = 'unavailable';

    /** Status => the words and colour the interface shows for it. */
    public const STATES = [
        self::VALID => [
            'label' => 'Verified sticker',
            'tone' => 'ok',
            'detail' => 'The signature is genuine and matches the asset in the database.',
        ],
        self::LEGACY => [
            'label' => 'Unsigned sticker',
            'tone' => 'warning',
            'detail' => 'Printed before signed codes were introduced. The code was matched against the database, but it carries no signature.',
        ],
        self::INVALID_SIGNATURE => [
            'label' => 'Failed authenticity check',
            'tone' => 'danger',
            'detail' => 'The signature does not match this payload. The sticker has been altered or was not produced by this system.',
        ],
        self::UNKNOWN_KEY => [
            'label' => 'Unknown signing key',
            'tone' => 'danger',
            'detail' => 'The sticker names a signing key this system does not hold. It is either forged or signed with a key that was retired without keeping its public half.',
        ],
        self::UNKNOWN_ASSET => [
            'label' => 'Asset not found',
            'tone' => 'warning',
            'detail' => 'The code does not belong to any asset in this database.',
        ],
        self::REVOKED => [
            'label' => 'Sticker revoked',
            'tone' => 'danger',
            'detail' => 'The Asset Management Office revoked this asset\'s sticker. Do not rely on it.',
        ],
        self::MALFORMED => [
            'label' => 'Damaged payload',
            'tone' => 'danger',
            'detail' => 'The sticker carries a NU Trace payload that cannot be read.',
        ],
        self::UNSUPPORTED => [
            'label' => 'Not a NU Trace code',
            'tone' => 'neutral',
            'detail' => 'This is not a NU Trace sticker.',
        ],
        self::UNAVAILABLE => [
            'label' => 'Verification unavailable',
            'tone' => 'neutral',
            'detail' => 'This server has no signing keys configured, so the signature could not be checked. The sticker is neither confirmed nor rejected.',
        ],
    ];

    /** Statuses that must not be acted on (an altered or revoked sticker). */
    public const BLOCKING = [
        self::INVALID_SIGNATURE,
        self::UNKNOWN_KEY,
        self::REVOKED,
        self::MALFORMED,
    ];

    /**
     * Resolve one scan.
     *
     * @return array{
     *     status: string, label: string, tone: string, detail: string,
     *     signed: bool, key_id: string|null, code: string|null,
     *     permitted: bool, asset: array|null
     * }
     */
    public static function verify(string $scanned, ?User $viewer = null): array
    {
        // A NUTAG sticker: a different carrier, exactly the same job. Delegating
        // here means every screen that already verifies scanned text - the
        // dashboard lookup, the pullout scanner, the request forms - accepts a
        // tag without a second code path, and no key leaves the server.
        if (NutTag::isTagText($scanned)) {
            return AssetTag::verify($scanned, $viewer);
        }

        $parsed = QrPayload::parse($scanned);
        $status = null;
        $detail = null;

        switch ($parsed['type']) {
            case 'malformed':
                $status = self::MALFORMED;
                $detail = $parsed['detail'];
                break;

            case 'unsupported':
                $status = self::UNSUPPORTED;
                $detail = $parsed['detail'];
                break;

            case 'legacy':
                $status = self::LEGACY;
                break;

            case 'signed':
                [$status, $detail] = self::checkSignature($parsed);

                // A sealed sticker whose code this server cannot open: the
                // signature may be perfectly good, but the asset behind it is
                // unreadable here (a different payload key). That is a server
                // limitation, not an accusation against the sticker.
                if ($status === self::VALID && $parsed['code'] === null) {
                    $status = self::UNAVAILABLE;
                    $detail = 'This sticker is sealed with a payload key this server does not hold, so its asset could not be read. It is neither confirmed nor rejected.';
                }
                break;
        }

        $row = null;

        // A sticker whose signature failed is not looked up: the identifier in
        // it cannot be trusted, so reporting an asset for it would dress up a
        // forgery with genuine details.
        if ($parsed['code'] !== null
            && ! in_array($status, [self::INVALID_SIGNATURE, self::UNKNOWN_KEY, self::MALFORMED, self::UNSUPPORTED], true)) {
            $row = AssetQr::findByCode($parsed['code']);

            if ($row === null) {
                $status = self::UNKNOWN_ASSET;
            } elseif (! empty($row->qr_revoked_at)) {
                $status = self::REVOKED;
            }
        }

        $state = self::STATES[$status] ?? self::STATES[self::MALFORMED];
        $permitted = $row !== null && self::permitted($row, $viewer);

        $result = [
            'status' => $status,
            'label' => $state['label'],
            'tone' => $state['tone'],
            'detail' => $detail ?? $state['detail'],
            'signed' => $parsed['type'] === 'signed',
            'sealed' => $parsed['sealed'],
            'key_id' => $parsed['key_id'],
            'code' => $parsed['code'],
            'permitted' => $permitted,
            'asset' => $permitted && $row !== null ? AssetQr::summary($row) : null,
        ];

        self::log($result, $viewer);

        // One sticker, one label on one object: if the same payload arrives
        // from somewhere else around the same time, it is worth checking the
        // object physically. The scan itself still resolves - this is a
        // warning, never a refusal.
        $result['duplicate'] = QrScans::record($scanned, $result, $viewer);

        return $result;
    }

    /**
     * The signature half of a scan.
     *
     * @param  array{code: string|null, key_id: string|null, signature: string|null, message: string|null}  $parsed
     * @return array{0: string, 1: string|null} status and (when it needs saying) detail
     */
    private static function checkSignature(array $parsed): array
    {
        // An install with no keyring at all cannot check any signature. That is
        // reported as "unavailable" - never as a fake sticker - and it is
        // deliberately different from a key id the ring does not hold, which is
        // the honest "this sticker names a key we do not have" case.
        if (QrSigner::keys() === []) {
            return [self::UNAVAILABLE, null];
        }

        $keyId = (string) $parsed['key_id'];

        if (! QrSigner::known($keyId)) {
            return [self::UNKNOWN_KEY, null];
        }

        // A retired key kept as `ed25519-public` verifies the stickers printed
        // with it, so rotation does not invalidate paper. A key whose
        // signature does not match its own message is the one case that is
        // reported as tampering, which is what it is.
        //
        // The message is what the payload's own prefix built - `NUT2-<key>-<body>`
        // for a sealed sticker - so the signature is judged on the exact text
        // that was signed, not on a code this server had to open first.
        $verified = QrSigner::verify(
            (string) $parsed['message'],
            (string) $parsed['signature'],
            $keyId
        );

        return match ($verified) {
            true => [self::VALID, null],
            false => [self::INVALID_SIGNATURE, null],
            null => [self::UNKNOWN_KEY, null],
        };
    }

    /**
     * May this viewer see the asset behind a sticker?
     *
     * Same rules the rest of the app already applies: the Asset Management
     * Office sees everything, a department head sees their own department, an
     * employee sees what is assigned to them. A sticker that verifies but
     * belongs elsewhere is reported as genuine with no asset details.
     */
    public static function permitted(object $row, ?User $viewer): bool
    {
        if ($viewer === null) {
            return true;
        }

        $role = (string) ($viewer->role ?? '');

        if (Access::isAssetOffice($viewer)) {
            return true;
        }

        if ($role === 'Department Head') {
            return (int) ($row->department_id ?? 0) === (int) $viewer->department_id;
        }

        return (int) ($row->user_id ?? 0) === (int) $viewer->id;
    }

    /**
     * Security events worth keeping, without any of the material that would
     * turn a log line into a secret: the payload, the signature and the key
     * material never reach the log.
     */
    private static function log(array $result, ?User $viewer): void
    {
        if (! in_array($result['status'], [self::INVALID_SIGNATURE, self::UNKNOWN_KEY, self::REVOKED, self::MALFORMED], true)) {
            return;
        }

        Log::warning('qr.verification_failed', [
            'status' => $result['status'],
            'key_id' => $result['key_id'],
            'code' => $result['code'],
            'asset_id' => $result['asset']['id'] ?? null,
            'viewer_id' => $viewer?->id,
        ]);
    }
}
