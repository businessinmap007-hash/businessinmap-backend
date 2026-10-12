<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\ServiceChair;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * An appointment with the business itself, with no chairs and no unit, is ONE line: a time can be held once. The grid
 * hides a held time, but two customers (or a direct API call) used to pass it together — the server now judges it.
 */
class AppointmentOverlapGuardTest extends TestCase
{
    use DatabaseTransactions;

    private User $salon;

    private BusinessServicePrice $service;

    private Carbon $at;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = $this->account(User::TYPE_BUSINESS, 23, 51);
        $this->service = BusinessServicePrice::create([
            'business_id' => $this->salon->id, 'service_id' => 1, 'child_id' => 51,
            'bookable_item_type' => 'booking_appointment', 'price' => 150, 'currency' => 'EGP',
            'is_active' => 1, 'duration_minutes' => 45,
        ]);

        DB::table('business_working_hours')->where('business_id', $this->salon->id)->delete();
        foreach (range(0, 6) as $d) {
            DB::table('business_working_hours')->insert([
                'business_id' => $this->salon->id, 'day_of_week' => $d, 'is_closed' => 0,
                'open_time' => '00:00:00', 'close_time' => '23:59:00', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->at = now()->addDays(2)->setTime(11, 0, 0);
    }

    private function account(string $type, ?int $category = null, ?int $child = null): User
    {
        return User::create([
            'name' => 'T ' . Str::random(4), 'email' => 't' . uniqid() . '@example.test', 'password' => bcrypt('Test1234'),
            'type' => $type, 'category_id' => $category, 'category_child_id' => $child,
            'api_token' => Str::random(60), 'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    private function book(Carbon $start, ?Carbon $end = null, ?User $who = null)
    {
        Sanctum::actingAs($who ?: $this->account(User::TYPE_CLIENT));

        return $this->postJson('/api/v2/bookings', array_filter([
            'business_id' => $this->salon->id, 'service_id' => 1, 'offering_id' => $this->service->id,
            'offering_type' => 'service_price',
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $end ? $end->format('Y-m-d H:i:s') : null,
        ]));
    }

    public function test_the_same_time_is_held_once(): void
    {
        $this->book($this->at, $this->at->copy()->addMinutes(45))->assertCreated();

        $this->book($this->at, $this->at->copy()->addMinutes(45))->assertStatus(422)->assertJsonValidationErrors('starts_at');
    }

    public function test_an_overlapping_time_is_refused_and_an_adjacent_one_is_not(): void
    {
        $this->book($this->at, $this->at->copy()->addMinutes(45))->assertCreated();

        // starts inside the first
        $this->book($this->at->copy()->addMinutes(30), $this->at->copy()->addMinutes(75))->assertStatus(422);
        // ends inside the first
        $this->book($this->at->copy()->subMinutes(15), $this->at->copy()->addMinutes(30))->assertStatus(422);

        // right after, and right before: back to back is fine
        $this->book($this->at->copy()->addMinutes(45), $this->at->copy()->addMinutes(90))->assertCreated();
        $this->book($this->at->copy()->subMinutes(45), $this->at)->assertCreated();
    }

    public function test_a_booking_with_no_end_is_held_for_the_services_own_length(): void
    {
        $this->book($this->at)->assertCreated();

        // the service is 45 minutes: 10 minutes later is still inside it
        $this->book($this->at->copy()->addMinutes(10))->assertStatus(422);
        $this->book($this->at->copy()->addMinutes(45))->assertCreated();
    }

    public function test_a_cancelled_booking_frees_the_time(): void
    {
        $id = $this->book($this->at, $this->at->copy()->addMinutes(45))->assertCreated()->json('data.booking.id');
        Booking::query()->whereKey($id)->update(['status' => Booking::STATUS_CANCELLED]);

        $this->book($this->at, $this->at->copy()->addMinutes(45))->assertCreated();
    }

    public function test_another_business_is_not_affected(): void
    {
        $this->book($this->at, $this->at->copy()->addMinutes(45))->assertCreated();

        $other = $this->account(User::TYPE_BUSINESS, 23, 51);
        $price = BusinessServicePrice::create([
            'business_id' => $other->id, 'service_id' => 1, 'child_id' => 51,
            'bookable_item_type' => 'booking_appointment', 'price' => 150, 'currency' => 'EGP', 'is_active' => 1,
            'duration_minutes' => 45,
        ]);
        foreach (range(0, 6) as $d) {
            DB::table('business_working_hours')->insert([
                'business_id' => $other->id, 'day_of_week' => $d, 'is_closed' => 0,
                'open_time' => '00:00:00', 'close_time' => '23:59:00', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($this->account(User::TYPE_CLIENT));
        $this->postJson('/api/v2/bookings', [
            'business_id' => $other->id, 'service_id' => 1, 'offering_id' => $price->id, 'offering_type' => 'service_price',
            'starts_at' => $this->at->format('Y-m-d H:i:s'), 'ends_at' => $this->at->copy()->addMinutes(45)->format('Y-m-d H:i:s'),
        ])->assertCreated();
    }

    public function test_a_business_with_chairs_keeps_its_own_per_chair_rule(): void
    {
        // two chairs: two customers may hold the same time, one on each; the third finds none
        ServiceChair::create(['business_id' => $this->salon->id, 'name' => 'كرسي 1', 'sort_order' => 1, 'is_active' => true]);
        ServiceChair::create(['business_id' => $this->salon->id, 'name' => 'كرسي 2', 'sort_order' => 2, 'is_active' => true]);

        $end = $this->at->copy()->addMinutes(45);
        $this->book($this->at, $end)->assertCreated();
        $this->book($this->at, $end)->assertCreated();
        $this->book($this->at, $end)->assertStatus(422);
    }
}
