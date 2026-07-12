<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Creates catalog_products fixtures under a seeded retail item type, so retail
 * feature tests no longer depend on pre-existing imported catalog rows (the
 * catalog is wiped ahead of the retail rebuild). Rows are inserted with unique
 * bim_codes and, under DatabaseTransactions, rolled back after each test.
 *
 * Relies on RetailProductTaxonomySeeder having laid down product_categories /
 * product_category_children (branch key / type key == slug).
 */
trait SeedsRetailCatalog
{
    /**
     * Insert one catalog master under the given retail type slug and return its
     * id. Defaults to 'furniture' (home_furnishings branch).
     */
    protected function makeCatalogProduct(string $childSlug = 'furniture', string $nameAr = 'منتج اختبار'): int
    {
        $child = DB::table('product_category_children')->where('slug', $childSlug)->first();

        if (! $child) {
            $this->markTestSkipped("Retail taxonomy child '{$childSlug}' not seeded — run db:seed.");
        }

        $suffix = substr(md5(uniqid('', true)), 0, 10);

        return (int) DB::table('catalog_products')->insertGetId([
            'bim_code' => 'TST-' . $suffix,
            'product_category_id' => (int) $child->product_category_id,
            'product_category_child_id' => (int) $child->id,
            'product_type' => 'simple',
            'name_ar' => $nameAr,
            'market_scope' => 'egypt',
            'duplicate_status' => 'unique',
            'is_active' => 1,
            'approval_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
