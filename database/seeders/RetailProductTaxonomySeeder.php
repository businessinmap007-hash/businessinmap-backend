<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the catalog product taxonomy (product_categories + children) so it
 * mirrors the retail branches 1:1 — the same source file RetailBranchesSeeder
 * reads:
 *
 *   product_categories.slug        == retail branch key
 *   product_category_children.slug == retail item-type key
 *
 * That invariant is what lets the owner panel scope "My Products": a child's
 * config.allowed_item_types (branch item-type keys) → product_category_children
 * by slug → catalog_products.product_category_child_id.
 *
 * Upserts by slug exactly like CatalogImportService::upsertCategory/Child, so a
 * later `bim:catalog-import` writes INTO these rows instead of duplicating them.
 * Idempotent and additive — never deletes. Adding a branch later (e.g. grocery)
 * is a pure append to data/retail_taxonomy.php.
 */
class RetailProductTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $taxonomy = require __DIR__ . '/data/retail_taxonomy.php';

        $branchSort = 0;
        $childSort = 0;
        $categories = 0;
        $children = 0;

        foreach ($taxonomy as $branchKey => $branch) {
            $categoryId = $this->upsert('product_categories', ['slug' => $branchKey], [
                'name_ar' => $branch['name_ar'],
                'name_en' => $branch['name_en'],
                'is_active' => 1,
                'sort_order' => ++$branchSort,
            ]);
            $categories++;

            foreach ($branch['types'] as $typeKey => [$ar, $en]) {
                $this->upsert('product_category_children', ['product_category_id' => $categoryId, 'slug' => $typeKey], [
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'is_active' => 1,
                    'sort_order' => ++$childSort,
                ]);
                $children++;
            }
        }

        $this->command?->info("retail catalog taxonomy: {$categories} categories, {$children} children.");
    }

    /**
     * updateOrInsert on the given unique keys and return the row id. Mirrors the
     * importer's write() semantics (timestamps maintained, slug-keyed).
     */
    private function upsert(string $table, array $keys, array $values): int
    {
        $now = now();
        $existing = DB::table($table)->where($keys)->first();

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($values + ['updated_at' => $now]);

            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId($keys + $values + [
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
