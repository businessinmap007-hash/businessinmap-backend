<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\MenuItem;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A booking could only be about a price row. That covers a clinic («كشف عظام»)
 * but not the two cases that made menu_items a priced surface at all: a
 * furniture showroom lists «غرفة نوم — مودرن» and an estate agent «شقة —
 * غرفتين — سوبر لوكس», and a viewing is booked ON one of those.
 *
 * The trap this guards: a listing's price is NOT the booking's price. Two
 * million pounds is what the flat costs, not what the viewing costs — so the
 * listing says what the booking is ABOUT and never reaches the pricing ladder.
 *
 * @see \App\Http\Controllers\Api\V2\BookingController::resolveOffering()
 */
class BookingOnListingTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:User,2:int} business, client, service */
    private function actors(): array
    {
        $business = User::query()->where('type', 'business')->whereNotNull('category_child_id')->first();
        $client = User::query()->where('type', 'client')->first();

        if (! $business || ! $client) {
            $this->markTestSkipped('No business/client pair.');
        }

        $serviceId = (int) DB::table('platform_services')->where('is_active', 1)->value('id');

        return [$business, $client, $serviceId];
    }

    private function listing(User $business, string $name = 'شقة للعرض'): MenuItem
    {
        $item = MenuItem::create([
            'business_id' => $business->id,
            'name_ar' => $name,
            'base_price' => 2000000,
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        $line = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->where('g.is_active', 1)
            ->value('o.id');

        if ($line) {
            $item->syncOfferingOptions((int) $line);
        }

        return $item;
    }

    /** The service's own price row — what a viewing actually costs. */
    private function viewingPrice(User $business, int $serviceId, float $amount = 0): BusinessServicePrice
    {
        return BusinessServicePrice::create([
            'business_id' => $business->id,
            'child_id' => $business->category_child_id,
            'service_id' => $serviceId,
            'bookable_item_type' => 'category',
            'price' => $amount,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);
    }

    private function book(User $client, array $payload)
    {
        return $this->actingAs($client, 'sanctum')->postJson('/api/v2/bookings', $payload);
    }

    /** A viewing can be booked on a listing, and the booking says which. */
    public function test_a_listing_can_be_booked(): void
    {
        [$business, $client, $serviceId] = $this->actors();

        $this->viewingPrice($business, $serviceId, 0);
        $listing = $this->listing($business);

        $response = $this->book($client, [
            'business_id' => $business->id,
            'service_id' => $serviceId,
            'offering_type' => 'menu_item',
            'offering_id' => $listing->id,
            'date' => now()->addDay()->toDateString(),
            'time' => '11:00',
        ]);

        $response->assertCreated();

        $booking = Booking::query()->findOrFail($response->json('data.booking.id'));

        $this->assertSame(MenuItem::class, $booking->offering_type);
        $this->assertSame((int) $listing->id, (int) $booking->offering_id);
        $this->assertStringContainsString('شقة للعرض', $booking->title());
    }

    /**
     * The trap. The flat is listed at two million; the viewing is free. The
     * booking must cost what the SERVICE costs.
     */
    public function test_the_listing_price_is_not_the_booking_price(): void
    {
        [$business, $client, $serviceId] = $this->actors();

        $this->viewingPrice($business, $serviceId, 0);
        $listing = $this->listing($business);

        $response = $this->book($client, [
            'business_id' => $business->id,
            'service_id' => $serviceId,
            'offering_type' => 'menu_item',
            'offering_id' => $listing->id,
            'date' => now()->addDays(2)->toDateString(),
            'time' => '12:00',
        ]);

        $response->assertCreated();

        $booking = Booking::query()->findOrFail($response->json('data.booking.id'));

        $this->assertEqualsWithDelta(0.0, (float) $booking->price, 0.001);
        $this->assertNotEquals(2000000.0, (float) $booking->price);
    }

    /** Another business's listing is not bookable here. */
    public function test_a_listing_from_another_business_is_refused(): void
    {
        [$business, $client, $serviceId] = $this->actors();

        $stranger = User::query()->where('type', 'business')->where('id', '!=', $business->id)->first();

        if (! $stranger) {
            $this->markTestSkipped('Only one business exists.');
        }

        $this->viewingPrice($business, $serviceId, 0);
        $theirs = $this->listing($stranger, 'شقة الغير');

        $this->book($client, [
            'business_id' => $business->id,
            'service_id' => $serviceId,
            'offering_type' => 'menu_item',
            'offering_id' => $theirs->id,
            'date' => now()->addDays(3)->toDateString(),
            'time' => '13:00',
        ])->assertStatus(422);
    }

    /** A listing nobody switched on cannot be booked. */
    public function test_an_inactive_listing_is_refused(): void
    {
        [$business, $client, $serviceId] = $this->actors();

        $this->viewingPrice($business, $serviceId, 0);
        $listing = $this->listing($business);
        $listing->update(['is_active' => 0]);

        $this->book($client, [
            'business_id' => $business->id,
            'service_id' => $serviceId,
            'offering_type' => 'menu_item',
            'offering_id' => $listing->id,
            'date' => now()->addDays(4)->toDateString(),
            'time' => '14:00',
        ])->assertStatus(422);
    }

    /** Naming no type still means a price row, as it did before. */
    public function test_the_default_offering_type_is_still_a_price_row(): void
    {
        [$business, $client, $serviceId] = $this->actors();

        $price = $this->viewingPrice($business, $serviceId, 150);

        $response = $this->book($client, [
            'business_id' => $business->id,
            'service_id' => $serviceId,
            'offering_id' => $price->id,
            'date' => now()->addDays(5)->toDateString(),
            'time' => '15:00',
        ]);

        $response->assertCreated();

        $booking = Booking::query()->findOrFail($response->json('data.booking.id'));

        $this->assertSame(BusinessServicePrice::class, $booking->offering_type);
        $this->assertEqualsWithDelta(150.0, (float) $booking->price, 0.001);
    }
}
