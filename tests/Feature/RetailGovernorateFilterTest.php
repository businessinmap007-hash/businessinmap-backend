<?php

namespace Tests\Feature;

use App\Models\BusinessCatalogListing;
use App\Models\CatalogListingAudience;
use App\Models\Governorate;
use App\Models\User;
use App\Services\Retail\RetailListingVisibility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * A retail listing's own governorate allow-list — sits ABOVE the wholesale
 * audience (RetailWholesaleVisibilityTest): that system grants visibility to
 * named buyers, this one narrows an otherwise-visible listing to specific
 * governorates, checked against the VIEWER's own `users.governorate_id`,
 * regardless of who they are. See RetailListingVisibility::matchGovernorate()
 * / passesGovernorateGate().
 */
class RetailGovernorateFilterTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    private RetailListingVisibility $visibility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->visibility = app(RetailListingVisibility::class);
    }

    /** @return array{0:int,1:int} two distinct real governorate ids */
    private function twoGovernorates(): array
    {
        $ids = Governorate::query()->orderBy('id')->limit(2)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('Needs at least two seeded governorates.');
        }

        return $ids;
    }

    private function seller(): User
    {
        $user = User::query()->where('type', 'business')->first();

        if (! $user) {
            $this->markTestSkipped('No business account.');
        }

        return $user;
    }

    private function listing(User $seller, array $governorateIds = [], string $visibility = RetailListingVisibility::PUBLIC): BusinessCatalogListing
    {
        return BusinessCatalogListing::create([
            'business_id' => $seller->id,
            'catalog_product_id' => $this->makeCatalogProduct(),
            'price' => 100,
            'currency' => 'EGP',
            'stock' => 100,
            'is_active' => 1,
            'visibility' => $visibility,
            'governorate_ids' => $governorateIds ?: null,
        ]);
    }

    private function viewerIn(int $governorateId): User
    {
        $user = User::query()->where('type', '!=', 'business')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $user) {
            $this->markTestSkipped('No user to act as a viewer.');
        }

        $user->forceFill(['governorate_id' => $governorateId])->save();

        return $user->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | The rule itself
    |--------------------------------------------------------------------------
    */

    public function test_no_restriction_is_seen_regardless_of_governorate(): void
    {
        [$inside, $outside] = $this->twoGovernorates();
        $row = $this->listing($this->seller());

        $this->assertTrue($this->visibility->canSee($row, $this->viewerIn($inside)));
        $this->assertTrue($this->visibility->canSee($row, $this->viewerIn($outside)));
        $this->assertTrue($this->visibility->canSee($row, null), 'an unrestricted listing must still show for a guest');
    }

    public function test_a_governorate_restricted_listing_is_seen_only_from_that_governorate(): void
    {
        [$allowed, $elsewhere] = $this->twoGovernorates();
        $row = $this->listing($this->seller(), [$allowed]);

        $this->assertTrue($this->visibility->canSee($row, $this->viewerIn($allowed)));
        $this->assertFalse($this->visibility->canSee($row, $this->viewerIn($elsewhere)));
    }

    public function test_the_owner_always_sees_their_own_listing_regardless_of_governorate(): void
    {
        [, $elsewhere] = $this->twoGovernorates();
        $seller = $this->seller();
        $seller->forceFill(['governorate_id' => $elsewhere])->save();

        [$allowed] = $this->twoGovernorates();
        $row = $this->listing($seller, [$allowed]);

        $this->assertTrue($this->visibility->canSee($row, $seller->fresh()));
    }

    public function test_a_viewer_with_no_governorate_set_cannot_see_a_restricted_listing(): void
    {
        [$allowed] = $this->twoGovernorates();
        $row = $this->listing($this->seller(), [$allowed]);

        $viewer = User::query()->where('type', '!=', 'business')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();
        $viewer->forceFill(['governorate_id' => null])->save();

        $this->assertFalse($this->visibility->canSee($row, $viewer->fresh()));
    }

    public function test_a_guest_cannot_see_a_governorate_restricted_listing(): void
    {
        [$allowed] = $this->twoGovernorates();
        $row = $this->listing($this->seller(), [$allowed]);

        $this->assertFalse($this->visibility->canSee($row, null));
    }

    /** The geography gate is an AND on top of the wholesale audience, not an alternative to it. */
    public function test_it_composes_with_wholesale_audience_as_an_and(): void
    {
        [$allowed, $elsewhere] = $this->twoGovernorates();
        $seller = $this->seller();
        $buyer = User::query()->where('type', 'business')->where('id', '!=', $seller->id)->first();

        if (! $buyer) {
            $this->markTestSkipped('Needs a second business account.');
        }

        $row = $this->listing($seller, [$allowed], RetailListingVisibility::RESTRICTED);
        CatalogListingAudience::create([
            'business_catalog_listing_id' => $row->id,
            'audience_type' => CatalogListingAudience::TYPE_BUSINESS,
            'audience_id' => $buyer->id,
        ]);

        // Named AND inside the allowed governorate: visible.
        $buyer->forceFill(['governorate_id' => $allowed])->save();
        $this->assertTrue($this->visibility->canSee($row, $buyer->fresh()));

        // Named but OUTSIDE the allowed governorate: still hidden.
        $buyer->forceFill(['governorate_id' => $elsewhere])->save();
        $this->assertFalse(
            $this->visibility->canSee($row, $buyer->fresh()),
            'being named as a wholesale buyer must not bypass the governorate gate'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | …applied where it counts
    |--------------------------------------------------------------------------
    */

    public function test_it_is_absent_from_discovery_for_a_viewer_outside_the_allowed_governorates(): void
    {
        [$allowed, $elsewhere] = $this->twoGovernorates();
        $row = $this->listing($this->seller(), [$allowed]);

        $insider = $this->viewerIn($allowed);
        $outsider = $this->viewerIn($elsewhere);

        Sanctum::actingAs($insider);
        $ids = collect($this->getJson('/api/v2/discovery/retail/listings')->json('data.listings.data') ?? [])
            ->pluck('listing_id')->map(fn ($id) => (int) $id);
        $this->assertContains((int) $row->id, $ids->all());

        Sanctum::actingAs($outsider);
        $ids = collect($this->getJson('/api/v2/discovery/retail/listings')->json('data.listings.data') ?? [])
            ->pluck('listing_id')->map(fn ($id) => (int) $id);
        $this->assertNotContains((int) $row->id, $ids->all());
    }

    public function test_a_viewer_outside_the_allowed_governorates_cannot_add_it_to_cart(): void
    {
        [$allowed, $elsewhere] = $this->twoGovernorates();
        $row = $this->listing($this->seller(), [$allowed]);
        $outsider = $this->viewerIn($elsewhere);

        $cart = app(\App\Services\CustomerCartService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $cart->addItem((int) $outsider->id, 'retail', (int) $row->id, 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Writing it
    |--------------------------------------------------------------------------
    */

    public function test_merchant_can_set_and_update_governorate_ids_via_api(): void
    {
        [$first, $second] = $this->twoGovernorates();
        $seller = $this->seller();
        $row = $this->listing($seller, [$first]);

        Sanctum::actingAs($seller);

        $governorates = $this->getJson("/api/v2/business/retail-listings/{$row->id}")
            ->assertOk()
            ->json('data.governorates');

        $this->assertSame([$first], $governorates['ids']);
        $this->assertSame($first, $governorates['items'][0]['id']);
        $this->assertNotEmpty($governorates['items'][0]['name']);

        $this->putJson("/api/v2/business/retail-listings/{$row->id}", [
            'price' => 100,
            'stock' => 100,
            'is_active' => true,
            'governorate_ids' => [$first, $second],
        ])->assertOk()->assertJsonPath('data.governorates.ids', [$first, $second]);

        $this->assertSame([$first, $second], $row->fresh()->governorate_ids);
    }

    /** Sending an empty list clears the restriction — back to every governorate. */
    public function test_an_empty_selection_clears_the_restriction(): void
    {
        [$first] = $this->twoGovernorates();
        $seller = $this->seller();
        $row = $this->listing($seller, [$first]);

        Sanctum::actingAs($seller);

        $this->putJson("/api/v2/business/retail-listings/{$row->id}", [
            'price' => 100,
            'stock' => 100,
            'is_active' => true,
            'governorate_ids' => [],
        ])->assertOk()->assertJsonPath('data.governorates.ids', []);

        $this->assertNull($row->fresh()->governorate_ids);
    }

    public function test_an_invalid_governorate_id_is_refused(): void
    {
        $seller = $this->seller();
        $row = $this->listing($seller);

        Sanctum::actingAs($seller);

        $this->putJson("/api/v2/business/retail-listings/{$row->id}", [
            'price' => 100,
            'stock' => 100,
            'is_active' => true,
            'governorate_ids' => [999999],
        ])->assertStatus(422)->assertJsonValidationErrors('governorate_ids.0');
    }

    /** Nothing that exists today changed: every pre-existing row has no restriction. */
    public function test_the_default_is_no_restriction_so_nothing_already_listed_moved(): void
    {
        $restricted = DB::table('business_catalog_listings')
            ->whereNotNull('governorate_ids')
            ->count();

        $this->assertSame(0, $restricted, 'a live listing was governorate-restricted by the migration');
    }
}
