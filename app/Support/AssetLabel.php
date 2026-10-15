<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Everything one printed asset sticker needs, in one place.
 *
 * The sticker itself is drawn by partials/asset_label.blade.php. The department
 * asset page, the asset registry and the replacement flow all open the same
 * print sheet (admin.assets.labels) instead of each building its own copy of the
 * markup, so the label layout can only change in one direction from here.
 *
 * The code printed on the sticker is always the asset's own Asset_code and the
 * QR always encodes that same code — the identifier is never re-derived for the
 * layout's convenience.
 */
class AssetLabel
{
    /**
     * Bundled copy of the official NU seal. Resolved through Media::brand() so
     * a replacement uploaded to the "brand" folder of the media disk wins, and
     * the copy inside public/ is the fallback.
     */
    public const LOGO_FILE = 'nu-seal.png';

    /** Sticker geometry in millimetres — the sheet stylesheet uses these too. */
    public const WIDTH_MM = 90;

    public const HEIGHT_MM = 40;

    /** Longest value we will print in a cell before wrapping takes over. */
    private const MAX_CELL_CHARS = 60;

    /**
     * Build a label from a database row.
     *
     * Accepts either naming style the app uses: the aliased columns the asset
     * listings select (id/name/category/location) or the raw table columns.
     */
    public static function fromRow(object $row): array
    {
        $code = trim((string) ($row->id ?? $row->Asset_code ?? ''));

        return [
            'code'     => $code,
            'name'     => self::clean($row->name ?? $row->Asset_name ?? ''),
            'location' => self::clean($row->location ?? $row->asset_location ?? ''),
            'category' => self::clean($row->category ?? $row->Category ?? ''),
            'acquired' => self::formatAcquired($row->accusion_date ?? $row->date_acquired ?? null),
            'qr'       => Media::url($row->qr_code_path ?? null),
            // What the sticker's QR encodes when the browser has to draw it:
            // the asset's signed payload, or its plain code on an install
            // without signing keys.
            'qr_payload' => AssetQr::textFor($code),
            'logo'     => self::logoUrl(),
            'logo_fallback' => self::logoFallbackUrl(),
        ];
    }

    /**
     * Build a label from form values, for the print buttons that run before (or
     * without) an asset row — the registry form and the replacement flow.
     */
    public static function fromInput(array $input): array
    {
        $code = trim((string) ($input['code'] ?? ''));

        return [
            'code'     => $code,
            'name'     => self::clean($input['name'] ?? ''),
            'location' => self::clean($input['location'] ?? ''),
            'category' => self::clean($input['category'] ?? ''),
            'acquired' => self::formatAcquired($input['acquired'] ?? null),
            'qr'       => null, // no stored image yet; the sheet draws the code instead
            // Signing needs no database row, so a label previewed before the
            // asset exists carries the same payload it will carry once saved.
            'qr_payload' => AssetQr::textFor($code),
            'logo'     => self::logoUrl(),
            'logo_fallback' => self::logoFallbackUrl(),
        ];
    }

    /**
     * The date printed on the sticker, in the same M/D/YYYY form every label
     * printed so far has used.
     */
    public static function formatAcquired(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : $value;

        if ($value === null || $value === '' || $value === '—') {
            return '—';
        }

        try {
            return Carbon::parse($value)->format('n/j/Y');
        } catch (Throwable) {
            // An unparseable value is shown as-is rather than dropped.
            return self::clean((string) $value);
        }
    }

    /**
     * URL of the official NU seal, or null when no copy is available anywhere.
     *
     * Resolved once per request: a print sheet renders dozens of labels and the
     * storage check must not be repeated for each of them.
     */
    public static function logoUrl(): ?string
    {
        static $resolved = false;
        static $url = null;

        if ($resolved) {
            return $url;
        }

        $resolved = true;
        $url = self::logoAvailable() ? Media::brand(self::LOGO_FILE) : null;

        return $url;
    }

    /**
     * The bundled copy of the seal, used as the browser-side fallback when the
     * published copy is missing from the media disk.
     *
     * A deployment that points the media disk at object storage resolves the
     * sticker's logo to a bucket URL; if nothing has been uploaded to the
     * "brand" folder yet that URL 404s, and the sticker would print a broken
     * image. The fallback is the same one partials/logo.blade.php uses.
     */
    public static function logoFallbackUrl(): string
    {
        return Media::brandLocalUrl(self::LOGO_FILE);
    }

    /**
     * Is the official seal reachable — bundled in the app, or published to the
     * media disk's "brand" folder?
     *
     * The bundled copy is checked on disk so the sticker simply omits the logo
     * until the artwork is added; it never prints a broken image and never
     * substitutes some other picture.
     */
    private static function logoAvailable(): bool
    {
        if (is_file(public_path(Media::brandLocalPath(self::LOGO_FILE)))) {
            return true;
        }

        return Media::usesSupabase() && Media::exists(Media::brandPath(self::LOGO_FILE));
    }

    /**
     * Trim, collapse whitespace and cap a printed value so a stray long string
     * cannot break the sticker's fixed box.
     */
    private static function clean(mixed $value): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return mb_strimwidth($text, 0, self::MAX_CELL_CHARS, '…');
    }
}
