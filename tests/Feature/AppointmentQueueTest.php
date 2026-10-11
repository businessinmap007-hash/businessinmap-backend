<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «بالدور»: the customer names the day, the business gives the turn and the time it should come to — and the
 * estimate moves as the queue does.
 */
class AppointmentQueueTest extends TestCase
{
    use DatabaseTransactions;

    private User $salon;

    private BusinessServicePrice $service;

    private Carbon $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = $this->account(User::TYPE_BUSINESS, 'Salon', 23, 51);
        $this->service = BusinessServicePrice::create([
            'business_id' => $this->salon->id, 'service_id' => 1, 'child_id' => 51,
            'bookable_item_type' => 'booking_appointment', 'price' => 150, 'currency' => 'EGP',
            'is_active' => 1, 'duration_minutes' => 45,
        ]);

        $this->day = now()->addDays(2)->startOfDay();
        DB::table('business_working_hours')->where('business_id', $this->salon->id)->delete();
        DB::table('business_working_hours')->insert([
            'business_id' => $this->salon->id, 'day_of_week' => $this->day->dayOfWeek, 'is_closed' => 0,
            'open_time' => '10:00:00', 'close_time' => '13:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function account(string $type, string $tag, ?int $category = null, ?int $child = null): User
    {
        return User::create([
            'name' => $tag . ' ' . Str::random(4),
            'email' => strtolower($tag) . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => $type,
            'category_id' => $category,
            'category_child_id' => $child,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    private function chooseQueue(): void
    {
        Sanctum::actingAs($this->salon);
        $this->putJson('/api/v2/business/booking-settings/flow', ['flow' => 'queue'])
            ->assertOk()->assertJsonPath('data.flow', 'queue');
    }

    private function take(User $customer)
    {
        Sanctum::actingAs($customer);

        return $this->postJson('/api/v2/bookings/queue', [
            'business_id' => $this->salon->id,
            'service_id' => 1,
            'offering_id' => $this->service->id,
            'offering_type' => 'service_price',
            'date' => $this->day->toDateString(),
        ]);
    }

    public function test_a_business_that_books_by_appointment_refuses_a_turn(): void
    {
        $this->assertSame('slots', \App\Services\AppointmentQueue::flowOf($this->salon->id));

        $this->take($this->account(User::TYPE_CLIENT, 'A'))->assertStatus(422);
    }

    public function test_each_customer_gets_the_next_turn_and_the_time_after_the_one_before_it(): void
    {
        $this->chooseQueue();

        $times = [];
        foreach (['A', 'B', 'C', 'D'] as $i => $tag) {
            $response = $this->take($this->account(User::TYPE_CLIENT, $tag))->assertCreated();
            $this->assertSame($i + 1, $response->json('data.booking.queue_turn'));
            $times[] = substr($response->json('data.booking.starts_at'), 11, 5);
        }

        // 45 minutes each from opening: 10:00, 10:45, 11:30, 12:15 — and the fifth does not fit before 13:00
        $this->assertSame(['10:00', '10:45', '11:30', '12:15'], $times);
        $this->take($this->account(User::TYPE_CLIENT, 'E'))->assertStatus(422);

        // the preview a customer sees before booking says the same
        $this->getJson("/api/v2/discovery/appointments/{$this->salon->id}/queue?date={$this->day->toDateString()}&offering_id={$this->service->id}")
            ->assertOk()->assertJsonPath('data.full', true);
    }

    public function test_a_cancelled_turn_pulls_the_ones_behind_it_forward(): void
    {
        $this->chooseQueue();
        $a = $this->account(User::TYPE_CLIENT, 'A');
        $b = $this->account(User::TYPE_CLIENT, 'B');
        $c = $this->account(User::TYPE_CLIENT, 'C');

        $this->take($a)->assertCreated();
        $second = $this->take($b)->assertCreated()->json('data.booking.id');
        $third = $this->take($c)->assertCreated()->json('data.booking.id');

        Sanctum::actingAs($c);
        $before = $this->getJson("/api/v2/bookings/{$third}")->assertOk()->json('data.booking.queue');
        $this->assertSame(3, $before['turn']);
        $this->assertSame(2, $before['ahead']);
        $this->assertSame('11:30', substr($before['expected_at'], 11, 5));

        // the second customer leaves the queue
        Booking::query()->whereKey($second)->update(['status' => Booking::STATUS_CANCELLED]);

        $after = $this->getJson("/api/v2/bookings/{$third}")->assertOk()->json('data.booking.queue');
        $this->assertSame(1, $after['ahead']);
        $this->assertSame('10:45', substr($after['expected_at'], 11, 5));

        // a turn given up is never handed out again
        $d = $this->take($this->account(User::TYPE_CLIENT, 'D'))->assertCreated();
        $this->assertSame(4, $d->json('data.booking.queue_turn'));
    }
}
