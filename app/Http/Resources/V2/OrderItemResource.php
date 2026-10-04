<?php

namespace App\Http\Resources\V2;

use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use Illuminate\Http\Resources\Json\JsonResource;

/** A single line on a placed order, with a best-effort display name. */
class OrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'offering_type' => $this->shortType((string) $this->offering_type),
            'offering_id' => $this->offering_id !== null ? (int) $this->offering_id : null,
            'name' => $this->resource->displayName(),
            'qty' => $this->qty,
            // «سمك 450» and «الطهى مشوى 150» are two lines of the invoice, not one blended price.
            'unit' => $this->saleUnitLabel(),
            'base_price' => round((float) $this->price - $this->extrasPerUnit(), 2),
            'extras_detail' => collect(is_array($this->addons) ? $this->addons : [])->map(fn ($a) => [
                'name' => (string) ($a['name'] ?? ''), 'unit_price' => round((float) ($a['price'] ?? 0), 2), 'qty' => (int) ($a['qty'] ?? 1),
                'total' => round((float) ($a['price'] ?? 0) * (int) ($a['qty'] ?? 1) * (float) $this->qty, 2),
            ])->values()->all(),
            'price' => (float) $this->price,
            'total_price' => (float) $this->total_price,
            'addons' => $this->addons ?: [],
            // Set once a business marks this line unavailable — see
            // OrderController::businessMarkItemUnavailable. null/null/null
            // for the overwhelming majority of lines that were fulfilled as
            // ordered.
            'resolution' => $this->resolution,
            'resolution_note' => $this->resolution_note,
            'unavailable_marked_at' => optional($this->unavailable_marked_at)->toIso8601String(),
        ];
    }

    private function extrasPerUnit(): float
    {
        return (float) collect(is_array($this->addons) ? $this->addons : [])->sum(fn ($a) => (float) ($a['price'] ?? 0) * (int) ($a['qty'] ?? 1));
    }

    /** «كجم» / «لتر» when the line is measured, null for pieces. */
    private function saleUnitLabel(): ?string
    {
        $menuId = (int) ($this->menu_id ?: ($this->offering_type === MenuItem::class ? $this->offering_id : 0));

        return $menuId > 0 ? \App\Support\SaleUnits::label(MenuItem::query()->whereKey($menuId)->value('sale_unit')) : null;
    }

    private function shortType(string $type): string
    {
        return match ($type) {
            MenuItem::class => 'menu_item',
            BusinessCatalogListing::class => 'catalog_listing',
            default => $type !== '' ? class_basename($type) : 'item',
        };
    }
}
