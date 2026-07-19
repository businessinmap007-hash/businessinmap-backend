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
            'name' => $this->displayName(),
            'qty' => (int) $this->qty,
            'price' => (float) $this->price,
            'total_price' => (float) $this->total_price,
            'addons' => $this->addons ?: [],
        ];
    }

    private function shortType(string $type): string
    {
        return match ($type) {
            MenuItem::class => 'menu_item',
            BusinessCatalogListing::class => 'catalog_listing',
            default => $type !== '' ? class_basename($type) : 'item',
        };
    }

    private function displayName(): string
    {
        $item = $this->menuItem;
        if ($item) {
            return (string) ($item->loc('name') ?: ('#' . $this->menu_id));
        }

        return (string) $this->offering_type === BusinessCatalogListing::class
            ? __('منتج #') . $this->offering_id
            : '#' . ($this->menu_id ?: $this->offering_id);
    }
}
