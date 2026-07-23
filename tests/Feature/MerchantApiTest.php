<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V2\BusinessBookableItemController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * The merchant-management API added so a business can run its offerings from the
 * app, not only the web panel: pricing, bookable units, and retail listings.
 * Each walks the full CRUD over the real endpoints under the `business` gate,
 * and every row is scoped to the acting owner.
 *
 * The (service, item type) a price/bookable needs must be one the owner's
 * subcategory actually offers (assertAllowed), so it is resolved through the
 * controller's own trait logic rather than hardcoded — if the taxonomy changes,
 * the test still asks for a real pair or skips.
 */
class MerchantApiTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    /** A business owner whose category child ("آثاث") has retail active. */
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

        $user->category_child_id = $childId; // in-memory, rolled back
        $user->is_suspend = 0;

        return $user;
    }

    /**
     * A (service_id, item_type) the owner's subcategory offers — resolved the
     * same way the controllers do, so assertAllowed will accept it.
     *
     * @return array{0:int,1:string}
     */
    private function allowedPair(User $owner): array
    {
        Auth::setUser($owner);

        $ctrl = new BusinessBookableItemController();

        $servicesM = new ReflectionMethod($ctrl, 'servicesForChild');
        $servicesM->setAccessible(true);
        $services = $servicesM->invoke($ctrl);

        $allowedM = new ReflectionMethod($ctrl, 'allowedTypesByService');
        $allowedM->setAccessible(true);
        $allowed = $allowedM->invoke($ctrl, $services);

        foreach ($services as $s) {
            $types = $allowed[(int) $s->id] ?? [];
            if (! empty($types)) {
                return [(int) $s->id, (string) $types[0]['key']];
            }
        }

        $this->markTestSkipped('Owner subcategory offers no (service, item type) pair.');
    }

    public function test_business_prices_crud(): void
    {
        $owner = $this->owner();
        [$serviceId, $itemType] = $this->allowedPair($owner);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v2/business/prices/options')->assertOk()->assertJsonPath('success', true);

        $created = $this->postJson('/api/v2/business/prices', [
            'service_id' => $serviceId,
            'bookable_item_type' => $itemType,
            'price' => 100,
            'is_active' => true,
        ])->assertCreated();
        $this->assertEquals(100, $created->json('data.price'));

        $id = (int) $created->json('data.id');

        $this->getJson("/api/v2/business/prices/{$id}")->assertOk()->assertJsonPath('data.id', $id);

        $updated = $this->putJson("/api/v2/business/prices/{$id}", [
            'service_id' => $serviceId,
            'bookable_item_type' => $itemType,
            'price' => 150,
        ])->assertOk();
        $this->assertEquals(150, $updated->json('data.price'));

        // A duplicate (same service + type) is refused, not silently doubled.
        $this->postJson('/api/v2/business/prices', [
            'service_id' => $serviceId,
            'bookable_item_type' => $itemType,
            'price' => 200,
        ])->assertStatus(422);

        $this->deleteJson("/api/v2/business/prices/{$id}")->assertOk();
        $this->getJson("/api/v2/business/prices/{$id}")->assertNotFound();
    }

    public function test_business_bookable_items_crud(): void
    {
        $owner = $this->owner();
        [$serviceId, $itemType] = $this->allowedPair($owner);

        Sanctum::actingAs($owner);

        $code = 'UNIT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $created = $this->postJson('/api/v2/business/bookable-items', [
            'service_id' => $serviceId,
            'item_type' => $itemType,
            'code' => $code,
            'title' => 'وحدة اختبار',
            'quantity' => 3,
        ])->assertCreated()->assertJsonPath('data.code', $code);

        $id = (int) $created->json('data.id');

        $this->getJson("/api/v2/business/bookable-items/{$id}")->assertOk()->assertJsonPath('data.quantity', 3);

        $this->putJson("/api/v2/business/bookable-items/{$id}", [
            'service_id' => $serviceId,
            'item_type' => $itemType,
            'code' => $code,
            'quantity' => 5,
        ])->assertOk()->assertJsonPath('data.quantity', 5);

        $this->getJson('/api/v2/business/bookable-items')->assertOk()
            ->assertJsonPath('success', true);

        $this->deleteJson("/api/v2/business/bookable-items/{$id}")->assertOk();
        $this->getJson("/api/v2/business/bookable-items/{$id}")->assertNotFound();
    }

    public function test_business_retail_listings_crud(): void
    {
        $owner = $this->owner();
        $productId = $this->makeCatalogProduct('furniture', 'كنبة API');

        Sanctum::actingAs($owner);

        // The unlisted in-scope master shows in the picker feed.
        $lookup = $this->getJson('/api/v2/business/retail-listings/lookup?q=API')->assertOk();
        $lookupIds = array_map(fn ($i) => (int) $i['id'], $lookup->json('data.items'));
        $this->assertContains($productId, $lookupIds, 'an in-scope unlisted master must appear in the picker');

        $created = $this->postJson('/api/v2/business/retail-listings', [
            'catalog_product_id' => $productId,
            'price' => 500,
            'stock' => 4,
        ])->assertCreated()->assertJsonPath('data.product.id', $productId);

        $id = (int) $created->json('data.id');

        // Once listed, it drops out of the picker.
        $lookup2 = $this->getJson('/api/v2/business/retail-listings/lookup?q=API')->assertOk();
        $lookup2Ids = array_map(fn ($i) => (int) $i['id'], $lookup2->json('data.items'));
        $this->assertNotContains($productId, $lookup2Ids, 'an already-listed master must not reappear');

        // Listing the same product twice is refused.
        $this->postJson('/api/v2/business/retail-listings', [
            'catalog_product_id' => $productId,
            'price' => 600,
        ])->assertStatus(422);

        $priced = $this->putJson("/api/v2/business/retail-listings/{$id}", ['price' => 750])->assertOk();
        $this->assertEquals(750, $priced->json('data.price'));

        $this->deleteJson("/api/v2/business/retail-listings/{$id}")->assertOk();
        $this->getJson("/api/v2/business/retail-listings/{$id}")->assertNotFound();
    }

    public function test_a_non_business_user_is_refused(): void
    {
        $client = User::query()->where('type', 'client')->orderBy('id')->first()
            ?: User::query()->where('type', '!=', 'business')->orderBy('id')->first();
        if (! $client) {
            $this->markTestSkipped('Needs a non-business user.');
        }

        Sanctum::actingAs($client);

        $this->getJson('/api/v2/business/prices')->assertForbidden();
        $this->getJson('/api/v2/business/bookable-items')->assertForbidden();
        $this->getJson('/api/v2/business/retail-listings')->assertForbidden();
    }
}
