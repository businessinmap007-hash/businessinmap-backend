<?php

namespace Tests\Feature;

use App\Models\CommercialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Gap #1 coverage: the unified /search/offers surface (businesses + offers +
 * best_offer). Same audience-privacy rule as discovery: clients/guests never
 * see B2B or PRIVATE. Rolls back.
 */
class SearchOffersApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $client;
    private User $business;
    private int $offerableId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        $this->client = User::query()->where('type', 'client')->orderBy('id')->first()
            ?: User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail();

        $this->offerableId = 800000 + random_int(1000, 9999);
    }

    private function makeOffer(string $audience, array $attributes = []): CommercialOffer
    {
        return CommercialOffer::create(array_merge([
            'offerable_type' => CommercialOffer::OFFERABLE_PRODUCT,
            'offerable_id' => $this->offerableId,
            'owner_business_id' => $this->business->id,
            'seller_business_id' => $this->business->id,
            'source_type' => CommercialOffer::SOURCE_DIRECT,
            'audience_type' => $audience,
            'title_ar' => 'عرض بحث',
            'title_en' => 'Search offer',
            'base_price' => 100,
            'final_price' => 90,
            'currency' => 'EGP',
            'availability_mode' => CommercialOffer::AVAILABILITY_INSTANT,
            'status' => CommercialOffer::STATUS_ACTIVE,
        ], $attributes));
    }

    private function searchOfferIds(?User $actor, string $query): array
    {
        $request = $actor ? $this->actingAs($actor, 'sanctum') : $this;

        return collect(
            $request->getJson('/api/v2/search/offers?' . $query)
                ->assertOk()
                ->json('data.offers.data')
        )->pluck('id')->all();
    }

    public function test_search_returns_the_expected_envelope(): void
    {
        $this->getJson('/api/v2/search/offers')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['query', 'businesses', 'offers', 'best_offer'],
            ]);
    }

    public function test_client_search_hides_b2b_and_private(): void
    {
        $b2c = $this->makeOffer(CommercialOffer::AUDIENCE_B2C);
        $b2b = $this->makeOffer(CommercialOffer::AUDIENCE_B2B);
        $private = $this->makeOffer(CommercialOffer::AUDIENCE_PRIVATE);

        $ids = $this->searchOfferIds(
            $this->client,
            'business_id=' . $this->business->id . '&per_page=50'
        );

        $this->assertContains($b2c->id, $ids);
        $this->assertNotContains($b2b->id, $ids);
        $this->assertNotContains($private->id, $ids);
    }

    public function test_guest_search_is_public_and_still_hides_b2b(): void
    {
        $b2c = $this->makeOffer(CommercialOffer::AUDIENCE_B2C);
        $b2b = $this->makeOffer(CommercialOffer::AUDIENCE_B2B);

        $ids = $this->searchOfferIds(
            null,
            'business_id=' . $this->business->id . '&per_page=50'
        );

        $this->assertContains($b2c->id, $ids);
        $this->assertNotContains($b2b->id, $ids);
    }

    public function test_by_business_route_scopes_to_that_business(): void
    {
        $mine = $this->makeOffer(CommercialOffer::AUDIENCE_B2C);

        $ids = collect(
            $this->getJson("/api/v2/search/business/{$this->business->id}/offers?per_page=50")
                ->assertOk()
                ->json('data.offers.data')
        )->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
    }

    public function test_invalid_sort_is_rejected(): void
    {
        $this->getJson('/api/v2/search/offers?sort=nonsense')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    }
}
