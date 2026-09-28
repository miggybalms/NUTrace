<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The app runs behind Railway's TLS-terminating proxy, so every request
 * arrives as plain HTTP with X-Forwarded-* headers attached. The proxy must
 * be trusted or Laravel generates http:// form actions and redirects on the
 * https:// site — which triggers Chrome's "form is not secure" interstitial
 * and, after "Send anyway", drops the POST's flash data (like the QR labels
 * shown after registering assets).
 */
class TrustedProxiesTest extends TestCase
{
    public function test_forwarded_https_headers_are_trusted_on_generated_urls(): void
    {
        Route::get('/proxy-check', fn () => url('/proxy-check'));

        $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'nutrace-production.up.railway.app',
        ])
            ->get('/proxy-check')
            ->assertOk()
            ->assertSee('https://nutrace-production.up.railway.app/proxy-check', escape: false);
    }

    public function test_plain_local_requests_still_use_http(): void
    {
        Route::get('/proxy-check', fn () => url('/proxy-check'));

        $this->get('/proxy-check')
            ->assertOk()
            ->assertSee('http://localhost/proxy-check', escape: false);
    }
}
