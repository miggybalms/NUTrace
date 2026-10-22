<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The glue between an asset and its sticker.
 *
 * Everything that puts a code on a sticker - the registry form, the department
 * page, the registry row, the replacement flow, the label sheet, the stored
 * PNG - asks this class for the text, so a signed sticker can only be built one
 * way. When no signing key is configured (a development machine, or a
 * deployment that has not been given one yet) it falls back to the plain asset
 * code: the app keeps working, and every scan reports those stickers as
 * unsigned rather than verified.
 */
class AssetQr
{
    /** Payloads minted for one request, so a print sheet signs each code once. */
    private static array $cache = [];

    /**
     * The exact text to encode for an asset code: a signed payload when the
     * server can sign, otherwise the plain code.
     */
    public static function textFor(?string $code): string
    {
        $code = AssetCode::normalize((string) $code);

        if ($code === '') {
            return '';
        }

        return self::payloadFor($code) ?? $code;
    }

    /**
     * A signed payload for an asset code, or null when signing is unavailable
     * or the code cannot be canonicalised.
     *
     * Signing does not need the asset to exist: the code is the whole message,
     * and verification checks the database separately. That is what lets the
     * registration form preview the exact sticker the asset will get.
     */
    public static function payloadFor(?string $code): ?string
    {
        $code = AssetCode::normalize((string) $code);

        if ($code === '' || ! AssetCode::isValid($code)) {
            return null;
        }

        if (array_key_exists($code, self::$cache)) {
            return self::$cache[$code];
        }

        $signed = QrSigner::sign(QrPayload::message($code, (string) QrSigner::activeKeyId()));

        self::$cache[$code] = $signed === null
            ? null
            : QrPayload::build($code, $signed['key_id'], $signed['signature']);

        return self::$cache[$code];
    }

    /**
     * Forget the payloads minted so far in this process. Used after the keyring
     * changes (rotation, tests), so the next sticker is signed with the key that
     * is actually configured now.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /** Payloads for several codes, keyed by code. */
    public static function payloadsFor(array $codes): array
    {
        $payloads = [];

        foreach ($codes as $code) {
            $normalized = AssetCode::normalize((string) $code);
            $payloads[$normalized] = self::payloadFor($normalized);
        }

        return $payloads;
    }

    /**
     * One asset row, looked up by its code the same way every scanner already
     * resolves codes: trimmed, case-insensitively.
     *
     * Pullout-removed and disposed assets are included on purpose - a scan of a
     * sticker that is still on a shelf has to be able to say "this asset is
     * gone" instead of "no such asset".
     */
    public static function findByCode(string $code): ?object
    {
        $code = AssetCode::normalize($code);

        if ($code === '') {
            return null;
        }

        // Codes are matched case-insensitively, the way every scanner already
        // resolves them, so the needle is lower-cased to match the LOWER() on
        // the column.
        $needle = mb_strtolower($code);

        foreach (['LOWER("Asset_code") = ?', 'LOWER(Asset_code) = ?'] as $where) {
            try {
                $row = self::lookup($where, $needle);
            } catch (Throwable $e) {
                // A driver that will not take the quoted form; the next
                // spelling is tried instead of failing the scan. Reported, not
                // swallowed: a lookup that silently finds nothing would be read
                // as "unknown asset" and blame the sticker.
                report($e);

                continue;
            }

            if ($row) {
                return $row;
            }
        }

        return null;
    }

    private static function lookup(string $where, string $code): ?object
    {
        return DB::table('assets')
            ->leftJoin('users', 'assets.user_id', '=', 'users.id')
            ->leftJoin('employee_numbers', 'users.employee_numbers_id', '=', 'employee_numbers.id')
            // The department recorded on the asset wins; the holder's department
            // is the fallback for every asset registered before departments
            // were stored on the asset itself.
            ->leftJoin('departments', 'assets.department_id', '=', 'departments.id')
            ->leftJoin('departments as holder_departments', 'users.department_id', '=', 'holder_departments.id')
            ->leftJoin('asset_files', 'assets.id', '=', 'asset_files.Asset_id')
            ->whereRaw($where, [$code])
            ->select(
                'assets.id',
                'assets.user_id',
                'assets.Asset_code',
                'assets.Asset_name',
                'assets.Category',
                'assets.Condition',
                'assets.Lifecycle_Status',
                'assets.asset_location',
                'assets.qr_code_path',
                'assets.qr_revoked_at',
                'assets.property_id_no',
                'assets.completion_status',
                'employee_numbers.Full_Name as owner_name',
                'assets.accountable_name',
                DB::raw('COALESCE(departments.Name, holder_departments.Name) as department_name'),
                DB::raw('COALESCE(departments.id, holder_departments.id) as department_id'),
                DB::raw('MAX(asset_files.url) as image_url')
            )
            ->groupBy(
                'assets.id',
                'assets.user_id',
                'assets.Asset_code',
                'assets.Asset_name',
                'assets.Category',
                'assets.Condition',
                'assets.Lifecycle_Status',
                'assets.asset_location',
                'assets.qr_code_path',
                'assets.qr_revoked_at',
                'assets.property_id_no',
                'assets.completion_status',
                'employee_numbers.Full_Name',
                'assets.accountable_name',
                DB::raw('COALESCE(departments.Name, holder_departments.Name)'),
                DB::raw('COALESCE(departments.id, holder_departments.id)')
            )
            ->first();
    }

    /**
     * The asset information shown after a scan. Same keys the scan modal has
     * always read, so the interface did not have to change shape.
     */
    public static function summary(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'name' => $row->Asset_name ?? '-',
            'asset_code' => $row->Asset_code ?? '-',
            'status' => $row->Lifecycle_Status ?? '-',
            'category' => $row->Category ?? '-',
            'condition' => $row->Condition ?? '-',
            'department' => $row->department_name ?? '-',
            // An imported asset keeps the name the office wrote down even while
            // nobody has been assigned: "Unassigned" alone would lose it.
            'owner' => $row->owner_name ?? ($row->accountable_name ?? 'Unassigned'),
            'location' => $row->asset_location ?? '-',
            'property_id_no' => $row->property_id_no ?? null,
            'image_url' => Media::url($row->image_url ?? null),
            'revoked' => ! empty($row->qr_revoked_at),
            'revoked_at' => $row->qr_revoked_at,
        ];
    }

    /**
     * Void an asset's sticker: from now on its QR payload verifies as revoked,
     * including the printed sticker itself. Used when a sticker is lost or when
     * an asset's code has to stop being trusted.
     */
    public static function revoke(int $assetId, ?int $by = null): bool
    {
        $updated = DB::table('assets')
            ->where('id', $assetId)
            ->whereNull('qr_revoked_at')
            ->update(['qr_revoked_at' => now(), 'updated_at' => now()]);

        if ($updated > 0) {
            Log::notice('qr.sticker_revoked', ['asset_id' => $assetId, 'by_user_id' => $by]);
        }

        return $updated > 0;
    }

    /**
     * Mint the asset's sticker image and record both halves of it: the path of
     * the PNG on the media disk and the exact text it encodes.
     *
     * Every place that stores a QR image goes through here, so the recorded
     * payload can never drift from the image it describes - that record is what
     * `php artisan qr:resign` uses to tell which printed stickers still carry
     * an unsigned code.
     *
     * Returns the media path, or null when the image could not be produced.
     */
    public static function saveSticker(int $assetId, int $size = 400): ?string
    {
        $code = DB::table('assets')->where('id', $assetId)->value('Asset_code');
        $code = trim((string) $code);

        if ($code === '') {
            return null;
        }

        $text = self::textFor($code);
        $path = Media::generateQr($code, $size, $text);

        if ($path === null) {
            return null;
        }

        DB::table('assets')->where('id', $assetId)->update([
            'qr_code_path' => $path,
            'qr_payload' => $text,
            'updated_at' => now(),
        ]);

        return $path;
    }

    /**
     * Undo a revocation and mint a fresh sticker image for the asset.
     *
     * Returns the new media path, or null when the image could not be produced
     * (the revocation is cleared either way - the sticker is valid again).
     */
    public static function reissue(int $assetId): ?string
    {
        $code = DB::table('assets')->where('id', $assetId)->value('Asset_code');

        if ($code === null || trim((string) $code) === '') {
            return null;
        }

        DB::table('assets')->where('id', $assetId)->update([
            'qr_revoked_at' => null,
            'updated_at' => now(),
        ]);

        self::clearCache();

        $path = self::saveSticker($assetId);

        Log::notice('qr.sticker_reissued', ['asset_id' => $assetId, 'stored' => $path !== null]);

        return $path;
    }
}
