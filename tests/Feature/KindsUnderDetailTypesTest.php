<?php

namespace Tests\Feature;

use App\Models\MenuDetailProfile;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «أكمل دمج الأنواع القديمة تحت الأنواع العشرة» — المالك، 2026-10-04. A «kind» is what a group ADDS on top of
 * its detail type (a field set); every one belongs to one of the ten types, and a group has ONE type.
 * Rolls back.
 */
class KindsUnderDetailTypesTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
    }

    public function test_every_kind_belongs_to_one_of_the_ten_types(): void
    {
        $types = DB::table('menu_detail_types')->pluck('code')->all();

        $this->assertCount(10, $types);
        $this->assertSame([], MenuDetailProfile::query()->whereNull('detail_type')->orWhereNotIn('detail_type', $types)->pluck('code')->all());
    }

    public function test_a_group_follows_the_type_of_the_kind_it_uses(): void
    {
        $kindTypes = MenuDetailProfile::query()->pluck('detail_type', 'id');
        $mismatched = OptionGroup::query()->whereNotNull('menu_detail_profile_id')->get(['name_ar', 'menu_detail_profile_id', 'detail_type'])
            ->filter(fn ($g) => $g->detail_type !== ($kindTypes[$g->menu_detail_profile_id] ?? null))->pluck('name_ar')->all();

        $this->assertSame([], $mismatched, 'a group that uses a kind is of that kind\'s type');
    }

    public function test_the_screen_lists_the_kinds_under_their_types(): void
    {
        $group = OptionGroup::query()->where('name_ar', 'أنواع الأسماك والمأكولات البحرية')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.menu-shapes.index', ['group_id' => $group->id], false))
            ->assertOk()
            ->assertSee('نوع التفاصيل:')
            ->assertSee('بالوزن والحجم')        // the type heading…
            ->assertSee('أسماك ومأكولات بحرية'); // …with the fish field set under it
    }

    public function test_choosing_a_field_set_gives_the_group_that_sets_type(): void
    {
        $group = OptionGroup::query()->where('name_ar', 'أنواع الحدايد')->first() ?: OptionGroup::query()->where('name_ar', 'أنواع الصلصات والشوربات')->firstOrFail();
        $stone = MenuDetailProfile::query()->where('code', 'stone')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.assign', [], false), ['group_id' => $group->id, 'profile_id' => $stone->id, 'detail_type' => 'basic'])->assertRedirect();

        $this->assertSame($stone->id, $group->fresh()->menu_detail_profile_id);
        $this->assertSame('measured', $group->fresh()->detail_type, 'the field set decides the type');
    }

    public function test_a_group_with_no_field_set_takes_the_type_the_admin_picks(): void
    {
        $group = OptionGroup::query()->where('name_ar', 'أنواع الصلصات والشوربات')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.assign', [], false), ['group_id' => $group->id, 'profile_id' => '', 'detail_type' => 'piece_goods'])->assertRedirect();

        $this->assertNull($group->fresh()->menu_detail_profile_id);
        $this->assertSame('piece_goods', $group->fresh()->detail_type);
        $this->assertSame(['pcs', 'pack', 'box', 'set', 'dozen'], \App\Support\SaleUnits::codesForGroup($group->id), 'the type narrows the units');
    }

    public function test_an_unknown_type_is_refused(): void
    {
        $group = OptionGroup::query()->where('name_ar', 'أنواع الصلصات والشوربات')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.assign', [], false), ['group_id' => $group->id, 'detail_type' => 'nonsense'])->assertSessionHasErrors('detail_type');
    }

    public function test_a_new_field_set_belongs_to_the_chosen_type(): void
    {
        $group = OptionGroup::query()->where('name_ar', 'أنواع الصلصات والشوربات')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.profiles.store', [], false), [
            'group_id' => $group->id, 'name_ar' => 'نوع تجريبى للدمج', 'name_en' => 'Merge test kind', 'detail_type' => 'weighed',
        ])->assertRedirect();

        $this->assertSame('weighed', MenuDetailProfile::query()->where('name_ar', 'نوع تجريبى للدمج')->value('detail_type'));
    }
}
