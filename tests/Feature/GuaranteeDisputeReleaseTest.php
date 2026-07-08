<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\GuaranteeLevel;
use App\Models\OperationGuarantor;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\BookingDepositService;
use App\Services\DisputeService;
use App\Services\Guarantees\OperationGuarantorService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dispute integration: a friend co-guarantor's frozen coverage stays frozen
 * while a booking dispute is open, and is returned when the dispute is resolved
 * — the coverage is never charged. All rows rolled back.
 */
class GuaranteeDisputeReleaseTest extends TestCase
{
    use DatabaseTransactions;

    private OperationGuarantorService $guarantors;

    private BookingDepositService $deposits;

    private Booking $booking;

    private User $client;

    private User $friend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guarantors = app(OperationGuarantorService::class);
        $this->deposits = app(BookingDepositService::class);

        $booking = Booking::withTrashed()
            ->whereNotNull('user_id')->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if ($booking && $booking->trashed()) {
            $booking->restore();
        }

        $levelId = (int) DB::table('guarantee_levels')->value('id');
        $client = $booking?->user;
        $friend = $client
            ? User::query()->whereNotIn('id', [(int) $booking->user_id, (int) $booking->business_id])->first()
            : null;

        if (! $booking || ! $client || ! $friend || $levelId <= 0) {
            $this->markTestSkipped('Needs a booking with a client, a distinct friend, and a guarantee level.');
        }

        $this->booking = $booking;
        $this->client = $client;
        $this->friend = $friend;

        // Friend's platform-purchased guarantee (coverage 500, nothing used).
        UserGuarantee::query()->where('user_id', $friend->id)->where('target_type', 'client')->delete();
        UserGuarantee::create([
            'user_id' => $friend->id, 'target_type' => GuaranteeLevel::TARGET_CLIENT,
            'purchased_level_id' => $levelId, 'effective_level_id' => $levelId,
            'status' => UserGuarantee::STATUS_ACTIVE, 'current_coverage_amount' => 500, 'used_coverage_amount' => 0,
        ]);

        // Clean slate + funded client wallet (for the wallet deposit).
        OperationGuarantor::query()->forOperation('booking', (int) $booking->id)->delete();
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $booking->id)->delete();
        $w = app(WalletService::class)->getOrCreateWallet((int) $client->id);
        $w->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
    }

    private function friendGuarantee(): UserGuarantee
    {
        return UserGuarantee::query()->where('user_id', $this->friend->id)->where('target_type', 'client')->first();
    }

    public function test_friend_coverage_stays_frozen_during_dispute_and_returns_on_resolution(): void
    {
        // Friend freezes 200 of coverage; a 100 wallet deposit is also frozen.
        $row = $this->guarantors->invite('booking', (int) $this->booking->id, $this->client, $this->friend);
        $this->guarantors->accept($row, 200);
        $this->deposits->freezeForBooking(
            $this->booking,
            100,
            ['wallet_hold_amount' => 100, 'business_counter_hold_amount' => 0.0, 'amount' => 100]
        );

        // Open the dispute — coverage must remain frozen.
        $dispute = $this->deposits->openDisputeForBooking($this->booking, (int) $this->client->id);
        $this->assertEqualsWithDelta(200.0, (float) $this->friendGuarantee()->used_coverage_amount, 0.001, 'frozen while dispute is open');

        // Resolve — the frozen coverage returns to the friend.
        app(DisputeService::class)->resolve($dispute, 'release_business');

        $this->assertEqualsWithDelta(0.0, (float) $this->friendGuarantee()->used_coverage_amount, 0.001, 'returned on resolution');
        $this->assertSame(
            OperationGuarantor::STATUS_RELEASED,
            OperationGuarantor::query()->whereKey($row->id)->value('status')
        );
    }
}
