<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * POST /bookings/preview — the exact scenario the owner described, 2026-09-04:
 * «غرفة فردية سعرها 650 + إطلالة على البحر (50) + إقامة كاملة (500) = 1200
 * اليوم» و«غرفة فردية + إفطار فقط (50) = 700 اليوم»، والعميل يختار
 * (غرفة فردية + إفطار) × 5 أيام = 3500 إجمالي. Same math as store(), no
 * booking row created — see BookingController::preview's own doc.
 */
class BookingPreviewApiTest extends TestCase
{
    use DatabaseTransactions;

    private function client(): User
    {
        return User::query()->where('type', User::TYPE_CLIENT)->firstOrFail();
    }

    private function option(string $name): int
    {
        return (int) DB::table('options')->where('name_ar', $name)->value('id')
            ?: (int) DB::table('options')->value('id');
    }

    private function room(): BusinessServicePrice
    {
        $business = User::query()->where('type', User::TYPE_BUSINESS)->firstOrFail();
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        return BusinessServicePrice::create([
            'business_id' => $business->id,
            'child_id' => $business->category_child_id,
            'service_id' => $serviceId,
            'bookable_item_type' => 'booking_time',
            'price' => 650,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);
    }

    public function test_the_room_plus_sea_view_plus_full_board_totals_1200_for_one_day(): void
    {
        $room = $this->room();
        $seaView = $this->option('إطلالة على البحر');
        $fullBoard = $this->option('إقامة كاملة');

        $room->syncOfferingOptions(null, [$seaView, $fullBoard], [
            $seaView => ['type' => 'amount', 'value' => 50],
            $fullBoard => ['type' => 'amount', 'value' => 500],
        ]);

        $response = $this->actingAs($this->client(), 'sanctum')->postJson('/api/v2/bookings/preview', [
            'business_id' => $room->business_id,
            'service_id' => $room->service_id,
            'offering_id' => $room->id,
            'offering_type' => 'service_price',
            'starts_at' => now()->addDay()->toDateString(),
            'ends_at' => now()->addDays(2)->toDateString(),
            'option_ids' => [$seaView, $fullBoard],
        ])->assertOk();

        $this->assertSame(1200.0, (float) $response->json('data.price'));
    }

    public function test_the_room_plus_breakfast_over_5_days_totals_3500(): void
    {
        $room = $this->room();
        $breakfast = $this->option('إفطار فقط');

        $room->syncOfferingOptions(null, [$breakfast], [
            $breakfast => ['type' => 'amount', 'value' => 50],
        ]);

        $response = $this->actingAs($this->client(), 'sanctum')->postJson('/api/v2/bookings/preview', [
            'business_id' => $room->business_id,
            'service_id' => $room->service_id,
            'offering_id' => $room->id,
            'offering_type' => 'service_price',
            'starts_at' => now()->addDay()->toDateString(),
            'ends_at' => now()->addDays(6)->toDateString(),
            'option_ids' => [$breakfast],
        ])->assertOk();

        $this->assertSame(3500.0, (float) $response->json('data.price'));
    }

    public function test_preview_creates_no_booking_row(): void
    {
        $room = $this->room();
        $before = \App\Models\Booking::count();

        $this->actingAs($this->client(), 'sanctum')->postJson('/api/v2/bookings/preview', [
            'business_id' => $room->business_id,
            'service_id' => $room->service_id,
            'offering_id' => $room->id,
            'offering_type' => 'service_price',
        ])->assertOk();

        $this->assertSame($before, \App\Models\Booking::count());
    }

    public function test_preview_requires_authentication(): void
    {
        $this->postJson('/api/v2/bookings/preview', [])->assertUnauthorized();
    }
}
