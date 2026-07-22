<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Editing a guarantee level. The code is unique PER target_type, not globally:
 * the same ladder (bronze/silver/gold…) exists for both client and business, so
 * client/bronze and business/bronze share the code "bronze". The unique rule
 * used to be global, so editing either reported "code مستخدم" and the balance/
 * coverage edit never saved.
 */
class GuaranteeLevelAdminTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $a = User::where('type', 'admin')->firstOrFail();
        foreach (['admin.access', 'admin.trust'] as $ab) {
            \Bouncer::allow($a)->to($ab);
        }
        \Bouncer::refresh();

        return $a;
    }

    private function form(GuaranteeLevel $l, array $over = []): array
    {
        return array_merge([
            'code' => $l->code, 'name_ar' => $l->name_ar, 'name_en' => $l->name_en, 'target_type' => $l->target_type,
            'required_locked_amount' => $l->required_locked_amount, 'pending_coverage_amount' => $l->pending_coverage_amount,
            'active_coverage_amount' => $l->active_coverage_amount,
            'boost_coverage_amount' => '', 'boost_min_operations' => '', 'boost_min_success_rate' => '', 'boost_max_dispute_rate' => '',
            'required_completed_operations' => $l->required_completed_operations, 'required_trust_score' => $l->required_trust_score,
            'max_lost_disputes' => '', 'max_late_cancellations' => '', 'priority' => $l->priority, 'is_active' => '1', 'meta_json' => '',
        ], $over);
    }

    public function test_editing_a_level_whose_code_is_shared_across_targets_now_saves(): void
    {
        // Two levels sharing a code across target types (the real data shape).
        $client = GuaranteeLevel::create(['code' => 'shared_' . uniqid(), 'name_ar' => 'x', 'name_en' => 'x', 'target_type' => 'client',
            'required_locked_amount' => 100, 'pending_coverage_amount' => 50, 'active_coverage_amount' => 200,
            'required_completed_operations' => 0, 'required_trust_score' => 0, 'priority' => 90, 'is_active' => 1]);
        GuaranteeLevel::create(['code' => $client->code, 'name_ar' => 'y', 'name_en' => 'y', 'target_type' => 'business',
            'required_locked_amount' => 100, 'pending_coverage_amount' => 50, 'active_coverage_amount' => 200,
            'required_completed_operations' => 0, 'required_trust_score' => 0, 'priority' => 91, 'is_active' => 1]);

        $this->actingAs($this->admin())
            ->put(route('admin.guarantee-levels.update', $client->id), $this->form($client, ['active_coverage_amount' => 4321]))
            ->assertSessionHasNoErrors();

        $this->assertEquals(4321.0, (float) $client->fresh()->active_coverage_amount);
    }

    public function test_a_real_collision_within_the_same_target_is_still_rejected(): void
    {
        $a = $this->admin();
        $first = GuaranteeLevel::where('target_type', 'client')->firstOrFail();
        $second = GuaranteeLevel::create(['code' => 'zzz_' . uniqid(), 'name_ar' => 'x', 'name_en' => 'x', 'target_type' => 'client',
            'required_locked_amount' => 1, 'pending_coverage_amount' => 1, 'active_coverage_amount' => 1,
            'required_completed_operations' => 0, 'required_trust_score' => 0, 'priority' => 1, 'is_active' => 1]);

        $this->actingAs($a)
            ->put(route('admin.guarantee-levels.update', $second->id), $this->form($second, ['code' => $first->code]))
            ->assertSessionHasErrors('code');
    }
}
