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
            'product' => $product ? [
                'id' => (int) $product->id,
                'name' => $this->localize($product->name_ar, $product->name_en),
                'image' => $product->main_image,
                'barcode' => $product->default_barcode,
            ] : ['id' => (int) $this->catalog_product_id],
        ];
    }

    private function localize(?string $ar, ?string $en): ?string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : (($ar ?: $en) ?: null);
    }
}
