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
}
