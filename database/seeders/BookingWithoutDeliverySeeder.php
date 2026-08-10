<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Takes `delivery` off a child that sells time rather than goods.
 *
 *     php artisan db:seed --class=BookingWithoutDeliverySeeder
 *
 * See data/booking_without_delivery.php for the owner's rule and the three
 * carpenters he excepted from it.
 *
 * A RULE, evaluated per (root, child), not a list — a list would rot the first
 * time a child was added to a root. Which also means the seeder must run AFTER
 * DeliveryChildBranchesSeeder in DatabaseSeeder: that map is keyed by root and
 * would otherwise re-wire delivery onto the very children this switches off.
 * The entries were withdrawn from the map as well, but the ordering is what
 * makes the rule authoritative rather than a race.
 *
 * Idempotent, and reversible by hand: switching a service off leaves the config
 * row in place with everything the admin ever put in it.
 */
class BookingWithoutDeliverySeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/booking_without_delivery.php';

        DB::transaction(function () use ($data) {
            $ids = DB::table('platform_services')->whereIn('key', ['booking', 'delivery', 'menu', 'retail'])
                ->pluck('id', 'key');

            $booking = (int) ($ids['booking'] ?? 0);
            $delivery = (int) ($ids['delivery'] ?? 0);
            $goods = array_values(array_filter([(int) ($ids['menu'] ?? 0), (int) ($ids['retail'] ?? 0)]));

            if ($booking <= 0 || $delivery <= 0) {
                $this->command?->warn('  ! خدمتا الحجز والتوصيل غير معرّفتين.');

                return;
            }

            $writer = app(ChildServiceWriter::class);
            $stripped = 0;
            $kept = [];

            $rows = DB::table('category_platform_services as b')
                ->join('category_parent_child as p', function ($join) {
                    $join->on('p.parent_id', '=', 'b.category_id')->on('p.child_id', '=', 'b.child_id');
                })
                ->join('category_children_master as c', 'c.id', '=', 'b.child_id')
                ->join('categories as r', 'r.id', '=', 'b.category_id')
                ->where('b.platform_service_id', $booking)->where('b.is_active', 1)
                ->get(['b.category_id', 'b.child_id', 'c.name_ar', 'r.slug']);

            foreach ($rows as $row) {
                $rootId = (int) $row->category_id;
                $childId = (int) $row->child_id;

                $hasDelivery = DB::table('category_platform_services')
                    ->where('category_id', $rootId)->where('child_id', $childId)
                    ->where('platform_service_id', $delivery)->where('is_active', 1)->exists();

                if (! $hasDelivery) {
                    continue;
                }

                $sellsGoods = DB::table('category_platform_services')
                    ->where('category_id', $rootId)->where('child_id', $childId)
                    ->whereIn('platform_service_id', $goods)->where('is_active', 1)->exists();

                if ($sellsGoods) {
                    continue;
                }

                if (in_array($row->name_ar, $data['keep_delivery'], true)) {
                    $kept[] = "{$row->name_ar}@{$row->slug}";

                    continue;
                }

                $writer->disable($rootId, $childId, $delivery);
                $stripped++;
            }

            $this->command?->info('Booking without delivery:');
            $this->command?->line("  - صفوف تُحجز ولا تبيع سلعة : " . count($rows));
            $this->command?->line("  - توصيل أُوقف : {$stripped}");
            $this->command?->line('  - مستثناة بأمر المالك : ' . (implode('، ', $kept) ?: 'لا شيء'));
        });
    }
}
