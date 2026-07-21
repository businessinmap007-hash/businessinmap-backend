<?php

namespace Tests\Feature;

use App\Models\CommercialOffer;
use App\Models\OfferFollow;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The business-offers service, walked end to end over the API — the last of the
 * six typed services to get a journey test.
 *
 * Discipline (see the journey-test rule): every id the walk acts on comes from a
 * PRIOR API response, never a hardcoded or model-made one. A business subscribes,
 * publishes an offer, that offer surfaces in public discovery and on its
 * storefront, a client views/follows the seller, the business toggles/edits/
 * boosts/reads performance, and finally deletes it — after which it is gone from
 * both discovery and the seller's own list.
 */
class BusinessOfferJourneyTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;
    private User $client;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $service = DB::table('platform_services')->where('key', 'business_offers')->where('is_active', 1)->first();
        if (! $service) {
            $this->markTestSkipped('business_offers platform service is not active.');
        }
        $this->serviceId = (int) $service->id;

        $this->business = User::query()->where('type', 'business')->orderBy('id')->first();
        $this->client = User::query()->where('type', 'client')->orderBy('id')->first()
            ?: User::query()->where('type', '!=', 'business')->orderBy('id')->first();

        if (! $this->business || ! $this->client) {
            $this->markTestSkipped('Needs a business and a client user.');
        }

        // Subscribe the business so publishing an offer is not gated.
        DB::table('user_platform_service')->updateOrInsert(
            ['user_id' => (int) $this->business->id, 'platform_service_id' => $this->serviceId],
            ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()],
        );

        // Start from a clean storefront so discovery assertions are unambiguous.
        CommercialOffer::query()->where('seller_business_id', (int) $this->business->id)->forceDelete();
    }

    /** @return array<int,int> ids of offers on the seller's storefront in discovery */
    private function discoveredOfferIds(): array
    {
        return collect(
            $this->getJson('/api/v2/offers?seller_business_id=' . $this->business->id . '&per_page=50')
                ->assertOk()
                ->json('data.offers.data')
        )->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function test_a_business_publishes_an_offer_and_walks_it_to_deletion(): void
    {
        // ── 1. Publish ────────────────────────────────────────────────
        Sanctum::actingAs($this->business);

        $created = $this->postJson('/api/v2/business/offers', [
            'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
            'offerable_id' => 0,
            'audience_type' => CommercialOffer::AUDIENCE_BOTH,
            'base_price' => 200,
            'final_price' => 150,
            'title_ar' => 'عرض رحلة اختبار',
        ])->assertCreated()->assertJsonPath('success', true);

        $offerId = (int) $created->json('data.offer.id');
        $this->assertGreaterThan(0, $offerId);
        // ranking_score is system-owned — a fresh offer starts at 0.
        $this->assertSame(0.0, (float) CommercialOffer::whereKey($offerId)->value('ranking_score'));

        // ── 2. It is on the seller's own list ─────────────────────────
        $mine = $this->getJson('/api/v2/business/offers')->assertOk()->json('data.offers.data');
        $this->assertContains($offerId, collect($mine)->pluck('id')->map(fn ($i) => (int) $i)->all());

        // ── 3. It surfaces in PUBLIC discovery ────────────────────────
        $this->assertContains($offerId, $this->discoveredOfferIds(), 'a published offer is publicly discoverable');

        // ── 4. Show + storefront-by-business (public) ─────────────────
        $this->getJson("/api/v2/offers/{$offerId}")->assertOk()->assertJsonPath('data.offer.id', $offerId);

        $byBiz = $this->getJson("/api/v2/offers/business/{$this->business->id}")->assertOk()->json('data.offers.data');
        $this->assertContains($offerId, collect($byBiz)->pluck('id')->map(fn ($i) => (int) $i)->all());

        // ── 5. A client views (tracks) the offer ──────────────────────
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/offers/{$offerId}/track", ['event_type' => 'view'])->assertSuccessful();

        // ── 6. The client follows the SELLER (id from the offer above) ─
        $followId = (int) $this->postJson('/api/v2/offer-follows', [
            'followable_type' => OfferFollow::FOLLOW_BUSINESS,
            'followable_id' => (int) $this->business->id,
        ])->assertCreated()->json('data.follow.id');
        $this->assertGreaterThan(0, $followId);

        $followsBody = $this->getJson('/api/v2/offer-follows')->assertOk()->json('data.follows');
        $followRows = $followsBody['data'] ?? $followsBody; // paginated or plain list
        $followIds = collect($followRows)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $this->assertContains($followId, $followIds);

        $this->deleteJson("/api/v2/offer-follows/{$followId}")->assertOk();

        // ── 7. Boost packages are listable by the seller ──────────────
        Sanctum::actingAs($this->business);
        $this->getJson('/api/v2/business/offers/boost/packages')->assertOk();

        // ── 8. Toggle off → gone from discovery → toggle back ─────────
        $this->postJson("/api/v2/business/offers/{$offerId}/toggle")->assertOk();
        $this->assertNotContains($offerId, $this->discoveredOfferIds(), 'a paused offer leaves discovery');

        $this->postJson("/api/v2/business/offers/{$offerId}/toggle")->assertOk();
        $this->assertContains($offerId, $this->discoveredOfferIds(), 'reactivating brings it back');

        // ── 9. Edit the price (ranking_score stays system-owned) ──────
        $this->putJson("/api/v2/business/offers/{$offerId}", [
            'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
            'offerable_id' => 0,
            'base_price' => 200,
            'final_price' => 120,
            'ranking_score' => 999999,
        ])->assertOk();

        $fresh = CommercialOffer::whereKey($offerId)->first();
        $this->assertSame(120.0, (float) $fresh->final_price, 'the edit took');
        $this->assertSame(0.0, (float) $fresh->ranking_score, 'ranking_score cannot be raised by the seller');

        // ── 10. Performance is readable (the view above was recorded) ─
        $this->getJson('/api/v2/business/offers/performance/me')->assertOk();

        // ── 11. Delete → gone from the seller list AND discovery ──────
        $this->deleteJson("/api/v2/business/offers/{$offerId}")->assertOk();

        $mineAfter = collect($this->getJson('/api/v2/business/offers')->json('data.offers.data'))
            ->pluck('id')->map(fn ($i) => (int) $i)->all();
        $this->assertNotContains($offerId, $mineAfter, 'a deleted offer leaves the seller list');
        $this->assertNotContains($offerId, $this->discoveredOfferIds(), 'and leaves discovery');
    }

    public function test_publishing_is_refused_without_a_subscription(): void
    {
        // Remove the subscription the setUp added.
        DB::table('user_platform_service')
            ->where('user_id', (int) $this->business->id)
            ->where('platform_service_id', $this->serviceId)
            ->delete();

        Sanctum::actingAs($this->business);

        $this->postJson('/api/v2/business/offers', [
            'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
            'offerable_id' => 0,
            'base_price' => 100,
            'final_price' => 80,
        ])->assertStatus(422);
    }
}
