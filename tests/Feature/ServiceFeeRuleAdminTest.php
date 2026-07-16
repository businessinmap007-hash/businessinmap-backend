<?php

namespace Tests\Feature;

use App\Models\ServiceFeeRule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * BIM-3.5 admin screen. The interesting part is the conditions blob: the form
 * shows a field per condition and stores one JSON column, so this pins that an
 * untouched field stays absent (never a null that could narrow a rule) and that
 * a saved rule loads back into the same form values. Rolls back.
 */
class ServiceFeeRuleAdminTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::query()->where('type', 'admin')->orderBy('id')->first();

        if (! $admin) {
            $this->markTestSkipped('Needs an admin user.');
        }

        $this->admin = $admin;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'peak surcharge',
            'payer' => ServiceFeeRule::PAYER_BUSINESS,
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'effect_value' => 50,
            'priority' => 100,
            'is_active' => 1,
        ], $overrides);
    }

    public function test_a_non_admin_cannot_reach_the_screen(): void
    {
        $user = User::query()->where('type', '!=', 'admin')->orderBy('id')->firstOrFail();

        $this->actingAs($user)->get('/admin/service-fee-rules')->assertStatus(302);
    }

    public function test_index_and_create_render(): void
    {
        $this->actingAs($this->admin)->get('/admin/service-fee-rules')->assertOk();
        $this->actingAs($this->admin)->get('/admin/service-fee-rules/create')->assertOk();
    }

    public function test_a_rule_with_no_conditions_saves_an_empty_blob(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/service-fee-rules', $this->payload())
            ->assertRedirect(route('admin.service-fee-rules.index'));

        $rule = ServiceFeeRule::query()->where('name', 'peak surcharge')->firstOrFail();

        $this->assertSame([], $rule->conditions, 'blank condition fields must not become null conditions');
        $this->assertSame(50.0, (float) $rule->effect_value);
    }

    public function test_filled_conditions_are_assembled_into_the_blob(): void
    {
        $this->actingAs($this->admin)->post('/admin/service-fee-rules', $this->payload([
            'c_min_base_amount' => 500,
            'c_days_of_week' => [4, 5],
            'c_time_from' => '18:00',
            'c_time_to' => '23:00',
            'c_subscribed' => '0',
            'c_max_base_amount' => '',      // left blank
            'c_min_success_operations' => '', // left blank
        ]))->assertRedirect();

        $rule = ServiceFeeRule::query()->where('name', 'peak surcharge')->firstOrFail();

        // assertEquals, not assertSame: JSON drops a trailing .0, so 500.0 comes
        // back as int 500. matches() casts to float, so the type is immaterial —
        // but the numbers and keys are not.
        $this->assertEquals([
            'min_base_amount' => 500,
            'days_of_week' => [4, 5],
            'time_from' => '18:00',
            'time_to' => '23:00',
            'subscribed' => false,
        ], $rule->conditions);

        $this->assertSame(false, $rule->conditions['subscribed'], 'a boolean condition must stay boolean, not become 0');
        $this->assertArrayNotHasKey('max_base_amount', $rule->conditions, 'a blank field is not a condition');
        $this->assertArrayNotHasKey('min_success_operations', $rule->conditions);
    }

    public function test_subscribed_false_survives_the_round_trip(): void
    {
        // "unsubscribed only" is a real, different rule from "don't care", and
        // false is exactly the value a careless empty-check would drop.
        $this->actingAs($this->admin)
            ->post('/admin/service-fee-rules', $this->payload(['c_subscribed' => '0']))
            ->assertRedirect();

        $rule = ServiceFeeRule::query()->where('name', 'peak surcharge')->firstOrFail();

        $this->assertArrayHasKey('subscribed', $rule->conditions);
        $this->assertFalse($rule->conditions['subscribed']);

        // And the edit form must show it back as "unsubscribed only", not "any".
        $this->actingAs($this->admin)
            ->get("/admin/service-fee-rules/{$rule->id}/edit")
            ->assertOk()
            ->assertSee('غير مشترك فقط');
    }

    public function test_a_waive_rule_needs_no_value_and_stores_none(): void
    {
        $this->actingAs($this->admin)->post('/admin/service-fee-rules', $this->payload([
            'name' => 'exempt',
            'effect' => ServiceFeeRule::EFFECT_WAIVE,
            'effect_value' => null,
        ]))->assertRedirect();

        $rule = ServiceFeeRule::query()->where('name', 'exempt')->firstOrFail();

        $this->assertNull($rule->effect_value);
    }

    public function test_a_non_waive_rule_requires_a_value(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/service-fee-rules', $this->payload(['effect_value' => null]))
            ->assertSessionHasErrors('effect_value');
    }

    public function test_max_fee_below_min_fee_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/service-fee-rules', $this->payload(['min_fee' => 50, 'max_fee' => 10]))
            ->assertSessionHasErrors('max_fee');
    }

    public function test_update_and_toggle_and_delete(): void
    {
        $rule = ServiceFeeRule::create($this->payload(['conditions' => ['min_base_amount' => 100]]));

        $this->actingAs($this->admin)
            ->put("/admin/service-fee-rules/{$rule->id}", $this->payload([
                'name' => 'renamed',
                'effect_value' => 25,
                'c_min_base_amount' => 900,
            ]))
            ->assertRedirect(route('admin.service-fee-rules.index'));

        $rule->refresh();
        $this->assertSame('renamed', $rule->name);
        $this->assertSame(25.0, (float) $rule->effect_value);
        $this->assertEquals(['min_base_amount' => 900], $rule->conditions);

        $this->actingAs($this->admin)->post("/admin/service-fee-rules/{$rule->id}/toggle");
        $this->assertFalse((bool) $rule->fresh()->is_active);

        $this->actingAs($this->admin)->delete("/admin/service-fee-rules/{$rule->id}");
        $this->assertNull(ServiceFeeRule::query()->find($rule->id));
    }
}
