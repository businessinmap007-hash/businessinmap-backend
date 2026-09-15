<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The rest of «معارض» (Exhibitions, root 21): every remaining child still
 * carried `booking` with no `menu` link at all - the same mistake
 * ExhibitionsAntiquesMenuSeeder fixed for one reported child (#21), found
 * to be root-wide on inspection. A showroom sells what it displays; it does
 * not take service bookings.
 *
 * Each child is shared with other roots (see ChildServiceWriter::
 * configElsewhere()'s own doc), so most already carry a menu config there -
 * reused as-is rather than guessed. Three - «ملابس جاهزة» #60, «جلود وشنط
 * وأحذية» #168, «مستلزمات مطاعم وكافيهات» #247 - say `menu_food` next door,
 * which is wrong for clothes/leather goods/restaurant SUPPLIES (not food
 * itself); those three fall back to `menu_market` instead of propagating
 * that mismatch. `item_groups: [85]` is applied uniformly, matching every
 * sibling already correct under this same root (#57, #115, #116, #189, plus
 * #21 fixed earlier) rather than each child's unrelated elsewhere value.
 */
class ExhibitionsMenuRolloutSeeder extends Seeder
{
    private const ROOT_ID = 21; // معارض (Exhibitions)

    private const ITEM_GROUPS = [85];

    /** Elsewhere says `menu_food`, but these sell general goods, not food. */
    private const FOOD_TYPE_OVERRIDES = [60, 168, 247];

    public function run(): void
    {
        $bookingId = (int) PlatformService::where('key', PlatformService::KEY_BOOKING)->value('id');
        $menuId = (int) PlatformService::where('key', PlatformService::KEY_MENU)->value('id');

        if ($bookingId <= 0 || $menuId <= 0) {
            return;
        }

        $writer = app(ChildServiceWriter::class);

        $childIds = DB::table('category_platform_services')
            ->where('category_id', self::ROOT_ID)
            ->where('platform_service_id', $bookingId)
            ->where('is_active', 1)
            ->pluck('child_id');

        $fixed = 0;

        foreach ($childIds as $childId) {
            $childId = (int) $childId;

            $hasMenu = DB::table('category_platform_services')
                ->where('category_id', self::ROOT_ID)
                ->where('child_id', $childId)
                ->where('platform_service_id', $menuId)
                ->where('is_active', 1)
                ->exists();

            if ($hasMenu) {
                continue; // already correct (#57, #115, #116, #189)
            }

            $writer->disable(self::ROOT_ID, $childId, $bookingId);

            $elsewhere = $writer->configElsewhere($childId, $menuId, self::ROOT_ID);
            $allowedTypes = $elsewhere['allowed_item_types'] ?? ['menu_market'];

            if (in_array('menu_food', $allowedTypes, true) && in_array($childId, self::FOOD_TYPE_OVERRIDES, true)) {
                $allowedTypes = ['menu_market'];
            }

            $writer->enable(
                rootId: self::ROOT_ID,
                childId: $childId,
                serviceId: $menuId,
                configPatch: [
                    'allowed_item_types' => $allowedTypes,
                    'item_groups' => self::ITEM_GROUPS,
                ],
                source: 'exhibitions_menu_rollout'
            );

            $fixed++;
        }

        $this->command?->info("Exhibitions menu rollout: {$fixed} children switched from booking to menu.");
    }
}
