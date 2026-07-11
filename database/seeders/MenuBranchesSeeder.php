<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Database\Seeder;

/**
 * Menu branches taxonomy — the Menu counterpart of Delivery/BookingBranchesSeeder.
 *
 * Menu had two real branches: restaurant_menu (14 types, fine) and supermarket —
 * a 53-type dump. The division keeps `supermarket` as the full umbrella (hyper /
 * mini market children genuinely stock everything) and CROSS-LISTS subsets into
 * 5 new specialised branches (m2m), so a bakery child gets only «مخبوزات
 * وحلويات» instead of the whole dump. Idempotent.
 *
 * Left untouched on purpose: the placeholder type `menu` («منيو», ungrouped).
 *
 * Legacy cleanup (see cleanupLegacyTypes): `3dmax` is detached from the
 * *training* branch (a menu-type in a booking branch was import garbage) but
 * kept active — a live business price references it; `ultra_modern` (unclear
 * legacy label, zero references) is detached from supermarket and deactivated.
 */
class MenuBranchesSeeder extends Seeder
{
    public function run(): void
    {
        $menu = PlatformService::where('key', 'menu')->first();

        if (! $menu) {
            return;
        }

        $serviceId = (int) $menu->id;

        $newBranches = [
            'fresh_market' => ['طازج (خضار وفاكهة ولحوم وأسماك)', 'Fresh Market'],
            'bakery_sweets' => ['مخبوزات وحلويات', 'Bakery & Sweets'],
            'beverages_drinks' => ['مشروبات وعصائر', 'Beverages & Juices'],
            'grocery_pantry' => ['بقالة ومواد غذائية', 'Grocery & Pantry'],
            'household_personal' => ['منظفات وعناية ومنزلية', 'Household & Personal Care'],
        ];

        $sort = 1 + (int) PlatformServiceItemGroup::max('sort_order');

        foreach ($newBranches as $key => [$ar, $en]) {
            PlatformServiceItemGroup::updateOrCreate(
                ['key' => $key],
                [
                    'platform_service_id' => $serviceId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'sort_order' => $sort++,
                    'is_active' => 1,
                ]
            );
        }

        /*
        | branch key => menu item-type keys (syncWithoutDetaching — the types all
        | stay members of `supermarket` too).
        */
        $assign = [
            'fresh_market' => [
                'fresh_produce', 'fruits', 'fruit_salad', 'meat_poultry', 'meat',
                'chickens', 'fish', 'seafood_grocery', 'salted_fish', 'smoked_fish',
                'frozen_food', 'freezers_and_fresh', 'frozen', 'fresh',
                'dairy_eggs', 'cheese', 'dairy_products',
            ],
            'bakery_sweets' => [
                'bakery', 'pies', 'waffle', 'sweets_chocolate', 'candy_and_biscuit',
                'sweets', 'ice_cream', 'sandwich', 'sandwitches',
            ],
            'beverages_drinks' => [
                'beverages', 'drinks', 'cold_drink', 'hot_drink', 'juices',
            ],
            'grocery_pantry' => [
                'canned_food', 'canned_food_2', 'oils_ghee', 'ghee_and_oil',
                'pasta_rice_grains', 'pasta_2', 'flour_and_legumes', 'rice_and_sugar',
                'spices', 'foodstuffs', 'moad_ghthayy_1', 'moad_ghthayy_2',
                'snacks', 'tuna',
            ],
            'household_personal' => [
                'cleaning_supplies', 'cleaners_and_sterilizers', 'personal_care',
                'baby_products', 'pet_supplies', 'household_items', 'coal',
            ],
        ];

        foreach ($assign as $groupKey => $typeKeys) {
            $group = PlatformServiceItemGroup::where('key', $groupKey)->first();

            if (! $group) {
                continue;
            }

            $typeIds = PlatformServiceItemType::query()
                ->where('platform_service_id', $serviceId)
                ->whereIn('key', $typeKeys)
                ->pluck('id')
                ->all();

            if (! empty($typeIds)) {
                $group->itemTypes()->syncWithoutDetaching($typeIds);
            }
        }

        $this->cleanupLegacyTypes($serviceId);
    }

    protected function cleanupLegacyTypes(int $serviceId): void
    {
        // 3dmax: menu-service type wrongly cross-linked into the booking
        // `training` branch. Detach only — it keeps an active business price.
        $threeDMax = PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->where('key', '3dmax')
            ->first();

        $training = PlatformServiceItemGroup::where('key', 'training')->first();

        if ($threeDMax && $training) {
            $training->itemTypes()->detach($threeDMax->id);
        }

        // ultra_modern: meaningless legacy label, zero references anywhere —
        // detach from supermarket and deactivate.
        $ultraModern = PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->where('key', 'ultra_modern')
            ->first();

        if ($ultraModern) {
            $ultraModern->groups()->detach();
            $ultraModern->update(['is_active' => 0]);
        }
    }
}
