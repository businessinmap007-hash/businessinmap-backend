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
                'image' => $product->main_image,
                'barcode' => $product->default_barcode,
            ] : ['id' => (int) $this->catalog_product_id],
        ];
    }

    /**
     * @return array<string,array<int,int>>
     */
    private function audiencePayload(): array
    {
        $rows = $this->resource->relationLoaded('audiences')
            ? $this->resource->audiences
            : $this->resource->audiences()->get(['audience_type', 'audience_id']);

        $pick = fn (string $type) => $rows->where('audience_type', $type)
            ->pluck('audience_id')->map(fn ($id) => (int) $id)->values()->all();

        return [
            'business_ids' => $pick(\App\Models\CatalogListingAudience::TYPE_BUSINESS),
            'child_ids' => $pick(\App\Models\CatalogListingAudience::TYPE_CATEGORY_CHILD),
            'category_ids' => $pick(\App\Models\CatalogListingAudience::TYPE_CATEGORY),
        ];
    }

    private function localize(?string $ar, ?string $en): ?string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : (($ar ?: $en) ?: null);
    }
}
