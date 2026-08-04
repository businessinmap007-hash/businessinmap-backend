<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\User;
use App\Services\OfferingDiscovery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The heading is the whole option COMBINATION, not the line alone.
 *
 * A furniture merchant ticks غرفة نوم، ركنة and مودرن، كلاسيك، ألترا مودرن once
 * at registration, and his menu reads «غرفة نوم — مودرن» (3), «غرفة نوم —
 * كلاسيك» (5), «ركنة — ألترا مودرن» (7). The customer taps one of those
 * instead of assembling it out of loose options and then narrowing a second
 * time by service — محافظة، تصنيف، ابن، خيارات، خدمات was five steps to a
 * bedroom.
 *
 * @see \App\Models\MenuItem::heading()
 * @see \App\Services\OfferingDiscovery::combinations()
 */
class MenuOptionHeadingTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        $b = User::query()->where('type', 'business')->whereNotNull('category_child_id')->first();

        if (! $b) {
            $this->markTestSkipped('No business.');
        }

        return $b;
    }

    /** @return array{0:Option,1:Option,2:Option} one line, two modifiers */
    private function vocabulary(): array
    {
        $line = Option::query()
            ->whereIn('group_id', OptionGroup::query()->where('price_role', OptionGroup::ROLE_LINE)->select('id'))
            ->first();

        $modifiers = Option::query()
            ->whereIn('group_id', OptionGroup::query()->where('price_role', OptionGroup::ROLE_MODIFIER)->select('id'))
            ->take(2)->get();

        if (! $line || $modifiers->count() < 2) {
            $this->markTestSkipped('Not enough line/modifier options.');
        }

        return [$line, $modifiers[0], $modifiers[1]];
    }

    private function item(User $business, string $name, ?int $line, array $modifiers = []): MenuItem
    {
        $item = MenuItem::create([
            'business_id' => $business->id,
            'name_ar' => $name,
            'base_price' => 100,
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        $item->syncOfferingOptions($line, $modifiers);

        return $item;
    }

    private function menu(User $business): array
    {
        return $this->getJson('/api/v2/discovery/menu/' . $business->id)->assertOk()->json('data.sections');
    }

    /** «غرفة نوم — مودرن» and «غرفة نوم — كلاسيك» are two headings, not one. */
    public function test_the_same_line_with_different_modifiers_splits_into_two_headings(): void
    {
        $business = $this->business();
        [$line, $modern, $classic] = $this->vocabulary();

        foreach (range(1, 3) as $i) {
            $this->item($business, "مودرن {$i}", $line->id, [$modern->id]);
        }

        foreach (range(1, 5) as $i) {
            $this->item($business, "كلاسيك {$i}", $line->id, [$classic->id]);
        }

        $combos = collect($this->menu($business))->where('source', 'option_combo')->values();

        $this->assertCount(2, $combos, 'one line with two modifiers produced ' . $combos->count() . ' headings');

        $counts = $combos->map(fn ($c) => count($c['items']))->sort()->values()->all();
        $this->assertSame([3, 5], $counts);

        foreach ($combos as $combo) {
            $this->assertStringContainsString($line->displayName(), $combo['name']);
        }
    }

    /** Every heading carries the filter that reproduces it — one tap, no guessing. */
    public function test_a_heading_carries_its_own_filter(): void
    {
        $business = $this->business();
        [$line, $modern] = $this->vocabulary();

        $this->item($business, 'غرفة أ', $line->id, [$modern->id]);

        $combo = collect($this->menu($business))->firstWhere('source', 'option_combo');

        $this->assertNotNull($combo);
        $this->assertEqualsCanonicalizing([(int) $line->id, (int) $modern->id], $combo['option_ids']);
        $this->assertSame((int) $line->id, $combo['option_ids'][0], 'the line must lead the combination');
    }

    /** Order of entry must not split one combination into two headings. */
    public function test_modifier_order_does_not_split_a_heading(): void
    {
        $business = $this->business();
        [$line, $a, $b] = $this->vocabulary();

        $this->item($business, 'أ ثم ب', $line->id, [$a->id, $b->id]);
        $this->item($business, 'ب ثم أ', $line->id, [$b->id, $a->id]);

        $combos = collect($this->menu($business))->where('source', 'option_combo')->values();

        $this->assertCount(1, $combos, 'the same combination entered in two orders made two headings');
        $this->assertCount(2, $combos[0]['items']);
    }

    /** The option wins over the item type — one vocabulary, the merchant's own. */
    public function test_the_option_combination_outranks_the_item_type(): void
    {
        $business = $this->business();
        [$line] = $this->vocabulary();

        $type = DB::table('platform_service_item_types as t')
            ->join('platform_services as s', 's.id', '=', 't.platform_service_id')
            ->where('s.key', 'menu')->value('t.key');

        $item = MenuItem::create([
            'business_id' => $business->id,
            'name_ar' => 'صنف',
            'base_price' => 10,
            'is_active' => 1,
            'sort_order' => 0,
            'item_type' => $type,
        ]);

        $item->syncOfferingOptions((int) $line->id);

        $this->assertSame('option_combo', $item->fresh()->heading()['source']);
    }

    /** A child with no line options still gets its item-type heading. */
    public function test_the_item_type_still_serves_a_child_with_no_options(): void
    {
        $business = $this->business();

        $type = DB::table('platform_service_item_types as t')
            ->join('platform_services as s', 's.id', '=', 't.platform_service_id')
            ->where('s.key', 'menu')->value('t.key');

        $item = MenuItem::create([
            'business_id' => $business->id,
            'name_ar' => 'صنف بلا خيار',
            'base_price' => 10,
            'is_active' => 1,
            'sort_order' => 0,
            'item_type' => $type,
        ]);

        $this->assertSame('item_type', $item->heading()['source']);
    }

    /** Restaurants finally have a priced vocabulary: «مشويات» is a line option. */
    public function test_the_restaurant_menu_headings_are_line_options(): void
    {
        $restaurant = (int) DB::table('category_children_master')->where('name_ar', 'مطعم')->value('id');

        $this->assertGreaterThan(0, $restaurant);

        $lines = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', $restaurant)
            ->where('g.price_role', OptionGroup::ROLE_LINE)
            ->pluck('o.name_ar');

        foreach (['مشويات', 'ساندوتشات', 'مقبلات'] as $expected) {
            $this->assertContains($expected, $lines->all(), "«{$expected}» is not a line option for a restaurant");
        }
    }

    /** The cross-business list a customer taps, with the filter attached. */
    public function test_combinations_are_offered_as_one_step(): void
    {
        $business = $this->business();
        [$line, $modern, $classic] = $this->vocabulary();

        foreach (range(1, 3) as $i) {
            $this->item($business, "م {$i}", $line->id, [$modern->id]);
        }

        $this->item($business, 'ك 1', $line->id, [$classic->id]);

        $combos = app(OfferingDiscovery::class)->combinations((int) $business->category_child_id);

        $this->assertGreaterThanOrEqual(2, $combos->count());

        $top = $combos->first();
        $this->assertArrayHasKey('option_ids', $top);
        $this->assertArrayHasKey('offerings', $top);

        // The filter it hands back must actually return that many offerings.
        $found = app(OfferingDiscovery::class)
            ->search((int) $business->category_child_id, $top['option_ids'], 0, [], 50);

        $this->assertGreaterThanOrEqual($top['offerings'], $found->total());
    }
}
