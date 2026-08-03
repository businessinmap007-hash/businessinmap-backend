<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Services\BookableAvailabilityService;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Two things the booking path claimed to do and did not.
 *
 * `requires_bookable_item` was written by three admin screens and read by none,
 * so a hotel that demanded a specific room accepted a booking with no room and
 * priced it off the generic slot. And availability only ever consulted the slots
 * a BUSINESS had blocked by hand — never the bookings CUSTOMERS had already
 * made — so the same room could be sold twice for the same nights.
 *
 * Rolls back.
 */
class BookableGuardsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:int,1:int,2:BookableItem} a business whose child demands a unit */
    private function unitBooking(): array
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $row = DB::table('bookable_items as b')
            ->join('users as u', 'u.id', '=', 'b.business_id')
            ->join('category_service_configs as c', function ($j) use ($serviceId) {
                $j->on('c.category_id', '=', 'u.category_id')
                    ->on('c.child_id', '=', 'u.category_child_id')
                    ->where('c.platform_service_id', '=', $serviceId);
            })
            ->where('b.is_active', 1)
            ->where('c.is_active', 1)
            ->where('c.config', 'like', '%"requires_bookable_item":true%')
            ->first(['b.id as bookable_id', 'b.business_id']);

        if (! $row) {
            $this->markTestSkipped('No business whose child requires a reservable unit.');
        }

        return [(int) $row->business_id, $serviceId, BookableItem::findOrFail($row->bookable_id)];
    }

    public function test_a_child_that_demands_a_unit_refuses_a_booking_without_one(): void
    {
        [$businessId, $serviceId] = $this->unitBooking();

        $this->expectException(ValidationException::class);

        app(ServiceExecutionEngine::class)->prepare(
            businessId: $businessId,
            serviceId: $serviceId,
            bookableId: null
        );
    }

    public function test_the_same_child_accepts_the_booking_once_a_unit_is_named(): void
    {
        [$businessId, $serviceId, $item] = $this->unitBooking();

        $calc = app(ServiceExecutionEngine::class)->prepare(
            businessId: $businessId,
            serviceId: $serviceId,
            bookableId: (int) $item->id
        );

        $this->assertSame((int) $item->id, (int) $calc['bookable']->id);
        $this->assertGreaterThan(0, (float) $calc['price'], 'the unit must resolve a price of its own type');
    }

    /** A live booking on the unit blocks the window; a free window stays open. */
    public function test_an_occupied_unit_is_not_available_again(): void
    {
        [$businessId, $serviceId, $item] = $this->unitBooking();

        $start = now()->addYear()->startOfDay()->addHours(14);
        $end = (clone $start)->addDay();

        $availability = app(BookableAvailabilityService::class);

        $this->assertTrue(
            $availability->isAvailable($item, $start, $end),
            'a window with nothing in it must start out available'
        );

        Booking::query()->create([
            'user_id' => DB::table('users')->where('type', 'client')->value('id'),
            'business_id' => $businessId,
            'service_id' => $serviceId,
            'bookable_type' => $item->getMorphClass(),
            'bookable_id' => $item->id,
            'date' => $start->toDateString(),
            'time' => $start->format('H:i:s'),
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => Booking::STATUS_ACCEPTED,
            'quantity' => 1,
            'price' => 100,
        ]);

        $result = $availability->check($item, (clone $start)->addHours(2), (clone $end)->addHours(2));

        $this->assertFalse($result['available'], 'an overlapping live booking must close the window');
        $this->assertSame('booking_conflict', $result['code']);

        $this->assertTrue(
            $availability->isAvailable($item, (clone $end)->addDays(3), (clone $end)->addDays(4)),
            'a window after the stay must stay open'
        );
    }

    /** A cancelled booking gives the unit back. */
    public function test_a_cancelled_booking_releases_the_unit(): void
    {
        [$businessId, $serviceId, $item] = $this->unitBooking();

        $start = now()->addYear()->addMonth()->startOfDay()->addHours(14);
        $end = (clone $start)->addDay();

        Booking::query()->create([
            'user_id' => DB::table('users')->where('type', 'client')->value('id'),
            'business_id' => $businessId,
            'service_id' => $serviceId,
            'bookable_type' => $item->getMorphClass(),
            'bookable_id' => $item->id,
            'date' => $start->toDateString(),
            'time' => $start->format('H:i:s'),
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => Booking::STATUS_CANCELLED,
            'quantity' => 1,
            'price' => 100,
        ]);

        $this->assertTrue(
            app(BookableAvailabilityService::class)->isAvailable($item, $start, $end),
            'a cancelled booking must not keep holding the unit'
        );
    }

    /** The engine's guard turns an unavailable window into a refusal. */
    public function test_the_engine_refuses_an_occupied_window(): void
    {
        [$businessId, $serviceId, $item] = $this->unitBooking();

        $start = now()->addYear()->addMonths(2)->startOfDay()->addHours(14);
        $end = (clone $start)->addDay();

        Booking::query()->create([
            'user_id' => DB::table('users')->where('type', 'client')->value('id'),
            'business_id' => $businessId,
            'service_id' => $serviceId,
            'bookable_type' => $item->getMorphClass(),
            'bookable_id' => $item->id,
            'date' => $start->toDateString(),
            'time' => $start->format('H:i:s'),
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => Booking::STATUS_IN_PROGRESS,
            'quantity' => 1,
            'price' => 100,
        ]);

        $this->expectException(ValidationException::class);

        app(ServiceExecutionEngine::class)->assertBookableAvailable($item, $start, $end);
    }
}
