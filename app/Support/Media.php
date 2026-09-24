<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single entry point for everything the app stores as media: profile photos,
 * asset photos, request attachments and asset QR codes.
 *
 * The "public" disk is the app's media disk. When SUPABASE_URL is configured it
 * is backed by Supabase Storage (see config/filesystems.php), otherwise it
 * falls back to the local public disk for development.
 */
class Media
{
    public const DISK = 'public';

    public const QR_FOLDER = 'assets/qr';

    /**
     * Folder on the media disk that holds the app's own brand artwork.
     */
    public const BRAND_FOLDER = 'brand';

    /**
     * Brand artwork mapped from its name on the media disk to the copy that
     * ships inside the app. Both sides exist on purpose: publishing to the
     * bucket is what lets branding change without a rebuild, while the bundled
     * copy is what keeps the logo on screen if the bucket is unreachable or has
     * never been given the file.
     */
    public const BRAND_FILES = [
        'logo-mark.png' => 'images/logo-mark.png',
        'favicon.ico' => 'favicon.ico',
    ];

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Is the media disk backed by Supabase Storage?
     */
    public static function usesSupabase(): bool
    {
        return config('filesystems.disks.'.self::DISK.'.driver') === 'supabase';
    }

    /**
     * Path of a brand asset inside the media disk, e.g. "brand/logo-mark.png".
     */
    public static function brandPath(string $file): string
    {
        return self::BRAND_FOLDER.'/'.ltrim($file, '/');
    }

    /**
     * Where the copy that ships inside the app lives, relative to public/.
     */
    public static function brandLocalPath(string $file): string
    {
        return self::BRAND_FILES[$file] ?? 'images/'.ltrim($file, '/');
    }

    /**
     * Browser URL of the bundled copy, used whenever the media disk is not
     * configured and as the onerror fallback in markup.
     */
    public static function brandLocalUrl(string $file): string
    {
        return '/'.ltrim(self::brandLocalPath($file), '/');
    }

    /**
     * Browser URL for a brand asset such as the logo mark.
     *
     * Prefers the published copy so the logo can be replaced by uploading one
     * file to Supabase instead of rebuilding and redeploying the app. Returns
     * the bundled URL when the media disk is not backed by storage we can
     * address over HTTP.
     */
    public static function brand(string $file): string
    {
        $local = self::brandLocalUrl($file);

        if (! self::usesSupabase()) {
            return $local;
        }

        try {
            return self::disk()->url(self::brandPath($file));
        } catch (Throwable $e) {
            report($e);

            return $local;
        }
    }

    /**
     * Turn whatever a database column holds into a browser-ready URL.
     *
     * Accepts an already-absolute URL (older rows), a legacy "/storage/..."
     * path, or a plain relative path such as "assets/qr/AST-1-17.png".
     */
    public static function url(?string $path): ?string
    {
        $path = is_string($path) ? trim($path) : '';

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['data:'])) {
            return $path;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            // Leftover links to the app's own /storage folder (the old local
            // disk) still point at nothing on the deployed site; re-resolve
            // them against the media disk.
            return self::pointsAtLocalStorage($path)
                ? self::disk()->url(self::normalizePath($path))
                : $path;
        }

        return self::disk()->url(self::normalizePath($path));
    }

    /**
     * Is this an absolute link to the app's own /storage folder rather than a
     * real file host (Supabase or any other CDN)?
     */
    public static function pointsAtLocalStorage(string $url): bool
    {
        $urlPath = (string) parse_url($url, PHP_URL_PATH);

        // Supabase object URLs live under /storage/v1/object/public/... and are
        // already correct.
        if (Str::startsWith($urlPath, '/storage/v1/')) {
            return false;
        }

        return Str::startsWith($urlPath, '/storage/');
    }

    /**
     * Strip the legacy "/storage" prefix so a stored value maps onto the
     * relative path inside the media disk.
     */
    public static function normalizePath(string $path): string
    {
        $path = trim($path);

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            $urlPath = (string) parse_url($path, PHP_URL_PATH);

            // .../storage/v1/object/public/<bucket>/<relative path>
            if (($bucketPos = strpos($urlPath, '/object/public/')) !== false) {
                $path = substr($urlPath, $bucketPos + strlen('/object/public/'));
                $path = preg_replace('#^[^/]+/#', '', $path) ?? $path;
            } else {
                $path = $urlPath;
            }
        }

        return ltrim(preg_replace('#^(/)?(storage/)+#', '', (string) $path) ?? $path, '/');
    }

    /**
     * Write raw contents (QR PNG, generated file, ...) to the media disk.
     */
    public static function put(string $path, string $contents, array $options = []): bool
    {
        try {
            return self::disk()->put($path, $contents, $options) !== false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public static function exists(?string $path): bool
    {
        $path = self::normalizePath((string) $path);

        if ($path === '') {
            return false;
        }

        try {
            return self::disk()->exists($path);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    public static function delete(?string $path): void
    {
        $path = self::normalizePath((string) $path);

        if ($path === '') {
            return;
        }

        try {
            self::disk()->delete($path);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Render an asset code as a PNG QR image, store it and return its path
     * (relative to the media disk) so it can be saved on the asset row.
     *
     * Returns null when the QR could not be produced; callers keep the asset
     * without a stored QR and the UI falls back to drawing it in the browser.
     */
    public static function generateQr(string $assetCode, int $size = 400): ?string
    {
        $assetCode = trim($assetCode);

        if ($assetCode === '') {
            return null;
        }

        $png = self::fetchQrPng($assetCode, $size);

        if ($png === null) {
            return null;
        }

        $path = self::QR_FOLDER.'/'.$assetCode.'-'.time().'.png';

        return self::put($path, $png, ['mimetype' => 'image/png']) ? $path : null;
    }

    protected static function fetchQrPng(string $assetCode, int $size): ?string
    {
        try {
            $response = Http::timeout(15)
                ->retry(2, 400)
                ->get('https://api.qrserver.com/v1/create-qr-code/', [
                    'size' => $size.'x'.$size,
                    'data' => $assetCode,
                ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $body = $response->successful() ? $response->body() : '';

        // A real PNG is always well over this; a tiny body means an error page.
        if (strlen($body) < 100 || ! str_starts_with($body, "\x89PNG")) {
            return null;
        }

        return $body;
    }
}
