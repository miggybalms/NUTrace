<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security response headers.
 *
 * A scan of the deployed site reported six headers missing. They are set here,
 * in one place, for every response the app produces, so no controller has to
 * remember them and no page can be forgotten.
 *
 * Two of them are worth a word, because both are easy to set in a way that
 * breaks the app:
 *
 *  - Strict-Transport-Security is only sent when the request actually arrived
 *    over HTTPS. On a plain-http development machine the header would do
 *    nothing (browsers ignore it off https) but sending it anyway would pin a
 *    host that has no certificate to pin — so it is sent only when the request
 *    is secure. Railway terminates TLS in front of the app, and the forwarded
 *    headers are trusted (see bootstrap/app.php), so the live site does get it.
 *
 *  - Content-Security-Policy is written around what this app actually loads.
 *    The views are Blade pages with inline <script> blocks, inline event
 *    handlers (onclick=...) and inline styles, and several screens are styled
 *    by the Tailwind Play CDN, which compiles classes in the browser. A policy
 *    without 'unsafe-inline' / 'unsafe-eval' would block all of that and take
 *    the interface down with it, so the policy allows exactly the directives
 *    the pages need and is strict everywhere it safely can be: plugins, base
 *    tags, form targets and framing are all locked to this origin.
 *
 *    Tightening the script/style directives later means compiling the
 *    stylesheet (vite build) and moving the inline handlers into files, then
 *    dropping 'unsafe-inline'. Until that refactor happens, the honest
 *    position is: the header is present and the risky directives are
 *    documented here rather than hidden in a template.
 */
class SecurityHeaders
{
    /** Content the pages legitimately load from other origins. */
    private const SCRIPT_SOURCES = [
        'https://cdn.tailwindcss.com',   // Tailwind Play CDN (used by several admin screens)
        'https://cdn.jsdelivr.net',      // qrcodejs, jsQR, html5-qrcode, remixicon
        'https://cdnjs.cloudflare.com',  // qrcodejs (older pages)
        'https://unpkg.com',             // html5-qrcode (request pages)
    ];

    private const STYLE_SOURCES = [
        'https://fonts.googleapis.com',  // Inter / Fraunces / IBM Plex Mono
        'https://cdn.tailwindcss.com',   // styles the Play CDN injects
        'https://cdn.jsdelivr.net',      // remixicon
        'https://cdnjs.cloudflare.com',
        'https://unpkg.com',
    ];

    private const FONT_SOURCES = [
        'https://fonts.gstatic.com',
        'https://cdn.jsdelivr.net',
        'https://cdnjs.cloudflare.com',
    ];

    /**
     * Apply the headers to a response.
     *
     * Also called for error responses, which do not travel back through the
     * middleware stack (see bootstrap/app.php).
     */
    public static function apply(Response $response, bool $secure): Response
    {
        $headers = [
            // Stop the browser guessing a type we did not declare.
            'X-Content-Type-Options' => 'nosniff',
            // The app never frames another site's page, and must not be framed.
            'X-Frame-Options' => 'SAMEORIGIN',
            // Send the full URL only to this origin; elsewhere just the origin.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // The QR scanner needs the camera; nothing here needs the rest.
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()',
            'Content-Security-Policy' => self::contentSecurityPolicy(),
        ];

        if ($secure) {
            // A year, and the www/apex sub-domains, as the scan recommends.
            // No preload directive: that is a one-way decision and belongs to
            // whoever owns the domain, not to this middleware.
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // A response that already sets one of these (a download, an embed)
            // keeps its own value.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($next($request), $request->isSecure());
    }

    private static function contentSecurityPolicy(): string
    {
        $scripts = implode(' ', array_merge(["'self'", "'unsafe-inline'", "'unsafe-eval'"], self::SCRIPT_SOURCES));
        $styles = implode(' ', array_merge(["'self'", "'unsafe-inline'"], self::STYLE_SOURCES));
        $fonts = implode(' ', array_merge(["'self'", 'data:'], self::FONT_SOURCES));

        return implode('; ', [
            "default-src 'self'",
            'script-src ' . $scripts,
            'style-src ' . $styles,
            'font-src ' . $fonts,
            // Asset photos live in Supabase Storage, and a few screens fall
            // back to the QR service; both are arbitrary https hosts.
            "img-src 'self' data: blob: https:",
            // Camera frames and exported QR images.
            "media-src 'self' data: blob: https:",
            // Every fetch()/XHR in the app talks to this origin or to https.
            "connect-src 'self' https:",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            // Deliberately none of upgrade-insecure-requests: on a plain-http
            // development machine it would rewrite the local assets to https
            // and the pages would come up unstyled, for no gain on the https
            // live site.
        ]);
    }
}
