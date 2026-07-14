<?php

namespace Tests\Feature;

use App\Models\CommercialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Gap #1 coverage: the client-facing offer discovery surface, focused on the
 * audience-visibility rule (clients must never see B2B or PRIVATE offers) plus
 * price filtering and the show/compare/lowest endpoints. Rolls back.
 */
class OfferDiscoveryApiTest extends TestCase
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

        // A distinctive offerable id so our rows don't collide with seeded offers.
        $this->offerableId = 900000 + random_int(1000, 9999);
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
            'title_ar' => 'عرض اكتشاف',
            'title_en' => 'Discovery offer',
            'base_price' => 100,
            'final_price' => 90,
            'currency' => 'EGP',
            'availability_mode' => CommercialOffer::AVAILABILITY_INSTANT,
            'status' => CommercialOffer::STATUS_ACTIVE,
        ], $attributes));
    }

    private function idsFor(User $actor, string $query = ''): array
    {
        return collect(
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v2/offers?offerable_id=' . $this->offerableId . '&per_page=50' . $query)
                ->assertOk()
                ->json('data.offers.data')
        )->pluck('id')->all();
    }

    public function test_client_sees_b2c_and_both_but_not_b2b_or_private(): void
    {
        $b2c = $this->makeOffer(CommercialOffer::AUDIENCE_B2C);
        $both = $this->makeOffer(CommercialOffer::AUDIENCE_BOTH);
        $b2b = $this->makeOffer(CommercialOffer::AUDIENCE_B2B);
        $private = $this->makeOffer(CommercialOffer::AUDIENCE_PRIVATE);

        $ids = $this->idsFor($this->client);

        $this->assertContains($b2c->id, $ids);
        $this->assertContains($both->id, $ids);
        $this->assertNotContains($b2b->id, $ids, 'A client must not see B2B offers.');
        $this->assertNotContains($private->id, $ids, 'A client must not see PRIVATE offers.');
    }

    public function test_business_sees_b2b_and_both_but_not_b2c(): void
    {
        $b2c = $this->makeOffer(CommercialOffer::AUDIENCE_B2C);
        $both = $this->makeOffer(CommercialOffer::AUDIENCE_BOTH);
        $b2b = $this->makeOffer(CommercialOffer::AUDIENCE_B2B);

        $ids = $this->idsFor($this->business);

        $this->assertContains($b2b->id, $ids);
        $this->assertContains($both->id, $ids);
        $this->assertNotContains($b2c->id, $ids, 'A business browsing sees B2B/both, not B2C.');
    }

    public function test_inactive_offer_is_not_discoverable(): void
    {
        $paused = $this->makeOffer(CommercialOffer::AUDIENCE_B2C, [
            'status' => CommercialOffer::STATUS_PAUSED,
        ]);

        $this->assertNotContains($paused->id, $this->idsFor($this->client));
    }

    public function test_price_filter_bounds_results(): void
    {
        $cheap = $this->makeOffer(CommercialOffer::AUDIENCE_B2C, ['final_price' => 50]);
        $pricey = $this->makeOffer(CommercialOffer::AUDIENCE_B2C, ['final_price' => 500]);

        $ids = $this->idsFor($this->client, '&max_price=100');

        $this->assertContains($cheap->id, $ids);
        $this->assertNotContains($pricey->id, $ids);
    }

    public function test_show_returns_active_visible_offer(): void
    {
        $offer = $this->makeOffer(CommercialOffer::AUDIENCE_B2C);

        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.offer.id', $offer->id);
    }

    public function test_show_of_private_offer_is_hidden_from_client(): void
    {
        $private = $this->makeOffer(CommercialOffer::AUDIENCE_PRIVATE);

        $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/offers/{$private->id}")
            ->assertNotFound();
    }

    public function test_lowest_endpoint_requires_offerable_identity(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v2/offers/lowest')
            ->assertStatus(422);
    }

    public function test_compare_requires_offerable_identity(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/v2/offers/compare', [])
            ->assertStatus(422);
    }

    public function test_guest_browsing_is_public_but_only_sees_b2c_and_both(): void
    {
        // Offer discovery is intentionally public (guests browse as clients).
        // The privacy guarantee must still hold: no B2B, no PRIVATE for a guest.
        $b2c = $this->makeOffer(CommercialOffer::AUDIENCE_B2C);
        $b2b = $this->makeOffer(CommercialOffer::AUDIENCE_B2B);
        $private = $this->makeOffer(CommercialOffer::AUDIENCE_PRIVATE);

        $ids = collect(
            $this->getJson('/api/v2/offers?offerable_id=' . $this->offerableId . '&per_page=50')
                ->assertOk()
                ->json('data.offers.data')
        )->pluck('id')->all();

        $this->assertContains($b2c->id, $ids);
        $this->assertNotContains($b2b->id, $ids, 'A guest must not see B2B offers.');
        $this->assertNotContains($private->id, $ids, 'A guest must not see PRIVATE offers.');
    }
}
