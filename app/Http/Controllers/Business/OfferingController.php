<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * "My offerings" — one screen over everything the owner sells, regardless of
 * source: bespoke service prices (booking), menu items (food), and retail
 * catalog listings (Phase 3d). Each row is source-tagged and links to the
 * source's own edit screen; adding still happens on the per-source screens.
 */
class OfferingController extends Controller
{
    /** Source keys → Arabic labels shown as pills / filter options. */
    private const SOURCES = [
        'bespoke' => 'خدمة',
        'menu' => 'منيو',
        'retail' => 'تجزئة',
    ];

    private function businessId(): int
    {
        return (int) Auth::id();
    }

    public function index(): View
    {
        $offerings = $this->bespoke()
            ->concat($this->menu())
            ->concat($this->retail())
            ->sortBy([['is_active', 'desc'], ['source', 'asc'], ['name', 'asc']])
            ->values();

        $counts = [
            'all' => $offerings->count(),
            'active' => $offerings->where('is_active', true)->count(),
        ] + $offerings->groupBy('source')->map->count()->all();

        return view('business.offerings.index', [
            'offerings' => $offerings,
            'counts' => $counts,
            'sources' => self::SOURCES,
        ]);
    }

    /** Bespoke booking offerings — one row per priced item type. */
    private function bespoke(): Collection
    {
        return BusinessServicePrice::query()
            ->with('service:id,key,name_ar,name_en')
            ->where('business_id', $this->businessId())
            ->orderByDesc('id')
            ->get()
            ->map(fn ($row) => [
                'source' => 'bespoke',
                'source_label' => self::SOURCES['bespoke'],
                'id' => (int) $row->id,
                'name' => (string) $row->bookable_item_type,
                'detail' => $this->label($row->service?->name_ar, $row->service?->name_en, $row->service?->key),
                'price' => (float) $row->price,
                'currency' => $row->currency ?: 'EGP',
                'is_active' => (bool) $row->is_active,
                'edit_url' => route('business.prices.edit', $row->id),
            ]);
    }

    /** Food offerings from the owner's menu. */
    private function menu(): Collection
    {
        return MenuItem::query()
            ->where('business_id', $this->businessId())
            ->orderByDesc('id')
            ->get(['id', 'name_ar', 'name_en', 'base_price', 'is_active'])
            ->map(fn ($row) => [
                'source' => 'menu',
                'source_label' => self::SOURCES['menu'],
                'id' => (int) $row->id,
                'name' => $this->label($row->name_ar, $row->name_en, 'صنف #' . $row->id),
                'detail' => 'منيو',
                'price' => (float) $row->base_price,
                'currency' => 'EGP',
                'is_active' => (bool) $row->is_active,
                'edit_url' => route('business.menu.edit', $row->id),
            ]);
    }

    /** Retail offerings — listings over the shared catalog master. */
    private function retail(): Collection
    {
        $query = DB::table('business_catalog_listings as l')
            ->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
            ->where('l.business_id', $this->businessId());

        if (Schema::hasColumn('catalog_products', 'deleted_at')) {
            $query->whereNull('p.deleted_at');
        }

        return $query
            ->orderByDesc('l.id')
            ->get([
                'l.id', 'l.price', 'l.currency', 'l.is_active', 'l.sku',
                'p.name_ar as product_name_ar', 'p.name_en as product_name_en',
            ])
            ->map(fn ($row) => [
                'source' => 'retail',
                'source_label' => self::SOURCES['retail'],
                'id' => (int) $row->id,
                'name' => $this->label($row->product_name_ar, $row->product_name_en, 'منتج #' . $row->id),
                'detail' => $row->sku ? ('SKU ' . $row->sku) : 'تجزئة',
                'price' => (float) $row->price,
                'currency' => $row->currency ?: 'EGP',
                'is_active' => (bool) $row->is_active,
                'edit_url' => route('business.products.edit', $row->id),
            ]);
    }

    private function label($ar, $en, $fallback): string
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);

        return $ar !== '' ? $ar : ($en !== '' ? $en : (string) $fallback);
    }
}
