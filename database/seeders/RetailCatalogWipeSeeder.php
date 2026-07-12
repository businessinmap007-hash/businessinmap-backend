<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ONE-TIME DESTRUCTIVE wipe of the sample catalog + its product taxonomy, ahead
 * of the retail rebuild. Intentionally NOT wired into DatabaseSeeder — run it by
 * hand exactly once:
 *
 *     php artisan db:seed --class=Database\\Seeders\\RetailCatalogWipeSeeder
 *
 * It clears catalog_products and product_categories/children (which were sample
 * imports with duplicated children from two batches), but KEEPS the slug-keyed
 * masters brands/manufacturers/units/attributes — the importer reuses them.
 * After this, run `php artisan db:seed` to lay down the mirrored retail taxonomy
 * (RetailProductTaxonomySeeder), then import real product sections.
 *
 * Guard: refuses to run if any business_catalog_listings reference the products
 * being deleted, unless RETAIL_WIPE_CONFIRM=1 — so an accidental second run
 * can't silently erase real owner listings + imported products.
 *
 * MySQL forbids TRUNCATE on FK-referenced parents, so this is ordered DELETE
 * from child to parent inside one transaction.
 */
class RetailCatalogWipeSeeder extends Seeder
{
    public function run(): void
    {
        $listings = DB::table('business_catalog_listings')->count();
        $products = DB::table('catalog_products')->count();

        if ($listings > 0 && env('RETAIL_WIPE_CONFIRM') != '1') {
            $this->command?->error(
                "Refusing to wipe: {$listings} business_catalog_listings exist (would be deleted along with {$products} products). "
                . 'Set RETAIL_WIPE_CONFIRM=1 to override.'
            );

            return;
        }

        DB::transaction(function () {
            // Child → parent order (respect FKs).
            DB::table('business_catalog_listings')->delete();
            DB::table('catalog_product_variant_attribute_values')->delete();
            DB::table('catalog_product_variants')->delete();
            DB::table('catalog_product_attribute_values')->delete();
            DB::table('catalog_product_barcodes')->delete();
            DB::table('catalog_product_images')->delete();
            DB::table('catalog_products')->delete();
            DB::table('product_category_children')->delete();
            DB::table('product_categories')->delete();
        });

        $this->command?->info('Catalog wiped. Kept masters: '
            . DB::table('catalog_brands')->count() . ' brands, '
            . DB::table('catalog_manufacturers')->count() . ' manufacturers, '
            . DB::table('catalog_units')->count() . ' units, '
            . DB::table('catalog_attributes')->count() . ' attributes.');
        $this->command?->info('Next: php artisan db:seed  (lays down the retail taxonomy), then bim:catalog-import.');
    }
}
