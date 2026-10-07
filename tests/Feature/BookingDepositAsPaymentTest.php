<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\ServiceExecutionEngine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «الديبوزت والضمان إجراءات لضمان الجدية ولا تدخل في قيمة الخدمة» — المالك، 2026-10-07.
 *
 * A deposit comes back once the customer has paid the whole value directly outside the app. The ONE exception is an
 * optional term of the business: the customer may ask that the frozen deposit be taken as a payment instead, and the
 * business accepts or declines. Platform fees stay separate from the deposit. Rolls back.
 */
class BookingDepositAsPaymentTest extends TestCase
{
    use DatabaseTransactions;

    private Booking $booking;

    private User $client;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $walletSvc = app(WalletService::class);

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        $business = $booking?->business;

        if (! $booking || ! $business || (string) $business->type !== User::TYPE_BUSINESS) {
            $this->markTestSkipped('Needs a booking whose business is a business account.');
        }

        $this->booking = $booking;
        $this->client = $booking->user;
        $this->business = $business;

        $businessId = (int) $business->id;
        $serviceId = (int) $booking->service_id;
        $childId = (int) ($business->category_child_id ?? 0);

        BusinessServicePrice::query()->where('business_id', $businessId)->where('service_id', $serviceId)->where('child_id', $childId)->delete();
        BusinessServicePrice::create([
            'business_id' => $businessId, 'service_id' => $serviceId, 'child_id' => $childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        BusinessDepositPolicy::query()->where('business_id', $businessId)->delete();
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        UserGuarantee::query()->where('user_id', $this->client->id)->where('target_type', 'client')->delete();
        foreach ([$this->client->id, $businessId] as $uid) {
            DB::table('user_service_fee_consents')->updateOrInsert(
                ['user_id' => $uid],
                ['fee_auto_charge_enabled' => 0, 'rating_enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }
        DB::table('category_child_service_fees')->where('category_id', (int) $business->category_id)->where('child_id', $childId)->delete();

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
        $meta['pricing'] = ['final_price' => 1000];
        unset($meta['_execution_fee'], $meta['_financial_guard'], $meta['_deposit_as_payment']);
        $booking->meta = $meta;
        $booking->price = 1000;
        $booking->status = Booking::STATUS_ACCEPTED;
        $booking->save();

        $walletSvc->getOrCreateWallet($this->client->id)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
        $walletSvc->getOrCreateWallet($businessId)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
    }

    private function policy(bool $acceptAsPayment): void
    {
        DB::table('business_deposit_policies')->insert([
            'business_id' => $this->business->id,
            'platform_service_id' => $this->booking->service_id,
            'category_child_id' => (int) ($this->business->category_child_id ?? 0),
            'scope_key' => BusinessDepositPolicy::SCOPE_BUSINESS_GLOBAL,
            'priority' => 0,
            'is_enabled' => 1,
            'deposit_mode' => BusinessDepositPolicy::MODE_WALLET_HOLD,
            'calculation_base' => BusinessDepositPolicy::BASE_TOTAL,
            'deposit_type' => BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => 20,
            'max_deposit_percent' => 50,
            'wallet_hold_enabled' => 1,
            'business_counter_hold_enabled' => 1,
            'business_counter_hold_percent' => 50,
            'accept_deposit_as_payment' => $acceptAsPayment ? 1 : 0,
            'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function start(): void
    {
        app(ServiceExecutionEngine::class)->moveBookingToInProgress($this->booking);
        $this->booking->refresh();
    }

    private function deposit(): Deposit
    {
        return Deposit::query()->where('target_type', Booking::class)->where('target_id', $this->booking->id)->firstOrFail();
    }

    /** What the person holds in all: available balance plus what is frozen. */
    private function total(User $user): float
    {
        $wallet = Wallet::query()->where('user_id', $user->id)->firstOrFail();

        return (float) $wallet->balance + (float) $wallet->locked_balance;
    }

    public function test_it_is_off_unless_the_business_opted_in(): void
    {
        $this->policy(false);
        $this->start();

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/request-as-payment")
            ->assertStatus(422);

        $this->actingAs($this->client, 'sanctum')->getJson("/api/v2/bookings/{$this->booking->id}")
            ->assertOk()->assertJsonPath('data.deposit_as_payment.allowed', false);
    }

    public function test_the_customer_asks_and_the_business_is_told(): void
    {
        $this->policy(true);
        $this->start();

        $this->actingAs($this->client, 'sanctum')->getJson("/api/v2/bookings/{$this->booking->id}")
            ->assertJsonPath('data.deposit_as_payment.allowed', true)
            ->assertJsonPath('data.deposit_as_payment.requested', false);

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/request-as-payment")
            ->assertOk()
            ->assertJsonPath('data.deposit_as_payment.requested', true);

        $this->assertTrue(
            AppNotification::query()->where('user_id', $this->business->id)->where('source_type', 'booking.deposit_payment_requested')->exists()
        );

        // not twice
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/request-as-payment")->assertStatus(422);
    }

    public function test_the_business_cannot_accept_what_was_not_asked(): void
    {
        $this->policy(true);
        $this->start();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/accept-as-payment")
            ->assertStatus(422);
    }

    public function test_accepting_moves_the_customers_hold_to_the_business_and_returns_its_own(): void
    {
        $this->policy(true);
        $this->start();

        $deposit = $this->deposit();
        $hold = (float) $deposit->client_amount;
        $this->assertSame(200.0, $hold, '20% of 1000');

        $clientBefore = $this->total($this->client);
        $businessBefore = $this->total($this->business);

        $this->actingAs($this->client, 'sanctum')->postJson("/api/v2/bookings/{$this->booking->id}/deposit/request-as-payment")->assertOk();
        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/accept-as-payment")
            ->assertOk()
            ->assertJsonPath('data.deposit_as_payment.accepted', true)
            ->assertJsonPath('data.deposit_as_payment.amount', 200);

        $this->assertFalse($this->deposit()->fresh()->isFrozen(), 'the deposit is settled, not still frozen');
        // the customer's hold left their wallet and reached the business; the business's own counter-hold came back
        $this->assertEqualsWithDelta($clientBefore - $hold, $this->total($this->client), 0.01);
        $this->assertEqualsWithDelta($businessBefore + $hold, $this->total($this->business), 0.01);
        $this->assertSame(0.0, (float) Wallet::query()->where('user_id', $this->client->id)->value('locked_balance'));
        $this->assertSame(0.0, (float) Wallet::query()->where('user_id', $this->business->id)->value('locked_balance'));

        $this->assertTrue(
            AppNotification::query()->where('user_id', $this->client->id)->where('source_type', 'booking.deposit_payment_accepted')->exists()
        );
    }

    public function test_declining_leaves_the_deposit_frozen_and_the_normal_way_open(): void
    {
        $this->policy(true);
        $this->start();

        $this->actingAs($this->client, 'sanctum')->postJson("/api/v2/bookings/{$this->booking->id}/deposit/request-as-payment")->assertOk();
        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/decline-as-payment")
            ->assertOk()
            ->assertJsonPath('data.deposit_as_payment.requested', false)
            ->assertJsonPath('data.deposit_as_payment.declined', true);

        $this->assertTrue($this->deposit()->isFrozen());

        // the deposit still comes back when both confirm the cash was paid outside
        $this->actingAs($this->client, 'sanctum')->postJson("/api/v2/bookings/{$this->booking->id}/confirm-payment")->assertOk();
        $this->actingAs($this->business, 'sanctum')->postJson("/api/v2/business/bookings/{$this->booking->id}/confirm-payment")->assertOk();
        $this->assertTrue($this->deposit()->isReleased());
    }

    public function test_a_stranger_cannot_ask_or_answer(): void
    {
        $this->policy(true);
        $this->start();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/request-as-payment")->assertStatus(403);

        $other = User::query()->where('id', '!=', $this->client->id)->where('id', '!=', $this->business->id)->where('type', User::TYPE_BUSINESS)->firstOrFail();
        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/accept-as-payment")->assertStatus(403);
    }

    public function test_using_a_deposit_says_the_rating_is_open_to_each_party(): void
    {
        $this->policy(false);
        $this->start();

        foreach ([$this->client, $this->business] as $party) {
            $this->assertTrue(
                AppNotification::query()->where('user_id', $party->id)->where('source_type', 'wallet_rating_opened')->exists(),
                "party {$party->id} was not told the rating is open"
            );
            $this->assertTrue((bool) $party->fresh()->rating_enabled);
        }
    }
}
