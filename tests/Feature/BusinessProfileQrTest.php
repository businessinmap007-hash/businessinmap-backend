<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Business-profile QR (BIM-13.4): a permanent /b/{business} storefront page +
 * its SVG QR, and the owner "share your store" panel screen.
 */
class BusinessProfileQrTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        return User::query()->where('type', 'business')->firstOrFail();
    }

    public function test_storefront_page_renders_public_with_the_business(): void
    {
        $biz = $this->business();

        $res = $this->get("/b/{$biz->id}");

        $res->assertOk();
        $res->assertViewIs('business-profile');
        $res->assertViewHas('biz');
        $res->assertSee($biz->name, false);
    }

    public function test_storefront_qr_returns_svg(): void
    {
        $biz = $this->business();

        $res = $this->get("/b/{$biz->id}/qr");

        $res->assertOk();
        $this->assertStringContainsString('image/svg+xml', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $res->getContent());
    }

    public function test_unknown_business_is_404_on_page_and_qr(): void
    {
        $nonBusinessId = (int) User::query()->where('type', '!=', 'business')->value('id');

        $this->get('/b/999999999')->assertNotFound();
        $this->get('/b/999999999/qr')->assertNotFound();
        // A non-business user id must not resolve as a storefront.
        if ($nonBusinessId) {
            $this->get("/b/{$nonBusinessId}")->assertNotFound();
        }
    }

    public function test_owner_share_store_screen_renders_with_the_qr(): void
    {
        $biz = $this->business();
        $this->actingAs($biz);

        $res = $this->get(route('business.share-store', [], false));

        $res->assertOk();
        $res->assertViewIs('business.share-store');
        // The owner's own storefront QR + link are wired in.
        $res->assertSee('/b/' . $biz->id . '/qr', false);
        $res->assertSee('/b/' . $biz->id, false);
    }
}
