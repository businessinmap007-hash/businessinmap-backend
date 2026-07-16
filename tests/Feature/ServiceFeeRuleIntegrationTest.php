<?php

namespace Tests\Feature;

use App\Actions\ResolveServiceFeesAction;
use App\Models\Booking;
use App\Models\CategoryChildServiceFee;
use App\Models\ServiceFeeRule;
use App\Services\WalletFeeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BIM-3.5 end to end: a rule must actually move the fee a real booking resolves
 * through WalletFeeService, not just inside the engine. Also pins the layering —
 * base fee → rules → promotion — and the no-rules no-op. All writes roll back.
 */
class ServiceFeeRuleIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private WalletFeeService $fees;
    private Booking $booking;
    private int $businessId;
    private int $categoryId;
    private int $childId;
    private int $serviceId;
    private string $feeCode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fees = app(WalletFeeService::class);

        $booking = Booking::query()
            ->whereNotNull('user_id')
            ->whereNotNull('business_id')
            ->whereNotNull('service_id')
            ->whereHas('business', fn ($q) => $q->whereNotNull('category_id')->whereNotNull('category_child_id'))
            ->with('business:id,category_id,category_child_id')
            ->first();

        if (! $booking) {
            $this->markTestSkipped('Needs a booking whose business has a category + child.');
        }

        $this->booking = $booking;
        $this->businessId = (int) $booking->business_id;
        $this->serviceId = (int) $booking->service_id;
        $this->categoryId = (int) $booking->business->category_id;
        $this->childId = (int) $booking->business->category_child_id;
        $this->feeCode = CategoryChildServiceFee::DEFAULT_FEE_CODE;

        // A known, flat 20 base fee on the business side only, so any change in
        // the resolved amount is the rules layer and nothing else.
        CategoryChildServiceFee::query()
            ->where('child_id', $this->childId)
            ->where('platform_service_id', $this->serviceId)
            ->delete();

        CategoryChildServiceFee::create([
            'category_id' => $this->categoryId,
            'child_id' => $this->childId,
            'platform_service_id' => $this->serviceId,
            'business_fee_enabled' => 1,
            'business_fee_type' => CategoryChildServiceFee::CALC_TYPE_FIXED,
            'business_fee_amount' => 20,
            'client_fee_enabled' => 0,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        // The business must consent, or the line is dropped before rules run.
        DB::table('user_service_fee_consents')->updateOrInsert(
            ['user_id' => $this->businessId],
            ['fee_auto_charge_enabled' => 1, 'updated_at' => now(), 'created_at' => now()]
        );

        ServiceFeeRule::query()->delete();
        DB::table('platform_service_fee_promotions')->delete();
    }

    private function businessLine(): ?array
    {
        return $this->fees->resolveBookingFees($this->booking, $this->feeCode)
            ->firstWhere('payer', CategoryChildServiceFee::PAYER_BUSINESS);
    }

    public function test_without_rules_the_static_base_fee_is_charged_unchanged(): void
    {
        $line = $this->businessLine();

        $this->assertNotNull($line, 'the base fee row should resolve a business line');
        $this->assertSame(20.0, round((float) $line['amount'], 2));
        $this->assertArrayNotHasKey('fee_rules', $line, 'no rules means the line is not touched at all');
    }

    public function test_a_matching_rule_moves_the_real_resolved_fee(): void
    {
        ServiceFeeRule::create([
            'name' => 'peak surcharge',
            'payer' => ServiceFeeRule::PAYER_BUSINESS,
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => 50,
            'is_active' => true,
        ]);

        $line = $this->businessLine();

        $this->assertSame(30.0, round((float) $line['amount'], 2), '20 + 50%');
        $this->assertSame(20.0, $line['amount_before_rules']);
        $this->assertSame('service_fee_rule', $line['source']);
        $this->assertSame('peak surcharge', $line['fee_rules'][0]['name']);
        $this->assertSame('business', $line['fee_context']['payer'], 'the trace records what the rule was judged on');
    }

    public function test_a_rule_for_the_other_payer_leaves_the_business_fee_alone(): void
    {
        ServiceFeeRule::create([
            'name' => 'client-only surcharge',
            'payer' => ServiceFeeRule::PAYER_CLIENT,
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => 100,
            'is_active' => true,
        ]);

        $this->assertSame(20.0, round((float) $this->businessLine()['amount'], 2));
    }

    public function test_a_waiving_rule_drops_the_line_entirely(): void
    {
        ServiceFeeRule::create([
            'name' => 'exempt',
            'payer' => ServiceFeeRule::PAYER_ANY,
            'effect' => ServiceFeeRule::EFFECT_WAIVE,
            'is_active' => true,
        ]);

        $this->assertNull($this->businessLine(), 'a zero fee is not a chargeable line');
    }

    public function test_a_promotion_discounts_the_rule_adjusted_fee_not_the_base(): void
    {
        // This is the layering claim: rules set policy, then the promotion
        // discounts what policy arrived at.
        ServiceFeeRule::create([
            'name' => 'peak surcharge',
            'payer' => ServiceFeeRule::PAYER_BUSINESS,
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => 50, // 20 → 30
            'is_active' => true,
        ]);

        DB::table('platform_service_fee_promotions')->insert([
            'name' => 'half off',
            'scope_type' => 'service',
            'service_id' => $this->serviceId,
            'child_id' => null,
            'target_party' => 'business',
            'discount_type' => 'percent_discount',
            'discount_value' => 50, // 30 → 15, not 20 → 10
            'priority' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $line = $this->businessLine();

        $this->assertSame(15.0, round((float) $line['amount'], 2), 'the promotion halves 30 (post-rule), not 20 (base)');
        $this->assertSame(30.0, $line['amount_before_promotion']);
        $this->assertSame(20.0, $line['amount_before_rules']);
    }

    public function test_the_action_explains_how_the_fee_was_reached(): void
    {
        ServiceFeeRule::create([
            'name' => 'big job discount',
            'payer' => ServiceFeeRule::PAYER_BUSINESS,
            'effect' => ServiceFeeRule::EFFECT_FIXED_ADJUST,
            'effect_value' => -5,
            'is_active' => true,
        ]);

        $explained = app(ResolveServiceFeesAction::class)->explain($this->booking, $this->feeCode);

        $this->assertArrayHasKey('business', $explained);

        $business = $explained['business'];
        $this->assertSame(20.0, $business['base_fee']);
        $this->assertSame(15.0, $business['final_fee']);
        $this->assertSame(-5.0, $business['total_change']);
        $this->assertSame('big job discount', $business['rules_applied'][0]['name']);
        $this->assertNull($business['promotion']);
    }

    public function test_the_action_resolves_without_charging_anything(): void
    {
        $before = DB::table('wallet_transactions')->count();

        app(ResolveServiceFeesAction::class)->execute($this->booking, $this->feeCode);

        $this->assertSame($before, DB::table('wallet_transactions')->count(), 'resolving a fee must never move money');
    }
}
