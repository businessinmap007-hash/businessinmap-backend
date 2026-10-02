<?php

namespace Tests\Feature;

use App\Models\MenuDetailProfile;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «أشكال المنيو» — المالك، 2026-10-02: «منيو أساسي» vs «منيو تفصيلي» per
 * priced option group, tried on a phone preview, and the fields of each
 * detail kind. Rolls back.
 */
class MenuShapesTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->first() ?: $this->markTestSkipped('No admin account to act as.');
    }

    private function mobiles(): OptionGroup
    {
        return OptionGroup::query()->where('name_ar', 'أجهزة الموبايل')->firstOrFail();
    }

    public function test_the_phone_previews_a_group_on_a_detail_kind_with_that_kinds_fields(): void
    {
        $group = $this->mobiles();
        $cars = MenuDetailProfile::query()->where('code', 'cars')->firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.menu-shapes.index', ['group_id' => $group->id, 'preview' => $cars->id], false))
            ->assertOk()
            ->assertSee('التسعير والتفاصيل')
            ->assertSee('عدد الكيلومترات')   // a car field…
            ->assertSee('أضف للسلة')         // …and the one cart bar
            ->assertSee('شراء مباشر');
    }

    public function test_the_basic_preview_is_the_quick_price_card_not_a_catalog_picker(): void
    {
        $group = $this->mobiles();

        $this->actingAs($this->admin())
            ->get(route('admin.menu-shapes.index', ['group_id' => $group->id, 'preview' => 'basic'], false))
            ->assertOk()
            ->assertSee('إضافة سعر')
            ->assertSee('سعر التوريد')
            ->assertDontSee('اختر منتجًا حقيقيًا');
    }

    public function test_assigning_a_kind_makes_the_group_detailed_and_basic_clears_it(): void
    {
        $admin = $this->admin();
        $group = OptionGroup::query()->where('price_role', OptionGroup::ROLE_LINE)->whereNull('menu_detail_profile_id')->firstOrFail();
        $computers = MenuDetailProfile::query()->where('code', 'computers')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.menu-shapes.assign', [], false), [
            'group_id' => $group->id,
            'profile_id' => $computers->id,
        ])->assertRedirect();
        $this->assertSame($computers->id, $group->fresh()->menu_detail_profile_id);

        $this->actingAs($admin)->post(route('admin.menu-shapes.assign', [], false), [
            'group_id' => $group->id,
            'profile_id' => '',
        ])->assertRedirect();
        $this->assertNull($group->fresh()->menu_detail_profile_id, 'empty = back to «منيو أساسي»');
    }

    public function test_only_a_priced_line_group_can_take_a_menu_shape(): void
    {
        $modifier = OptionGroup::query()->where('price_role', OptionGroup::ROLE_MODIFIER)->firstOrFail();
        $cars = MenuDetailProfile::query()->where('code', 'cars')->firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.assign', [], false), [
            'group_id' => $modifier->id,
            'profile_id' => $cars->id,
        ])->assertSessionHasErrors('group_id');

        $this->assertNull($modifier->fresh()->menu_detail_profile_id);
    }

    public function test_a_kinds_fields_are_replaced_in_the_order_given(): void
    {
        $cars = MenuDetailProfile::query()->where('code', 'cars')->firstOrFail();
        $year = (int) DB::table('catalog_attributes')->where('code', 'model_year')->value('id');
        $color = (int) DB::table('catalog_attributes')->where('code', 'color')->value('id');

        $this->actingAs($this->admin())->post(route('admin.menu-shapes.profiles.fields', $cars, false), [
            'fields' => [
                $color => ['enabled' => 1, 'sort_order' => 10, 'show_on_card' => 1],
                $year => ['enabled' => 1, 'sort_order' => 20, 'is_filterable' => 1],
                // Not enabled → dropped from the kind.
                (int) DB::table('catalog_attributes')->where('code', 'mileage_km')->value('id') => ['sort_order' => 30],
            ],
        ])->assertRedirect();

        $fields = MenuDetailProfile::fieldsFor([$cars->id])[$cars->id];
        $this->assertSame(['color', 'model_year'], array_column($fields, 'code'));
        $this->assertTrue($fields[0]['show_on_card']);
        $this->assertFalse($fields[0]['is_filterable']);
        $this->assertTrue($fields[1]['is_filterable']);
    }

    public function test_a_new_detail_kind_can_be_added(): void
    {
        $this->actingAs($this->admin())->post(route('admin.menu-shapes.profiles.store', [], false), [
            'name_ar' => 'ألواح بديل الخشب',
            'name_en' => 'WPC panels',
        ])->assertRedirect();

        $this->assertDatabaseHas('menu_detail_profiles', ['code' => 'wpc-panels', 'name_ar' => 'ألواح بديل الخشب']);
    }

    public function test_re_running_the_seeder_never_undoes_the_admins_choice(): void
    {
        $group = $this->mobiles();
        $mobiles = MenuDetailProfile::query()->where('code', 'mobiles')->firstOrFail();
        $battery = (int) DB::table('catalog_attributes')->where('code', 'battery_mah')->value('id');

        $group->update(['menu_detail_profile_id' => null]);
        DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $mobiles->id)->where('catalog_attribute_id', $battery)->delete();

        (new \Database\Seeders\MenuDetailProfilesSeeder)->run();

        $this->assertNull($group->fresh()->menu_detail_profile_id, 'set back to basic stays basic');
        $this->assertFalse(
            DB::table('menu_detail_profile_attributes')->where('menu_detail_profile_id', $mobiles->id)->where('catalog_attribute_id', $battery)->exists(),
            'an unticked field stays unticked'
        );
    }

    /**
     * «اربط السيارات واللاب توب بأنواعها» — المالك، 2026-10-02. Cars take the
     * hatla2ee fields (year, mileage, gearbox, fuel on the card), computers
     * the laptop-site ones (processor, RAM, storage on the card), and the
     * peripherals that were in the computers group — a printer asks nothing
     * of a processor — stay a basic menu.
     */
    public function test_cars_and_computers_are_linked_to_their_kinds_and_peripherals_stay_basic(): void
    {
        $byGroup = fn (string $name) => OptionGroup::query()->where('name_ar', $name)->firstOrFail();

        $cars = MenuDetailProfile::query()->where('code', 'cars')->firstOrFail();
        $this->assertSame($cars->id, $byGroup('نوع المركبة')->menu_detail_profile_id);
        $carFields = MenuDetailProfile::fieldsFor([$cars->id])[$cars->id];
        $this->assertSame(['model_year', 'mileage_km', 'transmission', 'fuel_type'], array_column(array_filter($carFields, fn ($f) => $f['show_on_card']), 'code'));
        $this->assertNotContains('body_type', array_column($carFields, 'code'), 'the body type is the branch, not a field');

        $computers = MenuDetailProfile::query()->where('code', 'computers')->firstOrFail();
        $this->assertSame($computers->id, $byGroup('أجهزة الكمبيوتر')->menu_detail_profile_id);
        $this->assertSame(
            ['لابتوب', 'كمبيوتر مكتبي', 'تابلت'],
            DB::table('options')->where('group_id', $byGroup('أجهزة الكمبيوتر')->id)->orderBy('id')->pluck('name_ar')->all()
        );
        $this->assertNull($byGroup('ملحقات ومعدات الكمبيوتر')->menu_detail_profile_id, 'a printer is not described by a processor');
        $this->assertDatabaseMissing('menu_detail_profiles', ['code' => 'laptops']);
    }
}
