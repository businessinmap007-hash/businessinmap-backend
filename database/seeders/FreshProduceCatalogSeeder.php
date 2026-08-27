<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Stocks the three retail item-types added 2026-08-27 (`fruits`, `vegetables`,
 * `poultry` — see data/retail_taxonomy.php) that would otherwise leave
 * «خضار وفاكهة» and «دواجن» open to an empty picker
 * (RetailCatalogStockedTest::test_no_retail_child_opens_an_empty_picker,
 * whose own UNSTOCKED_TRADES list says "nothing may be added to it" — so
 * this stocks them instead of documenting the gap away).
 *
 * Names are drawn from the option-group vocabulary the taxonomy already
 * trusts — «الفواكه» (77), «الخضروات» (45), «أنواع الدواجن والطيور» — not
 * invented here. Poultry drops two: «كتاكيت» (chicks) and «بيض تفريخ»
 * (hatching eggs) are livestock/hatchery trade, not a consumer product a
 * shop shelves, the same distinction that kept the option GROUP itself off
 * the wholesale-factory grant earlier the same day
 * (see [[unified-per-child-fees]] memory — unrelated table, same reasoning).
 *
 * Idempotent (upsert by product_category_child_id + name_ar) and additive
 * only. bim_code prefix `BIM-FR-<NNN>` — collision-free, no existing prefix
 * used it.
 */
class FreshProduceCatalogSeeder extends Seeder
{
    private const BIM_PREFIX = 'BIM-FR-';

    private const POULTRY_EXCLUDED_AR = ['كتاكيت', 'بيض تفريخ'];

    public function run(): void
    {
        $categoryId = (int) DB::table('product_categories')->where('slug', 'grocery_retail')->value('id');

        if (! $categoryId) {
            $this->command?->warn('grocery_retail product_categories row not found — run RetailProductTaxonomySeeder first.');

            return;
        }

        $seq = (int) DB::table('catalog_products')
            ->where('bim_code', 'like', self::BIM_PREFIX . '%')
            ->count();

        $inserted = 0;

        $inserted += $this->stockFromOptionGroup('fruits', $categoryId, 'الفواكه', [], $seq);
        $seq += $inserted;

        $justInserted = $this->stockFromOptionGroup('vegetables', $categoryId, 'الخضروات', [], $seq);
        $inserted += $justInserted;
        $seq += $justInserted;

        $inserted += $this->stockFromOptionGroup('poultry', $categoryId, 'أنواع الدواجن والطيور', self::POULTRY_EXCLUDED_AR, $seq);

        $this->command?->info("fresh produce catalog: {$inserted} products added.");
    }

    private function stockFromOptionGroup(string $childSlug, int $categoryId, string $groupNameAr, array $excludeAr, int $seqStart): int
    {
        $childId = (int) DB::table('product_category_children')->where('slug', $childSlug)->value('id');

        if (! $childId) {
            return 0;
        }

        $names = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupNameAr)
            ->whereNotIn('o.name_ar', $excludeAr)
            ->orderBy('o.id')
            ->pluck('o.name_ar')
            ->unique()
            ->values();

        $seq = $seqStart;
        $count = 0;

        foreach ($names as $nameAr) {
            $exists = DB::table('catalog_products')
                ->where('product_category_child_id', $childId)
                ->where('name_ar', $nameAr)
                ->exists();

            if ($exists) {
                continue;
            }

            $seq++;

            DB::table('catalog_products')->insert([
                'bim_code' => self::BIM_PREFIX . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'product_category_id' => $categoryId,
                'product_category_child_id' => $childId,
                'product_type' => 'simple',
                'name_ar' => $nameAr,
                'market_scope' => 'egypt',
                'is_verified_egypt' => 1,
                'is_active' => 1,
                'approval_status' => 'pending',
                'sort_order' => $seq,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }
}
