<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كمّل على المخبوزات والحلويات» — المالك، 2026-10-04. Rolls back.
 */
class BakeryAndSweetsMenuShapesTest extends TestCase
{
    use DatabaseTransactions;

    private function vocabulary(int $child): array
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', $child)->orderBy('id')->first()
            ?? tap(User::query()->where('type', 'business')->orderBy('id')->firstOrFail(), fn ($u) => $u->forceFill(['category_child_id' => $child])->save());
        DB::table('option_user')->where('user_id', $shop->id)->delete();

        Sanctum::actingAs($shop);

        return $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');
    }

    public function test_the_bakery_has_kinds_for_bread_and_sweets_and_describes_by_flour_and_diet(): void
    {
        $vocab = $this->vocabulary(27);
        $lines = collect($vocab['lines'])->keyBy('group_name');

        $this->assertSame('مخبوزات', $lines['أنواع المخبوزات']['detail_profile']['name']);
        $this->assertSame('حلويات وجاتوه', $lines['أصناف الحلويات والجاتوه']['detail_profile']['name']);

        $modifiers = collect($vocab['modifiers'])->pluck('group_name')->all();
        foreach (['نوع الدقيق', 'موعد الخبز', 'خيارات غذائية', 'المناسبة'] as $group) {
            $this->assertContains($group, $modifiers);
        }
        $this->assertContains('وحدة البيع', array_column($vocab['price_axes'], 'group_name'), 'sweets are priced by the sale unit');
    }

    public function test_the_sweet_shop_is_not_asked_about_flour(): void
    {
        $vocab = $this->vocabulary(210);
        $modifiers = collect($vocab['modifiers'])->pluck('group_name')->all();

        $this->assertContains('المناسبة', $modifiers);
        $this->assertNotContains('نوع الدقيق', $modifiers);
        $fields = collect(collect($vocab['lines'])->firstWhere('group_name', 'أصناف الحلويات والجاتوه')['detail_profile']['fields'])->keyBy('code');
        $this->assertTrue($fields['weight']['per_item']);
        $this->assertTrue($fields['package_count']['show_on_card']);
    }

    public function test_a_supermarket_is_not_asked_about_flour_or_occasions(): void
    {
        $modifiers = collect($this->vocabulary(272)['modifiers'])->pluck('group_name')->all();

        foreach (['نوع الدقيق', 'موعد الخبز', 'خيارات غذائية', 'المناسبة'] as $group) {
            $this->assertNotContains($group, $modifiers);
        }
    }
}
