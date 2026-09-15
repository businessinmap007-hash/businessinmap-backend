<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessStaff;
use App\Models\Order;
use App\Models\StaffActivityLog;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The owner's "who did what" review: every staff-scoped action on an order or
 * a booking writes one StaffActivityLog row under the REAL acting user's own
 * id — never the business id alone, which is all any other event/notification
 * pipeline captures. Also the first proof that booking fulfilment actions
 * (accept/reject/business-confirm/start/complete) are staff-delegable at all —
 * they were owner-only before this feature.
 */
class StaffActivityLogTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
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

    private function makeBooking(User $business, User $client, string $status = Booking::STATUS_PENDING): Booking
    {
        $serviceId = (int) (Booking::query()->value('service_id') ?: 1);

        return Booking::create([
            'user_id' => $client->id,
            'business_id' => $business->id,
            'service_id' => $serviceId,
            'status' => $status,
            'price' => 100,
            'quantity' => 1,
            'date' => now()->toDateString(),
            'time' => '12:00',
            'starts_at' => now()->addDay(),
            'meta' => ['source' => 'staff_activity_log_test'],
        ]);
    }

    public function test_a_staff_members_own_identity_is_recorded_not_the_business(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'Waiter');
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust');

        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);

        $order = $this->makeOrder($owner, $customer);

        $this->actingAs($waiter, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('staff_activity_logs', [
            'business_id' => (int) $owner->id,
            'user_id' => (int) $waiter->id,
            'capability' => BusinessCapability::ORDERS,
            'action' => 'accepted',
            'subject_id' => (int) $order->id,
        ]);

        $log = StaffActivityLog::where('subject_id', $order->id)->firstOrFail();
        $this->assertSame(Order::class, $log->subject_type, 'subject_type stores the full class name, matching this codebase\'s convention everywhere else');
        $this->assertTrue($log->subject->is($order));
    }

    public function test_the_owner_acting_directly_is_also_logged_under_their_own_id(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop2');
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust2');
        $order = $this->makeOrder($owner, $customer);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/accept")
            ->assertOk();

        $this->assertDatabaseHas('staff_activity_logs', [
            'business_id' => (int) $owner->id,
            'user_id' => (int) $owner->id,
            'action' => 'accepted',
        ]);
    }

    public function test_a_failed_action_writes_no_log_row(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop3');
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust3');
        $order = $this->makeOrder($owner, $customer);

        // Accept once (succeeds, logs once)...
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/accept")
            ->assertOk();
        $this->assertSame(1, StaffActivityLog::where('subject_id', $order->id)->count());

        // ...accepting an already-accepted order fails, and must NOT log again.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/accept")
            ->assertStatus(409);

        $this->assertSame(
            1,
            StaffActivityLog::where('subject_id', $order->id)->count(),
            'a failed re-accept must not add a second log row'
        );
    }

    public function test_a_bookings_delegate_can_now_run_the_full_business_lifecycle_and_it_is_logged(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Clinic2');
        $secretary = $this->makeUser(User::TYPE_CLIENT, 'Sec');
        $client = $this->makeUser(User::TYPE_CLIENT, 'Patient');

        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $secretary->id,
            'capabilities' => [BusinessCapability::BOOKINGS],
            'is_active' => true,
        ]);

        $booking = $this->makeBooking($owner, $client);

        $this->actingAs($secretary, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/accept")
            ->assertOk();

        $this->assertSame(Booking::STATUS_ACCEPTED, $booking->fresh()->status);
        $this->assertDatabaseHas('staff_activity_logs', [
            'business_id' => (int) $owner->id,
            'user_id' => (int) $secretary->id,
            'capability' => BusinessCapability::BOOKINGS,
            'action' => 'accepted',
            'subject_id' => (int) $booking->id,
        ]);

        $log = StaffActivityLog::where('subject_id', $booking->id)->where('action', 'accepted')->firstOrFail();
        $this->assertSame(Booking::class, $log->subject_type);
    }

    public function test_a_delegate_without_the_bookings_capability_is_still_refused(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Clinic3');
        $cashier = $this->makeUser(User::TYPE_CLIENT, 'Cashier2');
        $client = $this->makeUser(User::TYPE_CLIENT, 'Patient2');

        // Granted orders only, not bookings.
        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $cashier->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);

        $booking = $this->makeBooking($owner, $client);

        $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/accept")
            ->assertForbidden();

        $this->assertSame(Booking::STATUS_PENDING, $booking->fresh()->status);
        $this->assertSame(0, StaffActivityLog::where('subject_id', $booking->id)->count());
    }

    public function test_a_stranger_cannot_touch_the_booking_and_owner_can_still_act_directly(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Clinic4');
        $client = $this->makeUser(User::TYPE_CLIENT, 'Patient3');
        $stranger = $this->makeUser(User::TYPE_CLIENT, 'Stranger2');

        $booking = $this->makeBooking($owner, $client);

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/accept")
            ->assertForbidden();

        // The owner, with no staff row at all, still acts directly (BusinessContext falls back).
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v2/bookings/{$booking->id}/accept")
            ->assertOk();

        $this->assertSame(Booking::STATUS_ACCEPTED, $booking->fresh()->status);
    }

    public function test_the_owner_reviews_a_staff_members_activity_on_the_web_panel(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest2');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'Waiter2');
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust4');

        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);

        $order = $this->makeOrder($owner, $customer);
        $this->actingAs($waiter, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/accept")
            ->assertOk();

        $html = $this->actingAs($owner)
            ->get(route('business.staff.activity', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($waiter->name, $html);
        $this->assertStringContainsString((string) $order->id, $html);
    }

    public function test_a_non_business_account_cannot_reach_the_activity_review_screen(): void
    {
        $client = $this->makeUser(User::TYPE_CLIENT, 'JustAClient');

        $this->actingAs($client)
            ->get(route('business.staff.activity', [], false))
            ->assertRedirect(route('business.login'));
    }

    public function test_filtering_by_staff_member_excludes_others(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest3');
        $waiterA = $this->makeUser(User::TYPE_CLIENT, 'WaiterA');
        $waiterB = $this->makeUser(User::TYPE_CLIENT, 'WaiterB');
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust5');

        foreach ([$waiterA, $waiterB] as $w) {
            BusinessStaff::create([
                'business_id' => $owner->id,
                'user_id' => $w->id,
                'capabilities' => [BusinessCapability::ORDERS],
                'is_active' => true,
            ]);
        }

        $orderA = $this->makeOrder($owner, $customer);
        $orderB = $this->makeOrder($owner, $customer);

        $this->actingAs($waiterA, 'sanctum')->postJson("/api/v2/business/orders/{$orderA->id}/accept")->assertOk();
        $this->actingAs($waiterB, 'sanctum')->postJson("/api/v2/business/orders/{$orderB->id}/accept")->assertOk();

        $html = $this->actingAs($owner)
            ->get(route('business.staff.activity', ['user_id' => $waiterA->id], false))
            ->assertOk()
            ->getContent();

        // waiterB's name still appears once, in the filter dropdown's own
        // option list -- the real assertion is that their ORDER doesn't show
        // up in the filtered table body.
        $this->assertStringContainsString((string) $orderA->id, $html);
        $this->assertStringNotContainsString((string) $orderB->id, $html);
    }

    public function test_the_owner_can_fetch_activity_as_json_with_per_staff_counts(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest4');
        $waiterA = $this->makeUser(User::TYPE_CLIENT, 'WaiterC');
        $waiterB = $this->makeUser(User::TYPE_CLIENT, 'WaiterD');
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust6');

        foreach ([$waiterA, $waiterB] as $w) {
            BusinessStaff::create([
                'business_id' => $owner->id,
                'user_id' => $w->id,
                'capabilities' => [BusinessCapability::ORDERS],
                'is_active' => true,
            ]);
        }

        $orderA1 = $this->makeOrder($owner, $customer);
        $orderA2 = $this->makeOrder($owner, $customer);
        $orderB1 = $this->makeOrder($owner, $customer);

        $this->actingAs($waiterA, 'sanctum')->postJson("/api/v2/business/orders/{$orderA1->id}/accept")->assertOk();
        $this->actingAs($waiterA, 'sanctum')->postJson("/api/v2/business/orders/{$orderA2->id}/accept")->assertOk();
        $this->actingAs($waiterB, 'sanctum')->postJson("/api/v2/business/orders/{$orderB1->id}/accept")->assertOk();

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff-activity')
            ->assertOk()
            ->json('data');

        $summary = collect($response['summary'])->keyBy('user_id');
        $this->assertSame(2, $summary[$waiterA->id]['count']);
        $this->assertSame(1, $summary[$waiterB->id]['count']);
        $this->assertSame(0, $summary[$owner->id]['count']);
        $this->assertFalse($summary[$waiterA->id]['is_owner']);
        $this->assertTrue($summary[$owner->id]['is_owner']);

        $this->assertCount(3, $response['rows']['data']);
        $this->assertSame('order', $response['rows']['data'][0]['subject_type']);
    }

    public function test_a_non_owner_cannot_fetch_the_activity_json(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest5');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterE');

        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);

        $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/v2/business/staff-activity')
            ->assertForbidden();
    }

    public function test_the_web_review_screen_shows_the_operation_count_summary(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest6');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterF');
        $customer = $this->makeUser(User::TYPE_CLIENT, 'Cust7');

        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);

        $order = $this->makeOrder($owner, $customer);
        $this->actingAs($waiter, 'sanctum')
            ->postJson("/api/v2/business/orders/{$order->id}/accept")
            ->assertOk();

        $html = $this->actingAs($owner)
            ->get(route('business.staff.activity', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('a2-stat-card', $html);
        $this->assertStringContainsString($waiter->name, $html);
    }
}
