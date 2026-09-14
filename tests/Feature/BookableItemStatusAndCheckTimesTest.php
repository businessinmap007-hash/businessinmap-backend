<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookableItem;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The merchant's manual room state (available/maintenance) and the
 * business-wide check-in/check-out clock — both plain additive fields on
 * existing tables, no new concepts. "Booked" is never one of the manual
 * states: it's computed from live bookings, see BookableItem::isCurrentlyBooked().
 */
class BookableItemStatusAndCheckTimesTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::create([
            'name' => 'فندق الحالة',
            'email' => 'roomstatus' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => 24,
            'category_child_id' => 536,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    public function test_a_new_room_defaults_to_available(): void
    {
        $item = $this->item();

        $this->actingAs($this->business, 'sanctum')
            ->getJson("/api/v2/business/bookable-items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.status', BookableItem::STATUS_AVAILABLE)
            ->assertJsonPath('data.is_currently_booked', false);
    }

    public function test_the_merchant_can_close_a_room_for_maintenance(): void
    {
        $item = $this->item();

        $this->actingAs($this->business, 'sanctum')
            ->putJson("/api/v2/business/bookable-items/{$item->id}", [
                'service_id' => $item->service_id,
                'item_type' => $item->item_type,
                'code' => $item->code,
                'status' => BookableItem::STATUS_MAINTENANCE,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', BookableItem::STATUS_MAINTENANCE);
    }

    public function test_an_invalid_status_is_refused(): void
    {
        $item = $this->item();

        $this->actingAs($this->business, 'sanctum')
            ->putJson("/api/v2/business/bookable-items/{$item->id}", [
                'service_id' => $item->service_id,
                'item_type' => $item->item_type,
                'code' => $item->code,
                'status' => 'demolished',
            ])
            ->assertStatus(422);
    }

    public function test_a_live_booking_marks_the_room_currently_booked(): void
    {
        $item = $this->item();

        Booking::create([
            'user_id' => $this->business->id,
            'business_id' => $this->business->id,
            'service_id' => $item->service_id,
            'bookable_type' => BookableItem::class,
            'bookable_id' => $item->id,
            'date' => now()->toDateString(),
            'time' => now()->format('H:i:s'),
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'quantity' => 1,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $this->actingAs($this->business, 'sanctum')
            ->getJson("/api/v2/business/bookable-items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.is_currently_booked', true)
            // Manual status is untouched by an overlapping booking.
            ->assertJsonPath('data.status', BookableItem::STATUS_AVAILABLE);
    }

    public function test_a_cancelled_booking_does_not_count_as_currently_booked(): void
    {
        $item = $this->item();

        Booking::create([
            'user_id' => $this->business->id,
            'business_id' => $this->business->id,
            'service_id' => $item->service_id,
            'bookable_type' => BookableItem::class,
            'bookable_id' => $item->id,
            'date' => now()->toDateString(),
            'time' => now()->format('H:i:s'),
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'quantity' => 1,
            'status' => Booking::STATUS_CANCELLED,
        ]);

        $this->assertFalse($item->fresh()->isCurrentlyBooked());
    }

    public function test_the_merchant_sets_check_in_and_check_out_times(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->putJson('/api/v2/business/booking-settings/check-times', [
                'check_in_time' => '15:00',
                'check_out_time' => '12:00',
            ])
            ->assertOk();

        $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/booking-settings/check-times')
            ->assertOk()
            ->assertJsonPath('data.check_in_time', '15:00:00')
            ->assertJsonPath('data.check_out_time', '12:00:00');
    }

    public function test_check_times_default_to_null_when_never_set(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/booking-settings/check-times')
            ->assertOk()
            ->assertJsonPath('data.check_in_time', null)
            ->assertJsonPath('data.check_out_time', null);
    }

    public function test_a_malformed_time_is_refused(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->putJson('/api/v2/business/booking-settings/check-times', [
                'check_in_time' => 'not-a-time',
            ])
            ->assertStatus(422);
    }

    private function item(): BookableItem
    {
        $serviceId = (int) PlatformService::where('key', 'booking')->value('id');

        return BookableItem::create([
            'business_id' => $this->business->id,
            'service_id' => $serviceId,
            'item_type' => 'booking_stay',
            'code' => 'ROOM-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'quantity' => 1,
            'is_active' => 1,
        ]);
    }
}
