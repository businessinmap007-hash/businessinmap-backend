<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BookableItemRoom;
use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ServiceExecutionEngine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «العدد والرقم لكل غرفة يكون لدى الفندق فقط ويظهر عند بداية التنفيذ» — المالك، 2026-10-07.
 *
 * The customer books a room TYPE; the numbered rooms behind it are the hotel's own. One is given to the stay when it
 * starts, and only then does the customer see its number. A hotel with no listed rooms is untouched. Rolls back.
 */
class BookableRoomsTest extends TestCase
{
    use DatabaseTransactions;

    private User $hotel;

    private User $guest;

    private BookableItem $type;

    private Booking $booking;

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

        $this->booking = $booking;
        $this->hotel = $hotel;
        $this->guest = $booking->user;

        $serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        $this->type = BookableItem::create([
            'business_id' => $hotel->id,
            'service_id' => $serviceId,
            'item_type' => 'booking_stay',
            'line_option_id' => 965,
            'title' => 'غرفة مزدوجة',
            'code' => 'TYPE-A',
            'notes' => 'التكييف يحتاج صيانة',
            'capacity' => 2,
            'quantity' => 1,
            'is_active' => 1,
        ]);

        $childId = (int) ($hotel->category_child_id ?? 0);
        BusinessServicePrice::query()->where('business_id', $hotel->id)->where('service_id', $serviceId)->where('child_id', $childId)->delete();
        BusinessServicePrice::create([
            'business_id' => $hotel->id, 'service_id' => $serviceId, 'child_id' => $childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);
        BusinessDepositPolicy::query()->where('business_id', $hotel->id)->delete();
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        DB::table('category_child_service_fees')->where('category_id', (int) $hotel->category_id)->where('child_id', $childId)->delete();

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
        $meta['pricing'] = ['final_price' => 1000];
        unset($meta['_execution_fee'], $meta['_financial_guard']);
        $booking->forceFill([
            'meta' => $meta,
            'price' => 1000,
            'status' => Booking::STATUS_ACCEPTED,
            'bookable_type' => BookableItem::class,
            'bookable_id' => $this->type->id,
            'room_id' => null,
            'starts_at' => now()->addDay()->startOfDay(),
            'ends_at' => now()->addDays(3)->startOfDay(),
        ])->save();

        app(WalletService::class)->getOrCreateWallet($this->guest->id)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
        app(WalletService::class)->getOrCreateWallet($hotel->id)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
    }

    private function addRooms(array $numbers)
    {
        return $this->actingAs($this->hotel, 'sanctum')
            ->postJson("/api/v2/business/bookable-items/{$this->type->id}/rooms", ['numbers' => $numbers]);
    }

    private function start(): void
    {
        app(ServiceExecutionEngine::class)->moveBookingToInProgress($this->booking);
        $this->booking->refresh();
    }

    public function test_the_hotel_lists_its_rooms_and_the_count_follows_them(): void
    {
        $this->addRooms(['101', '102', '103', '102'])
            ->assertCreated()
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.open_count', 3);

        $this->assertSame(3, (int) $this->type->fresh()->quantity, 'what can be sold is the rooms that are open');

        $room = $this->type->rooms()->where('number', '102')->firstOrFail();
        $this->actingAs($this->hotel, 'sanctum')
            ->patchJson("/api/v2/business/bookable-items/{$this->type->id}/rooms/{$room->id}", ['status' => 'maintenance'])
            ->assertOk()->assertJsonPath('data.open_count', 2);
        $this->assertSame(2, (int) $this->type->fresh()->quantity);

        $this->actingAs($this->hotel, 'sanctum')
            ->deleteJson("/api/v2/business/bookable-items/{$this->type->id}/rooms/{$room->id}")
            ->assertOk()->assertJsonPath('data.count', 2);
    }

    public function test_rooms_exist_only_for_stays(): void
    {
        $table = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->type->service_id, 'item_type' => 'booking_table', 'code' => 'T1', 'quantity' => 1, 'is_active' => 1,
        ]);

        $this->actingAs($this->hotel, 'sanctum')
            ->postJson("/api/v2/business/bookable-items/{$table->id}/rooms", ['numbers' => ['1']])
            ->assertStatus(422);
    }

    public function test_another_business_cannot_touch_the_rooms(): void
    {
        $other = User::query()->where('type', User::TYPE_BUSINESS)->where('id', '!=', $this->hotel->id)->firstOrFail();

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v2/business/bookable-items/{$this->type->id}/rooms", ['numbers' => ['1']])
            ->assertNotFound();
    }

    public function test_a_stay_is_given_a_room_when_it_starts_and_a_taken_room_is_not_given_twice(): void
    {
        $this->addRooms(['101', '102'])->assertCreated();

        $this->start();
        $first = BookableItemRoom::findOrFail($this->booking->room_id);
        $this->assertSame('101', $first->number);

        // a second stay over the same nights gets the other room
        $second = $this->booking->replicate();
        $second->forceFill(['room_id' => null, 'status' => Booking::STATUS_ACCEPTED])->save();
        app(ServiceExecutionEngine::class)->moveBookingToInProgress($second);
        $this->assertSame('102', BookableItemRoom::findOrFail($second->fresh()->room_id)->number);
    }

    public function test_a_stay_cannot_start_when_every_room_is_taken(): void
    {
        $this->addRooms(['101'])->assertCreated();
        $this->start();

        $second = $this->booking->replicate();
        $second->forceFill(['room_id' => null, 'status' => Booking::STATUS_ACCEPTED])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ServiceExecutionEngine::class)->moveBookingToInProgress($second);
    }

    public function test_a_hotel_with_no_listed_rooms_is_untouched(): void
    {
        $this->start();

        $this->assertNull($this->booking->room_id);
        $this->assertSame(Booking::STATUS_IN_PROGRESS, $this->booking->status);
    }

    public function test_the_hotel_may_choose_the_room_and_only_among_the_free_ones(): void
    {
        $this->addRooms(['101', '102', '103'])->assertCreated();
        $second = $this->type->rooms()->where('number', '102')->firstOrFail();

        $this->actingAs($this->hotel, 'sanctum')
            ->getJson("/api/v2/business/bookings/{$this->booking->id}/rooms")
            ->assertOk()->assertJsonPath('data.uses_rooms', true)->assertJsonCount(3, 'data.free');

        $this->actingAs($this->hotel, 'sanctum')
            ->postJson("/api/v2/business/bookings/{$this->booking->id}/room", ['room_id' => $second->id])
            ->assertOk()->assertJsonPath('data.room.number', '102');

        $this->start();
        $this->assertSame($second->id, (int) $this->booking->room_id, 'a room chosen by hand is kept at the start');

        // a room closed for maintenance is not offered
        $third = $this->type->rooms()->where('number', '103')->firstOrFail();
        $third->update(['status' => 'maintenance']);
        $this->actingAs($this->hotel, 'sanctum')
            ->postJson("/api/v2/business/bookings/{$this->booking->id}/room", ['room_id' => $third->id])
            ->assertStatus(422);
    }

    public function test_the_customer_sees_the_number_only_after_the_stay_starts_and_never_the_internal_note(): void
    {
        $this->addRooms(['101', '102'])->assertCreated();

        $before = $this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/bookings/{$this->booking->id}")->assertOk();
        $this->assertNull($before->json('data.booking.room'));
        $this->assertArrayNotHasKey('notes', (array) $before->json('data.booking.bookable'));
        $this->assertArrayNotHasKey('code', (array) $before->json('data.booking.bookable'), 'the type is shown by what it is');

        $this->start();

        $after = $this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/bookings/{$this->booking->id}")->assertOk();
        $this->assertSame('101', $after->json('data.booking.room.number'));

        // the hotel always sees it
        $this->actingAs($this->hotel, 'sanctum')->getJson("/api/v2/bookings/{$this->booking->id}")
            ->assertOk()->assertJsonPath('data.booking.room.number', '101')->assertJsonPath('data.booking.bookable.notes', 'التكييف يحتاج صيانة');
    }

    public function test_discovery_shows_a_room_type_by_what_it_is_not_by_a_number(): void
    {
        $this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/discovery/units/{$this->hotel->id}")->assertOk();
        $listed = collect($this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/discovery/units/{$this->hotel->id}")->json('data.kinds'))
            ->flatMap(fn ($g) => $g['units'] ?? [])->firstWhere('id', $this->type->id);
        $this->assertSame('TYPE-A', $listed['code'] ?? null, 'before rooms are listed the old way is unchanged');

        $this->addRooms(['101'])->assertCreated();

        $after = collect($this->actingAs($this->guest, 'sanctum')->getJson("/api/v2/discovery/units/{$this->hotel->id}")->json('data.kinds'))
            ->flatMap(fn ($g) => $g['units'] ?? [])->firstWhere('id', $this->type->id);
        $this->assertSame('', $after['code']);
        $this->assertStringNotContainsString('TYPE-A', (string) $after['label']);
    }
}
