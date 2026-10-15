<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\ExternalDepositService;
use App\Services\ServiceExecutionEngine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «لا يصادر العربون ولكن يتجمد حساب العميل حتى يؤكد البزنس أنه استلمه كاش أو تحويلًا خارج المنصة، ولديه زر قبول
 * العربون» — المالك، 2026-10-19.
 *
 * A deposit taken outside the platform: the customer's account waits for the business to say it arrived (or to accept
 * the booking without it); nothing is confiscated. In the wallet mode the business may take the frozen deposit as a
 * payment on its own. Rolls back.
 */
class ExternalDepositTest extends TestCase
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

        // nobody else of this customer may be owing a deposit in this shared database
        Booking::query()->where('user_id', $this->client->id)->where('id', '!=', $booking->id)
            ->whereIn('status', [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])->update(['status' => Booking::STATUS_COMPLETED]);

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
        unset($meta['_execution_fee'], $meta['_financial_guard'], $meta['_deposit_as_payment'], $meta['_external_deposit']);
        $booking->meta = $meta;
        $booking->price = 1000;
        $booking->status = Booking::STATUS_ACCEPTED;
        $booking->save();

        $walletSvc->getOrCreateWallet($this->client->id)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
        $walletSvc->getOrCreateWallet($businessId)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
    }

    private function externalPolicy(): void
    {
        DB::table('business_deposit_policies')->insert([
            'business_id' => $this->business->id,
            'platform_service_id' => $this->booking->service_id,
            'category_child_id' => (int) ($this->business->category_child_id ?? 0),
            'scope_key' => BusinessDepositPolicy::SCOPE_BUSINESS_GLOBAL,
            'priority' => 0,
            'is_enabled' => 1,
            'deposit_mode' => BusinessDepositPolicy::MODE_EXTERNAL_VERIFICATION,
            'calculation_base' => BusinessDepositPolicy::BASE_TOTAL,
            'deposit_type' => BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => 20,
            'max_deposit_percent' => 50,
            'wallet_hold_enabled' => 0,
            'external_verification_enabled' => 1,
            'business_counter_hold_enabled' => 0,
            'business_counter_hold_percent' => 0,
            'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function walletPolicy(): void
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
            'accept_deposit_as_payment' => 1,
            'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function start(): void
    {
        app(ServiceExecutionEngine::class)->moveBookingToInProgress($this->booking);
        $this->booking->refresh();
    }

    public function test_a_deposit_owed_outside_is_pending_with_its_amount_until_the_business_says_so(): void
    {
        $this->externalPolicy();

        $state = app(ExternalDepositService::class)->state($this->booking->fresh());

        $this->assertTrue($state['required']);
        $this->assertSame('pending', $state['status']);
        $this->assertEqualsWithDelta(200.0, $state['amount'], 0.01);

        // without a policy there is nothing owed
        DB::table('business_deposit_policies')->where('business_id', $this->business->id)->delete();
        $this->assertFalse(app(ExternalDepositService::class)->state($this->booking->fresh())['required']);
    }

    public function test_the_booking_cannot_start_until_the_deposit_is_received_or_waived(): void
    {
        $this->externalPolicy();

        try {
            $this->start();
            $this->fail('it started with the deposit outstanding');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/bookings/{$this->booking->id}/deposit/confirm-received", ['method' => 'cash'])
            ->assertOk()->assertJsonPath('data.booking.external_deposit.status', 'received');

        $this->start();
        $this->assertSame(Booking::STATUS_IN_PROGRESS, $this->booking->status);
    }

    public function test_a_deposit_owed_outside_never_stops_the_customers_account_and_nothing_is_confiscated(): void
    {
        $this->externalPolicy();

        // owing the deposit stops nothing: the account books as usual (here the body is just incomplete)
        Sanctum::actingAs($this->client);
        $response = $this->postJson('/api/v2/bookings', ['business_id' => $this->business->id]);
        $this->assertArrayNotHasKey('external_deposit', (array) $response->json('errors'));
        $this->assertArrayNotHasKey('booking_ids', (array) $response->json('errors'));

        // the business records receipt by transfer
        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/bookings/{$this->booking->id}/deposit/confirm-received", ['method' => 'transfer', 'reference' => 'TR-1'])->assertOk();

        // no money moved anywhere
        $wallet = Wallet::query()->where('user_id', $this->client->id)->firstOrFail();
        $this->assertEqualsWithDelta(1000.0, (float) $wallet->balance + (float) $wallet->locked_balance, 0.001);
    }

    public function test_the_business_may_accept_the_booking_with_no_deposit(): void
    {
        $this->externalPolicy();

        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/bookings/{$this->booking->id}/deposit/waive")
            ->assertOk()->assertJsonPath('data.booking.external_deposit.status', 'waived');

        $this->assertTrue(app(ExternalDepositService::class)->isSettled($this->booking->fresh()));
        $this->start();
        $this->assertSame(Booking::STATUS_IN_PROGRESS, $this->booking->status);
    }

    public function test_only_the_owning_business_answers_and_only_when_a_deposit_is_owed(): void
    {
        $this->externalPolicy();
        $stranger = User::query()->where('type', User::TYPE_BUSINESS)->where('id', '!=', $this->business->id)->firstOrFail();

        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/business/bookings/{$this->booking->id}/deposit/waive")->assertForbidden();

        // no policy → nothing owed → 422
        DB::table('business_deposit_policies')->where('business_id', $this->business->id)->delete();
        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/business/bookings/{$this->booking->id}/deposit/confirm-received", ['method' => 'cash'])->assertStatus(422);
    }

    public function test_the_business_takes_a_frozen_wallet_deposit_as_a_payment_on_its_own(): void
    {
        $this->walletPolicy();
        $this->start();

        // no request from the customer was ever made
        Sanctum::actingAs($this->business);
        $this->postJson("/api/v2/bookings/{$this->booking->id}/deposit/accept-as-payment")
            ->assertOk()->assertJsonPath('data.deposit_as_payment.accepted', true);
    }
}
