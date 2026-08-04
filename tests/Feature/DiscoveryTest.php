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

    /**
     * Naming an item type narrows to whoever actually offers it — this is the
     * half of the rule that must never soften. A customer asking for «سحب عينة
     * بالمنزل» is asking a yes/no question, and a doctor who never said he does
     * it is a wrong answer, not a lenient one.
     */
    public function test_naming_an_item_type_narrows_to_who_offers_it(): void
    {
        $price = DB::table('business_service_prices')
            ->where('is_active', 1)
            ->whereNotNull('child_id')
            ->where('bookable_item_type', '!=', '')
            ->whereNotNull('bookable_item_type')
            ->first();

        if (! $price) {
            $this->markTestSkipped('Needs an active priced row carrying an item type.');
        }

        $res = $this->getJson(
            "/api/v2/discovery/businesses?child_id={$price->child_id}&item_types[]={$price->bookable_item_type}"
        )->assertOk();

        foreach ($res->json('data.businesses.data') as $business) {
            $this->assertTrue(
                DB::table('business_service_prices')
                    ->where('business_id', (int) $business['id'])
                    ->where('bookable_item_type', $price->bookable_item_type)
                    ->where('is_active', 1)
                    ->exists(),
                "business #{$business['id']} was returned for a type it does not offer"
            );
        }

        // And a type nobody under that child has priced returns nobody.
        $unoffered = 'booking_home_visit_' . uniqid();

        $this->getJson("/api/v2/discovery/businesses?child_id={$price->child_id}&item_types[]={$unoffered}")
            ->assertOk()
            ->assertJsonPath('data.businesses.total', 0);
    }

    /**
     * The other half, changed on 2026-08-05: browsing without naming a type
     * returns every business in the child, priced or not.
     *
     * Requiring a priced row here hid 1,702 of 1,704 accounts. The pricing
     * screen is built and nobody has used it yet, and a customer reading an
     * empty list cannot tell an empty platform from a broken one. `has_prices`
     * is what the card uses to say «اتصل للسعر» instead.
     */
    public function test_a_business_with_no_prices_still_appears_when_browsing(): void
    {
        $childId = DB::table('users as u')
            ->where('u.type', 'business')
            ->whereNotNull('u.category_child_id')
            ->whereNotExists(fn ($q) => $q->from('business_service_prices as p')
                ->whereColumn('p.business_id', 'u.id'))
            ->value('u.category_child_id');

        if (! $childId) {
            $this->markTestSkipped('Every business has priced something.');
        }

        $res = $this->getJson("/api/v2/discovery/businesses?child_id={$childId}")->assertOk();

        $returned = $res->json('data.businesses.data');

        $this->assertNotEmpty($returned, 'a child full of unpriced businesses must not read as empty');

        $unpriced = collect($returned)->first(fn ($b) => $b['has_prices'] === false);

        $this->assertNotNull($unpriced, 'a business that priced nothing must still be listed');
        $this->assertSame([], $unpriced['offered_types']);
    }

    public function test_filters_requires_a_child_id(): void
    {
        $this->getJson('/api/v2/discovery/filters')->assertStatus(422);
    }
}
