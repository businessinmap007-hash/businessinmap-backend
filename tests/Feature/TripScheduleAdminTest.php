<?php

namespace Tests\Feature;

use App\Models\Governorate;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * AdminV2 oversight for the scheduling service. Read-only, so the things worth
 * pinning are that the pages render with real rows in them, that the filters
 * narrow rather than blow up, and that the counts mean what they say. Rolls back.
 */
class TripScheduleAdminTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $business;
    private User $client;
    private TripSchedule $leg;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();
        $business = User::query()->where('type', 'business')->orderBy('id')->first();
        $client = User::query()->where('type', '!=', 'business')->where('type', '!=', 'admin')->orderBy('id')->first();
        $govs = Governorate::query()->orderBy('id')->limit(2)->pluck('id');

        if (! $admin || ! $business || ! $client || $govs->count() < 2) {
            $this->markTestSkipped('Needs an admin, a business, a client and 2 governorates.');
        }

        $this->admin = $admin;
        $this->business = $business;
        $this->client = $client;

        $this->leg = TripSchedule::create([
            'business_id' => (int) $business->id,
            'mode' => TripSchedule::MODE_PASSENGER,
            'scope' => TripSchedule::SCOPE_DOMESTIC,
            'origin_governorate_id' => (int) $govs[0],
            'destination_governorate_id' => (int) $govs[1],
            'schedule_pattern' => TripSchedule::PATTERN_WEEKLY,
            'day_of_week' => 0,
            'departure_time' => '09:00',
            'capacity' => 14,
            'capacity_unit' => 'مقعد',
            'price' => 120,
            'currency' => 'EGP',
            'status' => TripSchedule::STATUS_ACTIVE,
        ]);
    }

    public function test_a_non_admin_is_bounced(): void
    {
        $this->actingAs($this->business)->get('/admin/trip-schedules')->assertStatus(302);
        $this->actingAs($this->business)->get('/admin/trip-schedules/reservations')->assertStatus(302);
    }

    public function test_the_schedules_page_renders_the_leg(): void
    {
        $res = $this->actingAs($this->admin)->get('/admin/trip-schedules');

        $res->assertOk();
        $res->assertSee(__('خطوط التشغيل (الجدولة)'));
        $res->assertSee($this->business->name, false);
        $res->assertSee('الأحد', false); // day_of_week 0 read as a name, not "يوم 0"
    }

    public function test_the_reservations_page_renders(): void
    {
        TripReservation::create([
            'trip_schedule_id' => (int) $this->leg->id,
            'business_id' => (int) $this->business->id,
            'client_id' => (int) $this->client->id,
            'units' => 2,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_PENDING,
        ]);

        $res = $this->actingAs($this->admin)->get('/admin/trip-schedules/reservations');

        $res->assertOk();
        $res->assertSee($this->client->name, false);
    }

    public function test_filters_narrow_the_list(): void
    {
        $res = $this->actingAs($this->admin)->get('/admin/trip-schedules?mode=passenger&scope=domestic&status=active');
        $res->assertOk();
        $this->assertContains($this->leg->id, $res->viewData('schedules')->pluck('id')->all());

        // A mode the leg is not.
        $res = $this->actingAs($this->admin)->get('/admin/trip-schedules?mode=freight');
        $res->assertOk();
        $this->assertNotContains($this->leg->id, $res->viewData('schedules')->pluck('id')->all());
    }

    public function test_an_unknown_filter_value_is_ignored_not_applied(): void
    {
        $res = $this->actingAs($this->admin)->get('/admin/trip-schedules?mode=nonsense&scope=nonsense');

        $res->assertOk();
        $this->assertContains($this->leg->id, $res->viewData('schedules')->pluck('id')->all(), 'a junk mode must not silently filter everything out');
    }

    public function test_active_reservations_count_only_counts_capacity_holders(): void
    {
        TripReservation::create([
            'trip_schedule_id' => (int) $this->leg->id,
            'business_id' => (int) $this->business->id,
            'client_id' => (int) $this->client->id,
            'units' => 2,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_CONFIRMED,
        ]);

        // Cancelled: released its capacity, so it must not be counted.
        TripReservation::create([
            'trip_schedule_id' => (int) $this->leg->id,
            'business_id' => (int) $this->business->id,
            'client_id' => (int) $this->client->id,
            'units' => 5,
            'currency' => 'EGP',
            'source' => TripReservation::SOURCE_APP,
            'status' => TripReservation::STATUS_CANCELLED,
        ]);

        $res = $this->actingAs($this->admin)->get('/admin/trip-schedules');
        $row = $res->viewData('schedules')->firstWhere('id', $this->leg->id);

        $this->assertSame(1, (int) $row->active_reservations_count, 'the cancelled reservation must not count as active');
    }
}
