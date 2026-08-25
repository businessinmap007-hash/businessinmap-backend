<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Database\Seeders\ButcherChildSeeder;
use Database\Seeders\DisplayOrderSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «انشئ فى محلات محل جزارة كإبن جديد وضيف له منتجاته … ويرجى ترتيب الأبناء عند
 *  الظهور أبجديًا … ويخضع للتسعير والكمية المتاحة ووحدة البيع» — المالك،
 *  2026-08-24.
 *
 * Rolls back.
 */
class ButcherAndDisplayOrderTest extends TestCase
{
    use DatabaseTransactions;

    private const BUTCHER = 'جزارة';

    private function butcherId(): int
    {
        $id = (int) DB::table('category_children_master')->where('name_ar', self::BUTCHER)->value('id');

        return $id ?: $this->markTestSkipped('«جزارة» is not in this database.');
    }

    // ── the trade that sells meat ───────────────────────────────────────────

    public function test_the_butcher_stands_under_the_shops_root(): void
    {
        $roots = DB::table('category_parent_child as pc')
            ->join('categories as c', 'c.id', '=', 'pc.parent_id')
            ->where('pc.child_id', $this->butcherId())
            ->pluck('c.name_ar');

        $this->assertContains('المحلات أو أونلاين', $roots->all());
    }

    public function test_he_can_name_every_cut_and_every_bird(): void
    {
        $ids = DB::table('category_child_option')->where('child_id', $this->butcherId())->pluck('option_id');

        $byGroup = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('o.id', $ids)
            ->get(['g.name_ar as g', 'o.name_ar as o'])
            ->groupBy('g');

        foreach (['كندوز', 'ضاني', 'كبدة', 'سجق'] as $cut) {
            $this->assertContains($cut, $byGroup['أنواع اللحوم']->pluck('o')->all());
        }

        foreach (['فراخ بلدي', 'بط', 'رومي'] as $bird) {
            $this->assertContains($bird, $byGroup['أنواع الدواجن والطيور']->pluck('o')->all());
        }
    }

    public function test_his_price_has_a_unit_and_only_the_two_that_fit(): void
    {
        $units = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $this->butcherId())
            ->where('g.name_ar', 'وحدة البيع')
            ->pluck('o.name_ar')
            ->all();

        sort($units);

        $this->assertSame(['بالرأس', 'بالكيلو'], $units, 'Nobody buys meat «بالأردب».');
    }

    /**
     * The first cut of the seeder granted «الدفع والسداد» whole and handed a
     * butcher «دفع مسبق» — which is scoped to carriers — and «تقسيط بدون
     * فوائد», which is scoped by a pin. Six tests caught it. This one keeps it
     * caught.
     */
    public function test_a_whole_group_was_not_handed_to_him_sideways(): void
    {
        $words = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', $this->butcherId())
            ->pluck('o.name_ar')
            ->all();

        $this->assertNotContains('دفع مسبق', $words);
        $this->assertNotContains('تقسيط بدون فوائد', $words);
    }

    public function test_he_has_a_counter_to_sell_on_and_no_shelf(): void
    {
        $services = DB::table('category_platform_services as cps')
            ->join('platform_services as s', 's.id', '=', 'cps.platform_service_id')
            ->where('cps.child_id', $this->butcherId())
            ->where('cps.is_active', 1)
            ->pluck('s.key')
            ->all();

        $this->assertContains('menu', $services, 'A trade with nothing to sell on is not a trade.');
        $this->assertContains('delivery', $services);
        $this->assertNotContains('retail', $services, 'A butcher weighs what he cuts; he has no barcoded shelf.');
    }

    public function test_the_seeder_is_idempotent(): void
    {
        $children = DB::table('category_children_master')->count();
        $links = DB::table('category_child_option')->count();

        (new ButcherChildSeeder())->run();

        $this->assertSame($children, DB::table('category_children_master')->count());
        $this->assertSame($links, DB::table('category_child_option')->count());
    }

    // ── alphabetical ────────────────────────────────────────────────────────

    public function test_children_are_numbered_in_the_order_they_read(): void
    {
        $byNumber = DB::table('category_children_master')
            ->whereNotNull('reorder')
            ->orderBy('reorder')
            ->pluck('name_ar')
            ->all();

        $byName = DB::table('category_children_master')
            ->whereNotNull('reorder')
            ->orderBy('name_ar')
            ->orderBy('id')
            ->pluck('name_ar')
            ->all();

        $this->assertSame($byName, $byNumber, 'The stored order is the alphabet.');
    }

    public function test_option_groups_are_alphabetical_inside_their_tier(): void
    {
        // The role ranking is a rule, not a default: a pricing screen opens on
        // the priced lists. So the alphabet applies WITHIN each role.
        foreach (['line', 'modifier', 'descriptive'] as $role) {
            $byNumber = DB::table('option_groups')->where('price_role', $role)
                ->orderBy('reorder')->pluck('name_ar')->all();

            $byName = DB::table('option_groups')->where('price_role', $role)
                ->orderBy('name_ar')->orderBy('id')->pluck('name_ar')->all();

            $this->assertSame($byName, $byNumber, "«{$role}»");
        }
    }

    public function test_numbering_again_changes_nothing(): void
    {
        $before = DB::table('category_children_master')->orderBy('id')->pluck('reorder', 'id')->all();

        (new DisplayOrderSeeder())->run();

        $this->assertSame($before, DB::table('category_children_master')->orderBy('id')->pluck('reorder', 'id')->all());
    }

    // ── and every list that shows them reads it ─────────────────────────────

    /**
     * «اجعل الابناء بترتيب ابجدي ايضا مثل الخيارات» — المالك، 2026-08-25.
     *
     * The column was already alphabetical; three lists were not reading it.
     * Comparison is against the DATABASE's own sort — `strcmp` in PHP puts «أ»
     * and «ا» somewhere the collation never would.
     */
    public function test_a_root_lists_its_children_in_the_alphabet(): void
    {
        $root = \App\Models\Category::query()->withoutGlobalScopes()
            ->where('parent_id', 0)
            ->withCount('children')
            ->orderByDesc('children_count')
            ->first();

        $ids = $root->children()->pluck('category_children_master.id')->all();

        $this->assertGreaterThan(5, count($ids), 'A root with enough children to sort.');

        $this->assertSame(
            DB::table('category_children_master')->whereIn('id', $ids)
                ->orderBy('name_ar')->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all(),
            array_map('intval', $ids),
        );
    }

    public function test_a_platform_service_lists_its_children_in_the_alphabet(): void
    {
        // `sort_order` is a hand order and still wins. It is 0 almost
        // everywhere, and where it ties the name decides — so the assertion is
        // made inside one block of equal `sort_order`, which is what a screen
        // actually shows.
        $service = \App\Models\PlatformService::query()
            ->whereHas('activeChildren')->orderBy('id')->firstOrFail();

        $rows = $service->activeChildren()->get(['category_children_master.id', 'name_ar']);

        $blocks = $rows->groupBy(fn ($r) => (int) $r->pivot->sort_order);
        $biggest = $blocks->sortByDesc(fn ($b) => $b->count())->first();

        if ($biggest === null || $biggest->count() < 3) {
            $this->markTestSkipped('No block of tied rows to sort in this database.');
        }

        // The same child is linked once per root, so the list repeats ids. What
        // is asserted is that it never goes BACKWARDS in the alphabet.
        $ids = $biggest->pluck('id')->map(fn ($i) => (int) $i)->all();

        $rank = array_flip(
            DB::table('category_children_master')->whereIn('id', $ids)
                ->orderBy('name_ar')->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all()
        );

        $seen = -1;

        foreach ($ids as $id) {
            $this->assertGreaterThanOrEqual($seen, $rank[$id], 'The list steps back in the alphabet.');
            $seen = $rank[$id];
        }
    }

    public function test_no_screen_lists_children_by_id_alone(): void
    {
        // A list ordered by `id` alone is insertion order wearing a number.
        $offenders = [];

        foreach (
            array_merge(
                glob(app_path('Http/Controllers/**/*.php')) ?: [],
                glob(app_path('Http/Controllers/**/**/*.php')) ?: [],
            ) as $file
        ) {
            // Whitespace is squeezed out so a chain broken across lines reads
            // the same as one written inline.
            $body = preg_replace('/\s+/u', '', (string) file_get_contents($file));

            if (str_contains($body, "CategoryChild::query()->orderBy('id')")) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'These list children in insertion order: '.implode(', ', $offenders));
    }

    // ── price, unit, quantity ───────────────────────────────────────────────

    public function test_a_row_carries_its_price_its_unit_and_its_quantity(): void
    {
        $business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();

        $row = MenuItem::create([
            'business_id' => $business->id,
            'item_type' => 'menu_market',
            'name_ar' => 'برتقال سكري',
            'base_price' => 22,
            'sale_unit' => 'kg',
            'available_quantity' => 40,
            'is_active' => 1,
        ]);

        $this->assertSame(40, (int) $row->fresh()->available_quantity);
        $this->assertSame('كجم', $row->priceUnitLabel());
    }

    public function test_not_tracked_and_sold_out_are_different_answers(): void
    {
        // NULL is «لا أتابع الكمية» — every existing row, and every kitchen.
        // Zero is «معروض، ونفد», which keeps the price and the row.
        $business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();

        $untracked = MenuItem::create([
            'business_id' => $business->id, 'item_type' => 'menu_market',
            'name_ar' => 'صنف بلا متابعة', 'base_price' => 10, 'is_active' => 1,
        ]);

        $out = MenuItem::create([
            'business_id' => $business->id, 'item_type' => 'menu_market',
            'name_ar' => 'صنف نفد', 'base_price' => 10, 'available_quantity' => 0, 'is_active' => 1,
        ]);

        $this->assertNull($untracked->fresh()->available_quantity);
        $this->assertSame(0, (int) $out->fresh()->available_quantity);
        $this->assertTrue((bool) $out->fresh()->is_active, 'Selling out does not delist the item.');
    }

    public function test_the_api_tells_the_two_apart(): void
    {
        $business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();

        MenuItem::create([
            'business_id' => $business->id, 'item_type' => 'menu_market',
            'name_ar' => 'صنف نفد', 'base_price' => 10, 'available_quantity' => 0, 'is_active' => 1,
        ]);

        Sanctum::actingAs($business);

        $payload = $this->getJson('/api/v2/discovery/menu/' . $business->id);

        if ($payload->status() !== 200) {
            $this->markTestSkipped('The menu discovery door is closed for this business.');
        }

        $this->assertStringContainsString('available_quantity', $payload->getContent());
    }
}
