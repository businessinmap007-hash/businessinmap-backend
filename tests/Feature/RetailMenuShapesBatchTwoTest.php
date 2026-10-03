<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Batch two of «كل منيو سيظهر حسب مجموعة الخيارات» — المالك، 2026-10-04: gold, perfume, plants,
 * rugs and medical supplies each say what their items are and what describes them. Rolls back.
 */
class RetailMenuShapesBatchTwoTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:string,1:string,2:string,3:list<string>} [trade, group, kind, describing groups] */
    public static function trades(): array
    {
        return [
            'ذهب' => [127, 'أصناف المجوهرات', 'مجوهرات', ['عيار المشغولات']],
            'عطور' => [213, 'أنواع العطور', 'عطور', ['تركيز العطر', 'الجمهور المستهدف']],
            'نباتات' => [79, 'النباتات ومستلزماتها', 'نباتات وزهور', ['مستوى العناية', 'الإضاءة المناسبة']],
            'سجاد' => [52, 'أنواع السجاد', 'سجاد', ['خامة السجاد']],
            'مستلزمات طبية' => [182, 'المستلزمات الطبية', 'مستلزمات طبية', ['حالة التعقيم', 'نوع الاستخدام']],
        ];
    }

    /**
     * @dataProvider tradesProvider
     */
    public function test_each_trade_has_its_kind_and_describes_its_items(int $child, string $group, string $kind, array $describing): void
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', $child)->orderBy('id')->first()
            ?: $this->markTestSkipped("No business stands on child #{$child}.");
        DB::table('option_user')->where('user_id', $shop->id)->delete();

        Sanctum::actingAs($shop);
        $vocab = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');

        $line = collect($vocab['lines'])->firstWhere('group_name', $group);
        $this->assertTrue($line['detailed']);
        $this->assertSame($kind, $line['detail_profile']['name']);
        $this->assertFalse($line['detail_profile']['uses_catalog']);

        $modifiers = collect($vocab['modifiers'])->pluck('group_name')->all();
        foreach ($describing as $name) {
            $this->assertContains($name, $modifiers, "«{$name}» describes an item of this trade");
        }
        $this->assertNotContains($describing[0], array_column($vocab['lines'], 'group_name'), 'a description is not «what is it»');
    }

    public static function tradesProvider(): array
    {
        return self::trades();
    }

    public function test_the_kinds_are_scoped_to_their_own_trades(): void
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', 272)->orderBy('id')->first()
            ?: $this->markTestSkipped('No supermarket account.');
        DB::table('option_user')->where('user_id', $shop->id)->delete();

        Sanctum::actingAs($shop);
        $modifiers = collect($this->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data.modifiers'))->pluck('group_name')->all();

        foreach (['عيار المشغولات', 'تركيز العطر', 'مستوى العناية', 'خامة السجاد', 'حالة التعقيم', 'نوع البشرة', 'مقاس المفرش'] as $foreign) {
            $this->assertNotContains($foreign, $modifiers, "a supermarket is not asked «{$foreign}»");
        }
    }
}
