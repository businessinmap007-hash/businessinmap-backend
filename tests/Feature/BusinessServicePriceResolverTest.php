<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BusinessServicePriceResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The single pricing-resolution seam the booking engine and the calendar both
 * rely on. Priority: same child + exact item type > child + default type >
 * child + any > (then the same, child-less). Runs against the dev DB inside a
 * rolled-back transaction; clears the (business, service, child) slate first
 * so the fallbacks are deterministic.
 */
class BusinessServicePriceResolverTest extends TestCase
{
    use DatabaseTransactions;

    private BusinessServicePriceResolver $resolver;

    private int $businessId;

    private int $serviceId;

    private int $childId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new BusinessServicePriceResolver();

        $business = User::query()->where('type', 'business')->whereNotNull('category_child_id')->first();
        $serviceId = (int) PlatformService::query()->value('id');

        if (! $business || $serviceId <= 0) {
            $this->markTestSkipped('Needs a business with a child and a platform service.');
        }

        $this->businessId = (int) $business->id;
        $this->serviceId = $serviceId;
        $this->childId = (int) $business->category_child_id;

        // Isolate: clear any existing prices for this exact context.
        BusinessServicePrice::query()
            ->where('business_id', $this->businessId)
            ->where('service_id', $this->serviceId)
            ->where('child_id', $this->childId)
            ->delete();
    }

    private function seedPrice(string $itemType, float $price, bool $active = true): BusinessServicePrice
    {
        return BusinessServicePrice::create([
            'business_id' => $this->businessId,
            'service_id' => $this->serviceId,
            'child_id' => $this->childId,
            'bookable_item_type' => $itemType,
            'price' => $price,
            'currency' => 'EGP',
            'is_active' => $active ? 1 : 0,
        ]);
    }

    public function test_resolves_exact_item_type(): void
    {
        $row = $this->seedPrice('room_test', 500);

        $got = $this->resolver->resolve($this->businessId, $this->serviceId, $this->childId, 'room_test');

        $this->assertNotNull($got);
        $this->assertSame($row->id, $got->id);
    }

    public function test_exact_item_type_beats_default(): void
    {
        $this->seedPrice(BusinessServicePrice::DEFAULT_ITEM_TYPE, 300);
        $exact = $this->seedPrice('room_test', 500);

        $got = $this->resolver->resolve($this->businessId, $this->serviceId, $this->childId, 'room_test');

        $this->assertSame($exact->id, $got->id, 'exact item type must win over the default');
        $this->assertSame('500.00', (string) $got->price);
    }

    public function test_falls_back_to_default_when_item_type_absent(): void
    {
        $default = $this->seedPrice(BusinessServicePrice::DEFAULT_ITEM_TYPE, 300);

        // Asking for an item type that has no row falls back to the default type.
        $got = $this->resolver->resolve($this->businessId, $this->serviceId, $this->childId, 'does_not_exist');

        $this->assertSame($default->id, $got->id);
    }

    public function test_inactive_price_does_not_resolve(): void
    {
        $this->seedPrice('room_test', 500, active: false);

        $got = $this->resolver->resolve($this->businessId, $this->serviceId, $this->childId, 'room_test');

        $this->assertNull($got, 'inactive prices must never resolve');
    }

    public function test_guards_reject_invalid_ids(): void
    {
        $this->assertNull($this->resolver->resolve(0, $this->serviceId, $this->childId, 'room_test'));
        $this->assertNull($this->resolver->resolve($this->businessId, 0, $this->childId, 'room_test'));
    }
}
