<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The six security headers the deployed-site scan reported missing.
 *
 * The two things worth guarding here are the ones that could quietly break the
 * app: the camera has to stay usable (the admin scanner reads QR stickers with
 * it), and the Content-Security-Policy has to keep allowing the CDNs the pages
 * are built on — a policy that blocks them takes the interface down without
 * ever failing a request.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_a_page_carries_the_security_headers(): void
    {
        Route::get('/headers-check', fn () => 'ok');

        $response = $this->get('/headers-check')->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
        $this->assertNotEmpty($response->headers->get('Permissions-Policy'));
    }

    public function test_hsts_is_sent_only_for_secure_requests(): void
    {
        Route::get('/headers-check', fn () => 'ok');

        // Plain http (a developer machine): no HSTS, and no panic.
        $this->get('/headers-check')
            ->assertOk()
            ->assertHeaderMissing('Strict-Transport-Security');

        // Railway terminates TLS and its forwarded headers are trusted.
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('/headers-check')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_policy_keeps_the_sources_the_pages_load(): void
    {
        Route::get('/headers-check', fn () => 'ok');

        $csp = $this->get('/headers-check')->headers->get('Content-Security-Policy');

        // Scripts the views and the scanner depend on.
        foreach (['cdn.tailwindcss.com', 'cdn.jsdelivr.net', 'cdnjs.cloudflare.com', 'unpkg.com'] as $host) {
            $this->assertStringContainsString($host, $csp, "CSP must still allow {$host}");
        }

        // Fonts and styles.
        $this->assertStringContainsString('fonts.googleapis.com', $csp);
        $this->assertStringContainsString('fonts.gstatic.com', $csp);

        // And the parts that are locked down.
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
    }

    public function test_the_camera_stays_available_to_this_origin(): void
    {
        Route::get('/headers-check', fn () => 'ok');

        $policy = $this->get('/headers-check')->headers->get('Permissions-Policy');

        // The QR scanner uses getUserMedia; camera=(self) keeps it working
        // while every other feature is refused.
        $this->assertStringContainsString('camera=(self)', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
    }

    /**
     * A CDN the policy does not allow is the one way this change can break the
     * site without breaking a single request: the page still returns 200, the
     * browser just refuses the stylesheet or the scanner library and the screen
     * comes up wrong. Every external asset the Blade views load is checked here,
     * so adding a new one fails the suite until the policy is updated with it.
     */
    public function test_every_external_asset_the_views_load_is_allowed(): void
    {
        Route::get('/headers-check', fn () => 'ok');

        $csp = $this->get('/headers-check')->headers->get('Content-Security-Policy');

        $explicit = [];
        preg_match_all('#https://[a-z0-9.-]+#i', $csp, $matches);
        foreach ($matches[0] as $url) {
            $explicit[] = strtolower($url);
        }

        // Images are allowed from any https host (asset photos live in
        // Supabase Storage and a few screens fall back to the QR service), so
        // only scripts and stylesheets need their host spelled out.
        $this->assertTrue(
            str_contains($csp, "img-src 'self' data: blob: https:"),
            'Image and camera-frame sources must stay open to https'
        );

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        $needsAllowlisting = [];

        foreach ($files as $file) {
            $contents = file_get_contents($file->getPathname());

            // Script and stylesheet tags carry a URL the browser will refuse
            // unless its host is in the policy.
            preg_match_all(
                '#<(script|link)\b[^>]*?(?:src|href)="https://([a-z0-9.-]+)#i',
                $contents,
                $tags,
                PREG_SET_ORDER
            );

            foreach ($tags as $tag) {
                $whole = $tag[0];

                // preconnect/dns-prefetch make no request, so they need no rule.
                if (stripos($whole, 'preconnect') !== false || stripos($whole, 'dns-prefetch') !== false) {
                    continue;
                }

                $needsAllowlisting[strtolower($tag[2])] = $file->getFilename();
            }
        }

        $offenders = [];

        foreach ($needsAllowlisting as $host => $view) {
            if (! in_array('https://' . $host, $explicit, true)) {
                $offenders[] = $host . ' (' . $view . ')';
            }
        }

        $this->assertNotSame([], $needsAllowlisting, 'No external script or stylesheet was found — the scan is broken');
        $this->assertSame([], $offenders,
            'The Content-Security-Policy would block these: ' . implode(', ', $offenders));
    }

    public function test_error_responses_carry_them_too(): void
    {
        $response = $this->get('/a-route-that-does-not-exist');

        $response->assertNotFound();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
    }
}
