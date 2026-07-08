<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Deposit;
use App\Models\WalletTransaction;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Mutating engine paths. Verifies the illegal-transition guard on
 * moveBookingToInProgress and the once-only contract of chargeExecutionFeeOnce.
 * Consent is disabled for the booking's parties so the execution-fee charge is
 * a no-op (no fee lines) — keeping these tests about state/idempotency, not
 * about fee amounts. All writes are rolled back.
 */
class ServiceExecutionEngineLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceExecutionEngine $engine;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(ServiceExecutionEngine::class);

        $booking = Booking::withTrashed()->whereNotNull('user_id')->whereNotNull('business_id')->first();
        if ($booking && $booking->trashed()) {
            $booking->restore();
        }
        if (! $booking) {
            $this->markTestSkipped('Needs a booking.');
        }
        $this->booking = $booking;
    }

    private function disableConsentForParties(): void
    {
        foreach ([(int) $this->booking->user_id, (int) $this->booking->business_id] as $uid) {
            DB::table('user_service_fee_consents')->updateOrInsert(
                ['user_id' => $uid],
                ['fee_auto_charge_enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    private function setMeta(array $meta): void
    {
        $this->booking->meta = $meta;
        $this->booking->save();
    }

    public function test_move_to_in_progress_is_rejected_from_a_non_movable_status(): void
    {
        // completed is not in [pending, accepted] → the transition must be refused.
        $this->booking->status = Booking::STATUS_COMPLETED;
        $this->booking->save();

        try {
            $this->engine->moveBookingToInProgress($this->booking);
            $this->fail('moving from a non-movable status must throw');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $this->assertSame(
            Booking::STATUS_COMPLETED,
            Booking::query()->whereKey($this->booking->id)->value('status'),
            'a refused transition must not change the status'
        );
    }

    public function test_charge_execution_fee_records_charged_at(): void
    {
        $this->disableConsentForParties();

        // Clear any previous execution-fee stamp.
        $meta = is_array($this->booking->meta) ? $this->booking->meta : [];
        unset($meta['_execution_fee']);
        $this->setMeta($meta);

        $this->engine->chargeExecutionFeeOnce($this->booking);

        $fresh = Booking::query()->whereKey($this->booking->id)->first();
        $this->assertNotEmpty(
            data_get($fresh->meta, '_execution_fee.charged_at'),
            'charging must stamp _execution_fee.charged_at'
        );
    }

    public function test_charge_execution_fee_is_idempotent(): void
    {
        $this->disableConsentForParties();

        $stamp = '2020-01-01 00:00:00';
        $meta = is_array($this->booking->meta) ? $this->booking->meta : [];
        $meta['_execution_fee'] = ['charged_at' => $stamp];
        $this->setMeta($meta);

        $feesBefore = WalletTransaction::query()
            ->where('reference_type', 'booking')
            ->where('reference_id', (string) $this->booking->id)
            ->where('type', 'platform_fee')
            ->count();

        $this->engine->chargeExecutionFeeOnce($this->booking);

        $fresh = Booking::query()->whereKey($this->booking->id)->first();
        $this->assertSame($stamp, data_get($fresh->meta, '_execution_fee.charged_at'), 'a second charge must be a no-op');

        $feesAfter = WalletTransaction::query()
            ->where('reference_type', 'booking')
            ->where('reference_id', (string) $this->booking->id)
            ->where('type', 'platform_fee')
            ->count();
        $this->assertSame($feesBefore, $feesAfter, 'no new fee transactions on a repeat charge');
    }

    /**
     * Put the booking into a state that can legally start: movable status, both
     * parties confirmed, no deposit required (policies removed), fees a no-op
     * (consent off). This isolates the transition itself from deposit/fee money.
     */
    private function prepareMovableConfirmedNoDeposit(): void
    {
        DB::table('business_deposit_policies')->where('business_id', $this->booking->business_id)->delete();
        Deposit::query()->where('target_type', Booking::class)->where('target_id', $this->booking->id)->delete();
        $this->disableConsentForParties();

        $meta = is_array($this->booking->meta) ? $this->booking->meta : [];
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
        unset($meta['_execution_fee'], $meta['_financial_guard']);
        $this->booking->meta = $meta;
        $this->booking->status = Booking::STATUS_ACCEPTED;
        $this->booking->save();
    }

    public function test_move_to_in_progress_happy_path_without_deposit(): void
    {
        $this->prepareMovableConfirmedNoDeposit();

        $this->engine->moveBookingToInProgress($this->booking);

        $fresh = Booking::query()->whereKey($this->booking->id)->first();
        $this->assertSame(Booking::STATUS_IN_PROGRESS, $fresh->status, 'a ready booking must start execution');
        $this->assertTrue((bool) data_get($fresh->meta, '_financial_guard.ok'), 'the financial guard must pass');
        $this->assertNotEmpty(data_get($fresh->meta, '_execution_fee.charged_at'), 'the execution fee must be stamped');
    }

    public function test_move_to_in_progress_rejected_when_parties_not_confirmed(): void
    {
        $this->prepareMovableConfirmedNoDeposit();

        // Withdraw the confirmations — the transition must now be refused.
        $meta = $this->booking->meta;
        $meta['_start_confirm'] = ['client' => false, 'business' => false];
        $this->booking->meta = $meta;
        $this->booking->save();

        try {
            $this->engine->moveBookingToInProgress($this->booking);
            $this->fail('starting without both confirmations must throw');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('تأكيد الطرفين', implode(' ', $e->errors()['status'] ?? []));
        }

        $this->assertSame(
            Booking::STATUS_ACCEPTED,
            Booking::query()->whereKey($this->booking->id)->value('status'),
            'an unconfirmed booking must stay accepted'
        );
    }
}
