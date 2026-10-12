<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminV2\BookingController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A booking made from the admin screen is stored the way one made from the app is: the shop's wall-clock digits, in the
 * booking's own zone — never converted to UTC (which put it three hours away from every reader that compares it with
 * the shop's «now»).
 */
class AdminBookingTimeTest extends TestCase
{
    use DatabaseTransactions;

    private function account(string $type, ?int $category = null, ?int $child = null): User
    {
        return User::create([
            'name' => 'T ' . Str::random(4), 'email' => 't' . uniqid() . '@example.test', 'password' => bcrypt('Test1234'),
            'type' => $type, 'api_token' => Str::random(60), 'phone' => '010' . random_int(10000000, 99999999),
            'category_id' => $category, 'category_child_id' => $child,
        ]);
    }

    private function validated(array $input): array
    {
        $controller = app(BookingController::class);
        $method = new ReflectionMethod($controller, 'validateBooking');
        $method->setAccessible(true);

        // a salon (category 23, child 51) that sells the booking service — the same shape the queue tests use
        $salon = $this->account(User::TYPE_BUSINESS, 23, 51);
        $serviceId = (int) \DB::table('platform_services')->where('key', 'booking')->value('id');
        \App\Models\BusinessServicePrice::create([
            'business_id' => $salon->id, 'service_id' => $serviceId, 'child_id' => 51,
            'bookable_item_type' => 'booking_appointment', 'price' => 100, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        $request = Request::create('/admin/bookings', 'POST', $input + [
            'user_id' => $this->account(User::TYPE_CLIENT)->id,
            'business_id' => $salon->id,
            'service_id' => $serviceId,
            'status' => 'pending',
        ]);
        $request->setLaravelSession(app('session.store'));

        return $method->invoke($controller, $request);
    }

    public function test_the_entered_time_is_stored_as_the_wall_clock_it_was_typed_on(): void
    {
        $data = $this->validated(['date' => '2026-10-20', 'starts_at' => '2026-10-20T14:00', 'ends_at' => '2026-10-20T15:30']);

        $this->assertSame('2026-10-20 14:00:00', $data['starts_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-20 15:30:00', $data['ends_at']->format('Y-m-d H:i:s'));
        $this->assertSame('14:00:00', $data['time']);
        $this->assertSame((string) config('app.timezone'), $data['timezone']);
    }

    public function test_a_booking_in_another_zone_keeps_its_own_digits(): void
    {
        $data = $this->validated(['date' => '2026-10-20', 'starts_at' => '2026-10-20T14:00', 'timezone' => 'Asia/Dubai']);

        // the digits the admin typed, labelled with the zone they were typed in — not shifted into UTC or into Cairo
        $this->assertSame('2026-10-20 14:00:00', $data['starts_at']->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Dubai', $data['timezone']);
    }
}
