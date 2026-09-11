<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A retail merchant's own priced listing over a shared catalog master. Mirrors
 * the web "My products" screen for the app/merchant client.
 */
class BusinessRetailListingResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->whenLoaded('product');

        return [
            'id' => (int) $this->id,
            'price' => (float) $this->price,
            'currency' => $this->currency ?: 'EGP',
            'stock' => $this->stock !== null ? (int) $this->stock : null,
            'min_order_qty' => $this->min_order_qty !== null ? (int) $this->min_order_qty : null,
            'unit' => $this->unit ?: null,
            'sku' => $this->sku,
            'is_active' => (bool) $this->is_active,

            /*
             * Who may see it. `public` is the shelf; `restricted` is a
             * wholesale list addressed to named buyers, and the audience is
             * echoed back so a merchant's own screen can show him what he set
             * — a restriction he cannot read is one he cannot correct.
             */
            'visibility' => (string) ($this->visibility ?: 'public'),
            'audience' => $this->audiencePayload(),
            'source_listing_id' => $this->source_listing_id ? (int) $this->source_listing_id : null,

            'product' => $product ? [
                'id' => (int) $product->id,
                'name' => $this->localize($product->name_ar, $product->name_en),
                'name_en' => $product->name_en ?: null,
                'image' => $product->main_image,
                'barcode' => $product->default_barcode,
            ] : ['id' => (int) $this->catalog_product_id],
        ];
    }

    /**
     * IDs alone are enough to WRITE an audience back, but a merchant's own
     * edit screen needs to show him what he set without a second round trip
     * per id — so each kind comes back both as bare ids (for the save
     * payload) and resolved to a display name (for the screen).
     *
     * @return array<string,mixed>
     */
    private function audiencePayload(): array
    {
        $rows = $this->resource->relationLoaded('audiences')
            ? $this->resource->audiences
            : $this->resource->audiences()->get(['audience_type', 'audience_id']);

        $pick = fn (string $type) => $rows->where('audience_type', $type)
            ->pluck('audience_id')->map(fn ($id) => (int) $id)->values()->all();

        $businessIds = $pick(\App\Models\CatalogListingAudience::TYPE_BUSINESS);
        $childIds = $pick(\App\Models\CatalogListingAudience::TYPE_CATEGORY_CHILD);
        $categoryIds = $pick(\App\Models\CatalogListingAudience::TYPE_CATEGORY);

        $businesses = $businessIds
            ? \App\Models\User::query()->whereIn('id', $businessIds)->get(['id', 'name', 'name_en'])
            : collect();
        $children = $childIds
            ? \Illuminate\Support\Facades\DB::table('category_children_master')->whereIn('id', $childIds)->get(['id', 'name_ar', 'name_en'])
            : collect();
        $categories = $categoryIds
            ? \Illuminate\Support\Facades\DB::table('categories')->whereIn('id', $categoryIds)->get(['id', 'name_ar', 'name_en'])
            : collect();

        return [
            'business_ids' => $businessIds,
            'child_ids' => $childIds,
            'category_ids' => $categoryIds,
            'businesses' => $businesses->map(fn ($b) => ['id' => (int) $b->id, 'name' => $b->displayName()])->values(),
            'children' => $children->map(fn ($c) => ['id' => (int) $c->id, 'name' => $this->localize($c->name_ar, $c->name_en)])->values(),
            'categories' => $categories->map(fn ($c) => ['id' => (int) $c->id, 'name' => $this->localize($c->name_ar, $c->name_en)])->values(),
        ];
    }

    private function localize(?string $ar, ?string $en): ?string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : (($ar ?: $en) ?: null);
    }
}
