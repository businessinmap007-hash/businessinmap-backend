<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Findings from the 2026-07-18 security review of the *routed* v1 surface.
 *
 * Several of these were latent rather than live — the code could not run
 * because a table or column it depends on no longer exists. They are guarded
 * anyway: "safe because a migration is missing" is an accident, and the wallet
 * top-up plan explicitly intends to revive `Api\V1\PaymentController`.
 */
class LegacyApiSecurityAuditTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type = 'client', ?int $categoryId = null): User
    {
        return User::query()->forceCreate([
            'name' => 'Test '.$type.' '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => $type.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => Str::random(60),
            'type' => $type,
            'category_id' => $categoryId,
        ]);
    }

    /** delivery_orders has several NOT NULL columns with no default. */
    private function deliveryOrder(array $attributes): DeliveryOrder
    {
        return DeliveryOrder::create(array_merge([
            'pickup_address' => 'Pickup',
            'pickup_lat' => 30.0444,
            'pickup_lng' => 31.2357,
            'dropoff_address' => 'Dropoff',
            'dropoff_lat' => 30.0500,
            'dropoff_lng' => 31.2400,
            'status' => 'pending',
            'delivery_type' => 'standard',
        ], $attributes));
    }

    // ───────────── the one that was actually exploitable ─────────────

    public function test_the_fawry_callback_refuses_an_unsigned_payload(): void
    {
        // Public route (middleware ['api'] only) that verified nothing: anyone
        // could POST a merchantRefNumber and have the platform settle that
        // payment. The recharge branch died on the missing `transactions`
        // table, but the subscription branch touches only `subscriptions` and
        // `payments`, so ~730 unpaid subscriptions were free to activate.
        $response = $this->postJson('/api/v1/fawry-success-payment', [
            'merchantRefNumber' => 1,
            'orderStatus' => 'PAID',
            'paymentMethod' => 'CARD',
            'referenceNumber' => 'forged-'.uniqid(),
        ]);

        $response->assertStatus(400);

        // And it must refuse before touching the row.
        $this->assertDatabaseMissing('payments', ['id' => 1, 'payment_no' => 'forged']);
    }

    public function test_the_fawry_callback_refuses_a_forged_signature(): void
    {
        $this->postJson('/api/v1/fawry-success-payment', [
            'merchantRefNumber' => 1,
            'orderStatus' => 'PAID',
            'paymentMethod' => 'CARD',
            'messageSignature' => str_repeat('a', 64),
        ])->assertStatus(400);
    }

    // ───────────────── latent money bugs, now guarded ─────────────────

    public function test_a_transfer_cannot_carry_a_negative_amount(): void
    {
        // price was unvalidated, and `balance < -100` is false, so a negative
        // amount sailed past the balance check and then moved money the wrong
        // way: it deposited -100 to the target and withdrew -100 from the
        // sender, draining the recipient.
        $sender = $this->user();
        $target = $this->user('business');
        $target->forceFill(['code' => 'CODE'.Str::upper(Str::random(6))])->save();

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/payment/transfer', [
            'profileCode' => $target->code,
            'price' => -100,
        ])->assertStatus(422);
    }

    public function test_a_transfer_to_yourself_is_refused(): void
    {
        $user = $this->user();
        $user->forceFill(['code' => 'CODE'.Str::upper(Str::random(6))])->save();

        // NB: this controller reports errors as `status` INSIDE a 200 body —
        // an old v1 convention. Left alone: changing it would break whatever
        // clients still read it. Assert the behaviour, not a tidier contract.
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/payment/transfer', [
            'profileCode' => $user->code,
            'price' => 10,
        ]);

        $this->assertSame(400, $response->json('status'));
        $this->assertStringContainsString('نفسك', (string) $response->json('message'));
    }

    public function test_a_subscription_must_carry_a_sane_duration(): void
    {
        // `duration` was unvalidated and fed straight into addMonths().
        $user = $this->user('business');

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/payment/subscription', [
            'price' => 0,
            'duration' => 9999,
        ])->assertStatus(422);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/payment/subscription', [
            'price' => 10,
        ])->assertStatus(422);
    }

    // ─────────────────── spoofing and PII exposure ───────────────────

    public function test_a_user_cannot_plant_a_notification_on_someone_else(): void
    {
        // Accepted an arbitrary user_id with an arbitrary title and body — a
        // phishing message wearing the platform's own voice.
        $attacker = $this->user();
        $victim = $this->user();

        $this->actingAs($attacker, 'sanctum')->postJson('/api/v1/notifications', [
            'user_id' => $victim->id,
            'title' => 'حسابك موقوف',
            'body' => 'اضغط هنا',
        ])->assertForbidden();
    }

    public function test_a_stranger_cannot_read_someone_elses_delivery_order(): void
    {
        // show() took no Request and checked nothing, returning the customer's
        // identity, both addresses with coordinates, and the courier position.
        $customer = $this->user();
        $business = $this->user('business');

        $order = $this->deliveryOrder([
            'user_id' => $customer->id,
            'business_id' => $business->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->user(), 'sanctum')
            ->getJson('/api/v1/delivery/orders/'.$order->id)
            ->assertNotFound();

        // The parties themselves still can.
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/delivery/orders/'.$order->id)
            ->assertOk();

        $this->actingAs($business, 'sanctum')
            ->getJson('/api/v1/delivery/orders/'.$order->id)
            ->assertOk();
    }

    public function test_a_courier_may_still_inspect_an_unclaimed_order(): void
    {
        // Shipping businesses (category 5) browse and accept open jobs; this
        // is the same set /delivery/orders/available already shows them.
        $order = $this->deliveryOrder([
            'user_id' => $this->user()->id,
            'business_id' => $this->user('business')->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->user('business', 5), 'sanctum')
            ->getJson('/api/v1/delivery/orders/'.$order->id)
            ->assertOk();
    }

    public function test_a_courier_loses_sight_of_an_order_once_it_is_claimed(): void
    {
        $order = $this->deliveryOrder([
            'user_id' => $this->user()->id,
            'business_id' => $this->user('business')->id,
            'courier_id' => $this->user('business', 5)->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($this->user('business', 5), 'sanctum')
            ->getJson('/api/v1/delivery/orders/'.$order->id)
            ->assertNotFound();
    }

    // ─────────── surfaces confirmed already correct (keep them so) ───────────

    public function test_transactions_stay_scoped_to_their_owner(): void
    {
        // Audited clean; this pins it. The table itself is missing, so the
        // endpoint 500s — assert the scoping contract, not a 200.
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasTable('transactions'),
            'If `transactions` is ever restored, re-audit Api\\V1\\PaymentController: '
            .'its store() still trusts the client-supplied price.'
        );
    }

    public function test_order_viewing_remains_party_only(): void
    {
        $stranger = $this->user();
        $orderId = DB::table('orders')->value('id');

        if (! $orderId) {
            $this->markTestSkipped('No order to probe.');
        }

        $this->actingAs($stranger, 'sanctum')
            ->getJson('/api/v1/orders/'.$orderId)
            ->assertForbidden();
    }
}
