<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/** A business's own menu item, with variants + extras when eager-loaded. */
class MenuItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            // Localized for display; the raw pair stays for edit screens.
            'name' => $this->loc('name'),
            'description' => $this->loc('description'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'menu_section_id' => $this->menu_section_id !== null ? (int) $this->menu_section_id : null,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'base_price' => (float) $this->base_price,
            // The merchant's own cost — this resource is owner-authed only,
            // never the customer-facing discovery payload.
            'supply_price' => $this->supply_price !== null ? (float) $this->supply_price : null,
            // Null is «by the item»; a shop that weighs what it sells says كجم.
            'sale_unit' => $this->sale_unit ?: null,
            'sale_unit_label' => $this->resource->priceUnitLabel(),
            'brand_name' => $this->brand_name,
            // null = not tracked, 0 = sold out. The app must tell them
            // apart: one hides nothing, the other greys the row.
            'available_quantity' => $this->available_quantity,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,

            // «ربط نظام المواصفات مع المنيو» — the catalog master this item
            // prices, when the merchant picked one, with its spec table
            // resolved so the edit screen can preview it without a round trip.
            'catalog_product_id' => $this->catalog_product_id !== null ? (int) $this->catalog_product_id : null,
            'catalog_product' => $this->catalog_product_id ? (function () {
                $product = $this->resource->catalogProduct;
                if (! $product) {
                    return null;
                }

                return [
                    'id' => (int) $product->id,
                    'name' => app()->getLocale() === 'en'
                        ? ($product->name_en ?: $product->name_ar)
                        : ($product->name_ar ?: $product->name_en),
                    'specs' => app(\App\Services\Catalog\ProductSpecs::class)->forProducts([(int) $product->id])[(int) $product->id] ?? [],
                ];
            })() : null,

            // What this item IS (a `line` option, e.g. "ثلاجات") and what
            // qualifies it (brand, condition...) — {@see HasOfferingOptions}.
            // Read fresh rather than gated behind whenLoaded: cheap (at most
            // a handful of rows) and every edit screen needs it up front.
            'line_option' => ($lineOption = $this->resource->lineOption()) ? [
                'id' => (int) $lineOption->id,
                'name_ar' => $lineOption->name_ar,
                'name_en' => $lineOption->name_en,
            ] : null,
            'modifier_options' => $this->resource->modifierOptions()->map(fn ($o) => [
                'id' => (int) $o->id,
                'name_ar' => $o->name_ar,
                'name_en' => $o->name_en,
            ])->values(),

            // Relative paths, as everywhere else — an absolute URL breaks the
            // moment the host changes, which is exactly what happened before.
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => [
                'id' => (int) $i->id,
                'image' => $i->image,
                // camera = a live shot — the app badges it.
                'source' => $i->source,
            ])->values()),

            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($v) => [
                'id' => (int) $v->id,
                'type' => $v->type,
                'name' => $v->loc('name'),
                'name_ar' => $v->name_ar,
                'name_en' => $v->name_en,
                'price' => $v->price !== null ? (float) $v->price : null,
                'price_delta' => $v->price_delta !== null ? (float) $v->price_delta : null,
                'is_default' => (bool) $v->is_default,
                'is_active' => (bool) $v->is_active,
            ])->values()),

            'extra_groups' => $this->whenLoaded('extraGroups', fn () => $this->extraGroups->map(fn ($g) => [
                'id' => (int) $g->id,
                'name' => $g->loc('name'),
                'name_ar' => $g->name_ar,
                'name_en' => $g->name_en,
                'selection_type' => $g->selection_type,
                'reorder' => (int) $g->reorder,
                'is_active' => (bool) $g->is_active,
            ])->values()),

            'extras' => $this->whenLoaded('extras', fn () => $this->extras->map(fn ($e) => [
                'id' => (int) $e->id,
                'extra_group_id' => $e->extra_group_id !== null ? (int) $e->extra_group_id : null,
                'name' => $e->loc('name'),
                'name_ar' => $e->name_ar,
                'name_en' => $e->name_en,
                'price' => (float) $e->price,
                'max_qty' => (int) $e->max_qty,
                'is_active' => (bool) $e->is_active,
            ])->values()),
        ];
    }
}
