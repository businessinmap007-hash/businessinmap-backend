<?php

namespace Database\Seeders;

use App\Models\BusinessServicePrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives the 28 «معارض» children a booking and a delivery service.
 *
 *   php artisan db:seed --class=ExhibitionServicesSeeder
 *
 * The whole root — 53 showrooms — had no live service at all: its children were
 * wired to `retail` alone, and `retail` is switched off deliberately. A showroom
 * could not list one sellable thing.
 *
 * Two services, matching what a showroom actually does:
 *
 * 1. booking, in DIRECT mode — the customer books a viewing appointment, not a
 *    named object out of an inventory, so the price sits on the default item
 *    type exactly as the approved direct-booking plan defines it.
 *
 * 2. delivery — mirrored from the SAME child's live delivery config under
 *    another root wherever one exists, because «آثاث» under معارض delivers what
 *    «آثاث» under شركات delivers. Twenty-six of the twenty-eight have such a
 *    template; the two car showrooms get the freight group, which is how a
 *    vehicle moves.
 *
 * Both tables matter. `category_service_configs` says what MAY be listed;
 * `category_platform_services` is what the owner panel and discovery read to
 * decide which services a business is offered at all (ResolvesOwnerCatalog,
 * DiscoveryController::filters). A config without its service link is a screen
 * no merchant can reach.
 *
 * Idempotent, and it never touches `retail`.
 */
class ExhibitionServicesSeeder extends Seeder
{
    private const ROOT = 'exhibitions';

    /** Heavy goods leave a showroom on a truck. Used when no template exists. */
    private const FALLBACK_GROUP = 'delivery_freight';

    public function run(): void
    {
        DB::transaction(function () {
            $rootId = (int) DB::table('categories')->where('slug', self::ROOT)->value('id');

            if (! $rootId) {
                $this->command?->warn('  ! جذر «' . self::ROOT . '» غير موجود.');

                return;
            }

            $children = DB::table('category_parent_child')->where('parent_id', $rootId)->pluck('child_id');

            $booking = $this->openDirectBooking($rootId, $children);
            $delivery = $this->openDelivery($rootId, $children);

            $this->command?->info('Exhibition services:');
            $this->command?->line('  - أبناء المعارض : ' . $children->count());
            $this->command?->line("  - حجز مباشر (موعد معاينة) : {$booking}");
            $this->command?->line("  - توصيل : {$delivery['set']} (منها {$delivery['fallback']} بالمجموعة الافتراضية)");
            $this->command?->line('  - إعدادات نشطة بلا أي نوع عنصر : ' . $this->emptyActiveConfigs());
        });
    }

    private function openDirectBooking(int $rootId, $children): int
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $defaultType = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->where('key', BusinessServicePrice::DEFAULT_ITEM_TYPE)
            ->value('key');

        if (! $serviceId || ! $defaultType) {
            $this->command?->warn('  ! نوع الحجز الافتراضي غير موجود — تُرك الحجز.');

            return 0;
        }

        foreach ($children as $childId) {
            $this->writeConfig($rootId, (int) $childId, $serviceId, [
                'booking_modes' => [],
                'item_family' => null,
                'requires_bookable_item' => false,
                'requires_start_end' => true,
                'supports_quantity' => false,
                'supports_guest_count' => false,
                'supports_extras' => false,
                'required_fields' => [],
                'item_groups' => [],
                'allowed_item_types' => [$defaultType],
            ]);

            $this->linkService($rootId, (int) $childId, $serviceId);
        }

        return $children->count();
    }

    /** @return array{set:int,fallback:int} */
    private function openDelivery(int $rootId, $children): array
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'delivery')->value('id');

        if (! $serviceId) {
            return ['set' => 0, 'fallback' => 0];
        }

        $set = $fallback = 0;

        foreach ($children as $childId) {
            $childId = (int) $childId;

            $template = DB::table('category_service_configs')
                ->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)
                ->where('is_active', 1)
                ->where('category_id', '!=', $rootId)
                ->value('config');

            $config = $template
                ? (json_decode($template, true) ?: [])
                : $this->fallbackDeliveryConfig($serviceId);

            if (! $template) {
                $fallback++;
            }

            if (empty($config['allowed_item_types'])) {
                $this->command?->warn("  ! لا أنواع توصيل للابن #{$childId} — تُرك.");

                continue;
            }

            $this->writeConfig($rootId, $childId, $serviceId, $config);
            $this->linkService($rootId, $childId, $serviceId);

            $set++;
        }

        return ['set' => $set, 'fallback' => $fallback];
    }

    private function fallbackDeliveryConfig(int $serviceId): array
    {
        $group = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->where('key', self::FALLBACK_GROUP)
            ->first(['id']);

        if (! $group) {
            return [];
        }

        $types = DB::table('platform_service_item_group_type as gt')
            ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->where('gt.group_id', $group->id)
            ->where('t.is_active', 1)
            ->pluck('t.key')
            ->unique()
            ->values()
            ->all();

        return [
            'has_delivery' => true,
            'delivery_type' => 'distance',
            'max_radius_km' => 0,
            'supports_scheduled_delivery' => false,
            'item_groups' => [(int) $group->id],
            'allowed_item_types' => $types,
        ];
    }

    /** Merge, so keys this seeder does not know about survive a re-run. */
    private function writeConfig(int $rootId, int $childId, int $serviceId, array $config): void
    {
        $row = DB::table('category_service_configs')
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->first(['id', 'config']);

        if ($row) {
            DB::table('category_service_configs')->where('id', $row->id)->update([
                'config' => json_encode(
                    array_merge(json_decode($row->config ?: '{}', true) ?: [], $config),
                    JSON_UNESCAPED_UNICODE
                ),
                'is_active' => 1,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('category_service_configs')->insert([
            'category_id' => $rootId,
            'child_id' => $childId,
            'platform_service_id' => $serviceId,
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The availability catalog. Without a row here the owner panel offers the
     * merchant nothing and discovery shows no tab, however complete the config is.
     */
    private function linkService(int $rootId, int $childId, int $serviceId): void
    {
        $existing = DB::table('category_platform_services')
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->value('id');

        if ($existing) {
            DB::table('category_platform_services')->where('id', $existing)
                ->update(['is_active' => 1, 'updated_at' => now()]);

            return;
        }

        DB::table('category_platform_services')->insert([
            'category_id' => $rootId,
            'child_id' => $childId,
            'platform_service_id' => $serviceId,
            'is_active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function emptyActiveConfigs(): int
    {
        return DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->get(['c.config'])
            ->filter(fn ($row) => empty((json_decode($row->config ?: '{}', true) ?: [])['allowed_item_types']))
            ->count();
    }
}
