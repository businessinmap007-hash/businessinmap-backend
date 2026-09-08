<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/** A business's own menu section. */
class MenuSectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->loc('name'),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            // Null for one the owner typed by hand; set for one grown from a
            // `line` option group — the edit screen locks the name field on
            // the latter, since renaming it would drift from the group it
            // still resolves the same items into.
            'option_group_id' => $this->option_group_id !== null ? (int) $this->option_group_id : null,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
