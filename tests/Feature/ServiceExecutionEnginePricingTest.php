<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Engine-level pricing resolution: resolveBusinessPriceForBooking derives the
 * item type / child from the booking and returns the backing
 * BusinessServicePrice (or null). Uses an unsaved Booking pointed at a real
 * business so relations load; prices are seeded/cleared in a rolled-back
 * transaction.
 */
class ServiceExecutionEnginePricingTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceExecutionEngine $engine;

    private User $business;

    private int $serviceId;

    private int $childId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(ServiceExecutionEngine::class);

        $business = User::query()->where('type', 'business')->whereNotNull('category_child_id')->first();
        $serviceId = (int) PlatformService::query()->value('id');

        if (! $business || $serviceId <= 0) {
            $this->markTestSkipped('Needs a business with a child and a platform service.');
        }

        $this->business = $business;
        $this->serviceId = $serviceId;
        $this->childId = (int) $business->category_child_id;

        BusinessServicePrice::query()
            ->where('business_id', $this->business->id)
            ->where('service_id', $this->serviceId)
            ->where('child_id', $this->childId)
            ->delete();
    }

    private function booking(): Booking
    {
        $booking = new Booking();
        $booking->business_id = (int) $this->business->id;
        $booking->service_id = $this->serviceId;

        return $booking;
    }

    public function test_resolves_the_backing_price_for_a_booking(): void
    {
        $price = BusinessServicePrice::create([
            'business_id' => $this->business->id,
            'service_id' => $this->serviceId,
            'child_id' => $this->childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => 700,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        $got = $this->engine->resolveBusinessPriceForBooking($this->booking());

        $this->assertNotNull($got);
        $this->assertSame($price->id, $got->id);
        $this->assertSame('700.00', (string) $got->price);
    }

    public function test_returns_null_when_no_active_price_backs_the_booking(): void
    {
        // Slate cleared in setUp; nothing to resolve.
        $this->assertNull($this->engine->resolveBusinessPriceForBooking($this->booking()));
    }
}
