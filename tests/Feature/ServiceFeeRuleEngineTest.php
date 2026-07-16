<?php

namespace Tests\Feature;

use App\DTO\FeeContext;
use App\Models\ServiceFeeRule;
use App\Services\ServiceFeeRuleEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BIM-3.5 — the engine's job: pick the rules that apply to an operation, run
 * them in the right order, and be able to say why. Rules are created inside a
 * rolled-back transaction.
 */
class ServiceFeeRuleEngineTest extends TestCase
{
    use DatabaseTransactions;

    private ServiceFeeRuleEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(ServiceFeeRuleEngine::class);

        // The dev DB is shared, so isolate these tests from any real rules.
        ServiceFeeRule::query()->delete();
    }

    private function context(array $overrides = []): FeeContext
    {
        $c = array_merge([
            'payer' => ServiceFeeRule::PAYER_BUSINESS,
            'feeCode' => 'booking_execution',
            'baseAmount' => 1000.0,
            'serviceId' => 1,
            'serviceKey' => 'booking',
            'categoryId' => 5,
            'childId' => 9,
            'governorateId' => 1,
            'cityId' => 11,
            'occurredAt' => Carbon::parse('2026-07-19 14:00:00'),
            'isSubscribed' => false,
            'successOperations' => 0,
        ], $overrides);

        return new FeeContext(
            payer: $c['payer'],
            feeCode: $c['feeCode'],
            baseAmount: (float) $c['baseAmount'],
            serviceId: $c['serviceId'],
            serviceKey: $c['serviceKey'],
            categoryId: $c['categoryId'],
            childId: $c['childId'],
            businessId: 100,
            clientId: 200,
            governorateId: $c['governorateId'],
            cityId: $c['cityId'],
            occurredAt: $c['occurredAt'],
            successOperations: $c['successOperations'],
            isSubscribed: $c['isSubscribed'],
        );
    }

    private function rule(array $attributes = []): ServiceFeeRule
    {
        return ServiceFeeRule::create(array_merge([
            'name' => 'rule',
            'payer' => ServiceFeeRule::PAYER_ANY,
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => 10,
            'priority' => 0,
            'is_active' => true,
        ], $attributes));
    }

    public function test_with_no_rules_the_base_fee_is_untouched(): void
    {
        $result = $this->engine->resolve(20.0, $this->context());

        $this->assertSame(20.0, $result['amount']);
        $this->assertSame([], $result['applied']);
    }

    public function test_matching_rules_compound_in_priority_order(): void
    {
        // +50% first, then -10 flat: 20 → 30 → 20. Order matters, so the
        // reverse priority would give a different answer.
        $this->rule(['name' => 'peak surcharge', 'priority' => 1, 'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST, 'effect_value' => 50]);
        $this->rule(['name' => 'loyalty credit', 'priority' => 2, 'effect' => ServiceFeeRule::EFFECT_FIXED_ADJUST, 'effect_value' => -10]);

        $result = $this->engine->resolve(20.0, $this->context());

        $this->assertSame(20.0, $result['amount']);
        $this->assertCount(2, $result['applied']);
        $this->assertSame('peak surcharge', $result['applied'][0]['name']);
        $this->assertSame(30.0, $result['applied'][0]['amount_after']);
        $this->assertSame('loyalty credit', $result['applied'][1]['name']);
        $this->assertSame(20.0, $result['applied'][1]['amount_after']);
    }

    public function test_stop_on_match_ends_evaluation(): void
    {
        $this->rule(['name' => 'exempt', 'priority' => 1, 'effect' => ServiceFeeRule::EFFECT_WAIVE, 'stop_on_match' => true]);
        $this->rule(['name' => 'surcharge', 'priority' => 2, 'effect' => ServiceFeeRule::EFFECT_FIXED_ADJUST, 'effect_value' => 99]);

        $result = $this->engine->resolve(20.0, $this->context());

        $this->assertSame(0.0, $result['amount'], 'the waive must win and the later surcharge never run');
        $this->assertCount(1, $result['applied']);
    }

    public function test_the_trace_explains_the_final_fee(): void
    {
        $this->rule(['name' => 'big job discount', 'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST, 'effect_value' => -25]);

        $result = $this->engine->resolve(40.0, $this->context());

        $this->assertSame(40.0, $result['base_amount']);
        $this->assertSame(30.0, $result['amount']);

        $applied = $result['applied'][0];
        $this->assertSame('big job discount', $applied['name']);
        $this->assertSame(40.0, $applied['amount_before']);
        $this->assertSame(30.0, $applied['amount_after']);
        $this->assertSame(-25.0, $applied['effect_value']);
    }

    // ---- selection ------------------------------------------------------

    public function test_a_rule_for_another_payer_does_not_apply(): void
    {
        $this->rule(['payer' => ServiceFeeRule::PAYER_CLIENT, 'effect_value' => 100]);

        $business = $this->engine->resolve(20.0, $this->context(['payer' => ServiceFeeRule::PAYER_BUSINESS]));
        $client = $this->engine->resolve(20.0, $this->context(['payer' => ServiceFeeRule::PAYER_CLIENT]));

        $this->assertSame(20.0, $business['amount']);
        $this->assertSame(40.0, $client['amount']);
    }

    public function test_a_rule_for_another_service_or_child_does_not_apply(): void
    {
        $this->rule(['platform_service_id' => 999, 'effect_value' => 100]);
        $this->rule(['child_id' => 888, 'effect_value' => 100]);

        $result = $this->engine->resolve(20.0, $this->context(['serviceId' => 1, 'childId' => 9]));

        $this->assertSame(20.0, $result['amount']);
        $this->assertSame([], $result['applied']);
    }

    public function test_a_null_scope_means_any(): void
    {
        $this->rule(['platform_service_id' => null, 'child_id' => null, 'fee_code' => null, 'effect_value' => 100]);

        $result = $this->engine->resolve(20.0, $this->context());

        $this->assertSame(40.0, $result['amount']);
    }

    public function test_inactive_and_out_of_window_rules_are_skipped(): void
    {
        $at = Carbon::parse('2026-07-19 14:00:00');

        $this->rule(['name' => 'inactive', 'is_active' => false, 'effect_value' => 100]);
        $this->rule(['name' => 'expired', 'ends_at' => $at->copy()->subDay(), 'effect_value' => 100]);
        $this->rule(['name' => 'not started', 'starts_at' => $at->copy()->addDay(), 'effect_value' => 100]);

        $result = $this->engine->resolve(20.0, $this->context(['occurredAt' => $at]));

        $this->assertSame(20.0, $result['amount']);
        $this->assertSame([], $result['applied']);
    }

    public function test_a_rule_running_in_its_window_applies(): void
    {
        $at = Carbon::parse('2026-07-19 14:00:00');

        $this->rule([
            'name' => 'summer campaign',
            'starts_at' => $at->copy()->subDay(),
            'ends_at' => $at->copy()->addDay(),
            'effect_value' => 100,
        ]);

        $this->assertSame(40.0, $this->engine->resolve(20.0, $this->context(['occurredAt' => $at]))['amount']);
    }

    /**
     * The scenario the roadmap describes: a peak-hour surcharge in one
     * governorate that only bites a business without a subscription.
     */
    public function test_a_realistic_layered_scenario(): void
    {
        $this->rule([
            'name' => 'Cairo Thursday-evening peak, unsubscribed only',
            'payer' => ServiceFeeRule::PAYER_BUSINESS,
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => 50,
            'max_fee' => 60,
            'conditions' => [
                'governorate_ids' => [1],
                'days_of_week' => [4],
                'time_from' => '18:00',
                'time_to' => '23:00',
                'subscribed' => false,
                'min_base_amount' => 500,
            ],
        ]);

        $peak = ['occurredAt' => Carbon::parse('2026-07-23 20:00:00'), 'governorateId' => 1, 'isSubscribed' => false, 'baseAmount' => 1000];

        $this->assertSame(30.0, $this->engine->resolve(20.0, $this->context($peak))['amount'], 'peak + Cairo + unsubscribed + big job → +50%');
        $this->assertSame(20.0, $this->engine->resolve(20.0, $this->context(array_merge($peak, ['isSubscribed' => true])))['amount'], 'a subscriber is exempt');
        $this->assertSame(20.0, $this->engine->resolve(20.0, $this->context(array_merge($peak, ['governorateId' => 2])))['amount'], 'another governorate is untouched');
        $this->assertSame(20.0, $this->engine->resolve(20.0, $this->context(array_merge($peak, ['occurredAt' => Carbon::parse('2026-07-23 09:00:00')])))['amount'], 'off-peak is untouched');
        $this->assertSame(20.0, $this->engine->resolve(20.0, $this->context(array_merge($peak, ['baseAmount' => 100])))['amount'], 'a small job is untouched');

        // The max_fee clamp holds even when the percentage would overshoot.
        $this->assertSame(60.0, $this->engine->resolve(100.0, $this->context($peak))['amount'], '100 + 50% = 150, clamped to 60');
    }
}
