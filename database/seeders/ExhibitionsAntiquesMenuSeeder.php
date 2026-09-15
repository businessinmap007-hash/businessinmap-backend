<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;

/**
 * «معارض» (Exhibitions, root 21) => «أنتيكات وتحف» (Antiques, child 21): a
 * showroom displays and sells its pieces, it does not take service bookings.
 * The child had `booking` active and no `menu` link at all, so the owner's
 * app showed "Incoming bookings" instead of a product menu.
 *
 * Four sibling children under the same root (نجف و تحف #57, مفروشات #115,
 * آثاث #116, معرض موتوسيكلات #189) already carry the correct menu link -
 * this seeder brings Antiques in line with that, using the same
 * `item_groups` convention. `menu_market` is the closest of the five menu
 * item types (menu_food/menu_furniture/menu_market/menu_properties/
 * menu_vehicles) to general antiques and collectibles.
 *
 * The other ~25 children under this root also still carry `booking` without
 * `menu` - likely the same mistake repeated root-wide, but each needs its
 * own `allowed_item_types` decided against its actual goods (carpets,
 * fabrics, appliances, ...) rather than guessed here, so this seeder is
 * scoped to the one reported child only.
 */
class ExhibitionsAntiquesMenuSeeder extends Seeder
{
    private const ROOT_ID = 21; // معارض (Exhibitions)
    private const CHILD_ID = 21; // أنتيكات وتحف (Antiques)

    public function run(): void
    {
        $bookingId = (int) PlatformService::where('key', PlatformService::KEY_BOOKING)->value('id');
        $menuId = (int) PlatformService::where('key', PlatformService::KEY_MENU)->value('id');

        if ($bookingId <= 0 || $menuId <= 0) {
            return;
        }

        $writer = app(ChildServiceWriter::class);

        $writer->disable(self::ROOT_ID, self::CHILD_ID, $bookingId);

        $writer->enable(
            rootId: self::ROOT_ID,
            childId: self::CHILD_ID,
            serviceId: $menuId,
            configPatch: [
                'allowed_item_types' => ['menu_market'],
                'item_groups' => [85],
            ],
            source: 'exhibitions_antiques_menu_not_booking'
        );

        $this->command?->info('Exhibitions/Antiques (21/21): booking disabled, menu enabled.');
    }
}
