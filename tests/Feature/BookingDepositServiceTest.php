<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\Wallet;
use App\Services\BookingDepositService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Deposit hold lifecycle (freeze → release). A client-only hold moves the
 * client's available balance into locked_balance and back; the deposit record
 * tracks FROZEN → RELEASED. Uses an existing booking (distinct client/business),
 * funds the client wallet, and clears any prior deposit — all rolled back.
 */
class BookingDepositServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BookingDepositService $deposits;

    private WalletService $wallet;

    private Booking $booking;

    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deposits = app(BookingDepositService::class);
        $this->wallet = app(WalletService::class);

        $booking = Booking::query()
            ->whereNotNull('user_id')
            ->whereNotNull('business_id')
            ->whereColumn('user_id', '!=', 'business_id')
            ->first();

        if (! $booking) {
            $this->markTestSkipped('Needs a booking with distinct client and business.');
        }

        $this->booking = $booking;
        $this->clientId = (int) $booking->user_id;

        // Clean slate: no prior deposit for this booking.
        Deposit::query()
            ->where('target_type', Booking::class)
            ->where('target_id', $booking->id)
            ->delete();

        // Fund + activate the client wallet.
        $w = $this->wallet->getOrCreateWallet($this->clientId);
        $w->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);
    }

    /** Client-only hold policy (no business counter-hold). */
    private function policy(float $hold): array
    {
        return ['wallet_hold_amount' => $hold, 'business_counter_hold_amount' => 0.0, 'amount' => $hold];
    }

    private function clientWallet(): Wallet
    {
        return Wallet::query()->where('user_id', $this->clientId)->first();
    }

    public function test_freeze_rejects_a_non_positive_amount(): void
    {
        $this->expectException(ValidationException::class);
        $this->deposits->freezeForBooking($this->booking, 0);
    }

    public function test_freeze_holds_client_funds_and_creates_a_frozen_deposit(): void
    {
        $deposit = $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));

        $this->assertTrue($deposit->isFrozen());
        $this->assertEqualsWithDelta(100.0, (float) $deposit->client_amount, 0.001);

        $w = $this->clientWallet();
        $this->assertEqualsWithDelta(900.0, (float) $w->balance, 0.001, 'available drops by the hold');
        $this->assertEqualsWithDelta(100.0, (float) $w->locked_balance, 0.001, 'locked rises by the hold');
    }

    public function test_release_restores_the_held_funds(): void
    {
        $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));

        $released = $this->deposits->releaseForBooking($this->booking);

        $this->assertTrue($released->isReleased());

        $w = $this->clientWallet();
        $this->assertEqualsWithDelta(1000.0, (float) $w->balance, 0.001, 'available fully restored');
        $this->assertEqualsWithDelta(0.0, (float) $w->locked_balance, 0.001, 'nothing left locked');
    }

    public function test_freeze_is_idempotent_per_booking(): void
    {
        $first = $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));
        $second = $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));

        $this->assertSame($first->id, $second->id, 'a second freeze returns the existing frozen deposit');

        $w = $this->clientWallet();
        $this->assertEqualsWithDelta(900.0, (float) $w->balance, 0.001, 'funds are held only once');
        $this->assertEqualsWithDelta(100.0, (float) $w->locked_balance, 0.001);
    }

    public function test_refund_restores_funds_and_marks_the_deposit_refunded(): void
    {
        $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));

        $refunded = $this->deposits->refundForBooking($this->booking);

        $this->assertTrue($refunded->isRefunded());

        $w = $this->clientWallet();
        $this->assertEqualsWithDelta(1000.0, (float) $w->balance, 0.001, 'refund returns the held funds');
        $this->assertEqualsWithDelta(0.0, (float) $w->locked_balance, 0.001);
    }

    public function test_refund_is_idempotent(): void
    {
        $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));

        $first = $this->deposits->refundForBooking($this->booking);
        $second = $this->deposits->refundForBooking($this->booking);

        $this->assertSame($first->id, $second->id, 'a second refund is a no-op');
        $this->assertTrue($second->isRefunded());

        $w = $this->clientWallet();
        $this->assertEqualsWithDelta(1000.0, (float) $w->balance, 0.001, 'funds refunded only once');
        $this->assertEqualsWithDelta(0.0, (float) $w->locked_balance, 0.001);
    }

    public function test_a_released_deposit_cannot_be_refunded(): void
    {
        $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));
        $this->deposits->releaseForBooking($this->booking);

        $this->expectException(ValidationException::class);
        $this->deposits->refundForBooking($this->booking);
    }

    public function test_external_deposit_submit_then_verify(): void
    {
        $this->booking->price = 500;
        $this->booking->save();
        $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));

        $submitted = $this->deposits->submitExternalDeposit($this->booking, 200, 'REF-1');
        $this->assertSame('submitted', (string) $submitted->external_deposit_status);
        $this->assertEqualsWithDelta(200.0, (float) $submitted->external_deposit_amount, 0.001);

        $verified = $this->deposits->verifyExternalDeposit($this->booking, 999);
        $this->assertSame('verified', (string) $verified->external_deposit_status);
        $this->assertNotNull($verified->external_verified_at);
        // remaining = bookingTotal(500) - external(200)
        $this->assertEqualsWithDelta(300.0, (float) $verified->remaining_amount_after_external, 0.001);
    }

    public function test_verify_external_deposit_requires_a_submission(): void
    {
        // Freeze leaves external_deposit_status = 'not_required'; verify must reject.
        $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));

        $this->expectException(ValidationException::class);
        $this->deposits->verifyExternalDeposit($this->booking);
    }

    public function test_open_dispute_creates_one_and_is_idempotent(): void
    {
        $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));

        $first = $this->deposits->openDisputeForBooking($this->booking, $this->clientId);
        $second = $this->deposits->openDisputeForBooking($this->booking, $this->clientId);

        $this->assertSame($first->id, $second->id, 'an already-open dispute is reused, not duplicated');
        $this->assertSame(Booking::class, $first->disputeable_type);
        $this->assertSame((int) $this->booking->id, (int) $first->disputeable_id);
    }

    public function test_cannot_open_dispute_after_deposit_is_final(): void
    {
        $this->deposits->freezeForBooking($this->booking, 100, $this->policy(100));
        $this->deposits->releaseForBooking($this->booking); // RELEASED = final

        $this->expectException(ValidationException::class);
        $this->deposits->openDisputeForBooking($this->booking, $this->clientId);
    }
}
