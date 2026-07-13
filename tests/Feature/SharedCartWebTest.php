<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The shared-cart web entry page (/cart/join/{token}): a self-contained shell
 * that drives the v2 API over fetch. No server auth — the page itself is public;
 * the API calls it makes are authenticated with a sanctum token client-side.
 */
class SharedCartWebTest extends TestCase
{
    public function test_join_page_renders_with_the_token_wired_in(): void
    {
        $token = 'abc123token';

        $res = $this->get("/cart/join/{$token}");

        $res->assertOk();
        $res->assertViewIs('cart.shared-join');
        $res->assertViewHas('token', $token);
        // The token is embedded for the client-side fetch, and the join endpoint
        // path is present in the page.
        $res->assertSee($token, false);
        $res->assertSee('/api/v2/cart/join/', false);
        $res->assertSee('انضمام وعرض السلة', false);
    }

    public function test_join_page_is_public_and_does_not_require_auth(): void
    {
        // No authenticated user — the shell must still load (auth happens in the
        // client-side API calls, not on the page).
        $this->get('/cart/join/anytoken')->assertOk();
    }

    public function test_host_share_page_renders_with_the_business_wired_in(): void
    {
        $res = $this->get('/cart/share/179');

        $res->assertOk();
        $res->assertViewIs('cart.shared-host');
        $res->assertViewHas('businessId', 179);
        // The share endpoint for this business is wired for the client-side fetch.
        $res->assertSee('/api/v2/cart/', false);
        $res->assertSee('إنشاء رابط المشاركة', false);
    }

    public function test_qr_endpoint_returns_an_svg_encoding_the_join_url(): void
    {
        $res = $this->get('/cart/join/sometoken123/qr');

        $res->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $res->getContent());
    }

    public function test_qr_encodes_the_request_host_not_a_fixed_app_url(): void
    {
        // The QR must point at the origin the host is browsing (so a mismatched
        // APP_URL can't send friends to the wrong host). Different hosts must
        // therefore produce different QR payloads.
        $a = $this->get('http://host-a.test/cart/join/sametoken/qr')->getContent();
        $b = $this->get('http://host-b.test/cart/join/sametoken/qr')->getContent();

        $this->assertNotSame($a, $b, 'the QR payload must vary with the request host');
    }
}
