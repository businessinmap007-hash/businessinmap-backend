<?php

namespace Tests\Feature;

use App\Models\BookingReminder;
use App\Models\ClinicAppointmentSlot;
use App\Models\User;
use App\Support\WallClock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «الآن» بساعة الجدار: shop and customer times are stored as wall-clock digits, so "has it started?" must be judged
 * against the wall clock of the shop (Cairo), not against UTC — a reminder, a slot and a day-use window were all three
 * hours off.
 */
class WallClockTest extends TestCase
{
    use DatabaseTransactions;

    private function business(?string $timezone = null): User
    {
        $user = User::create([
            'name' => 'Shop ' . Str::random(4), 'email' => 'shop' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'), 'type' => User::TYPE_BUSINESS, 'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);

        if ($timezone) {
            $user->forceFill(['timezone' => $timezone])->save();
        }

        return $user;
    }

    public function test_now_is_the_wall_clock_of_the_shop_not_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 16:30:00', 'UTC')); // 19:30 in Cairo

        $this->assertSame('2026-10-12 19:30:00', WallClock::now()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-12 00:00:00', WallClock::today()->format('Y-m-d H:i:s'));

        // across midnight: 22:30 UTC is already tomorrow in Cairo
        Carbon::setTestNow(Carbon::parse('2026-10-12 22:30:00', 'UTC'));
        $this->assertSame('2026-10-13 00:00:00', WallClock::today()->format('Y-m-d H:i:s'));

        // a shop that chose another zone is judged in it
        $tokyo = $this->business('Asia/Tokyo');
        $this->assertSame('2026-10-13 07:30:00', WallClock::now($tokyo->id)->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_the_instants_of_a_local_day_are_its_utc_bounds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 22:30:00', 'UTC')); // 13 Oct 00:30 Cairo

        [$start, $end] = WallClock::instantBounds();

        // Cairo is +3 in October: its 13th runs from 12th 21:00 UTC to 13th 20:59:59 UTC
        $this->assertSame('2026-10-12 21:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-13 20:59:59', $end->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_a_clinic_slot_is_open_until_its_own_start_on_the_clinic_clock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 16:30:00', 'UTC')); // 19:30 in Cairo
        $clinic = $this->business();

        // 19:00 wall-clock has already begun (it is 19:30 there) — on a UTC clock it would still look 2.5 h away
        $began = ClinicAppointmentSlot::create(['clinic_id' => $clinic->id, 'starts_at' => '2026-10-12 19:00:00', 'duration_minutes' => 30]);
        $ahead = ClinicAppointmentSlot::create(['clinic_id' => $clinic->id, 'starts_at' => '2026-10-12 20:00:00', 'duration_minutes' => 30]);

        $this->assertFalse($began->fresh()->isOpen());
        $this->assertTrue($ahead->fresh()->isOpen());

        $open = ClinicAppointmentSlot::query()->where('clinic_id', $clinic->id)->open()->pluck('id')->all();
        $this->assertSame([$ahead->id], $open);

        Carbon::setTestNow();
    }

    public function test_a_booking_reminder_is_due_on_the_shop_clock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 16:30:00', 'UTC')); // 19:30 in Cairo

        // the due bound is the shop's wall clock, so a reminder written for 18:30 wall-clock (an hour ago there) is due,
        // where on a UTC clock it would have waited until 21:30 Cairo time — after a 19:30 visit
        $bindings = BookingReminder::query()->due()->getBindings();

        $this->assertContains('2026-10-12 19:30:00', array_map('strval', $bindings));

        Carbon::setTestNow();
    }
}
