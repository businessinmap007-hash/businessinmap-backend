<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\CatalogListingAudience;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * Retail variant groups: several of a business's own listings (a size/color
 * family) shown to the customer as one product with a picker. Grouping is
 * purely presentational — the chosen variant's own listing is what the cart
 * and checkout still use, untouched.
 *
 * Auth here is a real Sanctum personal-access token per identity (rather than
 * Sanctum::actingAs(), whose state a plain forgetGuards() does not fully
 * clear mid-test) — same "actingWithToken" idiom GovernorateShippingTest and
 * MenuBundleJourneyTest use, which also gives a true, header-less "guest".
 */
class RetailVariantGroupTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    private function actingWithToken(?string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $token ? $this->withHeader('Authorization', 'Bearer ' . $token) : $this->withoutToken();
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /** A business owner whose category child ("أثاث") has retail active — same helper MerchantApiTest uses. */
    private function owner(): User
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'آثاث')->value('id');
        if ($childId <= 0) {
            $this->markTestSkipped('Business taxonomy child "آثاث" not seeded.');
        }

        $user = User::query()->where('type', 'business')->orderBy('id')->first();
        if (! $user) {
            $this->markTestSkipped('Needs a business user.');
        }

        // Persisted (DatabaseTransactions rolls it back) - token auth re-fetches
        // the user from the DB on every request, unlike Sanctum::actingAs().
        $user->update(['category_child_id' => $childId]);

        return $user;
    }

    private function listing(int $businessId, float $price, string $sku): int
    {
        $productId = $this->makeCatalogProduct('furniture', 'قميص ' . $sku);

        return (int) BusinessCatalogListing::create([
            'business_id' => $businessId, 'catalog_product_id' => $productId,
            'sku' => $sku, 'price' => $price, 'currency' => 'EGP', 'stock' => 10, 'is_active' => 1,
        ])->id;
    }

    public function test_a_group_needs_at_least_two_of_the_owners_own_listings(): void
    {
        $owner = $this->owner();
        $stranger = User::query()->where('type', 'business')->where('id', '!=', $owner->id)->first();
        $mine = $this->listing($owner->id, 100, 'V1');
        $ownerToken = $this->token($owner);

        // A single variant is not a "family".
        $this->actingWithToken($ownerToken)->postJson('/api/v2/business/retail/variant-groups', [
            'name_ar' => 'قميص كلاسيك', 'options' => [['listing_id' => $mine, 'label_ar' => 'أزرق']],
        ])->assertStatus(422);

        if ($stranger) {
            $theirs = $this->listing($stranger->id, 90, 'V2');
            $this->actingWithToken($ownerToken)->postJson('/api/v2/business/retail/variant-groups', [
                'name_ar' => 'قميص كلاسيك',
                'options' => [['listing_id' => $mine, 'label_ar' => 'أزرق'], ['listing_id' => $theirs, 'label_ar' => 'أحمر']],
            ])->assertStatus(422);
        }
    }

    public function test_the_full_group_lifecycle_and_discovery(): void
    {
        $owner = $this->owner();
        $ownerToken = $this->token($owner);
        $blue = $this->listing($owner->id, 100, 'BLU');
        $red = $this->listing($owner->id, 120, 'RED');
        $standalone = $this->listing($owner->id, 50, 'SOLO');

        $group = $this->actingWithToken($ownerToken)->postJson('/api/v2/business/retail/variant-groups', [
            'name_ar' => 'قميص كلاسيك', 'name_en' => 'Classic Shirt',
            'options' => [
                ['listing_id' => $blue, 'label_ar' => 'أزرق'],
                ['listing_id' => $red, 'label_ar' => 'أحمر'],
            ],
        ])->assertCreated()->assertJsonCount(2, 'data.options')->json('data');
        $groupId = (int) $group['id'];

        // Re-using an already-grouped listing in a NEW group is refused.
        $this->actingWithToken($ownerToken)->postJson('/api/v2/business/retail/variant-groups', [
            'name_ar' => 'قميص تاني', 'options' => [['listing_id' => $blue, 'label_ar' => 'أزرق فاتح'], ['listing_id' => $standalone, 'label_ar' => 'أبيض']],
        ])->assertStatus(422);

        // The storefront shows the group once, with both prices, and the
        // standalone listing on its own — but never the grouped listings loose.
        $storefront = $this->actingWithToken(null)->getJson("/api/v2/discovery/retail/business/{$owner->id}")->assertOk();
        $productIds = collect($storefront->json('data.listings'))->pluck('product.id')->all();
        $this->assertNotContains(
            (int) DB::table('business_catalog_listings')->where('id', $blue)->value('catalog_product_id'),
            $productIds,
            'a grouped listing must not also appear loose'
        );
        $groups = collect($storefront->json('data.variant_groups'));
        $this->assertCount(1, $groups);
        $options = collect($groups->first()['options']);
        $this->assertEqualsCanonicalizing([100.0, 120.0], $options->pluck('price')->map(fn ($p) => (float) $p)->all());

        // Renaming and shrinking to a different pair replaces the whole list.
        $green = $this->listing($owner->id, 130, 'GRN');
        $this->actingWithToken($ownerToken)->putJson("/api/v2/business/retail/variant-groups/{$groupId}", [
            'name_ar' => 'قميص كلاسيك', 'options' => [
                ['listing_id' => $red, 'label_ar' => 'أحمر'],
                ['listing_id' => $green, 'label_ar' => 'أخضر'],
            ],
        ])->assertOk()->assertJsonCount(2, 'data.options');

        // Blue is ungrouped again and must reappear loose on the storefront.
        $storefront2 = $this->actingWithToken(null)->getJson("/api/v2/discovery/retail/business/{$owner->id}")->assertOk();
        $productIds2 = collect($storefront2->json('data.listings'))->pluck('product.id')->all();
        $this->assertContains(
            (int) DB::table('business_catalog_listings')->where('id', $blue)->value('catalog_product_id'),
            $productIds2,
            'ungrouping must return the listing to the loose shelf'
        );

        // A stranger cannot manage this group.
        $other = User::query()->where('type', 'business')->where('id', '!=', $owner->id)->first();
        if ($other) {
            $otherToken = $this->token($other);
            $this->actingWithToken($otherToken)->getJson("/api/v2/business/retail/variant-groups/{$groupId}")->assertNotFound();
            $this->actingWithToken($otherToken)->deleteJson("/api/v2/business/retail/variant-groups/{$groupId}")->assertNotFound();
        }

        // Deleting the group ungroups everything — the listings themselves survive.
        $this->actingWithToken($ownerToken)->deleteJson("/api/v2/business/retail/variant-groups/{$groupId}")->assertOk();
        $this->assertDatabaseHas('business_catalog_listings', ['id' => $red]);
        $this->assertDatabaseHas('business_catalog_listings', ['id' => $green]);
        $this->assertDatabaseCount('retail_variant_options', 0);
    }

    public function test_a_wholesale_restricted_variant_stays_invisible_to_a_stranger(): void
    {
        $owner = $this->owner();
        $ownerToken = $this->token($owner);
        $public = $this->listing($owner->id, 40, 'PUB');
        $restrictedId = $this->listing($owner->id, 45, 'RES');
        BusinessCatalogListing::whereKey($restrictedId)->update(['visibility' => 'restricted']);
        $namedBuyer = User::query()->where('type', 'business')->where('id', '!=', $owner->id)->first();
        if ($namedBuyer) {
            CatalogListingAudience::create(['business_catalog_listing_id' => $restrictedId, 'audience_type' => CatalogListingAudience::TYPE_BUSINESS, 'audience_id' => $namedBuyer->id]);
        }

        $this->actingWithToken($ownerToken)->postJson('/api/v2/business/retail/variant-groups', [
            'name_ar' => 'خط مختلط', 'options' => [
                ['listing_id' => $public, 'label_ar' => 'عام'],
                ['listing_id' => $restrictedId, 'label_ar' => 'خاص'],
            ],
        ])->assertCreated();

        // A true guest — no Authorization header at all.
        $asGuest = $this->actingWithToken(null)->getJson("/api/v2/discovery/retail/business/{$owner->id}")->assertOk();
        $labels = collect($asGuest->json('data.variant_groups.0.options'))->pluck('label');
        $this->assertContains('عام', $labels);
        $this->assertNotContains('خاص', $labels);

        if ($namedBuyer) {
            $namedToken = $this->token($namedBuyer);
            $asNamed = $this->actingWithToken($namedToken)->getJson("/api/v2/discovery/retail/business/{$owner->id}")->assertOk();
            $namedLabels = collect($asNamed->json('data.variant_groups.0.options'))->pluck('label');
            $this->assertContains('خاص', $namedLabels, 'the named buyer must see the restricted variant');
        }
    }
}
