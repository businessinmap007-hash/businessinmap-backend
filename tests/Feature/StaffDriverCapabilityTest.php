<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The `drivers` capability is the Staff & Permissions door onto the same
 * private-fleet linking the dedicated "موصّليي" screen uses
 * (see BusinessDeliveryFleetTest for that screen's own coverage). A business
 * only sees this capability once it sells goods (the `menu` or `retail`
 * platform service) — delivery is no longer a service of its own, its
 * pickup/delivery option group carries the drivers - a category (21/21, Antiques under Exhibitions) confirmed to have
 * it is reused here rather than a synthetic one, so the test stays honest
 * about what BusinessCapability::forBusiness() actually derives.
 */
class StaffDriverCapabilityTest extends TestCase
{
    use DatabaseTransactions;

    private const DELIVERY_ROOT = 21;

    private const DELIVERY_CHILD = 21;

    private function makeUser(string $type, string $tag, bool $withDeliveryService = false): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;

        if ($withDeliveryService) {
            $u->category_id = self::DELIVERY_ROOT;
            $u->category_child_id = self::DELIVERY_CHILD;
        }

        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_granting_the_capability_links_the_staff_member_as_a_business_driver(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop', withDeliveryService: true);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $rider->phone,
            'capabilities' => [BusinessCapability::DRIVERS],
        ])->assertCreated();

        $this->assertDatabaseHas('delivery_drivers', [
            'user_id' => $rider->id,
            'business_id' => $owner->id,
            'is_active' => 1,
        ]);
    }

    public function test_a_business_without_the_delivery_service_cannot_grant_the_capability(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop2', withDeliveryService: false);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider2');

        // Ineligible capabilities are sanitised away, not rejected outright --
        // same as any other capability this business isn't entitled to. The
        // grant "succeeds" with an empty capability set and no driver link.
        $response = $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $rider->phone,
            'capabilities' => [BusinessCapability::DRIVERS],
        ])->assertCreated();

        $this->assertSame([], $response->json('data.staff.capabilities'));
        $this->assertDatabaseMissing('delivery_drivers', ['user_id' => $rider->id]);
    }

    public function test_granting_the_capability_to_a_driver_privately_linked_elsewhere_is_refused(): void
    {
        $ownerA = $this->makeUser(User::TYPE_BUSINESS, 'ShopA', withDeliveryService: true);
        $ownerB = $this->makeUser(User::TYPE_BUSINESS, 'ShopB', withDeliveryService: true);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider3');

        DeliveryDriver::create(['user_id' => $rider->id, 'business_id' => $ownerA->id, 'is_active' => true]);

        $this->actingAs($ownerB, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $rider->phone,
            'capabilities' => [BusinessCapability::DRIVERS],
        ])->assertStatus(422);

        // Neither poached, nor left as a half-saved staff row.
        $this->assertSame((int) $ownerA->id, (int) DeliveryDriver::where('user_id', $rider->id)->value('business_id'));
        $this->assertDatabaseMissing('business_staff', ['business_id' => $ownerB->id, 'user_id' => $rider->id]);
    }

    public function test_revoking_the_capability_takes_the_driver_off_duty_without_unlinking(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop4', withDeliveryService: true);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider4');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $rider->phone,
            'capabilities' => [BusinessCapability::DRIVERS, BusinessCapability::ORDERS],
        ])->assertCreated();

        $this->actingAs($owner, 'sanctum')->patchJson("/api/v2/business/staff/{$rider->id}", [
            'capabilities' => [BusinessCapability::ORDERS],
        ])->assertOk();

        $driver = DeliveryDriver::where('user_id', $rider->id)->first();
        $this->assertSame((int) $owner->id, (int) $driver->business_id, 'business_id must never clear on revoke');
        $this->assertFalse((bool) $driver->is_active);
    }

    public function test_removing_the_staff_member_entirely_also_takes_the_driver_off_duty(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop5', withDeliveryService: true);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider5');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $rider->phone,
            'capabilities' => [BusinessCapability::DRIVERS],
        ])->assertCreated();

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/v2/business/staff/{$rider->id}")->assertOk();

        $driver = DeliveryDriver::where('user_id', $rider->id)->first();
        $this->assertSame((int) $owner->id, (int) $driver->business_id);
        $this->assertFalse((bool) $driver->is_active);
        $this->assertDatabaseMissing('business_staff', ['business_id' => $owner->id, 'user_id' => $rider->id]);
    }

    public function test_the_linked_driver_appears_in_the_business_roster(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop6', withDeliveryService: true);
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider6');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $rider->phone,
            'capabilities' => [BusinessCapability::DRIVERS],
        ])->assertCreated();

        $roster = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/delivery-drivers')
            ->assertOk()
            ->json('data.drivers');

        $this->assertContains($rider->id, array_column($roster, 'user_id'));
    }

    public function test_the_capability_only_appears_for_a_business_that_sells_goods(): void
    {
        $withDelivery = $this->makeUser(User::TYPE_BUSINESS, 'Shop7', withDeliveryService: true);
        $withoutDelivery = $this->makeUser(User::TYPE_BUSINESS, 'Shop8', withDeliveryService: false);

        $this->assertArrayHasKey(BusinessCapability::DRIVERS, BusinessCapability::forBusiness($withDelivery));
        $this->assertArrayNotHasKey(BusinessCapability::DRIVERS, BusinessCapability::forBusiness($withoutDelivery));
    }
}
