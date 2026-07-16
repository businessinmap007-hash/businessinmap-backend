<?php

namespace Tests\Unit;

use App\DTO\FeeContext;
use App\Models\ServiceFeeRule;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * BIM-3.5 — the rule's own two decisions: does it apply (matches), and what
 * does it do to the fee (applyTo). Pure: rules are built in memory, never saved,
 * so this touches no database.
 */
class ServiceFeeRuleTest extends TestCase
{
    /**
     * array_merge (not ??) so an override of null is honoured as null rather
     * than falling back to the default — "this operation has no governorate" is
     * a case worth testing.
     */
    private function context(array $overrides = []): FeeContext
    {
        $c = array_merge([
            'payer' => 'business',
            'feeCode' => 'booking_execution',
            'baseAmount' => 1000.0,
            'serviceId' => 1,
            'serviceKey' => 'booking',
            'categoryId' => 5,
            'childId' => 9,
            'governorateId' => 1,
            'cityId' => 11,
            'occurredAt' => Carbon::parse('2026-07-19 14:00:00'), // Sunday
            'successOperations' => 0,
            'totalOperations' => 0,
            'disputedOperations' => 0,
            'isSubscribed' => false,
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
            totalOperations: $c['totalOperations'],
            disputedOperations: $c['disputedOperations'],
            isSubscribed: $c['isSubscribed'],
        );
    }

    private function rule(array $attributes = []): ServiceFeeRule
    {
        return new ServiceFeeRule(array_merge([
            'name' => 'test rule',
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => 10,
        ], $attributes));
    }

    // ---- effects --------------------------------------------------------

    public function test_percent_adjust_moves_the_fee_both_ways(): void
    {
        $up = $this->rule(['effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST, 'effect_value' => 25]);
        $down = $this->rule(['effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST, 'effect_value' => -25]);

        $this->assertSame(125.0, $up->applyTo(100.0, $this->context()));
        $this->assertSame(75.0, $down->applyTo(100.0, $this->context()));
    }

    public function test_fixed_adjust_multiply_and_overrides(): void
    {
        $context = $this->context(['baseAmount' => 1000.0]);

        $this->assertSame(
            105.0,
            $this->rule(['effect' => ServiceFeeRule::EFFECT_FIXED_ADJUST, 'effect_value' => 5])->applyTo(100.0, $context)
        );
        $this->assertSame(
            150.0,
            $this->rule(['effect' => ServiceFeeRule::EFFECT_MULTIPLY, 'effect_value' => 1.5])->applyTo(100.0, $context)
        );
        $this->assertSame(
            30.0,
            $this->rule(['effect' => ServiceFeeRule::EFFECT_OVERRIDE_FIXED, 'effect_value' => 30])->applyTo(100.0, $context)
        );
        // 2% of the operation's 1000, not of the running fee.
        $this->assertSame(
            20.0,
            $this->rule(['effect' => ServiceFeeRule::EFFECT_OVERRIDE_PERCENT, 'effect_value' => 2])->applyTo(100.0, $context)
        );
        $this->assertSame(
            0.0,
            $this->rule(['effect' => ServiceFeeRule::EFFECT_WAIVE])->applyTo(100.0, $context)
        );
    }

    public function test_fee_can_never_go_negative(): void
    {
        $rule = $this->rule(['effect' => ServiceFeeRule::EFFECT_FIXED_ADJUST, 'effect_value' => -500]);

        $this->assertSame(0.0, $rule->applyTo(100.0, $this->context()));
    }

    public function test_min_and_max_clamp_the_result(): void
    {
        $floored = $this->rule([
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => -90,
            'min_fee' => 25,
        ]);
        $capped = $this->rule([
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => 900,
            'max_fee' => 200,
        ]);

        $this->assertSame(25.0, $floored->applyTo(100.0, $this->context()));
        $this->assertSame(200.0, $capped->applyTo(100.0, $this->context()));
    }

    // ---- conditions (the six the roadmap names) -------------------------

    public function test_no_conditions_matches_everything_in_scope(): void
    {
        $this->assertTrue($this->rule()->matches($this->context()));
    }

    public function test_by_operation_value(): void
    {
        $rule = $this->rule(['conditions' => ['min_base_amount' => 500, 'max_base_amount' => 2000]]);

        $this->assertTrue($rule->matches($this->context(['baseAmount' => 1000])));
        $this->assertTrue($rule->matches($this->context(['baseAmount' => 500])), 'min is inclusive');
        $this->assertTrue($rule->matches($this->context(['baseAmount' => 2000])), 'max is inclusive');
        $this->assertFalse($rule->matches($this->context(['baseAmount' => 499])));
        $this->assertFalse($rule->matches($this->context(['baseAmount' => 2001])));
    }

    public function test_by_service_kind(): void
    {
        $rule = $this->rule(['conditions' => ['service_keys' => ['delivery', 'schedules']]]);

        $this->assertTrue($rule->matches($this->context(['serviceKey' => 'delivery'])));
        $this->assertFalse($rule->matches($this->context(['serviceKey' => 'booking'])));
        $this->assertFalse($rule->matches($this->context(['serviceKey' => null])));
    }

    public function test_by_governorate_and_city(): void
    {
        $gov = $this->rule(['conditions' => ['governorate_ids' => [1, 2]]]);
        $city = $this->rule(['conditions' => ['city_ids' => [11]]]);

        $this->assertTrue($gov->matches($this->context(['governorateId' => 2])));
        $this->assertFalse($gov->matches($this->context(['governorateId' => 3])));
        $this->assertFalse($gov->matches($this->context(['governorateId' => null])));

        $this->assertTrue($city->matches($this->context(['cityId' => 11])));
        $this->assertFalse($city->matches($this->context(['cityId' => 12])));
    }

    public function test_by_successful_operations(): void
    {
        $veteran = $this->rule(['conditions' => ['min_success_operations' => 50]]);
        $newcomer = $this->rule(['conditions' => ['max_success_operations' => 5]]);

        $this->assertTrue($veteran->matches($this->context(['successOperations' => 50])));
        $this->assertFalse($veteran->matches($this->context(['successOperations' => 49])));

        $this->assertTrue($newcomer->matches($this->context(['successOperations' => 0])));
        $this->assertFalse($newcomer->matches($this->context(['successOperations' => 6])));
    }

    public function test_by_subscription(): void
    {
        $subscribed = $this->rule(['conditions' => ['subscribed' => true]]);
        $unsubscribed = $this->rule(['conditions' => ['subscribed' => false]]);

        $this->assertTrue($subscribed->matches($this->context(['isSubscribed' => true])));
        $this->assertFalse($subscribed->matches($this->context(['isSubscribed' => false])));

        $this->assertTrue($unsubscribed->matches($this->context(['isSubscribed' => false])));
        $this->assertFalse($unsubscribed->matches($this->context(['isSubscribed' => true])));
    }

    public function test_by_peak_day_and_time(): void
    {
        // Thursday+Friday evenings.
        $rule = $this->rule(['conditions' => [
            'days_of_week' => [4, 5],
            'time_from' => '18:00',
            'time_to' => '23:00',
        ]]);

        $thursdayEvening = Carbon::parse('2026-07-23 20:00:00'); // Thursday
        $thursdayMorning = Carbon::parse('2026-07-23 09:00:00');
        $sundayEvening = Carbon::parse('2026-07-19 20:00:00'); // Sunday

        $this->assertSame(4, $thursdayEvening->dayOfWeek, 'guard: 0=Sunday convention');

        $this->assertTrue($rule->matches($this->context(['occurredAt' => $thursdayEvening])));
        $this->assertFalse($rule->matches($this->context(['occurredAt' => $thursdayMorning])), 'right day, wrong hour');
        $this->assertFalse($rule->matches($this->context(['occurredAt' => $sundayEvening])), 'right hour, wrong day');
    }

    public function test_peak_window_can_wrap_past_midnight(): void
    {
        $rule = $this->rule(['conditions' => ['time_from' => '22:00', 'time_to' => '02:00']]);

        $this->assertTrue($rule->matches($this->context(['occurredAt' => Carbon::parse('2026-07-19 23:30:00')])));
        $this->assertTrue($rule->matches($this->context(['occurredAt' => Carbon::parse('2026-07-19 01:00:00')])));
        $this->assertFalse($rule->matches($this->context(['occurredAt' => Carbon::parse('2026-07-19 12:00:00')])));
    }

    public function test_conditions_are_all_of_not_any_of(): void
    {
        $rule = $this->rule(['conditions' => [
            'min_base_amount' => 500,
            'governorate_ids' => [1],
        ]]);

        $this->assertTrue($rule->matches($this->context(['baseAmount' => 600, 'governorateId' => 1])));
        $this->assertFalse($rule->matches($this->context(['baseAmount' => 600, 'governorateId' => 2])), 'one failing condition sinks the rule');
    }

    public function test_an_unknown_condition_key_never_widens_a_rule(): void
    {
        // A typo or a condition from a newer version must fail closed, not be
        // silently ignored into "matches everything".
        $rule = $this->rule(['conditions' => ['min_bass_amount' => 500]]);

        $this->assertFalse($rule->matches($this->context()));
    }
}
