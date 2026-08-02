<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Closes the services sweep across the non-booking services (2026-08-02).
 * The survey found menu/delivery/schedules structurally sound and retail
 * intentionally untouchable (its item types are the 1:1 scoping mirror of
 * product_category_children — never priced, never to be "cleaned"). The only
 * junk was two ungrouped menu types: the meaningless placeholder «منيو» and
 * «3D Max», a training subject that leaked into the menu service, dragging one
 * orphan price row with it.
 *
 *   php artisan db:seed --class=OtherServicesReformSeeder
 *
 * Same contract as ServicesReformSeeder: deactivate + strip, never delete;
 * the orphan price row is deactivated too, not removed. Idempotent.
 */
class OtherServicesReformSeeder extends Seeder
{
    private const RETIRE_MENU_KEYS = ['menu', '3dmax'];

    public function run(): void
    {
        DB::transaction(function () {
            $serviceId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
            $retired = 0;
            $prices = 0;

            foreach (self::RETIRE_MENU_KEYS as $key) {
                $retired += DB::table('platform_service_item_types')
                    ->where('platform_service_id', $serviceId)->where('key', $key)
                    ->where('is_active', 1)
                    ->update(['is_active' => 0, 'updated_at' => now()]);

                // the leaked type dragged an orphan price row with it
                $prices += DB::table('business_service_prices')
                    ->where('service_id', $serviceId)->where('bookable_item_type', $key)
                    ->where('is_active', 1)
                    ->update(['is_active' => 0, 'updated_at' => now()]);
            }

            $this->command?->info("Other-services reform: menu types retired={$retired}, orphan prices deactivated={$prices}.");
            $this->command?->line('  delivery/schedules sound; retail untouched by design (catalog mirror).');
        });
    }
}
