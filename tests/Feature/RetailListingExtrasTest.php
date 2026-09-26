<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * «الإضافات» on a retail listing — warranty as a pick-one group, installation
 * as a tick box — the non-food twin of a menu item's extras: the merchant
 * defines them, the customer picks, the cart prices them in server-side and
 * snapshots them on the line. Rolls back.
 */
class RetailListingExtrasTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    private User $owner;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $childId = (int) DB::table('category_children_master')->where('name_ar', 'آثاث')->value('id');
        $this->owner = User::query()->where('type', 'business')->orderBy('id')->first();
        $this->customer = User::query()->where('id', '!=', $this->owner->id)->orderBy('id')->firstOrFail();

        if ($childId <= 0) {
            $this->markTestSkipped('Needs the «آثاث» child.');
        }

        $this->owner->category_child_id = $childId;
        $this->owner->is_suspend = 0;
        Auth::setUser($this->owner);
    }

    private function listing(): int
    {
        return BusinessCatalogListing::create([
            'business_id' => $this->owner->id,
            'catalog_product_id' => $this->makeCatalogProduct(),
            'sku' => 'EXT-' . uniqid(),
            'price' => 1000,
            'currency' => 'EGP',
            'stock' => 50,
            'is_active' => 1,
        ])->id;
    }

    private function saveExtras(int $listing): array
    {
        Sanctum::actingAs($this->owner);

        return $this->putJson("/api/v2/business/retail-listings/{$listing}/extras", ['extras' => [
            ['name_ar' => 'ضمان سنة', 'price' => 100, 'group_name_ar' => 'الضمان', 'selection_type' => 'single'],
            ['name_ar' => 'ضمان سنتين', 'price' => 180, 'group_name_ar' => 'الضمان', 'selection_type' => 'single'],
            ['name_ar' => 'تركيب', 'price' => 50],
        ]])->assertOk()->json('data.extras');
    }

    public function test_the_merchant_defines_extras_and_the_shopper_sees_them_grouped(): void
    {
        $listing = $this->listing();
        $extras = $this->saveExtras($listing);

        $this->assertCount(3, $extras);
        $this->assertSame('single', $extras[1]['selection_type']);
        $this->assertNull($extras[2]['group_name_ar']);

        $rows = collect($this->getJson('/api/v2/discovery/retail/business/' . $this->owner->id)->assertOk()->json('data.listings'))
            ->firstWhere('listing_id', $listing);

        $this->assertCount(3, $rows['extras']);
        $this->assertSame('الضمان', $rows['extras'][0]['group']);
        $this->assertSame(180.0, (float) $rows['extras'][1]['price']);

        // Saving a shorter list removes the rest.
        Sanctum::actingAs($this->owner);
        $this->putJson("/api/v2/business/retail-listings/{$listing}/extras", ['extras' => [
            ['id' => $extras[2]['id'], 'name_ar' => 'تركيب', 'price' => 60],
        ]])->assertOk()->assertJsonCount(1, 'data.extras');
    }

    public function test_the_cart_prices_the_chosen_extras_in_and_snapshots_them(): void
    {
        $listing = $this->listing();
        $extras = $this->saveExtras($listing);

        Sanctum::actingAs($this->customer);
        $cart = $this->postJson('/api/v2/cart/items', [
            'kind' => 'retail', 'offering_id' => $listing, 'qty' => 2,
            'extras' => [$extras[1]['id'], $extras[2]['id']],
        ])->assertCreated()->json('data.cart');

        $line = collect($cart['items'] ?? $cart['lines'] ?? [])->first();
        $this->assertNotNull($line, 'the cart carries the line');

        $item = DB::table('order_items')->where('offering_id', $listing)->latest('id')->first();
        $this->assertEqualsWithDelta(1230.0, (float) $item->price, 0.001, "1000 + 180 warranty + 50 installation");
        $this->assertSame(2, (int) $item->qty);
        $this->assertEqualsWithDelta(2460.0, (float) $item->total_price, 0.001);
        $this->assertCount(2, json_decode($item->addons, true));

        // The same listing with no extras is a separate line at the plain price.
        $this->postJson('/api/v2/cart/items', ['kind' => 'retail', 'offering_id' => $listing, 'qty' => 1])->assertCreated();
        $this->assertSame(2, DB::table('order_items')->where('offering_id', $listing)->count());
    }

    public function test_two_picks_from_a_pick_one_group_and_foreign_extras_are_refused(): void
    {
        $listing = $this->listing();
        $extras = $this->saveExtras($listing);
        $other = $this->listing();
        $foreign = $this->saveExtras($other);

        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v2/cart/items', [
            'kind' => 'retail', 'offering_id' => $listing, 'qty' => 1,
            'extras' => [$extras[0]['id'], $extras[1]['id']],
        ])->assertStatus(422)->assertJsonValidationErrors('extras');

        $this->postJson('/api/v2/cart/items', [
            'kind' => 'retail', 'offering_id' => $listing, 'qty' => 1,
            'extras' => [$foreign[0]['id']],
        ])->assertStatus(422)->assertJsonValidationErrors('extras');
    }

    public function test_another_business_cannot_edit_a_listings_extras(): void
    {
        $listing = $this->listing();

        Sanctum::actingAs($this->customer);
        $this->putJson("/api/v2/business/retail-listings/{$listing}/extras", ['extras' => []])
            ->assertStatus(403);
    }
}
