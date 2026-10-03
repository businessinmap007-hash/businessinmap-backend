<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «راجع كل المنيوهات … كل منيو سيظهر حسب مجموعة الخيارات» — المالك، 2026-10-04: after the fish
 * menu, the cosmetics, linens and fashion trades. Each states what its items ARE (a kind with
 * fields), what DESCRIBES them (groups chosen per item) and drops the factory's «نظام التصنيع».
 * Rolls back.
 */
class RetailMenuShapesBatchOneTest extends TestCase
{
    use DatabaseTransactions;

    /** The vocabulary a merchant of [$child] sees — a clean answer sheet, so it is the child's whole list. */
    private function vocabulary(int $child): array
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', $child)->orderBy('id')->first()
            ?: $this->markTestSkipped("No business stands on child #{$child}.");
        DB::table('option_user')->where('user_id', $shop->id)->delete();

        Sanctum::actingAs($shop);

        return $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');
    }

    private function kindOf(array $vocab, string $group): ?array
    {
        return collect($vocab['lines'])->firstWhere('group_name', $group)['detail_profile'] ?? null;
    }

    private function modifiers(array $vocab): array
    {
        return collect($vocab['modifiers'])->pluck('group_name')->all();
    }

    public function test_cosmetics_say_pack_size_skin_type_and_audience_and_make_nothing_to_order(): void
    {
        $vocab = $this->vocabulary(73);

        $kind = $this->kindOf($vocab, 'أصناف مستحضرات التجميل');
        $this->assertSame('مستحضرات تجميل', $kind['name']);
        $this->assertFalse($kind['uses_catalog']);
        $this->assertContains('volume', array_column($kind['fields'], 'code'));

        $this->assertContains('نوع البشرة', $this->modifiers($vocab));
        $this->assertContains('الجمهور المستهدف', $this->modifiers($vocab));
        $this->assertNotContains('نظام التصنيع', $this->modifiers($vocab));
        $this->assertSame([], $vocab['price_axes'], 'a cosmetics shop prices the product, not a manufacturing basis');
    }

    public function test_linens_describe_by_fabric_and_bed_size_and_the_fabric_is_not_a_shelf(): void
    {
        $vocab = $this->vocabulary(115);

        $this->assertSame('مفروشات', $this->kindOf($vocab, 'أصناف المفروشات')['name']);
        $this->assertContains('مقاس المفرش', $this->modifiers($vocab));
        $this->assertContains('أنواع الأقمشة', $this->modifiers($vocab));
        $this->assertNotContains('أنواع الأقمشة', array_column($vocab['lines'], 'group_name'), 'the fabric describes a sheet; it is not «what is it»');
    }

    public function test_fashion_asks_size_and_colour_per_item_and_describes_by_audience_and_season(): void
    {
        foreach ([60, 168] as $child) {
            $vocab = $this->vocabulary($child);

            $kind = $this->kindOf($vocab, 'موضة وعناية شخصية');
            $fields = collect($kind['fields'])->keyBy('code');
            $this->assertTrue($fields['size']['per_item'], "#{$child}: the size is stated for each item");
            $this->assertTrue($fields['color']['per_item']);
            $this->assertContains('الموسم', $this->modifiers($vocab));
            $this->assertContains('الجمهور المستهدف', $this->modifiers($vocab));
            $this->assertNotContains('نظام التصنيع', $this->modifiers($vocab));
            $axes = array_column($vocab['price_axes'], 'group_name');
            $this->assertNotContains('الجمهور المستهدف', $axes, "#{$child}: «حريمي / رجالي» is who it is for, not a second price");
            $this->assertNotContains('نظام التصنيع', $axes);
        }
    }

    public function test_the_withdrawal_is_recorded_so_no_seeder_grants_it_back(): void
    {
        $options = DB::table('options')->where('group_id', 394)->pluck('id');

        foreach ([73, 60, 168] as $child) {
            $this->assertSame($options->count(), DB::table('category_child_option_decisions')->where('child_id', $child)->where('kind', 'withdrawn')->whereIn('option_id', $options)->count());
        }
    }

    public function test_a_trade_that_really_manufactures_keeps_its_axis(): void
    {
        $this->assertTrue(DB::table('service_option_group_placements')->where('child_id', 301)->where('option_group_id', 394)->where('usage', 'price_variant')->where('is_active', 1)->exists(), 'أخشاب still sells to order');
    }
}
