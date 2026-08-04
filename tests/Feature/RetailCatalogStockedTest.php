<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Linking retail gave 87 children a working «منتجاتي» screen; 8 of the 9
 * branches then opened onto an EMPTY catalogue, which reads to a merchant as a
 * broken page rather than an unstocked one. This holds the promise the branch
 * makes: if a child is offered retail, its picker has something in it.
 *
 * @see \App\Http\Controllers\Business\CatalogListingController::productLookup()
 */
class RetailCatalogStockedTest extends TestCase
{
    private function serviceId(): int
    {
        return (int) DB::table('platform_services')->where('key', PlatformService::KEY_RETAIL)->value('id');
    }

    /** The item type keys a child may list from — exactly what retailScope() reads. */
    private function typesFor(int $childId): array
    {
        return DB::table('category_service_configs')
            ->where('child_id', $childId)
            ->where('platform_service_id', $this->serviceId())
            ->where('is_active', 1)
            ->pluck('config')
            ->flatMap(fn ($c) => (json_decode((string) $c, true) ?: [])['allowed_item_types'] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    private function productsUnder(array $typeKeys): int
    {
        if (empty($typeKeys)) {
            return 0;
        }

        return DB::table('catalog_products as p')
            ->join('product_category_children as c', 'c.id', '=', 'p.product_category_child_id')
            ->whereIn('c.slug', $typeKeys)
            ->whereNull('p.deleted_at')
            ->where('p.is_active', 1)
            ->count();
    }

    /** Every branch carries stock — none of the nine is a shelf with nothing on it. */
    public function test_every_retail_branch_has_products(): void
    {
        $branches = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $this->serviceId())
            ->get(['id', 'key', 'name_ar']);

        $this->assertNotEmpty($branches);

        foreach ($branches as $branch) {
            $keys = DB::table('platform_service_item_group_type as gt')
                ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
                ->where('gt.group_id', $branch->id)
                ->pluck('t.key')
                ->all();

            $this->assertGreaterThan(
                0,
                $this->productsUnder($keys),
                "branch «{$branch->name_ar}» ({$branch->key}) has no products — its merchants open an empty picker"
            );
        }
    }

    /**
     * The merchant-facing promise, and the one that actually matters: a child
     * offered the service must find something to list. A working screen onto
     * nothing is worse than no screen.
     */
    public function test_no_retail_child_opens_an_empty_picker(): void
    {
        $childIds = DB::table('category_platform_services')
            ->where('platform_service_id', $this->serviceId())
            ->where('is_active', 1)
            ->distinct()
            ->pluck('child_id');

        $this->assertNotEmpty($childIds);

        foreach ($childIds as $childId) {
            $types = $this->typesFor((int) $childId);

            if (empty($types)) {
                continue;               // no config yet — covered by RetailChildLinkTest
            }

            $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');

            $this->assertGreaterThan(
                0,
                $this->productsUnder($types),
                "«{$name}» is offered retail but has nothing to list"
            );
        }
    }

    /** Every product must be reachable: a live child, a brand and a unit. */
    public function test_the_new_products_are_well_formed(): void
    {
        $batch = DB::table('catalog_products')
            ->where('bim_code', 'like', 'BIM-RT-%')
            ->whereNull('deleted_at');

        $this->assertGreaterThan(0, (clone $batch)->count());

        $this->assertSame(0, (clone $batch)->whereNull('brand_id')->count(), 'a product carries no brand');
        $this->assertSame(0, (clone $batch)->whereNull('unit_id')->count(), 'a product carries no unit');
        $this->assertSame(0, (clone $batch)->whereNull('product_category_child_id')->count(), 'a product sits under no department');

        $orphan = (clone $batch)
            ->whereNotIn('product_category_child_id', DB::table('product_category_children')->whereNull('deleted_at')->pluck('id'))
            ->count();

        $this->assertSame(0, $orphan, 'a product points at a department that no longer exists');
    }

    /**
     * bim_code is matched by exact string on re-import, so a repeat silently
     * OVERWRITES the earlier product's whole content — that is how batch G of
     * the grocery run lost two rows.
     */
    public function test_no_product_code_is_used_twice(): void
    {
        $dupes = DB::table('catalog_products')
            ->selectRaw('bim_code, count(*) n')
            ->groupBy('bim_code')
            ->havingRaw('n > 1')
            ->pluck('bim_code');

        $this->assertEmpty($dupes, 'duplicate bim_code: ' . $dupes->implode('، '));
    }

    /**
     * A clean code check does not prove the PRODUCT is new — same brand, name
     * and package under a different code is still the same thing twice.
     */
    public function test_no_product_is_listed_twice_under_different_codes(): void
    {
        $dupes = DB::table('catalog_products')
            ->whereNull('deleted_at')
            ->whereNotNull('brand_id')
            ->selectRaw('brand_id, name_ar, COALESCE(package_label_ar, ?) label, count(*) n', [''])
            ->groupBy('brand_id', 'name_ar', 'label')
            ->havingRaw('n > 1')
            ->get();

        $this->assertCount(
            0,
            $dupes,
            'duplicate products: ' . $dupes->map(fn ($d) => "{$d->name_ar} {$d->label}")->implode('، ')
        );
    }

    /**
     * The shared attributes carry the grocery batch's 507 values; an import
     * that rewrites their unit changes what those numbers mean.
     */
    public function test_the_shared_attribute_units_were_not_rewritten(): void
    {
        foreach (['weight' => 'gram', 'volume' => 'liter'] as $code => $unit) {
            $attr = DB::table('catalog_attributes')->where('code', $code)->first();

            $this->assertNotNull($attr, "attribute «{$code}» is missing");

            $this->assertSame(
                $unit,
                DB::table('catalog_units')->where('id', $attr->unit_id)->value('code'),
                "attribute «{$code}» changed unit — every stored value now means something else"
            );
        }
    }
}
