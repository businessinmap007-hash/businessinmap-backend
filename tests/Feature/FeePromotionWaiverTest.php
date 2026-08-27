<?php

namespace Tests\Feature;

use App\Models\PlatformServiceFeePromotion;
use App\Services\WalletFeeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Gap #1 coverage: the service-fee promotion / waiver math in
 * WalletFeeService::applyPromotionToLine — waive, fixed discount, percent
 * discount, override-to-fixed, and the clamping edges. Pure math exercised via
 * reflection so it is isolated from the full pricing chain. Rolls back.
 */
class FeePromotionWaiverTest extends TestCase
{
    use DatabaseTransactions;

    private function apply(array $line, PlatformServiceFeePromotion $promotion, string $payer = 'client'): array
    {
        $service = app(WalletFeeService::class);
        $method = new ReflectionMethod($service, 'applyPromotionToLine');
        $method->setAccessible(true);

        return $method->invoke($service, $line, $promotion, $payer);
    }

    private function promotion(string $type, float $value): PlatformServiceFeePromotion
    {
        return (new PlatformServiceFeePromotion())->forceFill([
            'name' => 'test-promo',
            'discount_type' => $type,
            'discount_value' => $value,
            'target_party' => PlatformServiceFeePromotion::TARGET_BOTH,
            'scope_type' => PlatformServiceFeePromotion::SCOPE_ALL_SERVICES,
            'priority' => 1,
        ]);
    }

    private function line(float $amount = 100.0): array
    {
        return ['amount' => $amount, 'source' => 'category_child_service_fee', 'payer' => 'client'];
    }

    public function test_waive_zeroes_the_fee(): void
    {
        $result = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_WAIVE, 0));

        $this->assertSame(0.0, $result['amount']);
        $this->assertSame(100.0, $result['promotion_discount_amount']);
        $this->assertSame(100.0, $result['amount_before_promotion']);
        $this->assertSame('platform_service_fee_promotion', $result['source']);
    }

    public function test_fixed_discount_subtracts_the_value(): void
    {
        $result = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_FIXED_DISCOUNT, 30));

        $this->assertSame(70.0, $result['amount']);
        $this->assertSame(30.0, $result['promotion_discount_amount']);
    }

    public function test_fixed_discount_larger_than_fee_clamps_to_zero(): void
    {
        $result = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_FIXED_DISCOUNT, 500));

        $this->assertSame(0.0, $result['amount']);
        $this->assertSame(100.0, $result['promotion_discount_amount']);
    }

    public function test_percent_discount_applies_a_percentage(): void
    {
        $result = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_PERCENT_DISCOUNT, 25));

        $this->assertSame(75.0, $result['amount']);
        $this->assertSame(25.0, $result['promotion_discount_amount']);
    }

    public function test_percent_discount_is_capped_at_100(): void
    {
        $result = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_PERCENT_DISCOUNT, 150));

        $this->assertSame(0.0, $result['amount']);
    }

    public function test_override_to_fixed_sets_the_final_amount(): void
    {
        $result = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_OVERRIDE_TO_FIXED, 40));

        $this->assertSame(40.0, $result['amount']);
        $this->assertSame(60.0, $result['promotion_discount_amount']);
    }

    public function test_override_higher_than_original_never_produces_a_negative_discount(): void
    {
        $result = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_OVERRIDE_TO_FIXED, 250));

        $this->assertSame(250.0, $result['amount']);
        $this->assertSame(0.0, $result['promotion_discount_amount']);
    }

    public function test_zero_fee_is_returned_untouched(): void
    {
        $result = $this->apply($this->line(0), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_WAIVE, 0));

        $this->assertSame(0.0, $result['amount']);
        $this->assertArrayNotHasKey('promotion', $result, 'A zero fee should not be decorated with promotion metadata.');
    }

    public function test_unknown_discount_type_leaves_the_line_unchanged(): void
    {
        $result = $this->apply($this->line(100), $this->promotion('nonsense_type', 50));

        $this->assertSame(100.0, $result['amount']);
        $this->assertArrayNotHasKey('promotion', $result);
    }

    public function test_promotion_metadata_records_the_paying_party(): void
    {
        $result = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_PERCENT_DISCOUNT, 10), 'business');

        $this->assertSame('business', $result['promotion']['applied_for_payer']);
        $this->assertSame(PlatformServiceFeePromotion::DISCOUNT_PERCENT_DISCOUNT, $result['promotion']['discount_type']);
    }

    /**
     * Gap #2 coverage, 2026-08-27: a promotion was real money-wise but
     * invisible everywhere a payer could see it — WalletTransactionResource
     * strips `meta`, and the note used to never mention it. The note is the
     * one thing that survives into the payer's own transaction history.
     */
    private function buildNote(string $payer, array $line): string
    {
        $service = app(WalletFeeService::class);
        $method = new ReflectionMethod($service, 'buildHumanNote');
        $method->setAccessible(true);

        $booking = \App\Models\Booking::withTrashed()->whereNotNull('service_id')->first()
            ?: $this->markTestSkipped('Needs a booking.');

        if ($booking->trashed()) {
            $booking->restore();
        }

        return $method->invoke($service, 'booking_execution', $payer, $booking, $line);
    }

    public function test_the_note_names_a_full_waiver(): void
    {
        $line = $this->apply($this->line(100), $this->promotion(PlatformServiceFeePromotion::DISCOUNT_WAIVE, 0));

        $this->assertStringContainsString('إعفاء كامل من الرسوم', $this->buildNote('client', $line));
    }

    public function test_the_note_names_a_percent_discount_and_the_promotion(): void
    {
        $promotion = $this->promotion(PlatformServiceFeePromotion::DISCOUNT_PERCENT_DISCOUNT, 20);
        $promotion->name = 'عرض رمضان';
        $line = $this->apply($this->line(100), $promotion);

        $note = $this->buildNote('client', $line);

        $this->assertStringContainsString('عرض رمضان', $note);
        $this->assertStringContainsString('20', $note);
    }

    public function test_the_note_is_unchanged_without_a_promotion(): void
    {
        $note = $this->buildNote('client', $this->line(100));

        $this->assertStringNotContainsString('عرض', $note);
    }

    /** The booking screen's clean top-level summary, not buried in a raw meta blob. */
    public function test_the_booking_preview_summarizes_active_promotions(): void
    {
        $engine = app(\App\Services\ServiceExecutionEngine::class);
        $method = new ReflectionMethod($engine, 'summarizeActivePromotions');
        $method->setAccessible(true);

        $promotion = $this->promotion(PlatformServiceFeePromotion::DISCOUNT_FIXED_DISCOUNT, 15);
        $promotion->name = 'عرض تجريبي';
        $line = $this->apply($this->line(100), $promotion, 'client');

        $summary = $method->invoke($engine, collect([$line]));

        $this->assertCount(1, $summary);
        $this->assertSame('client', $summary[0]['payer']);
        $this->assertStringContainsString('عرض تجريبي', $summary[0]['message']);
    }

    public function test_no_active_promotions_summarizes_to_an_empty_list(): void
    {
        $engine = app(\App\Services\ServiceExecutionEngine::class);
        $method = new ReflectionMethod($engine, 'summarizeActivePromotions');
        $method->setAccessible(true);

        $summary = $method->invoke($engine, collect([$this->line(100)]));

        $this->assertSame([], $summary);
    }
}
