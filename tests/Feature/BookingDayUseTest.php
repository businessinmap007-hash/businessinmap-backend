<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «واضف خدمة Day use للحجز فى الفنادق» — المالك، 2026-10-07.
 *
 * A hotel room type is also sold through the day: a window, a flat price, one room held for that window only.
 * Rolls back.
 */
class BookingDayUseTest extends TestCase
{
    use DatabaseTransactions;

    private User $hotel;

    private User $guest;

    private BookableItem $type;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        $hotel = $booking?->business;

        if (! $booking || ! $hotel || (string) $hotel->type !== User::TYPE_BUSINESS) {
            $this->markTestSkipped('Needs a booking whose business is a business account.');
        }

        $this->hotel = $hotel;
        $this->guest = $booking->user;
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        $this->type = BookableItem::create([
            'business_id' => $hotel->id,
            'service_id' => $this->serviceId,
            'item_type' => 'booking_stay',
            'line_option_id' => 965,
            'title' => 'غرفة مزدوجة',
            'code' => 'TYPE-A',
            'capacity' => 2,
            'quantity' => 1,
            'is_active' => 1,
        ]);

        $childId = (int) ($hotel->category_child_id ?? 0);
        BusinessServicePrice::query()->where('business_id', $hotel->id)->where('service_id', $this->serviceId)->where('child_id', $childId)->delete();
        BusinessServicePrice::create([
            'business_id' => $hotel->id, 'service_id' => $this->serviceId, 'child_id' => $childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);
        BusinessDepositPolicy::query()->where('business_id', $hotel->id)->delete();
        Deposit::query()->where('target_type', Booking::class)->whereIn('target_id', Booking::query()->where('bookable_id', $this->type->id)->pluck('id'))->delete();
        DB::table('category_child_service_fees')->where('category_id', (int) $hotel->category_id)->where('child_id', $childId)->delete();
    }

    private function enable(array $overrides = [])
    {
        return $this->actingAs($this->hotel, 'sanctum')->putJson(
            "/api/v2/business/bookable-items/{$this->type->id}/day-use",
            $overrides + ['enabled' => true, 'from' => '09:00', 'to' => '18:00', 'price' => 350]
        );
    }

    private function body(array $extra = []): array
    {
        return $extra + [
            'business_id' => $this->hotel->id,
            'service_id' => $this->serviceId,
            'bookable_id' => $this->type->id,
            'day_use' => true,
            'date' => now()->addDays(3)->toDateString(),
        ];
    }

    public function test_the_hotel_sets_a_window_and_a_flat_price_on_a_room_type(): void
    {
        $this->actingAs($this->hotel, 'sanctum')->getJson("/api/v2/business/bookable-items/{$this->type->id}/day-use")
            ->assertOk()->assertJsonPath('data.day_use.enabled', false);

        $this->enable()->assertOk()
            ->assertJsonPath('data.day_use.enabled', true)
            ->assertJsonPath('data.day_use.from', '09:00')
            ->assertJsonPath('data.day_use.price', 350);

        // the room type reports it to the hotel's own list
        $this->actingAs($this->hotel, 'sanctum')->getJson("/api/v2/business/bookable-items/{$this->type->id}")
            ->assertOk()->assertJsonPath('data.day_use.enabled', true);
    }

    public function test_a_window_too_short_or_a_missing_price_is_refused(): void
    {
        $this->enable(['from' => '09:00', 'to' => '10:00'])->assertStatus(422);
        $this->enable(['from' => '18:00', 'to' => '09:00'])->assertStatus(422);
        $this->enable(['from' => '00:00', 'to' => '23:00'])->assertStatus(422);
        $this->enable(['price' => null])->assertStatus(422);
        $this->enable(['price' => 0])->assertStatus(422);

        // switching it off needs neither
        $this->enable(['enabled' => false, 'price' => null])->assertOk()->assertJsonPath('data.day_use.enabled', false);
    }

    public function test_only_a_stay_room_type_has_day_use(): void
    {
        $table = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_table', 'code' => 'T1', 'quantity' => 1, 'is_active' => 1,
        ]);

        $this->actingAs($this->hotel, 'sanctum')->putJson("/api/v2/business/bookable-items/{$table->id}/day-use", ['enabled' => true, 'from' => '09:00', 'to' => '18:00', 'price' => 100])
            ->assertStatus(422);
    }

    public function test_another_business_cannot_set_it(): void
    {
        $other = User::query()->where('type', User::TYPE_BUSINESS)->where('id', '!=', $this->hotel->id)->firstOrFail();

        $this->actingAs($other, 'sanctum')->putJson("/api/v2/business/bookable-items/{$this->type->id}/day-use", ['enabled' => true, 'from' => '09:00', 'to' => '18:00', 'price' => 1])
            ->assertNotFound();
    }

    public function test_the_guest_is_told_the_room_type_offers_day_use_only_when_it_does(): void
    {
        $units = fn () => collect($this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk()->json('data.kinds'))
            ->flatMap(fn ($g) => $g['units'] ?? [])->firstWhere('id', $this->type->id);

        $this->assertNull($units()['day_use'] ?? null);

        $this->enable()->assertOk();
        $this->assertEquals(['from' => '09:00', 'to' => '18:00', 'price' => 350], $units()['day_use']);
    }

    public function test_the_preview_prices_the_flat_day_use_price_not_a_night(): void
    {
        $this->enable()->assertOk();

        $res = $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings/preview', $this->body())->assertOk();

        $this->assertEquals(350, $res->json('data.price'));
        $this->assertSame('day_use', $res->json('data.price_breakdown.source'));
    }

    public function test_the_guest_books_day_use_and_holds_the_room_for_the_window_only(): void
    {
        $this->enable()->assertOk();
        $date = now()->addDays(3)->toDateString();

        $res = $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body())->assertCreated();
        $booking = Booking::findOrFail($res->json('data.booking.id'));

        $this->assertSame("{$date} 09:00:00", $booking->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame("{$date} 18:00:00", $booking->ends_at->format('Y-m-d H:i:s'));
        $this->assertEquals(350, (float) $booking->price);
        $this->assertFalse((bool) $booking->all_day);
        $this->assertTrue((bool) ($booking->meta['day_use'] ?? false));

        // the one room of this type is held for that window: a second guest cannot take the same afternoon…
        $second = $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body())->assertStatus(422);

        // …but the next day is free
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body(['date' => now()->addDays(4)->toDateString()]))->assertCreated();
    }

    public function test_a_room_type_without_day_use_refuses_it_and_a_past_window_is_refused(): void
    {
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body())->assertStatus(422);

        $this->enable()->assertOk();
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body(['date' => now()->subDay()->toDateString()]))->assertStatus(422);
        $this->actingAs($this->guest, 'sanctum')->postJson('/api/v2/bookings', $this->body(['bookable_id' => null]))->assertStatus(422);
    }
}
