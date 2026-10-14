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
 * «تغيير موعد الحجز» — the customer moves his own booking while it is pending or accepted. The new time is judged
 * exactly like a new booking, an accepted booking goes back to the business, and its own old time never stands in its
 * way. Rolls back.
 */
class BookingRescheduleTest extends TestCase
{
    use DatabaseTransactions;

    private User $salon;

    private BusinessServicePrice $service;

    private User $customer;

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
                'open_time' => '10:00:00', 'close_time' => '18:00:00', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->customer = $this->account(User::TYPE_CLIENT);
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

    private function book(Carbon $start, ?User $who = null): int
    {
        Sanctum::actingAs($who ?: $this->customer);

        return (int) $this->postJson('/api/v2/bookings', [
            'business_id' => $this->salon->id, 'service_id' => 1, 'offering_id' => $this->service->id, 'offering_type' => 'service_price',
            'starts_at' => $start->format('Y-m-d H:i:s'), 'ends_at' => $start->copy()->addMinutes(45)->format('Y-m-d H:i:s'),
        ])->assertCreated()->json('data.booking.id');
    }

    private function move(int $id, Carbon $to, ?User $who = null)
    {
        Sanctum::actingAs($who ?: $this->customer);

        return $this->postJson("/api/v2/bookings/{$id}/reschedule", ['starts_at' => $to->format('Y-m-d H:i:s')]);
    }

    public function test_a_pending_booking_moves_and_keeps_its_length(): void
    {
        $id = $this->book($this->at);
        $to = $this->at->copy()->addDay()->setTime(14, 0);

        $this->move($id, $to)->assertOk();

        $row = Booking::query()->find($id);
        $this->assertSame($to->format('Y-m-d H:i:s'), Carbon::parse($row->starts_at)->format('Y-m-d H:i:s'));
        $this->assertSame(45, Carbon::parse($row->starts_at)->diffInMinutes(Carbon::parse($row->ends_at)));
        $this->assertSame(Booking::STATUS_PENDING, $row->status);
        $this->assertSame($to->toDateString(), $row->date instanceof Carbon ? $row->date->toDateString() : (string) $row->date);
    }

    public function test_an_accepted_booking_goes_back_to_the_business_and_it_is_told(): void
    {
        $id = $this->book($this->at);
        Booking::query()->whereKey($id)->update(['status' => Booking::STATUS_ACCEPTED]);

        $this->move($id, $this->at->copy()->addDay()->setTime(12, 0))->assertOk();

        $this->assertSame(Booking::STATUS_PENDING, Booking::query()->find($id)->status);
        $this->assertTrue(
            \App\Models\AppNotification::query()->where('user_id', $this->salon->id)->where('source_id', $id)->exists()
        );
    }

    public function test_the_new_time_is_judged_like_a_new_booking(): void
    {
        $id = $this->book($this->at);
        $other = $this->book($this->at->copy()->addDay()->setTime(14, 0), $this->account(User::TYPE_CLIENT));

        // a time another customer holds
        $this->move($id, $this->at->copy()->addDay()->setTime(14, 15))->assertStatus(422)->assertJsonValidationErrors('starts_at');
        // outside the business's hours (it opens at 10:00)
        $this->move($id, $this->at->copy()->addDay()->setTime(8, 0))->assertStatus(422)->assertJsonValidationErrors('starts_at');
        // in the past
        $this->move($id, now()->subHour())->assertStatus(422);

        // it never stands in its own way: moving 15 minutes inside its own old window is fine
        $this->move($id, $this->at->copy()->addMinutes(15))->assertOk();

        $this->assertNotNull($other);
    }

    public function test_only_the_customer_and_only_while_pending_or_accepted(): void
    {
        $id = $this->book($this->at);
        $to = $this->at->copy()->addDay()->setTime(15, 0);

        // another customer, and the business itself
        $this->move($id, $to, $this->account(User::TYPE_CLIENT))->assertForbidden();
        $this->move($id, $to, $this->salon)->assertForbidden();

        Booking::query()->whereKey($id)->update(['status' => Booking::STATUS_IN_PROGRESS]);
        $this->move($id, $to)->assertStatus(422);

        Booking::query()->whereKey($id)->update(['status' => Booking::STATUS_CANCELLED]);
        $this->move($id, $to)->assertStatus(422);
    }
}
