<?php

namespace App\Services\Menu;

use App\Models\MenuSection;
use App\Models\Option;

/**
 * A goods business no longer types "أنواع الأجهزة الكهربائية" by hand — the
 * section is grown the first time one of its items picks a `line` option
 * from that group, and stays afterward even if every item leaves it (an
 * owner emptied section is his to delete, not ours to sweep).
 *
 * find-or-create is scoped by (business_id, option_group_id) — the unique
 * index added alongside `option_group_id` is what makes two concurrent first
 * uses of the same group settle on one row instead of two.
 */
class MenuSectionFromOptionGroup
{
    public function resolve(int $businessId, Option $lineOption): ?MenuSection
    {
        $group = $lineOption->group;

        if (! $group) {
            return null;
        }

        return MenuSection::query()->firstOrCreate(
            ['business_id' => $businessId, 'option_group_id' => $group->id],
            [
                'name_ar' => $group->name_ar,
                'name_en' => $group->name_en,
                'is_active' => true,
            ]
        );
    }
}
