<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\Fine;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BookingDepositService;
use App\Services\DisputeService;
use App\Services\FineService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The settlement → fine bridge.
 *
 * When the parties settle a dispute themselves, a platform fine that was part
 * of that settlement is recorded as NON-APPEALABLE — the consent to it was the
 * agreement, so «لا طعن بعد الاتفاق». The tests pin: it is non-appealable and
 * collectable at once (no waiting window), only one per dispute, and the admin
 * action is refused on a dispute that was not mutually settled.
 */
class SettlementFineBridgeTest extends TestCase
{
    use DatabaseTransactions;

    private FineService $fines;
    private User $admin;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fines = app(FineService::class);
        $this->admin = $this->makeAdmin();
        config(['bim.platform_wallet_user_id' => (int) $this->admin->id]);

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')->first();
        if ($booking && $booking->trashed()) {
            $booking->restore();
        }
        if (! $booking || ! $booking->user || ! $booking->business) {
            $this->markTestSkipped('Needs a booking with a client and a business.');
        }
        $this->booking = $booking;

        foreach ([(int) $booking->user_id, (int) $booking->business_id] as $uid) {
            app(WalletService::class)->getOrCreateWallet($uid)->update([
                'status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0,
            ]);
        }
    }

    private function makeAdmin(): User
    {
        $a = new User();
        $a->name = 'Settle Admin';
        $a->email = 'settle-' . uniqid() . '@example.test';
        $a->phone = '0155' . random_int(1000000, 9999999);
        $a->password = 'secret-password';
        $a->type = User::TYPE_ADMIN;
        $a->api_token = Str::random(80);
        $a->save();

        foreach ([\App\Support\AdminAbility::ACCESS, \App\Support\AdminAbility::DISPUTES, \App\Support\AdminAbility::MONEY] as $ability) {
            \Bouncer::allow($a)->to($ability);
        }
        \Bouncer::refresh();

        return $a;
    }

    public function test_a_settlement_fine_is_non_appealable_and_collectable_at_once(): void
    {
        $fine = $this->fines->levyFromSettlement(
            (int) $this->booking->user_id, 90, 'اتفاق على رسم شحن', (int) $this->admin->id, disputeId: 4242
        );

        $this->assertFalse($fine->is_appealable);
        $this->assertNull($fine->appeal_deadline_at, 'no appeal window');
        $this->assertSame(Fine::SOURCE_SETTLEMENT, $fine->source);
        $this->assertEqualsWithDelta(90, (float) $fine->frozen_amount, 0.001);
        // No window means it is due immediately, so the sweep collects it.
        $this->assertTrue($fine->isCollectable());
        $this->fines->processDue();
        $this->assertSame(Fine::STATUS_COLLECTED, $fine->fresh()->status);
    }

    public function test_it_cannot_be_appealed(): void
    {
        $fine = $this->fines->levyFromSettlement(
            (int) $this->booking->user_id, 50, 'اتفاق', (int) $this->admin->id, disputeId: 4243
        );

        $this->expectException(ValidationException::class);
        $this->fines->appeal($fine, (int) $this->booking->user_id, 'محاولة');
    }

    public function test_only_one_settlement_fine_per_dispute(): void
    {
        $this->fines->levyFromSettlement((int) $this->booking->user_id, 30, 'اتفاق', (int) $this->admin->id, disputeId: 4244);

        $this->expectException(ValidationException::class);
        $this->fines->levyFromSettlement((int) $this->booking->user_id, 40, 'مرة أخرى', (int) $this->admin->id, disputeId: 4244);
    }

    public function test_the_admin_action_is_refused_on_a_dispute_not_mutually_settled(): void
    {
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $this->booking->id)->delete();
        Dispute::query()->where('disputeable_type', Booking::class)->where('disputeable_id', $this->booking->id)->delete();

        app(BookingDepositService::class)->freezeForBooking($this->booking, 100.0, [
            'wallet_hold_amount' => 100.0, 'business_counter_hold_amount' => 0.0, 'amount' => 100.0,
        ]);
        $dispute = app(BookingDepositService::class)
            ->openDisputeForBooking($this->booking, (int) $this->booking->user_id);

        // Not mutually settled → the action refuses and creates no fine.
        $this->actingAs($this->admin)
            ->post(route('admin.disputes.settlement-fine', $dispute), [
                'side' => 'client', 'amount' => 50, 'reason' => 'x',
            ])->assertRedirect();

        $this->assertFalse(
            Fine::query()->where('source', Fine::SOURCE_SETTLEMENT)->where('meta->dispute_id', $dispute->id)->exists()
        );

        // Now mark it mutually settled and the same action creates the fine.
        $dispute->forceFill([
            'resolution_type' => DisputeService::RESOLUTION_MUTUAL,
            'status' => 'resolved',
        ])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.disputes.settlement-fine', $dispute), [
                'side' => 'client', 'amount' => 50, 'reason' => 'اتفاق على رسم',
            ])->assertRedirect();

        $this->assertTrue(
            Fine::query()->where('source', Fine::SOURCE_SETTLEMENT)->where('meta->dispute_id', $dispute->id)->exists()
        );
    }
}
