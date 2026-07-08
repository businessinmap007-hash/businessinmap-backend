<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bespoke customer discovery (Phase 2): the offer=filter=index principle over
 * business_service_prices. A business's priced item types are both what it
 * offers and what the customer filters by. Reuses an existing active priced
 * row so the joins (service, category child) resolve against real data.
 */
class DiscoveryTest extends TestCase
{
    use DatabaseTransactions;

    private function anyActivePrice(): ?object
    {
        return DB::table('business_service_prices')
            ->where('is_active', 1)
            ->whereNotNull('child_id')
            ->whereNotNull('service_id')
            ->first();
    }

    public function test_filters_lists_the_service_offered_in_a_child(): void
    {
        $price = $this->anyActivePrice();
        if (! $price) {
            $this->markTestSkipped('Needs an active business_service_prices row.');
        }

        $res = $this->getJson("/api/v2/discovery/filters?child_id={$price->child_id}");

        $res->assertOk()->assertJsonPath('success', true);

        $serviceIds = array_map(fn ($s) => (int) $s['id'], $res->json('data.services'));
        $this->assertContains((int) $price->service_id, $serviceIds, 'the offered service must appear as a filter');
    }

    public function test_businesses_returns_only_sellers_that_offer_the_filter(): void
    {
        $price = $this->anyActivePrice();
        if (! $price) {
            $this->markTestSkipped('Needs an active business_service_prices row.');
        }

        $res = $this->getJson("/api/v2/discovery/businesses?child_id={$price->child_id}&service_id={$price->service_id}");

        $res->assertOk();

        // Every returned business must actually offer that service in that child.
        $businessIds = array_map(fn ($b) => (int) $b['id'], $res->json('data.businesses.data'));
        foreach ($businessIds as $bid) {
            $offers = DB::table('business_service_prices')
                ->where('business_id', $bid)
                ->where('child_id', $price->child_id)
                ->where('service_id', $price->service_id)
                ->where('is_active', 1)
                ->exists();
            $this->assertTrue($offers, "business #{$bid} must offer the filtered service");
        }
    }

    public function test_filters_requires_a_child_id(): void
    {
        $this->getJson('/api/v2/discovery/filters')->assertStatus(422);
    }
}
