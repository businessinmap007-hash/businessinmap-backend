<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Governorate;
use App\Models\PlatformServiceItemType;
use App\Models\TripSchedule;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\UserOperationRating;
use App\Models\RatingOutcomeEvent;
use App\Services\Ratings\RatingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Scheduling / routes service: a business publishes trip legs and customers
 * search a route + day, getting carriers ranked by trust (guarantee + rating).
 * Rolls back.
 */
class TripScheduleApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;
    private User $otherBusiness;
    private int $cairo;
    private int $damietta;

    protected function setUp(): void
    {
        parent::setUp();

        $businesses = User::query()->where('type', 'business')->orderBy('id')->limit(2)->get();
        $govs = Governorate::query()->orderBy('id')->limit(2)->pluck('id');

        if ($businesses->count() < 2 || $govs->count() < 2) {
            $this->markTestSkipped('Needs 2 business users and 2 governorates.');
        }

        $this->business = $businesses[0];
        $this->otherBusiness = $businesses[1];
        $this->cairo = (int) $govs[0];
        $this->damietta = (int) $govs[1];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'mode' => TripSchedule::MODE_FREIGHT,
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->damietta,
            'schedule_pattern' => TripSchedule::PATTERN_WEEKLY,
            'day_of_week' => 0, // Sunday
            'departure_time' => '09:00',
            'capacity' => 10,
            'capacity_unit' => 'parcel',
            'price' => 150,
        ], $overrides);
    }

    public function test_business_can_publish_and_it_appears_in_search(): void
    {
        Sanctum::actingAs($this->business);

        $create = $this->postJson('/api/v2/business/schedules', $this->payload());
        $create->assertCreated();
        $id = (int) $create->json('data.schedule.id');

        $search = $this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->damietta,
            'day_of_week' => 0,
        ]));

        $search->assertOk();
        $ids = collect($search->json('data.results'))->pluck('schedule.id')->all();
        $this->assertContains($id, $ids);
    }

    public function test_search_excludes_a_different_day(): void
    {
        Sanctum::actingAs($this->business);
        $id = (int) $this->postJson('/api/v2/business/schedules', $this->payload())->json('data.schedule.id');

        $search = $this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->damietta,
            'day_of_week' => 3, // Wednesday — no schedule on this day
        ]));

        $ids = collect($search->json('data.results'))->pluck('schedule.id')->all();
        $this->assertNotContains($id, $ids);
    }

    public function test_on_demand_matches_any_day(): void
    {
        Sanctum::actingAs($this->business);
        $id = (int) $this->postJson('/api/v2/business/schedules', $this->payload([
            'schedule_pattern' => TripSchedule::PATTERN_ON_DEMAND,
            'day_of_week' => null,
        ]))->json('data.schedule.id');

        $search = $this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->damietta,
            'day_of_week' => 5,
        ]));

        $ids = collect($search->json('data.results'))->pluck('schedule.id')->all();
        $this->assertContains($id, $ids);
    }

    public function test_guaranteed_carrier_ranks_above_and_shows_trust(): void
    {
        // The other business publishes first (no guarantee, no rating).
        Sanctum::actingAs($this->otherBusiness);
        $this->postJson('/api/v2/business/schedules', $this->payload());

        // Our business publishes with an active guarantee + spotless rating.
        $level = \App\Models\GuaranteeLevel::query()->create([
            'code' => 'sched_trust_'.uniqid(),
            'name_ar' => 'مستوى اختبار',
            'name_en' => 'Test Level',
            'target_type' => 'business',
            'required_locked_amount' => 1000,
            'pending_coverage_amount' => 1000,
            'active_coverage_amount' => 1200,
            'required_completed_operations' => 0,
            'required_trust_score' => 0,
            'priority' => 1,
            'is_active' => 1,
        ]);

        UserGuarantee::create([
            'user_id' => $this->business->id,
            'target_type' => 'business',
            'purchased_level_id' => $level->id,
            'effective_level_id' => $level->id,
            'status' => UserGuarantee::STATUS_ACTIVE,
            'locked_amount' => 1000,
            'pending_coverage_amount' => 1000,
            'active_coverage_amount' => 1200,
            'current_coverage_amount' => 1200,
            'used_coverage_amount' => 0,
            'completed_operations_count' => 10,
            'trust_score' => 90,
        ]);

        $ratings = app(RatingService::class);
        UserOperationRating::query()->where('user_id', $this->business->id)->delete();
        RatingOutcomeEvent::query()->where('ratee_user_id', $this->business->id)->delete();
        for ($i = 1; $i <= 6; $i++) {
            $ratings->recordOutcome(
                $this->business->id,
                UserOperationRating::ROLE_BUSINESS,
                RatingOutcomeEvent::OUTCOME_SUCCESS,
                RatingOutcomeEvent::OP_BOOKING,
                $i
            );
        }

        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/schedules', $this->payload());

        $results = collect($this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->damietta,
            'day_of_week' => 0,
        ]))->json('data.results'));

        // Guaranteed, top-rated carrier surfaces first with a populated trust block.
        $top = $results->first();
        $this->assertSame((int) $this->business->id, (int) $top['schedule']['business_id']);
        $this->assertTrue($top['trust']['has_active_guarantee']);
        $this->assertSame(1200.0, (float) $top['trust']['available_coverage']);
        $this->assertSame(100.0, (float) $top['trust']['success_rate']);
    }

    public function test_backhaul_return_leg_hangs_off_its_parent(): void
    {
        Sanctum::actingAs($this->business);
        $parent = (int) $this->postJson('/api/v2/business/schedules', $this->payload())->json('data.schedule.id');

        // Reverse route, flagged as a discounted return leg.
        $leg = $this->postJson('/api/v2/business/schedules', $this->payload([
            'origin_governorate_id' => $this->damietta,
            'destination_governorate_id' => $this->cairo,
            'is_return_leg' => true,
            'parent_trip_id' => $parent,
            'price' => 100,
        ]));

        $leg->assertCreated();
        $this->assertTrue($leg->json('data.schedule.is_return_leg'));
        $this->assertSame($parent, (int) $leg->json('data.schedule.parent_trip_id'));
    }

    public function test_weekly_requires_a_day_of_week(): void
    {
        Sanctum::actingAs($this->business);

        $this->postJson('/api/v2/business/schedules', $this->payload(['day_of_week' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('day_of_week');
    }

    public function test_origin_and_destination_must_differ(): void
    {
        Sanctum::actingAs($this->business);

        $this->postJson('/api/v2/business/schedules', $this->payload([
            'destination_governorate_id' => $this->cairo,
        ]))->assertStatus(422)->assertJsonValidationErrors('destination_governorate_id');
    }

    public function test_a_business_cannot_touch_another_businesss_schedule(): void
    {
        Sanctum::actingAs($this->business);
        $id = (int) $this->postJson('/api/v2/business/schedules', $this->payload())->json('data.schedule.id');

        Sanctum::actingAs($this->otherBusiness);
        $this->deleteJson("/api/v2/business/schedules/{$id}")->assertNotFound();
    }

    public function test_vehicle_types_lookup_lists_classes_by_mode(): void
    {
        $res = $this->getJson('/api/v2/schedules/vehicle-types?mode=passenger');
        $res->assertOk();

        $keys = collect($res->json('data.vehicle_types'))->pluck('key')->all();
        $this->assertContains('passenger_minibus', $keys);
        $this->assertNotContains('container_20ft', $keys); // that is freight/international
    }

    public function test_publish_with_vehicle_type_is_searchable_and_filterable(): void
    {
        $minibus = (int) PlatformServiceItemType::query()->where('key', 'passenger_minibus')->value('id');

        Sanctum::actingAs($this->business);
        $create = $this->postJson('/api/v2/business/schedules', $this->payload([
            'mode' => TripSchedule::MODE_PASSENGER,
            'vehicle_type_id' => $minibus,
            'vehicle_label' => 'هيونداي H1',
        ]));
        $create->assertCreated()
            ->assertJsonPath('data.schedule.vehicle_type.id', $minibus)
            ->assertJsonPath('data.schedule.vehicle_label', 'هيونداي H1');
        $id = (int) $create->json('data.schedule.id');

        $res = $this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_governorate_id' => $this->cairo,
            'destination_governorate_id' => $this->damietta,
            'day_of_week' => 0,
            'vehicle_type_id' => $minibus,
        ]));
        $this->assertContains($id, collect($res->json('data.results'))->pluck('schedule.id')->all());
    }

    public function test_international_leg_anchors_on_country(): void
    {
        // Self-sufficient: create two throwaway countries (rolled back).
        $countries = collect([
            Country::create(['name_ar' => 'دولة أ', 'name_en' => 'Country A', 'iso2' => 'XA']),
            Country::create(['name_ar' => 'دولة ب', 'name_en' => 'Country B', 'iso2' => 'XB']),
        ])->pluck('id');
        $container = (int) PlatformServiceItemType::query()->where('key', 'container_20ft')->value('id');

        Sanctum::actingAs($this->business);
        $create = $this->postJson('/api/v2/business/schedules', [
            'mode' => TripSchedule::MODE_FREIGHT,
            'scope' => 'international',
            'origin_country_id' => (int) $countries[0],
            'destination_country_id' => (int) $countries[1],
            'vehicle_type_id' => $container,
            'schedule_pattern' => TripSchedule::PATTERN_ON_DEMAND,
            'capacity' => 28,
            'capacity_unit' => 'cbm',
            'price' => 5000,
        ]);
        $create->assertCreated()->assertJsonPath('data.schedule.scope', 'international');
        $id = (int) $create->json('data.schedule.id');

        // Found by country pair (no governorate involved).
        $res = $this->getJson('/api/v2/search/schedules?'.http_build_query([
            'origin_country_id' => (int) $countries[0],
            'destination_country_id' => (int) $countries[1],
        ]));
        $this->assertContains($id, collect($res->json('data.results'))->pluck('schedule.id')->all());
    }

    public function test_international_requires_a_country_pair(): void
    {
        Sanctum::actingAs($this->business);
        $this->postJson('/api/v2/business/schedules', [
            'mode' => TripSchedule::MODE_FREIGHT,
            'scope' => 'international',
            'schedule_pattern' => TripSchedule::PATTERN_ON_DEMAND,
        ])->assertStatus(422)->assertJsonValidationErrors('origin_country_id');
    }

    public function test_client_account_is_rejected(): void
    {
        $client = User::query()->where('type', '!=', 'business')->first();

        if (! $client) {
            $this->markTestSkipped('Needs a non-business user.');
        }

        Sanctum::actingAs($client);
        $this->postJson('/api/v2/business/schedules', $this->payload())->assertStatus(403);
    }
}
