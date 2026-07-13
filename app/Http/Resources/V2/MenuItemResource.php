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
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'menu_section_id' => $this->menu_section_id !== null ? (int) $this->menu_section_id : null,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'base_price' => (float) $this->base_price,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,

            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($v) => [
                'id' => (int) $v->id,
                'type' => $v->type,
                'name_ar' => $v->name_ar,
                'name_en' => $v->name_en,
                'price' => $v->price !== null ? (float) $v->price : null,
                'price_delta' => $v->price_delta !== null ? (float) $v->price_delta : null,
                'is_default' => (bool) $v->is_default,
                'is_active' => (bool) $v->is_active,
            ])->values()),

            'extras' => $this->whenLoaded('extras', fn () => $this->extras->map(fn ($e) => [
                'id' => (int) $e->id,
                'group_key' => $e->group_key,
                'name_ar' => $e->name_ar,
                'name_en' => $e->name_en,
                'price' => (float) $e->price,
                'max_qty' => (int) $e->max_qty,
                'is_active' => (bool) $e->is_active,
            ])->values()),
        ];
    }
}
