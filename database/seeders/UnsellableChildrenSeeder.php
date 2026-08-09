<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives a selling service to the children that had none.
 *
 *     php artisan db:seed --class=UnsellableChildrenSeeder
 *
 * The why, and the measurement that bounds it, is in
 * `data/unsellable_children.php`. In short: 18 children carry only `delivery`
 * and `business_offers`, so they can be delivered from and can publish an offer
 * but cannot list a product, price a line or take a booking. 17 real businesses
 * sit on four of them with no way to sell anything.
 *
 * **Goods get MENU, not retail.** Retail lists from the central catalog, and the
 * catalog has no seeds, feed or fertiliser — enabling it would hand a fertiliser
 * merchant all 75 buckets and let him list none of them, because an empty
 * `allowed_item_types` means EVERY type. Menu is the merchant writing his own
 * list with his own names and prices, which is exactly what these trades need
 * and needs no catalogue at all. `menu_market` is the general-goods surface.
 *
 * **Services get BOOKING, direct.** `requires_bookable_item = false`: a security
 * company or a telecoms contractor sells an appointment, not a named unit out of
 * an inventory.
 *
 * Written through ChildServiceWriter, which is the only writer that touches BOTH
 * `category_platform_services` (WHETHER) and `category_service_configs` (HOW) —
 * every older writer touched one and left the pair disagreeing.
 *
 * Idempotent, and it never overwrites a child that has since been given a
 * selling service by hand.
 */
class UnsellableChildrenSeeder extends Seeder
{
    public function run(): void
    {
        $map = require database_path('seeders/data/unsellable_children.php');

        $menu = (int) PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id');
        $booking = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        if ($menu <= 0 || $booking <= 0) {
            $this->command?->warn('  ! خدمة المنيو أو الحجز غير موجودة — لم يُنفَّذ شيء.');

            return;
        }

        $writer = app(ChildServiceWriter::class);

        DB::transaction(function () use ($map, $menu, $booking, $writer) {
            $goods = $this->apply($map['goods'] ?? [], $menu, [
                'allowed_item_types' => ['menu_market'],
            ], $writer);

            $service = $this->apply($map['service'] ?? [], $booking, [
                'allowed_item_types' => ['booking_appointment'],
                'requires_bookable_item' => false,
                'bookable_item_kinds' => [],
            ], $writer);

            $this->command?->info('Unsellable children:');
            $this->command?->line("  - أبناء سلع مُنحوا المنيو : {$goods}");
            $this->command?->line("  - أبناء خدمات مُنحوا الحجز : {$service}");
        });
    }

    /**
     * @param  array<int,string>  $names
     * @param  array<string,mixed>  $config
     */
    private function apply(array $names, int $serviceId, array $config, ChildServiceWriter $writer): int
    {
        $touched = 0;

        foreach ($names as $name) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');

            if ($childId <= 0) {
                $this->command?->warn("  ! «{$name}» غير موجود — تُخطّي.");

                continue;
            }

            // Somebody may have given it a selling service since this list was
            // written. Their choice wins; this only fills a silence.
            $already = DB::table('category_platform_services as l')
                ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
                ->where('l.child_id', $childId)
                ->where('l.is_active', 1)
                ->whereIn('s.key', [
                    PlatformService::KEY_MENU,
                    PlatformService::KEY_RETAIL,
                    PlatformService::KEY_BOOKING,
                ])
                ->exists();

            if ($already) {
                continue;
            }

            // The child sits under one root or several; a service reaches it
            // through the (root, child) pair, so every root has to be written.
            $roots = DB::table('category_parent_child')->where('child_id', $childId)->pluck('parent_id');

            foreach ($roots as $rootId) {
                $writer->enable((int) $rootId, $childId, $serviceId, $config, null, null, 'unsellable-children');
            }

            $touched++;
        }

        return $touched;
    }
}
