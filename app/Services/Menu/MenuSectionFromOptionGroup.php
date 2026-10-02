<?php

namespace App\Services\Menu;

use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\MenuSection;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement;
use App\Models\User;
use App\Services\Catalog\ServiceOptionPlacements;

/**
 * A goods business no longer types "أنواع الأجهزة الكهربائية" by hand — the
 * section is grown the first time one of its items picks a `line` option
 * from that group, and stays afterward even if every item leaves it (an
 * owner emptied section is his to delete, not ours to sweep).
 *
 * find-or-create is scoped by (business_id, option_group_id, option_id) —
 * the unique index added alongside these columns is what makes two
 * concurrent first uses of the same group (and, once split, the same
 * branch) settle on one row instead of two. `option_id` is 0 for the
 * ordinary case (the whole group is one section); when «مكونات الخدمة»
 * turned `branches_as_sections` on for this group, each LINE OPTION gets
 * its own section instead — «موبايل», «تابلت», «ساعة ذكية» each standalone
 * rather than all filed under one «أجهزة الموبايل وملحقاتها». See
 * [[tech-spec-menu-implementation]].
 */
class MenuSectionFromOptionGroup
{
    public function __construct(private readonly ServiceOptionPlacements $placements)
    {
    }

    public function resolve(int $businessId, Option $lineOption): ?MenuSection
    {
        $group = $lineOption->group;

        if (! $group) {
            return null;
        }

        if ($this->isSplit($businessId, $group->id)) {
            return MenuSection::query()->firstOrCreate(
                ['business_id' => $businessId, 'option_group_id' => $group->id, 'option_id' => $lineOption->id],
                ['name_ar' => $lineOption->name_ar, 'name_en' => $lineOption->name_en, 'is_active' => true]
            );
        }

        return $this->resolveGroupId($businessId, $group->id);
    }

    /**
     * Same find-or-create, entered by group id directly — for a merchant
     * naming a brand-new item that has no vocabulary option of its own to
     * read the group off of («اضافة صنف» flow: a custom name/price under a
     * section the merchant picked, not one of the fixed line options).
     * Always the whole-group section (option_id=0) — there is no specific
     * branch to split by in this flow.
     */
    public function resolveGroupId(int $businessId, int $groupId): ?MenuSection
    {
        $group = OptionGroup::find($groupId);

        if (! $group) {
            return null;
        }

        return MenuSection::query()->firstOrCreate(
            ['business_id' => $businessId, 'option_group_id' => $group->id, 'option_id' => 0],
            [
                'name_ar' => $group->name_ar,
                'name_en' => $group->name_en,
                'is_active' => true,
            ]
        );
    }

    /** Whether «مكونات الخدمة» split this group's branches for this business's own child, under the menu service. */
    public function isSplit(int $businessId, int $groupId): bool
    {
        $childId = (int) (User::query()->whereKey($businessId)->value('category_child_id') ?? 0);
        if ($childId <= 0) {
            return false;
        }

        $menuServiceId = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        if ($menuServiceId <= 0) {
            return false;
        }

        return $this->placements
            ->for($menuServiceId, $childId, ServiceOptionGroupPlacement::USAGE_SECTION)
            ->contains(fn (ServiceOptionGroupPlacement $p) => (int) $p->option_group_id === $groupId && $p->branches_as_sections);
    }
}
