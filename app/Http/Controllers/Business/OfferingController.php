<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
 *
 * It is also where the owner says what comes FIRST. Every list of what a
 * business sells was ordered by something the business had no say in — id
 * descending here, price ascending in discovery — so a restaurant could not put
 * its signature dish at the top of its own menu.
 *
 * The ordering is deliberately local: it sequences a business's offerings among
 * themselves and is only a tie-break inside one business in a cross-business
 * list. A merchant who could outrank a competitor by ticking «مميّز» would tick
 * it on everything, and the flag would stop meaning anything.
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
            ->sortBy([
                ['source', 'asc'],
                ['is_featured', 'desc'],
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])
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

    /**
     * Save the owner's sequence.
     *
     * The form posts one ordered list per source; position in that list IS the
     * order, so nothing has to be renumbered by hand. Every id is checked
     * against the owner's own rows before anything is written — a posted id is
     * a claim, not a fact.
     */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['nullable', 'array'],
            'order.*' => ['array'],
            'order.*.*' => ['integer', 'min:1'],
            'featured' => ['nullable', 'array'],
            'featured.*' => ['array'],
            'featured.*.*' => ['integer', 'min:1'],
        ]);

        $moved = 0;

        DB::transaction(function () use ($data, &$moved) {
            foreach (self::SOURCES as $source => $_label) {
                $ids = collect($data['order'][$source] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
                $featured = collect($data['featured'][$source] ?? [])->map(fn ($id) => (int) $id)->filter();

                if ($ids->isEmpty()) {
                    continue;
                }

                $mine = $this->ownedIds($source, $ids);

                foreach ($ids as $position => $id) {
                    if (! $mine->contains($id)) {
                        continue;
                    }

                    $this->tableFor($source)
                        ->where('id', $id)
                        ->where('business_id', $this->businessId())
                        ->update([
                            'sort_order' => $position,
                            'is_featured' => $featured->contains($id) ? 1 : 0,
                        ]);

                    $moved++;
                }
            }
        });

        return back()->with('success', __('تم حفظ الترتيب — :count عرضًا.', ['count' => $moved]));
    }

    /** Of the posted ids, the ones that really belong to this owner. */
    private function ownedIds(string $source, Collection $ids): Collection
    {
        return $this->tableFor($source)
            ->where('business_id', $this->businessId())
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
    }

    private function tableFor(string $source): \Illuminate\Database\Query\Builder
    {
        return DB::table(match ($source) {
            'bespoke' => 'business_service_prices',
            'menu' => 'menu_items',
            'retail' => 'business_catalog_listings',
        });
    }

    /** Bespoke booking offerings — one row per priced item type. */
    private function bespoke(): Collection
    {
        return BusinessServicePrice::query()
            ->with('service:id,key,name_ar,name_en')
            ->where('business_id', $this->businessId())
            ->orderBy('sort_order')
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
                'sort_order' => (int) $row->sort_order,
                'is_featured' => (bool) $row->is_featured,
                'edit_url' => route('business.prices.edit', $row->id),
            ]);
    }

    /** Food offerings from the owner's menu. */
    private function menu(): Collection
    {
        return MenuItem::query()
            ->where('business_id', $this->businessId())
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get(['id', 'name_ar', 'name_en', 'base_price', 'is_active', 'sort_order', 'is_featured'])
            ->map(fn ($row) => [
                'source' => 'menu',
                'source_label' => self::SOURCES['menu'],
                'id' => (int) $row->id,
                'name' => $this->label($row->name_ar, $row->name_en, 'صنف #' . $row->id),
                'detail' => 'منيو',
                'price' => (float) $row->base_price,
                'currency' => 'EGP',
                'is_active' => (bool) $row->is_active,
                'sort_order' => (int) $row->sort_order,
                'is_featured' => (bool) $row->is_featured,
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
            ->orderBy('l.sort_order')
            ->orderByDesc('l.id')
            ->get([
                'l.id', 'l.price', 'l.currency', 'l.is_active', 'l.sku',
                'l.sort_order', 'l.is_featured',
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
                'sort_order' => (int) $row->sort_order,
                'is_featured' => (bool) $row->is_featured,
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
