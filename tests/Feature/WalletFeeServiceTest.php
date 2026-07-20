<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CategoryChildServiceFee;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletFeeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * WalletFeeService guards (financial core). The critical property: platform
 * fees are NEVER auto-charged without the payer's consent
 * (user_service_fee_consents.fee_auto_charge_enabled), charging is idempotent
 * per booking+feeCode+payer, and it refuses to overdraw. Uses an existing
 * booking + its client as payer; all writes are rolled back.
 */
class WalletFeeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private WalletFeeService $fees;

    private Booking $booking;

    private int $userId;

    private string $feeCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fees = app(WalletFeeService::class);

        $booking = Booking::withTrashed()->whereNotNull('user_id')->whereNotNull('business_id')->first();
        if ($booking && $booking->trashed()) {
            $booking->restore();
        }
        if (! $booking) {
            $this->markTestSkipped('Needs a booking.');
        }
        $this->booking = $booking;
        $this->userId = (int) $booking->user_id;

        // Fund + activate the payer's wallet (rolled back after the test).
        $wallet = app(WalletService::class)->getOrCreateWallet($this->userId);
        $wallet->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => 1000, 'locked_balance' => 0]);

        // Unique fee code so the idempotency key never collides with real data.
        $this->feeCode = 'test_fee_' . uniqid();
    }

    private function setConsent(bool $enabled): void
    {
        DB::table('user_service_fee_consents')->updateOrInsert(
            ['user_id' => $this->userId],
            ['fee_auto_charge_enabled' => $enabled ? 1 : 0, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function charge(float $amount): WalletTransaction
    {
        $m = new ReflectionMethod(WalletFeeService::class, 'createWalletFeeTransaction');
        $m->setAccessible(true);

        return $m->invoke(
            $this->fees,
            $this->booking,
            $this->feeCode,
            CategoryChildServiceFee::PAYER_CLIENT,
            $this->userId,
            $amount,
            ['amount' => $amount]
        );
    }

    private function balance(): float
    {
        return (float) Wallet::query()->where('user_id', $this->userId)->value('balance');
    }

    public function test_fee_is_not_charged_without_consent(): void
    {
        $this->setConsent(false);
        $before = $this->balance();

        try {
            $this->charge(10);
            $this->fail('charging without consent must throw');
        } catch (RuntimeException $e) {
            // Compared through __() rather than a hardcoded Arabic substring:
            // the message is now translated, so a literal would only hold in
            // whichever locale the suite happens to run under.
            $this->assertSame(
                __('المستخدم رقم :id لم يوافق على خصم رسوم الخدمة تلقائيًا.', ['id' => $this->userId]),
                $e->getMessage()
            );
        }

        $this->assertEqualsWithDelta($before, $this->balance(), 0.001, 'no consent must never move money');
    }

    public function test_fee_is_charged_once_with_consent_and_is_idempotent(): void
    {
        $this->setConsent(true);
        $before = $this->balance();

        $tx1 = $this->charge(10);
        $tx2 = $this->charge(10);

        $this->assertSame($tx1->id, $tx2->id, 'same booking+feeCode+payer must not double-charge');
        $this->assertSame(WalletFeeService::TX_TYPE_PLATFORM_FEE, $tx1->type);
        $this->assertSame(
            'booking_fee:' . $this->booking->id . ':' . $this->feeCode . ':' . CategoryChildServiceFee::PAYER_CLIENT,
            $tx1->idempotency_key
        );
        $this->assertEqualsWithDelta($before - 10, $this->balance(), 0.001, 'exactly one charge of 10');
    }

    public function test_fee_refused_on_insufficient_balance(): void
    {
        $this->setConsent(true);
        Wallet::query()->where('user_id', $this->userId)->update(['balance' => 5]);

        try {
            $this->charge(10);
            $this->fail('charging more than the balance must throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                __('رصيد المستخدم رقم :id غير كافٍ لتطبيق رسوم :payer على الحجز #:booking', [
                    'id' => $this->userId, 'payer' => 'client', 'booking' => $this->booking->id,
                ]),
                $e->getMessage()
            );
        }

        $this->assertEqualsWithDelta(5, $this->balance(), 0.001, 'balance must be intact after a refused charge');
    }
}
