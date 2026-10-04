<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «ابدأ بمنيو الأسماك والمأكولات البحرية» — المالك، 2026-10-04. The fish group is a detailed menu
 * like furniture: its own fields, describing groups, and a price axis that is not «نظام التصنيع».
 * Rolls back.
 */
class SeafoodMenuTest extends TestCase
{
    use DatabaseTransactions;

    private const GROUP = 'أنواع الأسماك والمأكولات البحرية';

    private function fishShop(): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', 101)->orderBy('id')->first()
            ?: $this->markTestSkipped('No business stands on child #101 (أسماك).');
    }

    private function option(string $group, string $name): int
    {
        return (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', $group)->where('o.name_ar', $name)->value('o.id');
    }

    private function vocabulary(User $shop): array
    {
        Sanctum::actingAs($shop);

        return $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');
    }

    public function test_the_fish_group_is_a_detailed_menu_without_a_catalog(): void
    {
        $vocab = $this->vocabulary($this->fishShop());
        $line = collect($vocab['lines'])->firstWhere('group_name', self::GROUP);

        $this->assertTrue($line['detailed']);
        $this->assertSame('أسماك ومأكولات بحرية', $line['detail_profile']['name']);
        $this->assertFalse($line['detail_profile']['uses_catalog']);

        $fields = collect($line['detail_profile']['fields'])->keyBy('code');
        $this->assertTrue($fields['pieces_per_kg']['per_item'], 'the grading is stated for each item');
        $this->assertFalse($fields['description']['per_item'], 'the description is the box below, not a second input');
    }

    public function test_the_fish_shop_describes_and_prices_like_a_fish_shop(): void
    {
        $shop = $this->fishShop();
        // The shop's own ticks are live data: state the ones this test is about.
        foreach (['حالة السمك', 'مصدر السمك', 'حجم السمك'] as $group) {
            $option = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')->where('g.name_ar', $group)->orderBy('o.id')->value('o.id');
            DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $option], []);
        }
        $vocab = $this->vocabulary($shop);

        $modifiers = collect($vocab['modifiers'])->pluck('group_name')->all();
        foreach (['حالة السمك', 'مصدر السمك', 'حجم السمك'] as $group) {
            $this->assertContains($group, $modifiers, "«{$group}» describes a fish");
        }
        $this->assertSame([], $vocab['price_axes'], 'no price axis: the cooking method is a priced SERVICE of the shop, not a second price of the fish (see FishCookingAddonsTest)');
        $this->assertNotContains('طريقة الطهي', array_column($vocab['lines'], 'group_name'), 'a cooking method is not «what is it»');
    }

    public function test_an_item_shows_its_choices_on_the_page_and_its_grading_on_the_card(): void
    {
        $shop = $this->fishShop();
        $shrimp = $this->option(self::GROUP, 'جمبري');
        $fresh = $this->option('حالة السمك', 'سمك طازج');
        $large = $this->option('حجم السمك', 'حجم كبير');
        foreach ([$shrimp, $fresh, $large] as $id) {
            DB::table('option_user')->updateOrInsert(['user_id' => $shop->id, 'option_id' => $id], []);
        }
        $perKg = (int) DB::table('catalog_attributes')->where('code', 'pieces_per_kg')->value('id');

        Sanctum::actingAs($shop);
        $id = (int) $this->withHeaders(['Accept-Language' => 'ar'])->postJson('/api/v2/business/menu/items', [
            'name_ar' => 'جمبري جامبو', 'base_price' => 450, 'line_option_id' => $shrimp, 'modifier_option_ids' => [$fresh, $large],
            'attributes' => [$perKg => '21'], 'description_ar' => 'جمبري طازج من صيد اليوم',
        ])->assertCreated()->json('data.id');

        $page = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu-items/' . $id)->assertOk()->json('data.item');
        $specs = collect($page['specs'])->pluck('value', 'name')->all();

        $this->assertSame('سمك طازج', $specs['حالة السمك']);
        $this->assertSame('حجم كبير', $specs['حجم السمك']);
        $this->assertSame('جمبري طازج من صيد اليوم', $page['description']);
        $this->assertSame('21', (string) ($specs['عدد القطع فى الكيلو'] ?? ''));

        $sections = $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/discovery/menu/' . $shop->id)->assertOk()->json('data.sections');
        $card = collect($sections)->flatMap(fn ($section) => $section['items'])->firstWhere('id', $id);
        $this->assertStringContainsString('21', (string) $card['card_summary'], 'the grading is the card line');
    }

    public function test_a_market_that_carries_the_fish_list_is_not_asked_about_fish_condition(): void
    {
        $market = User::query()->where('type', 'business')->where('category_child_id', 272)->orderBy('id')->first()
            ?: $this->markTestSkipped('No supermarket account.');

        $this->assertNotContains('حالة السمك', collect($this->vocabulary($market)['modifiers'])->pluck('group_name')->all());
    }
}
