<?php

namespace Tests\Feature;

use App\Models\MenuDetailProfile;
use App\Models\MenuSection;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\User;
use App\Services\Menu\MenuSectionFromOptionGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * «الاقسام المفترض ان تؤخذ من اعدادات catalog-model-values» — المالك،
 * 2026-10-02: every group the admin gave a «شكل منيو» is split into its
 * branches — «غرفة نوم»، «سفرة»، «أنتريه» are the business's sections — with no
 * second switch to flip in «مكونات الخدمة». Rolls back.
 */
class KindGroupSectionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_group_with_a_kind_is_split_into_branch_sections_and_one_without_is_not(): void
    {
        $kind = MenuDetailProfile::query()->firstOrCreate(
            ['code' => 'sec-test'],
            ['name_ar' => 'نوع اختبار الأقسام', 'uses_catalog' => false, 'sort_order' => 901, 'is_active' => true]
        );
        $group = OptionGroup::query()->whereNull('menu_detail_profile_id')->orderBy('id')->firstOrFail();
        $resolver = app(MenuSectionFromOptionGroup::class);

        // A business with no child of its own has no «مكونات الخدمة» to split by.
        $this->assertFalse($resolver->isSplit(999999999, $group->id), 'no kind, no flag: the whole group is one section');

        $group->update(['menu_detail_profile_id' => $kind->id]);
        $this->assertTrue($resolver->isSplit(999999999, $group->id), 'a kind alone splits the group');
    }

    public function test_an_item_lands_in_a_section_named_after_its_branch(): void
    {
        $kind = MenuDetailProfile::query()->firstOrCreate(
            ['code' => 'sec-test'],
            ['name_ar' => 'نوع اختبار الأقسام', 'uses_catalog' => false, 'sort_order' => 901, 'is_active' => true]
        );
        $group = OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->firstOrFail();
        $group->update(['menu_detail_profile_id' => $kind->id]);
        $bedroom = Option::query()->where('group_id', $group->id)->where('name_ar', 'غرفة نوم')->firstOrFail();
        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->orderBy('id')->firstOrFail();

        $section = app(MenuSectionFromOptionGroup::class)->resolve($shop->id, $bedroom);

        $this->assertInstanceOf(MenuSection::class, $section);
        $this->assertSame('غرفة نوم', $section->name_ar);
        $this->assertSame($bedroom->id, (int) $section->option_id);
    }
}
