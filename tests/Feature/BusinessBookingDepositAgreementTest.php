<?php

namespace Tests\Feature;

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
 * The business owner panel's (/business) own half of the self-service
 * deposit settlement, mirroring the mobile app's endpoints — a business
 * owner browsing their own booking can agree release/refund without going
 * through /admin at all.
 */
class BusinessBookingDepositAgreementTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceExecutionEngine $engine;

    private Booking $booking;

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
        UserGuarantee::query()->where('user_id', $booking->user_id)->where('target_type', 'client')->delete();
        foreach ([$booking->user_id, $businessId] as $uid) {
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

        $w = $walletSvc->getOrCreateWallet($booking->user_id);
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

    public function test_business_can_see_the_settlement_section(): void
    {
        $html = $this->actingAs($this->business)
            ->get(route('business.bookings.show', $this->booking->id, false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('تسوية الضمان', $html);
        $this->assertStringContainsString('تأكيد نجاح المعاملة', $html);
    }

    public function test_business_agreeing_release_alone_does_not_release_it(): void
    {
        $this->actingAs($this->business)
            ->post(route('business.bookings.deposit.agree-release', $this->booking->id, false))
            ->assertRedirect();

        $deposit = $this->deposit();
        $this->assertTrue((bool) $deposit->release_agreed_business);
        $this->assertTrue($deposit->isFrozen());
    }

    public function test_both_sides_agreeing_release_through_mixed_surfaces_releases_it(): void
    {
        // Business agrees from the web panel...
        $this->actingAs($this->business)
            ->post(route('business.bookings.deposit.agree-release', $this->booking->id, false))
            ->assertRedirect();

        // ...client agrees from the mobile API — same underlying service.
        $this->actingAs($this->booking->user, 'sanctum')
            ->postJson("/api/v2/bookings/{$this->booking->id}/deposit/agree-release")
            ->assertOk();

        $this->assertTrue($this->deposit()->isReleased());
    }

    public function test_a_foreign_business_cannot_see_or_act_on_this_booking(): void
    {
        $stranger = User::query()
            ->where('type', 'business')
            ->whereKeyNot($this->business->id)
            ->first();

        if (! $stranger) {
            $this->markTestSkipped('Needs a second business account.');
        }

        $this->actingAs($stranger)
            ->get(route('business.bookings.show', $this->booking->id, false))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->post(route('business.bookings.deposit.agree-release', $this->booking->id, false))
            ->assertNotFound();
    }
}
