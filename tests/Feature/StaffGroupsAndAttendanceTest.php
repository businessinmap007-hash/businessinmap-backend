<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\Order;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The owner's grouped staff view (BusinessStaffController::groups()) and the
 * self-service attendance check-in/check-out it reads back
 * (StaffAttendanceController) - "which group is doing what right now" at a
 * glance: who showed up today, how many operations they ran, and, for
 * drivers, whether they're currently carrying something.
 */
class StaffGroupsAndAttendanceTest extends TestCase
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

    private function makeOrder(User $business, User $customer): Order
    {
        return Order::create([
            'user_id' => $customer->id, 'business_id' => $business->id,
            'fulfillment_type' => Order::FULFILLMENT_DELIVERY, 'status' => 'pending',
            'total' => 50, 'discount' => 0, 'delivery_fee' => 0, 'service_fee' => 0,
            'tax' => 0, 'final_total' => 50, 'payment_method' => 'cash', 'address' => 'x',
        ]);
    }

    public function test_a_staff_member_can_check_in_and_out(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'Waiter');
        BusinessStaff::create([
            'business_id' => $owner->id, 'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS], 'is_active' => true,
        ]);

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])
            ->assertOk()
            ->assertJsonPath('data.is_present', true);

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-out', ['business_id' => $owner->id])
            ->assertOk()
            ->assertJsonPath('data.is_present', false);
    }

    public function test_checking_out_without_checking_in_is_refused(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop2');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'Waiter2');
        BusinessStaff::create([
            'business_id' => $owner->id, 'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS], 'is_active' => true,
        ]);

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-out', ['business_id' => $owner->id])
            ->assertStatus(422);
    }

    public function test_checking_in_twice_the_same_day_stays_one_row(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop3');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'Waiter3');
        BusinessStaff::create([
            'business_id' => $owner->id, 'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS], 'is_active' => true,
        ]);

        $this->actingAs($waiter, 'sanctum')->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])->assertOk();
        $this->actingAs($waiter, 'sanctum')->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])->assertOk();

        $this->assertSame(1, \App\Models\StaffAttendance::where('business_id', $owner->id)->where('user_id', $waiter->id)->count());
    }

    public function test_the_owner_sees_grouped_staff_with_operations_and_attendance(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop4', withDeliveryService: true);
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'Waiter4');
        $rider = $this->makeUser(User::TYPE_CLIENT, 'Rider4');
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust4');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $waiter->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $rider->phone, 'capabilities' => [BusinessCapability::DRIVERS],
        ])->assertCreated();

        // The waiter runs one order operation and checks in.
        $order = $this->makeOrder($owner, $customer);
        $this->actingAs($waiter, 'sanctum')->postJson("/api/v2/business/orders/{$order->id}/accept")->assertOk();
        $this->actingAs($waiter, 'sanctum')->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])->assertOk();

        // The rider never checks in and never carries anything today.
        $groups = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/groups')
            ->assertOk()
            ->json('data.groups');

        $byCapability = collect($groups)->keyBy('capability');

        $this->assertTrue($byCapability->has('orders'));
        $this->assertTrue($byCapability->has('drivers'));

        $waiterCard = collect($byCapability['orders']['staff'])->firstWhere('user_id', $waiter->id);
        $this->assertSame(1, $waiterCard['operations_today']);
        $this->assertTrue($waiterCard['attendance']['is_present']);
        $this->assertArrayNotHasKey('delivery_status', $waiterCard);

        $riderCard = collect($byCapability['drivers']['staff'])->firstWhere('user_id', $rider->id);
        $this->assertSame(0, $riderCard['operations_today']);
        $this->assertFalse($riderCard['attendance']['is_present']);
        $this->assertArrayHasKey('delivery_status', $riderCard);
        $this->assertFalse($riderCard['delivery_status']['busy']);
    }

    public function test_a_staff_member_with_two_capabilities_appears_in_both_groups(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop5', withDeliveryService: true);
        $both = $this->makeUser(User::TYPE_CLIENT, 'Both5');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $both->phone,
            'capabilities' => [BusinessCapability::ORDERS, BusinessCapability::DRIVERS],
        ])->assertCreated();

        $groups = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/groups')
            ->assertOk()
            ->json('data.groups');

        $byCapability = collect($groups)->keyBy('capability');
        $this->assertNotNull(collect($byCapability['orders']['staff'])->firstWhere('user_id', $both->id));
        $this->assertNotNull(collect($byCapability['drivers']['staff'])->firstWhere('user_id', $both->id));
    }

    public function test_a_non_owner_cannot_see_the_grouped_staff_view(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop6');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'Waiter6');
        BusinessStaff::create([
            'business_id' => $owner->id, 'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS], 'is_active' => true,
        ]);

        $this->actingAs($waiter, 'sanctum')->getJson('/api/v2/business/staff/groups')->assertForbidden();
    }
}
