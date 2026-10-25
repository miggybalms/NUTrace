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

    /**
     * Default sticker geometry in millimetres, used when config/asset_label.php
     * has no value (or a nonsensical one).
     *
     * 50 × 25mm is the die-cut label loaded in the Zebra ZD230: the driver's
     * 1.969" × 0.984" media size. The sheet prints one sticker per page at
     * exactly this size, and the artwork is drawn 1mm inside those edges (see
     * the stylesheet), because the printer cannot put ink on the die line.
     *
     * These are only the fallbacks — the live values come from the config, so a
     * different roll can be set in .env without editing a view.
     */
    public const DEFAULT_WIDTH_MM = 50.0;

    public const DEFAULT_HEIGHT_MM = 25.0;

    /** Safe distance between the printed border and the label edge, in mm. */
    public const DEFAULT_INSET_MM = 1.0;

    /** Side of the QR square, quiet zone included, in mm. */
    public const DEFAULT_QR_MM = 21.0;

    /** Longest value we will print in a cell before wrapping takes over. */
    private const MAX_CELL_CHARS = 60;

    /**
     * Session key holding the office's label print settings for this browser.
     *
     * Kept per session on purpose: the sheet is printed from one workstation,
     * and a size that fits this office's roll must not silently change every
     * other administrator's printout.
     */
    public const SETTINGS_KEY = 'asset_label.print_settings';

    /** Sticker bounds the print settings panel accepts, in millimetres. */
    public const MIN_WIDTH_MM = 30.0;

    public const MAX_WIDTH_MM = 200.0;

    public const MIN_HEIGHT_MM = 12.0;

    public const MAX_HEIGHT_MM = 120.0;

    /**
     * A sealed payload (NUT2, ~190 characters) is 49 modules across; with the
     * 4-module quiet zone on each side the printed square is 57 modules wide.
     * Sizing is computed from this, so a sticker too small to hold a readable
     * code is refused instead of printed unscannable.
     */
    private const MODULES_ACROSS = 57;

    /** A 203 dpi thermal head lays down 8 dots per millimetre. */
    private const DOTS_PER_MM = 8.0;

    /**
     * Below this many printer dots per module the grey edges of neighbouring
     * modules merge on thermal stock and a reader stops seeing the code.
     */
    private const MIN_DOTS_PER_MODULE = 2.5;

    /** Width the printed text column needs beside the QR, in millimetres. */
    private const TEXT_COLUMN_MM = 12.0;

    /**
     * The settings in force for this print: the office's saved choices when
     * there are any, otherwise the configured label stock.
     *
     * Everything is clamped here, so a saved value that cannot be printed (a
     * sticker too small to hold a readable code, for instance) can never reach
     * the page: `safeSettings()` clamps, and this is the only way the view reads
     * a size.
     *
     * @return array{width_mm: float, height_mm: float, inset_mm: float, qr_mm: float}
     */
    public static function settings(): array
    {
        $saved = self::savedSettings();

        return self::resolve([
            'width_mm'  => $saved['width_mm'] ?? null,
            'height_mm' => $saved['height_mm'] ?? null,
            'qr_mm'     => $saved['qr_mm'] ?? null,
        ])['settings'];
    }

    /** Sticker width in mm: the office's choice, else the configured stock. */
    public static function widthMm(): float
    {
        return self::settings()['width_mm'];
    }

    /** Sticker height in mm: the office's choice, else the configured stock. */
    public static function heightMm(): float
    {
        return self::settings()['height_mm'];
    }

    /**
     * Margin kept inside the die line, in mm. Never more than a quarter of the
     * sticker, so a bad value cannot leave no room for the artwork.
     */
    public static function insetMm(): float
    {
        return self::settings()['inset_mm'];
    }

    /**
     * Width of the QR square in mm, quiet zone included.
     *
     * Clamped to the room the sticker has: a QR taller than the label is
     * clipped by the label box, and a clipped finder pattern never scans.
     */
    public static function qrMm(): float
    {
        return self::settings()['qr_mm'];
    }

    /**
     * The smallest QR worth printing: enough dots per module to survive a
     * thermal print head, in millimetres.
     */
    public static function minimumQrMm(): float
    {
        return ceil(self::MODULES_ACROSS * self::MIN_DOTS_PER_MODULE / self::DOTS_PER_MM);
    }

    /**
     * The narrowest sticker that still carries a readable QR beside its text.
     */
    public static function minimumStickerWidthMm(): float
    {
        $inset = self::configuredMm('inset_mm', self::DEFAULT_INSET_MM, 0, 10);

        return round(max(self::MIN_WIDTH_MM, self::minimumQrMm() + 2 * $inset + self::TEXT_COLUMN_MM), 1);
    }

    /**
     * The shortest sticker that still carries a readable QR.
     */
    public static function minimumStickerHeightMm(): float
    {
        $inset = self::configuredMm('inset_mm', self::DEFAULT_INSET_MM, 0, 10);

        return round(max(self::MIN_HEIGHT_MM, self::minimumQrMm() + 2 * $inset + 0.5), 1);
    }

    /**
     * How many printer dots each QR module gets at a given square size.
     *
     * Shown by the print settings panel so the office can see why a size is
     * refused: 3 dots per module reads reliably, under ~2.5 does not.
     */
    public static function moduleDots(float $qrMm): float
    {
        return round($qrMm / self::MODULES_ACROSS * self::DOTS_PER_MM, 1);
    }

    /**
     * Save the office's print settings for this browser session.
     *
     * Returns the warnings for anything that had to be adjusted instead of
     * silently printing a sticker whose code will not scan.
     *
     * @return array{ok: bool, message: string, warnings: array<int, string>, settings?: array}
     */
    public static function saveSettings(array $input): array
    {
        $resolved = self::resolve([
            'width_mm'  => $input['width_mm'] ?? null,
            'height_mm' => $input['height_mm'] ?? null,
            'qr_mm'     => $input['qr_mm'] ?? null,
        ]);

        $settings = $resolved['settings'];

        try {
            session([self::SETTINGS_KEY => [
                'width_mm'  => $settings['width_mm'],
                'height_mm' => $settings['height_mm'],
                'qr_mm'     => $settings['qr_mm'],
            ]]);
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => 'These print settings could not be saved for this browser.', 'warnings' => []];
        }

        return [
            'ok'       => true,
            'message'  => 'Print settings saved for this browser: '
                . self::css($settings['width_mm']) . ' × ' . self::css($settings['height_mm']) . ' mm sticker, '
                . self::css($settings['qr_mm']) . ' mm QR (' . self::moduleDots($settings['qr_mm']) . ' dots per module).',
            'warnings' => $resolved['warnings'],
            'settings' => $settings,
        ];
    }

    /** Drop the session's print settings, going back to the configured stock. */
    public static function resetSettings(): void
    {
        try {
            session()->forget(self::SETTINGS_KEY);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Clamp a set of requested sizes into something printable, collecting a
     * sentence for every value that had to move.
     *
     * @return array{settings: array{width_mm: float, height_mm: float, inset_mm: float, qr_mm: float}, warnings: array<int, string>}
     */
    private static function resolve(array $input): array
    {
        $warnings = [];

        $inset = self::configuredMm('inset_mm', self::DEFAULT_INSET_MM, 0, 10);

        $configuredWidth  = self::configuredMm('width_mm', self::DEFAULT_WIDTH_MM, self::MIN_WIDTH_MM, self::MAX_WIDTH_MM);
        $configuredHeight = self::configuredMm('height_mm', self::DEFAULT_HEIGHT_MM, self::MIN_HEIGHT_MM, self::MAX_HEIGHT_MM);
        $configuredQr     = self::configuredMm('qr_mm', self::DEFAULT_QR_MM, 8, 120);

        $minimumQr = self::minimumQrMm();

        // A sticker smaller than this cannot carry a readable code next to its
        // text, so the requested size is raised rather than printed unscannable.
        $minimumWidth  = self::minimumStickerWidthMm();
        $minimumHeight = self::minimumStickerHeightMm();

        $requestedWidth  = self::number($input['width_mm']);
        $requestedHeight = self::number($input['height_mm']);

        $width  = self::clampFloat($requestedWidth ?? $configuredWidth, $minimumWidth, self::MAX_WIDTH_MM);
        $height = self::clampFloat($requestedHeight ?? $configuredHeight, $minimumHeight, self::MAX_HEIGHT_MM);

        $inset = min($inset, min($width, $height) / 4);

        if ($requestedWidth !== null && abs($requestedWidth - $width) > 0.05) {
            $warnings[] = $requestedWidth < $width
                ? 'Sticker width raised to ' . self::css($width) . ' mm: narrower than that, the QR and its text no longer both fit.'
                : 'Sticker width lowered to ' . self::css($width) . ' mm, the widest the label layout supports.';
        }

        if ($requestedHeight !== null && abs($requestedHeight - $height) > 0.05) {
            $warnings[] = $requestedHeight < $height
                ? 'Sticker height raised to ' . self::css($height) . ' mm: shorter than that, a readable QR no longer fits.'
                : 'Sticker height lowered to ' . self::css($height) . ' mm, the tallest the label layout supports.';
        }

        $roomHeight = $height - 2 * $inset - 0.5;
        $roomWidth  = $width - 2 * $inset - self::TEXT_COLUMN_MM;
        $maximumQr  = max($minimumQr, min($roomHeight, $roomWidth));

        $requestedQr = self::number($input['qr_mm']);
        $qr = round(self::clampFloat($requestedQr ?? $configuredQr, $minimumQr, $maximumQr), 1);

        if ($requestedQr !== null && abs($requestedQr - $qr) > 0.05) {
            $warnings[] = $requestedQr < $qr
                ? 'QR size raised to ' . self::css($qr) . ' mm: below ' . self::css($minimumQr)
                    . ' mm the modules merge on a 203 dpi thermal head (' . self::moduleDots($qr) . ' dots per module).'
                : 'QR size lowered to ' . self::css($qr) . ' mm, the largest square a '
                    . self::css($width) . ' × ' . self::css($height) . ' mm sticker can hold beside its text.';
        }

        return [
            'settings' => [
                'width_mm'  => $width,
                'height_mm' => $height,
                'inset_mm'  => $inset,
                'qr_mm'     => $qr,
            ],
            'warnings' => $warnings,
        ];
    }

    /** @return array<string, float> */
    private static function savedSettings(): array
    {
        try {
            $saved = session(self::SETTINGS_KEY);
        } catch (Throwable) {
            // No session (a console command): the configured stock is in force.
            return [];
        }

        return is_array($saved) ? $saved : [];
    }

    private static function clampFloat(float $value, float $min, float $max): float
    {
        return round(max($min, min($value, $max)), 1);
    }

    private static function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }

    /**
     * A millimetre value as a CSS-ready string: `50`, `19.88`, never `50.00`.
     */
    public static function css(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private static function configuredMm(string $key, float $fallback, float $min, float $max): float
    {
        $value = config('asset_label.'.$key, $fallback);
        $value = is_numeric($value) ? (float) $value : $fallback;

        if ($value < $min || $value > $max) {
            return $fallback;
        }

        return $value;
    }

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
            // What the sticker's QR encodes when the browser has to draw it.
            'qr_payload' => self::payloadFor($row, $code),
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
            // The tag token is issued for the code, not the row, so a label
            // previewed before the asset exists carries the same identity it
            // will carry once saved.
            'qr_payload' => AssetQr::stickerText($code),
            'logo'     => self::logoUrl(),
            'logo_fallback' => self::logoFallbackUrl(),
        ];
    }

    /**
     * The exact text the sticker carries for a saved asset.
     *
     * A NUTAG is the current sticker, and its token is stored per code - so a
     * reprint is the same sticker rather than a second identity for the same
     * asset. An asset that already carries a sealed QR payload keeps reading
     * its stored one only when no tag can be issued, because printing a QR
     * again is exactly what the tag format replaces.
     *
     * A stored payload of the old readable format (version 1) is deliberately
     * not reused: printing it again would put the asset code back on the paper.
     * Either way the code printed is this asset's own: a stored payload naming
     * a different code is never used, which would put one asset's sticker on
     * another's record.
     */
    private static function payloadFor(object $row, string $code): string
    {
        $tag = AssetTag::textFor($code);

        if ($tag !== null) {
            return $tag;
        }

        $stored = trim((string) ($row->qr_payload ?? ''));

        if ($stored !== '' && $code !== '') {
            $parsed = QrPayload::parse($stored);

            $sealed = (int) ($parsed['version'] ?? 0) >= QrPayload::VERSION;

            if ($sealed && $parsed['code'] === $code && $parsed['type'] === 'signed') {
                return $stored;
            }
        }

        // No sealed payload on record yet: seal one now, or fall back to the
        // plain code on an install with no signing keys.
        return AssetQr::textFor($code);
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
