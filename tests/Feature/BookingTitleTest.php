<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\OptionGroup;
use App\Models\User;
use App\Services\BusinessServicePriceResolver;
use App\Services\CategoryChildOptionScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A booking could say its service and, at best, the unit reserved — «حجز
 * #4127». What the customer actually chose («كشف عظام») was nowhere on it,
 * because the price row it came from was never recorded.
 *
 * Recording it does two things: the booking can name itself, and the price
 * stops being ambiguous. One item type may now hold «كشف عظام 300» beside
 * «كشف باطنة 250», and the resolver's ladder would have taken whichever was
 * created last.
 *
 * @see \App\Models\Booking::title()
 */
class BookingTitleTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:User,1:int} */
    private function sellerAndLine(): array
    {
        $scope = app(CategoryChildOptionScope::class);

        foreach (User::query()->where('type', 'business')->whereNotNull('category_child_id')->cursor() as $user) {
            $line = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereIn('o.id', $scope->idsFor((int) $user->category_child_id, (int) $user->category_id))
                ->where('g.price_role', OptionGroup::ROLE_LINE)
                ->where('g.is_active', 1)
                ->value('o.id');

            if ($line) {
                return [$user, (int) $line];
            }
        }

        $this->markTestSkipped('No business sells anything priceable.');
    }

    private function price(User $business, ?int $lineId, float $amount, string $itemType = 'category'): BusinessServicePrice
    {
        $row = BusinessServicePrice::create([
            'business_id' => $business->id,
            'child_id' => $business->category_child_id,
            'service_id' => (int) DB::table('platform_services')->where('is_active', 1)->value('id'),
            'bookable_item_type' => $itemType,
            'price' => $amount,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        $row->syncOfferingOptions($lineId);

        return $row;
    }

    private function booking(User $business, ?BusinessServicePrice $offering): Booking
    {
        return Booking::create([
            'user_id' => User::query()->where('type', 'client')->value('id'),
            'business_id' => $business->id,
            'service_id' => $offering?->service_id
                ?? (int) DB::table('platform_services')->where('is_active', 1)->value('id'),
            'business_service_price_id' => $offering?->id,
            'date' => now()->toDateString(),
            'time' => '10:00',
            'price' => $offering?->price ?? 0,
            'status' => Booking::STATUS_PENDING,
        ]);
    }

    /** «كشف — عظام» instead of the service's bare name. */
    public function test_a_booking_names_what_was_actually_booked(): void
    {
        [$business, $line] = $this->sellerAndLine();

        $offering = $this->price($business, $line, 300);
        $booking = $this->booking($business, $offering);

        $this->assertSame($offering->offeringLabel(), $booking->fresh()->title());
        $this->assertNotSame('', $booking->title());
    }

    /** A booking from a row that names nothing falls back to the service. */
    public function test_a_nameless_booking_falls_back_to_its_service(): void
    {
        [$business] = $this->sellerAndLine();

        $booking = $this->booking($business, null);

        $service = $booking->service;

        // the title speaks the reader's language, like every other name here
        $expected = app()->getLocale() === 'en'
            ? ($service->name_en ?: $service->name_ar)
            : ($service->name_ar ?: $service->name_en);

        $this->assertSame((string) $expected, $booking->fresh()->title());
    }

    /**
     * The reason the reference has to be stored at all: without it the price
     * ladder picks whichever row was created last.
     */
    public function test_the_named_offering_decides_the_price(): void
    {
        [$business, $line] = $this->sellerAndLine();

        $other = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->where('o.id', '!=', $line)
            ->value('o.id');

        if (! $other) {
            $this->markTestSkipped('Only one line option exists.');
        }

        $cheap = $this->price($business, $line, 250);
        $dear = $this->price($business, (int) $other, 300);

        $resolver = app(BusinessServicePriceResolver::class);

        $ladder = $resolver->resolve(
            businessId: (int) $business->id,
            serviceId: (int) $cheap->service_id,
            childId: (int) $business->category_child_id,
            itemType: 'category'
        );

        $this->assertSame((int) $dear->id, (int) $ladder->id, 'the ladder takes the newest — that is the ambiguity');

        $named = $resolver->resolve(
            businessId: (int) $business->id,
            serviceId: (int) $cheap->service_id,
            childId: (int) $business->category_child_id,
            itemType: 'category',
            offeringId: (int) $cheap->id
        );

        $this->assertSame((int) $cheap->id, (int) $named->id);
        $this->assertEqualsWithDelta(250.0, (float) $named->price, 0.001);
    }

    /** A row belonging to another business is never honoured. */
    public function test_an_offering_from_another_business_is_ignored(): void
    {
        [$business, $line] = $this->sellerAndLine();

        $mine = $this->price($business, $line, 250);

        $stranger = User::query()->where('type', 'business')->where('id', '!=', $business->id)->first();

        if (! $stranger) {
            $this->markTestSkipped('Only one business exists.');
        }

        $resolved = app(BusinessServicePriceResolver::class)->resolve(
            businessId: (int) $stranger->id,
            serviceId: (int) $mine->service_id,
            childId: (int) $stranger->category_child_id,
            itemType: 'category',
            offeringId: (int) $mine->id
        );

        $this->assertNotSame((int) $mine->id, (int) optional($resolved)->id);
    }

    /** An inactive row is not a price anyone may book at. */
    public function test_an_inactive_offering_is_not_honoured(): void
    {
        [$business, $line] = $this->sellerAndLine();

        $row = $this->price($business, $line, 250);
        $row->update(['is_active' => 0]);

        $resolved = app(BusinessServicePriceResolver::class)->resolve(
            businessId: (int) $business->id,
            serviceId: (int) $row->service_id,
            childId: (int) $business->category_child_id,
            itemType: 'category',
            offeringId: (int) $row->id
        );

        $this->assertNotSame((int) $row->id, (int) optional($resolved)->id);
    }
}
