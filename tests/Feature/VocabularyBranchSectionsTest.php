<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement as P;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «فى service-components» — المالك، 2026-10-02: whether a group's branches are
 * the merchant's sections is the «فروع المجموعة أقسام» tick in «مكونات الخدمة»
 * and nothing else; the vocabulary hands that tick to the app. Rolls back.
 */
class VocabularyBranchSectionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_vocabulary_reports_the_service_components_tick_per_group(): void
    {
        $group = OptionGroup::query()->where('name_ar', 'أثاث وتشطيب منزلي')->firstOrFail();
        $shop = User::query()->where('type', 'business')->where('category_child_id', 116)->orderBy('id')->firstOrFail();
        $menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        Sanctum::actingAs($shop);

        $flag = function (bool $on) use ($group, $shop, $menu): bool {
            // Every row the child's own setting could be read from, replaced by one.
            P::query()->where('platform_service_id', $menu)->whereIn('child_id', [P::ALL_CHILDREN, (int) $shop->category_child_id])
                ->where('option_group_id', $group->id)->delete();
            P::query()->create([
                'platform_service_id' => $menu, 'child_id' => (int) $shop->category_child_id,
                'option_group_id' => $group->id, 'usage' => P::USAGE_SECTION,
                'branches_as_sections' => $on, 'is_active' => true,
            ]);

            $lines = collect($this->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.lines'));

            return (bool) $lines->firstWhere('group_id', $group->id)['branches_as_sections'];
        };

        $this->assertTrue($flag(true), 'ticked in مكونات الخدمة: the branches are the sections');
        $this->assertFalse($flag(false), 'unticked: the whole group stays one section');
    }
}
