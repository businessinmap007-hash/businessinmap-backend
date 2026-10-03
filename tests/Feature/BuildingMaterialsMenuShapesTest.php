<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كمّل على مواد البناء» — المالك، 2026-10-04. Rolls back.
 */
class BuildingMaterialsMenuShapesTest extends TestCase
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

    public static function tradesProvider(): array
    {
        return [
            'رخام' => [174, 'أنواع الرخام والجرانيت', 'رخام وجرانيت', ['تشطيب السطح']],
            'أبواب وشبابيك' => [50, 'أنواع الأبواب والشبابيك', 'أبواب وشبابيك', ['طريقة الفتح']],
            'أخشاب' => [301, 'أنواع الأخشاب', 'أخشاب', ['درجة الخشب']],
            'زجاج' => [126, 'أنواع الزجاج', 'زجاج', []],
            'طوب' => [34, 'أنواع الطوب', 'مواد بناء أساسية', []],
            'حدايد وبويات' => [207, 'الحدايد والبويات', 'حدايد وبويات', []],
        ];
    }

    /**
     * @dataProvider tradesProvider
     */
    public function test_each_material_is_sold_by_its_own_fields(int $child, string $group, string $kind, array $describing): void
    {
        $vocab = $this->vocabulary($child);

        $line = collect($vocab['lines'])->firstWhere('group_name', $group);
        $this->assertSame($kind, $line['detail_profile']['name']);
        $this->assertFalse($line['detail_profile']['uses_catalog']);
        $this->assertNotEmpty(array_filter($line['detail_profile']['fields'], fn ($f) => $f['per_item']), 'something is stated for each item');

        foreach ($describing as $name) {
            $this->assertContains($name, collect($vocab['modifiers'])->pluck('group_name')->all());
        }
    }

    public function test_stone_is_sold_by_size_and_thickness_and_still_made_to_order(): void
    {
        $vocab = $this->vocabulary(174);
        $fields = collect(collect($vocab['lines'])->firstWhere('group_name', 'أنواع الرخام والجرانيت')['detail_profile']['fields'])->keyBy('code');

        $this->assertTrue($fields['thickness_mm']['show_on_card']);
        $this->assertTrue($fields['size']['per_item']);
        $this->assertContains('نظام التصنيع', array_column($vocab['price_axes'], 'group_name'), 'a marble yard cuts to order — the axis stays');
    }

    public function test_a_supermarket_is_not_asked_about_surface_finish_or_how_a_door_opens(): void
    {
        $modifiers = collect($this->vocabulary(272)['modifiers'])->pluck('group_name')->all();

        foreach (['تشطيب السطح', 'طريقة الفتح', 'درجة الخشب'] as $group) {
            $this->assertNotContains($group, $modifiers);
        }
    }
}
