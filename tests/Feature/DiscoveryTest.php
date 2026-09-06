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

    public function test_governorate_and_city_narrow_the_results(): void
    {
        $childId = (int) DB::table('category_children_master')->value('id');
        $govA = (int) DB::table('governorates')->orderBy('id')->value('id');
        $govB = (int) DB::table('governorates')->orderBy('id', 'desc')->value('id');
        $cityInA = (int) DB::table('cities')->where('governorate_id', $govA)->value('id');

        $bizA = \App\Models\User::create([
            'name' => 'discovery-loc-a', 'email' => 'discovery-loc-a-' . uniqid() . '@example.test',
            'phone' => '01' . random_int(100000000, 999999999), 'password' => 'secret-password',
            'type' => 'business', 'category_child_id' => $childId, 'api_token' => \Illuminate\Support\Str::random(80),
            'governorate_id' => $govA, 'city_id' => $cityInA,
        ]);
        $bizB = \App\Models\User::create([
            'name' => 'discovery-loc-b', 'email' => 'discovery-loc-b-' . uniqid() . '@example.test',
            'phone' => '01' . random_int(100000000, 999999999), 'password' => 'secret-password',
            'type' => 'business', 'category_child_id' => $childId, 'api_token' => \Illuminate\Support\Str::random(80),
            'governorate_id' => $govB,
        ]);

        $byGovernorate = $this->getJson("/api/v2/discovery/businesses?child_id={$childId}&governorate_id={$govA}")
            ->assertOk()->json('data.businesses.data');
        $ids = array_column($byGovernorate, 'id');
        $this->assertContains($bizA->id, $ids);
        $this->assertNotContains($bizB->id, $ids);

        $byCity = $this->getJson(
            "/api/v2/discovery/businesses?child_id={$childId}&governorate_id={$govA}&city_id={$cityInA}"
        )->assertOk()->json('data.businesses.data');
        $this->assertContains($bizA->id, array_column($byCity, 'id'));
    }

    private function makeBusiness(string $namePrefix, ?int $categoryId = null): \App\Models\User
    {
        return \App\Models\User::create([
            'name' => $namePrefix,
            'email' => 'recommended-' . uniqid() . '@example.test',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => 'secret-password',
            'type' => 'business',
            'category_id' => $categoryId,
            'api_token' => \Illuminate\Support\Str::random(80),
        ]);
    }

    private function rate(\App\Models\User $business, int $starsSum, int $reviewCount): void
    {
        DB::table('user_operation_ratings')->insert([
            'user_id' => $business->id,
            'role' => \App\Models\UserOperationRating::ROLE_BUSINESS,
            'total_operations' => $reviewCount,
            'success_count' => $reviewCount,
            'cancelled_count' => 0,
            'disputed_count' => 0,
            'fault_count' => 0,
            'vindicated_count' => 0,
            'review_stars_sum' => $starsSum,
            'review_count' => $reviewCount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_recommended_ranks_by_average_stars_highest_first(): void
    {
        // A shared, unique name prefix scopes the query (via `q`) to just
        // these 3 rows — the dev DB already has hundreds of zero-rated
        // businesses that would otherwise bury a freshly created one past
        // the first page, since ties order oldest-id-first.
        $tag = 'rec-rank-' . uniqid();

        $low = $this->makeBusiness("{$tag}-low");
        $this->rate($low, starsSum: 6, reviewCount: 3); // 2.0 average

        $high = $this->makeBusiness("{$tag}-high");
        $this->rate($high, starsSum: 20, reviewCount: 4); // 5.0 average

        $unrated = $this->makeBusiness("{$tag}-unrated");

        $res = $this->getJson("/api/v2/discovery/recommended?q={$tag}&per_page=50")->assertOk();
        $rows = collect($res->json('data.businesses.data'))->keyBy('id');

        // assertEquals, not assertSame: json_encode drops the trailing .0 off
        // a whole-number float (5.0 round-trips through JSON as the int 5).
        $this->assertEquals(5.0, $rows[$high->id]['stars_average']);
        $this->assertEquals(2.0, $rows[$low->id]['stars_average']);
        $this->assertEquals(0.0, $rows[$unrated->id]['stars_average']);
        $this->assertSame(0, $rows[$unrated->id]['review_count']);

        $ids = array_column($res->json('data.businesses.data'), 'id');
        $this->assertLessThan(
            array_search($low->id, $ids),
            array_search($high->id, $ids),
            'the 5.0-star business must rank above the 2.0-star one'
        );
        $this->assertLessThan(
            array_search($unrated->id, $ids),
            array_search($low->id, $ids),
            'a rated business must rank above a never-rated one'
        );
    }

    public function test_recommended_category_id_narrows_to_that_root(): void
    {
        $rootA = (int) DB::table('categories')->orderBy('id')->value('id');
        $rootB = (int) DB::table('categories')->orderBy('id', 'desc')->value('id');
        $tag = 'rec-cat-' . uniqid();

        $inA = $this->makeBusiness("{$tag}-a", $rootA);
        $inB = $this->makeBusiness("{$tag}-b", $rootB);

        $ids = array_column(
            $this->getJson("/api/v2/discovery/recommended?category_id={$rootA}&q={$tag}&per_page=50")
                ->assertOk()->json('data.businesses.data'),
            'id'
        );

        $this->assertContains($inA->id, $ids);
        $this->assertNotContains($inB->id, $ids);
    }

    public function test_recommended_requires_no_parameters_at_all(): void
    {
        $this->getJson('/api/v2/discovery/recommended')->assertOk()->assertJsonPath('success', true);
    }
}
