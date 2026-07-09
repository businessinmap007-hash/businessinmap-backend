<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminV2\BookableAllocationController;
use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BusinessPartnership;
use App\Models\CommercialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * A bookable allocation is the owner carving inventory out to a partner. Saving
 * one must sync a commercial_offers row where the PARTNER is the seller, priced
 * at contract_price + markup, quantity = the allocated remainder — that offer is
 * how the reseller's allotment surfaces in discovery. Stop/delete propagate.
 * All rows are created inside a rolled-back transaction.
 */
class BookableAllocationSyncTest extends TestCase
{
    use DatabaseTransactions;

    private BookableItem $bookable;

    private BusinessPartnership $partnership;

    protected function setUp(): void
    {
        parent::setUp();

        $bookable = BookableItem::query()->whereNotNull('business_id')->first();
        $partner = $bookable
            ? User::query()->where('id', '!=', (int) $bookable->business_id)->first()
            : null;

        if (! $bookable || ! $partner) {
            $this->markTestSkipped('Needs a bookable item with an owner and a distinct partner user.');
        }

        $this->bookable = $bookable;
        $this->partnership = BusinessPartnership::create([
            'owner_business_id' => (int) $bookable->business_id,
            'partner_business_id' => (int) $partner->id,
            'relationship_type' => BusinessPartnership::TYPE_HOTEL_ALLOTMENT,
            'status' => BusinessPartnership::STATUS_ACTIVE,
        ]);
    }

    private function storeAllocation(array $overrides = []): BookableAllocation
    {
        $request = Request::create('/admin/bookable-allocations', 'POST', array_merge([
            'partnership_id' => $this->partnership->id,
            'bookable_item_id' => $this->bookable->id,
            'allocation_type' => BookableAllocation::TYPE_GUARANTEED,
            'quantity_total' => 10,
            'contract_price' => 100,
            'currency' => 'EGP',
            'markup_type' => 'percent',
            'markup_value' => 20,
            'status' => BookableAllocation::STATUS_ACTIVE,
        ], $overrides));

        app(BookableAllocationController::class)->store($request);

        return BookableAllocation::query()->latest('id')->firstOrFail();
    }

    private function offerFor(BookableAllocation $allocation): ?CommercialOffer
    {
        return CommercialOffer::query()
            ->where('source_type', CommercialOffer::SOURCE_ALLOCATION)
            ->where('source_id', (int) $allocation->id)
            ->first();
    }

    public function test_store_generates_a_partner_seller_offer_with_markup_price(): void
    {
        $allocation = $this->storeAllocation();

        $this->assertSame((int) $this->partnership->owner_business_id, (int) $allocation->owner_business_id);
        $this->assertSame((int) $this->partnership->partner_business_id, (int) $allocation->partner_business_id);

        $offer = $this->offerFor($allocation);
        $this->assertNotNull($offer, 'allocation should generate a commercial offer');

        // The PARTNER sells the owner's inventory.
        $this->assertSame((int) $this->partnership->partner_business_id, (int) $offer->seller_business_id);
        $this->assertSame((int) $this->partnership->owner_business_id, (int) $offer->owner_business_id);
        // 100 + 20% markup = 120; quantity = 10 - 0 sold/reserved/released.
        $this->assertEqualsWithDelta(100.0, (float) $offer->base_price, 0.001);
        $this->assertEqualsWithDelta(120.0, (float) $offer->final_price, 0.001);
        $this->assertSame(10, (int) $offer->available_quantity);
        $this->assertSame(CommercialOffer::STATUS_ACTIVE, (string) $offer->status);
        $this->assertSame(CommercialOffer::AVAILABILITY_LIMITED, (string) $offer->availability_mode);
    }

    public function test_available_quantity_excludes_sold_and_reserved(): void
    {
        $allocation = $this->storeAllocation(['quantity_total' => 10, 'quantity_sold' => 3, 'quantity_reserved' => 2]);

        // 10 - 3 - 2 = 5.
        $this->assertSame(5, $allocation->availableQuantity());
        $this->assertSame(5, (int) $this->offerFor($allocation)->available_quantity);
    }

    public function test_stop_pauses_the_offer_and_delete_removes_it(): void
    {
        $allocation = $this->storeAllocation();
        $offerId = (int) $this->offerFor($allocation)->id;

        $controller = app(BookableAllocationController::class);

        $controller->stop($allocation);
        $this->assertSame(CommercialOffer::STATUS_PAUSED, (string) CommercialOffer::query()->whereKey($offerId)->value('status'));

        $controller->destroy($allocation);
        $this->assertNull(CommercialOffer::query()->whereKey($offerId)->first(), 'deleting the allocation removes its offer');
    }
}
