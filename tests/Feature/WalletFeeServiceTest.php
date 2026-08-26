<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CategoryChildServiceFee;
use App\Models\FeeGroup;
use App\Models\PlatformService;
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

    /**
     * «رسمٌ واحدٌ لكل ابن … لا رسمٌ منفصل لكل خدمة» — المالك، 2026-08-26. A
     * business offering two different services on the same child is charged
     * the SAME fee for either one — proof the fee no longer stacks per
     * service.
     */
    public function test_the_same_fee_applies_whichever_service_the_booking_used(): void
    {
        $this->booking->loadMissing('business:id,category_id,category_child_id');
        $business = $this->booking->business;

        if (! $business || (int) $business->category_child_id <= 0) {
            $this->markTestSkipped('Needs a booking whose business has a category child.');
        }

        $categoryId = (int) $business->category_id;
        $childId = (int) $business->category_child_id;

        $otherServiceId = (int) PlatformService::query()
            ->where('is_active', 1)
            ->where('id', '!=', (int) $this->booking->service_id)
            ->value('id');

        if ($otherServiceId <= 0) {
            $this->markTestSkipped('Needs a second active platform service.');
        }

        CategoryChildServiceFee::query()->updateOrCreate(
            ['category_id' => $categoryId, 'child_id' => $childId],
            [
                'is_active' => 1,
                'client_fee_enabled' => 1,
                'client_fee_type' => CategoryChildServiceFee::CALC_TYPE_FIXED,
                'client_fee_amount' => 7,
                'business_fee_enabled' => 0,
            ]
        );

        $this->setConsent(true);

        $secondBooking = $this->booking->replicate();
        $secondBooking->service_id = $otherServiceId;
        $secondBooking->save();

        $firstFees = $this->fees->resolveBookingFees($this->booking->fresh());
        $secondFees = $this->fees->resolveBookingFees($secondBooking->fresh());

        $firstClient = $firstFees->firstWhere('payer', CategoryChildServiceFee::PAYER_CLIENT);
        $secondClient = $secondFees->firstWhere('payer', CategoryChildServiceFee::PAYER_CLIENT);

        $this->assertNotNull($firstClient, 'the first service resolved no fee at all');
        $this->assertNotNull($secondClient, 'the second service resolved no fee at all');
        $this->assertSame(7.0, (float) $firstClient['amount']);
        $this->assertSame(7.0, (float) $secondClient['amount'], 'the second service was charged a different amount than the first');

        $secondBooking->forceDelete();
    }

    /** A child assigned to a fee group is charged the GROUP's amount. */
    public function test_a_fee_group_assignment_is_what_gets_charged_on_a_booking(): void
    {
        $this->booking->loadMissing('business:id,category_id,category_child_id');
        $business = $this->booking->business;

        if (! $business || (int) $business->category_child_id <= 0) {
            $this->markTestSkipped('Needs a booking whose business has a category child.');
        }

        $group = FeeGroup::create(['name_ar' => 'مجموعة اختبار الرسوم', 'client_fee_amount' => 3]);

        CategoryChildServiceFee::query()->updateOrCreate(
            ['category_id' => (int) $business->category_id, 'child_id' => (int) $business->category_child_id],
            [
                'fee_group_id' => $group->id,
                'is_active' => 1,
                'client_fee_enabled' => 1,
                'client_fee_type' => CategoryChildServiceFee::CALC_TYPE_FIXED,
                'client_fee_amount' => 999,
            ]
        );

        $this->setConsent(true);

        $lines = $this->fees->resolveBookingFees($this->booking->fresh());
        $client = $lines->firstWhere('payer', CategoryChildServiceFee::PAYER_CLIENT);

        $this->assertNotNull($client);
        $this->assertSame(3.0, (float) $client['amount'], 'the group amount did not win over the row\'s own');
    }
}
