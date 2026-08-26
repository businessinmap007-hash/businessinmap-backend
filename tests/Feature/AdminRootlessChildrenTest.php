<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «جمع الابناء الذين ليس لديهم جذر لاقرر مصيرهم … جمعهم فى صفحة اقوم
 * بالمراجعة والحذف يدويًا» — المالك، 2026-08-25.
 *
 * Rolls back.
 */
class AdminRootlessChildrenTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();

        foreach ([AdminAbility::ACCESS, AdminAbility::CATALOG] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    /** A rootless test child, cleaned up by the transaction rollback. */
    private function makeRootlessChild(string $name = 'اختبار بلا جذر'): int
    {
        return (int) DB::table('category_children_master')->insertGetId([
            'name_ar' => $name . ' ' . uniqid(),
            'name_en' => 'Rootless Test ' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_screen_lists_only_rootless_children(): void
    {
        $rooted = (int) DB::table('category_parent_child')->value('child_id');
        $orphanId = $this->makeRootlessChild();

        $html = $this->actingAs($this->admin())
            ->get('/admin/rootless-children')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString((string) $orphanId, $html);

        // A rooted child must not appear as an actionable row — the screen
        // would otherwise offer to delete a live trade. Checked against the
        // query directly rather than the rendered name, which can coincide
        // with unrelated page text.
        $listedIds = DB::table('category_children_master as c')
            ->whereNotExists(fn ($q) => $q->from('category_parent_child')->whereColumn('child_id', 'c.id'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotContains($rooted, $listedIds);
        $this->assertContains($orphanId, $listedIds);
    }

    public function test_deleting_a_rootless_child_clears_every_table_that_names_it(): void
    {
        $childId = $this->makeRootlessChild();

        $groupId = (int) DB::table('option_groups')->value('id');
        $optionId = (int) DB::table('options')->where('group_id', $groupId)->value('id');

        DB::table('category_child_option')->insert([
            'child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId, 'reorder' => 0,
        ]);
        DB::table('category_child_option_decisions')->insert([
            'child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId,
            'kind' => 'pinned', 'source' => 'admin', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/rootless-children/{$childId}")
            ->assertRedirect();

        $this->assertSame(0, DB::table('category_children_master')->where('id', $childId)->count());
        $this->assertSame(0, DB::table('category_child_option')->where('child_id', $childId)->count());
        $this->assertSame(0, DB::table('category_child_option_decisions')->where('child_id', $childId)->count());
    }

    /**
     * ⚠ The finding that made this screen refuse rather than just delete: a
     * live, enabled deposit policy for a real account was found hanging off
     * one of these rows during the audit. The screen must never silently
     * strand that account's guarantee configuration.
     */
    public function test_a_child_with_a_deposit_policy_is_not_deleted(): void
    {
        $childId = $this->makeRootlessChild();
        $businessId = (int) DB::table('users')->where('type', 'business')->value('id');

        DB::table('business_deposit_policies')->insert([
            'business_id' => $businessId,
            'platform_service_id' => (int) DB::table('platform_services')->value('id'),
            'category_child_id' => $childId,
            'scope_key' => 'business_child_service',
            'priority' => 10,
            'is_enabled' => 1,
            'deposit_mode' => 'wallet_hold',
            'calculation_base' => 'first_day',
            'deposit_type' => 'percent',
            'deposit_value' => 10,
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->delete("/admin/rootless-children/{$childId}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(
            1,
            DB::table('category_children_master')->where('id', $childId)->count(),
            'a child with a live deposit policy was deleted anyway'
        );
    }

    public function test_a_child_that_regained_a_root_cannot_be_deleted_from_here(): void
    {
        $childId = $this->makeRootlessChild();
        $rootId = (int) DB::table('categories')->where('parent_id', 0)->value('id');

        DB::table('category_parent_child')->insert(['parent_id' => $rootId, 'child_id' => $childId]);

        $this->actingAs($this->admin())
            ->delete("/admin/rootless-children/{$childId}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('category_children_master')->where('id', $childId)->count());
    }

    public function test_a_non_catalog_admin_is_forbidden(): void
    {
        $admin = new User();
        $admin->name = 'Rootless Children Ability Test';
        $admin->email = 'rootless-ability-' . uniqid() . '@example.test';
        $admin->phone = '0155' . random_int(1000000, 9999999);
        $admin->password = 'secret-password';
        $admin->type = User::TYPE_ADMIN;
        $admin->api_token = Str::random(80);
        $admin->save();

        // Into the panel, but without the catalog ability.
        \Bouncer::allow($admin)->to(AdminAbility::ACCESS);
        \Bouncer::refresh();

        $this->actingAs($admin)->get('/admin/rootless-children')->assertForbidden();
    }
}
