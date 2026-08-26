<?php

namespace Tests\Feature;

use App\Models\CategoryChildServiceFee;
use App\Models\FeeGroup;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «مجموعات الرسوم» — the admin CRUD for a shared platform-fee rate several
 * children can point at instead of each carrying its own.
 */
class FeeGroupAdminTest extends TestCase
{
    use DatabaseTransactions;

    private function feesAdmin(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();
        foreach ([AdminAbility::ACCESS, AdminAbility::FEES] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    private function plainAdmin(): User
    {
        $plain = new User();
        $plain->name = 'Plain Admin';
        $plain->email = 'plainfees-' . uniqid() . '@example.test';
        $plain->phone = '0159' . random_int(1000000, 9999999);
        $plain->password = 'secret-password';
        $plain->type = User::TYPE_ADMIN;
        $plain->api_token = Str::random(80);
        $plain->save();

        \Bouncer::allow($plain)->to(AdminAbility::ACCESS);
        \Bouncer::refresh();

        return $plain;
    }

    public function test_the_index_renders(): void
    {
        $this->actingAs($this->feesAdmin())->get(route('admin.fee-groups.index'))->assertOk();
    }

    public function test_a_group_can_be_created(): void
    {
        $this->actingAs($this->feesAdmin())->post(route('admin.fee-groups.store'), [
            'name_ar' => 'مجموعة اختبار',
            'business_fee_enabled' => 1,
            'business_fee_type' => 'fixed',
            'business_fee_amount' => 5,
            'client_fee_enabled' => 1,
            'client_fee_type' => 'fixed',
            'client_fee_amount' => 1,
            'currency' => 'egp',
            'is_active' => 1,
        ])->assertRedirect(route('admin.fee-groups.index'));

        $group = FeeGroup::query()->where('name_ar', 'مجموعة اختبار')->first();

        $this->assertNotNull($group);
        $this->assertSame(5.0, (float) $group->business_fee_amount);
        $this->assertSame('EGP', $group->currency);
    }

    public function test_a_group_with_members_cannot_be_deleted(): void
    {
        $group = FeeGroup::create(['name_ar' => 'مجموعة بها أعضاء']);

        $pair = \Illuminate\Support\Facades\DB::table('category_parent_child')->first(['parent_id', 'child_id']);

        if (! $pair) {
            $this->markTestSkipped('Needs a (root, child) pair.');
        }

        CategoryChildServiceFee::query()->updateOrCreate(
            ['category_id' => $pair->parent_id, 'child_id' => $pair->child_id],
            ['fee_group_id' => $group->id, 'is_active' => 1]
        );

        $this->actingAs($this->feesAdmin())
            ->delete(route('admin.fee-groups.destroy', $group->id))
            ->assertRedirect();

        $this->assertNotNull(FeeGroup::find($group->id), 'a group in use was deleted');
    }

    public function test_a_group_with_no_members_can_be_deleted(): void
    {
        $group = FeeGroup::create(['name_ar' => 'مجموعة بلا أعضاء']);

        $this->actingAs($this->feesAdmin())
            ->delete(route('admin.fee-groups.destroy', $group->id))
            ->assertRedirect(route('admin.fee-groups.index'));

        $this->assertNull(FeeGroup::find($group->id));
    }

    public function test_an_admin_without_fees_ability_is_forbidden(): void
    {
        $this->actingAs($this->plainAdmin())
            ->get(route('admin.fee-groups.index'))
            ->assertForbidden();
    }
}
