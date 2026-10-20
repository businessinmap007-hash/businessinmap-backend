<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\BusinessStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «اختر مقدّم الخدمة»: a customer books a particular person (a barber, a trainer, a technician) of a business. The booking is
 * assigned to him at once, he is told, he holds one customer at a time — and other providers keep working in parallel.
 */
class BookingProviderChoiceTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $tag, string $type = User::TYPE_CLIENT): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function provider(User $business, string $name): User
    {
        $p = $this->user($name);
        BusinessStaff::create([
            'business_id' => $business->id, 'user_id' => $p->id, 'title' => 'حلاق', 'status' => BusinessStaff::STATUS_ACCEPTED,
            'is_active' => true, 'is_provider' => true, 'capabilities' => ['bookings'],
        ]);

        return $p;
    }

    /** @return array{0:User,1:int,2:int} an appointment-shape business open all day, its service id and its priced row */
    private function salon(): array
    {
        $salon = new User();
        $salon->forceFill([
            'name' => 'Salon ' . Str::random(4), 'type' => User::TYPE_BUSINESS, 'email' => 'salon-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => 23, 'category_child_id' => 51,
        ])->save();

        $row = \App\Models\BusinessServicePrice::create([
            'business_id' => $salon->id, 'service_id' => 1, 'child_id' => 51,
            'bookable_item_type' => 'booking_appointment', 'price' => 150, 'currency' => 'EGP',
            'is_active' => 1, 'duration_minutes' => 45,
        ]);

        \Illuminate\Support\Facades\DB::table('business_working_hours')->where('business_id', $salon->id)->delete();
        foreach (range(0, 6) as $d) {
            \Illuminate\Support\Facades\DB::table('business_working_hours')->insert([
                'business_id' => $salon->id, 'day_of_week' => $d, 'is_closed' => 0,
                'open_time' => '00:00:00', 'close_time' => '23:59:00', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$salon, 1, (int) $row->id];
    }

    private function book(User $as, User $salon, int $serviceId, int $rowId, Carbon $at, ?int $providerId)
    {
        Sanctum::actingAs($as);

        return $this->postJson('/api/v2/bookings', array_filter([
            'business_id' => $salon->id, 'service_id' => $serviceId, 'offering_id' => $rowId, 'offering_type' => 'service_price',
            'starts_at' => $at->format('Y-m-d H:i:s'), 'provider_id' => $providerId,
        ], fn ($v) => $v !== null));
    }

    public function test_the_chosen_provider_gets_the_booking_and_is_told_and_cannot_be_double_booked(): void
    {
        [$salon, $serviceId, $rowId] = $this->salon();
        $ahmed = $this->provider($salon, 'Ahmed');
        $sara = $this->provider($salon, 'Sara');
        $c1 = $this->user('C1');
        $c2 = $this->user('C2');
        $at = Carbon::now('Africa/Cairo')->addDays(5)->setTime(11, 0);

        $first = $this->book($c1, $salon, $serviceId, $rowId, $at, $ahmed->id)->assertCreated();
        $this->assertSame($ahmed->id, (int) $first->json('data.booking.assignee.id'));
        $this->assertSame(1, AppNotification::query()->where('user_id', $ahmed->id)->where('title_ar', 'تم تعيينك على عمل جديد')->count());

        // the same person at the same time is refused…
        $this->book($c2, $salon, $serviceId, $rowId, $at, $ahmed->id)->assertStatus(422)->assertJsonValidationErrors('provider_id');
        // …but another provider works in parallel
        $this->book($c2, $salon, $serviceId, $rowId, $at, $sara->id)->assertCreated();

        // someone who is not his provider is refused
        $this->book($c2, $salon, $serviceId, $rowId, $at->copy()->addHours(3), $c1->id)->assertStatus(422)->assertJsonValidationErrors('provider_id');
    }

    public function test_the_day_grid_of_a_chosen_provider_shows_only_his_own_taken_times(): void
    {
        [$salon, $serviceId, $rowId] = $this->salon();
        $ahmed = $this->provider($salon, 'Ahmed');
        $sara = $this->provider($salon, 'Sara');
        $at = Carbon::now('Africa/Cairo')->addDays(6)->setTime(12, 0);

        $this->book($this->user('C1'), $salon, $serviceId, $rowId, $at, $ahmed->id)->assertCreated();

        $slot = fn (User $p) => collect($this->getJson("/api/v2/discovery/appointments/{$salon->id}/day-grid?date={$at->toDateString()}&offering_id={$rowId}&provider_id={$p->id}")
            ->assertOk()->json('data.slots'))->firstWhere('starts_at', $at->format('Y-m-d H:i:s'));

        $ahmedSlot = $slot($ahmed);
        $saraSlot = $slot($sara);

        $this->assertNotNull($ahmedSlot);
        $this->assertSame(0, $ahmedSlot['free'], 'his time is taken');
        $this->assertSame(1, $saraSlot['free'], 'hers is free: they work in parallel');

        $this->getJson("/api/v2/discovery/appointments/{$salon->id}/day-grid?date={$at->toDateString()}&provider_id={$this->user('X')->id}")->assertStatus(422);
    }
}
