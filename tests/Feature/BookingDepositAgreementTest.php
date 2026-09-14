<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BusinessDepositPolicy;
use App\Models\BusinessServicePrice;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\ServiceExecutionEngine;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The self-service half of "move booking actions off the admin panel": a
 * completed booking's frozen deposit used to be releasable/refundable ONLY
 * by an admin (AdminV2\BookingController::depositRelease()/depositRefund()).
 * Now the client and business can agree it from their own accounts — once
 * BOTH sides agree the SAME outcome, it executes automatically. Admin's
 * manual buttons remain, unchanged, as a fallback for disputes/deadlocks.
 */
class BookingDepositAgreementTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceExecutionEngine $engine;

    private Booking $booking;

    private User $client;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(ServiceExecutionEngine::class);
        $walletSvc = app(WalletService::class);

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')
            ->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
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

        BusinessServicePrice::query()
            ->where('business_id', $businessId)->where('service_id', $serviceId)->where('child_id', $childId)
            ->delete();
        BusinessServicePrice::create([
            'business_id' => $businessId, 'service_id' => $serviceId, 'child_id' => $childId,
            'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);

        BusinessDepositPolicy::query()->where('business_id', $businessId)->delete();
        DB::table('business_deposit_policies')->insert([
            'business_id' => $businessId,
            'platform_service_id' => $serviceId,
            'category_child_id' => $childId,
            'scope_key' => BusinessDepositPolicy::SCOPE_BUSINESS_GLOBAL,
            'priority' => 0,
            'is_enabled' => 1,
            'deposit_mode' => BusinessDepositPolicy::MODE_WALLET_HOLD,
            'calculation_base' => BusinessDepositPolicy::BASE_TOTAL,
            'deposit_type' => BusinessDepositPolicy::TYPE_PERCENT,
            'deposit_value' => 10,
            'max_deposit_percent' => 20,
            'wallet_hold_enabled' => 1,
            'business_counter_hold_enabled' => 0,
            'currency' => 'EGP',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        UserGuarantee::query()->where('user_id', $this->client->id)->where('target_type', 'client')->delete();
        foreach ([$this->client->id, $businessId] as $uid) {
            DB::table('user_service_fee_consents')->updateOrInsert(
                ['user_id' => $uid],
                ['fee_auto_charge_enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        DB::table('category_child_service_fees')
            ->where('category_id', (int) $business->category_id)
            ->where('child_id', $childId)
            ->delete();

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
        $meta['pricing'] = ['final_price' => 1000];
        unset($meta['_execution_fee'], $meta['_financial_guard']);
        $booking->meta = $meta;
        $booking->price = 1000;
        $booking->status = Booking::STATUS_ACCEPTED;
        $booking->save();

        $w = $walletSvc->getOrCreateWallet($this->client->id);
        $w->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);

        $this->engine->moveBookingToInProgress($this->booking);
        $this->booking->refresh();
    }

    private function deposit(): Deposit
    {
        return Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', $this->booking->id)
            ->firstOrFail();
    }

    public function test_only_one_side_agreeing_to_release_does_not_release_it(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertOk();

        $deposit = $this->deposit();
        $this->assertTrue((bool) $deposit->release_agreed_client);
        $this->assertFalse((bool) $deposit->release_agreed_business);
        $this->assertTrue($deposit->isFrozen(), 'one-sided agreement must not release the deposit');
    }

    public function test_both_sides_agreeing_to_release_releases_it_automatically(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertOk();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertOk();

        $deposit = $this->deposit();
        $this->assertTrue($deposit->isReleased(), 'both parties agreeing must auto-release the deposit with no admin action');
    }

    public function test_both_sides_agreeing_to_refund_refunds_it_automatically(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-refund")
            ->assertOk();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-refund")
            ->assertOk();

        $this->assertTrue($this->deposit()->isRefunded());
    }

    public function test_a_mismatched_agreement_executes_nothing(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertOk();

        $this->actingAs($this->business, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-refund")
            ->assertOk();

        $this->assertTrue($this->deposit()->isFrozen(), 'client wants release, business wants refund — nothing should execute');
    }

    public function test_agreeing_release_then_changing_to_refund_withdraws_the_release_agreement(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertOk();

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-refund")
            ->assertOk();

        $deposit = $this->deposit();
        $this->assertFalse((bool) $deposit->release_agreed_client, 'agreeing refund must withdraw the earlier release agreement');
        $this->assertTrue((bool) $deposit->refund_agreed_client);
    }

    public function test_a_non_party_cannot_agree(): void
    {
        $stranger = User::query()
            ->where('type', 'client')
            ->whereKeyNot($this->client->id)
            ->first();

        if (! $stranger) {
            $this->markTestSkipped('Needs a second client account.');
        }

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertForbidden();
    }

    public function test_agreement_is_blocked_while_a_dispute_is_open_on_the_deposit(): void
    {
        $deposit = $this->deposit();

        $dispute = Dispute::create([
            'disputeable_type' => Booking::class,
            'disputeable_id' => $this->booking->id,
            'opened_by_user_id' => $this->client->id,
            'against_user_id' => $this->business->id,
            'status' => Dispute::STATUS_OPEN,
            'deposit_id' => $deposit->id,
            'opened_at' => now(),
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertStatus(422);

        $dispute->delete();
    }

    public function test_a_frozen_undecided_deposit_shows_up_as_pending_for_both_parties(): void
    {
        $clientIds = collect(
            $this->actingAs($this->client, 'sanctum')
                ->getJson('/api/v2/bookings/pending-settlements')
                ->assertOk()
                ->json('data.bookings')
        )->pluck('id');

        $businessIds = collect(
            $this->actingAs($this->business, 'sanctum')
                ->getJson('/api/v2/bookings/pending-settlements')
                ->assertOk()
                ->json('data.bookings')
        )->pluck('id');

        $this->assertContains($this->booking->id, $clientIds);
        $this->assertContains($this->booking->id, $businessIds);
    }

    public function test_a_booking_drops_off_pending_settlements_once_this_party_decides(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertOk();

        $clientIds = collect(
            $this->actingAs($this->client, 'sanctum')
                ->getJson('/api/v2/bookings/pending-settlements')
                ->assertOk()
                ->json('data.bookings')
        )->pluck('id');

        // The client already decided — it's no longer pending for them...
        $this->assertNotContains($this->booking->id, $clientIds);

        // ...but the business still hasn't, so it's still pending for them.
        $businessIds = collect(
            $this->actingAs($this->business, 'sanctum')
                ->getJson('/api/v2/bookings/pending-settlements')
                ->assertOk()
                ->json('data.bookings')
        )->pluck('id');
        $this->assertContains($this->booking->id, $businessIds);
    }

    public function test_a_booking_drops_off_pending_settlements_once_a_dispute_is_open(): void
    {
        $deposit = $this->deposit();

        Dispute::create([
            'disputeable_type' => Booking::class,
            'disputeable_id' => $this->booking->id,
            'opened_by_user_id' => $this->client->id,
            'against_user_id' => $this->business->id,
            'status' => Dispute::STATUS_OPEN,
            'deposit_id' => $deposit->id,
            'opened_at' => now(),
        ]);

        $clientIds = collect(
            $this->actingAs($this->client, 'sanctum')
                ->getJson('/api/v2/bookings/pending-settlements')
                ->assertOk()
                ->json('data.bookings')
        )->pluck('id');

        $this->assertNotContains($this->booking->id, $clientIds, 'a disputed booking must not also demand a settlement decision');
    }
}
