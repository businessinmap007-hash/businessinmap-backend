<?php

namespace Database\Seeders;

use App\Models\BusinessServicePrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives the transport children a service catalogue they can actually sell from.
 *
 *   php artisan db:seed --class=ChildServiceScopeSeeder
 *
 * The `cars` root carried 638 businesses and a single active service — the
 * `schedules` config — whose allowed_item_types was `[]` on every one of its
 * seven children. A limousine service with 560 accounts could list nothing at
 * all: no trip leg, because no mode was allowed, and no booking, because the
 * booking service was never enabled there.
 *
 * Two fixes, both driven by data/child_service_scope.php:
 *
 * 1. schedules gets the transport modes each child runs — or is switched off
 *    where a timetable is meaningless (car wash, garage, rescue winch) — and is
 *    extended to the carriers that never had it (شركة شحن، مندوب، شحن بري
 *    وبحري وجوى، نقل دولي).
 *
 * 2. booking is opened in DIRECT mode, so the price lands on the default item
 *    type instead of demanding a list of reservable units. That is the same
 *    rule BookingChildModesSeeder applies elsewhere; these children were simply
 *    never in its map, having had no booking config to classify.
 *
 * Idempotent. Nothing is deleted — a config that should not apply is set
 * is_active = 0 so the admin screens can still see and revive it.
 */
class ChildServiceScopeSeeder extends Seeder
{
    public function run(): void
    {
        $map = require database_path('seeders/data/child_service_scope.php');

        DB::transaction(function () use ($map) {
            $schedules = $this->applySchedules($map['schedules']);
            $booking = $this->openDirectBooking($map['booking_direct']);

            $this->command?->info('Child service scope:');
            $this->command?->line("  - إعدادات الجدولة : ضُبطت {$schedules['set']}، عُطّلت {$schedules['off']}");
            $this->command?->line("  - حجز مباشر : أُنشئ {$booking['created']}، حُدّث {$booking['updated']}");
            $this->command?->line('  - إعدادات نشطة بلا أي نوع عنصر : ' . $this->emptyActiveConfigs());
        });
    }

    /** @return array{set:int,off:int} */
    private function applySchedules(array $rows): array
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'schedules')->value('id');

        if (! $serviceId) {
            return ['set' => 0, 'off' => 0];
        }

        $set = $off = 0;

        foreach ($rows as $ref => $modes) {
            [$rootId, $childId] = $this->resolveRef($ref);

            if (! $rootId) {
                continue;
            }

            if ($modes === 'off') {
                $off += DB::table('category_service_configs')
                    ->where('category_id', $rootId)
                    ->where('child_id', $childId)
                    ->where('platform_service_id', $serviceId)
                    ->where('is_active', 1)
                    ->update(['is_active' => 0, 'updated_at' => now()]);

                continue;
            }

            $groups = DB::table('platform_service_item_groups')
                ->where('platform_service_id', $serviceId)
                ->whereIn('key', $modes)
                ->get(['id']);

            $types = DB::table('platform_service_item_group_type as gt')
                ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
                ->whereIn('gt.group_id', $groups->pluck('id'))
                ->where('t.is_active', 1)
                ->pluck('t.key')
                ->unique()
                ->values();

            if ($types->isEmpty()) {
                $this->command?->warn("  ! لا أنواع لـ {$ref} — تُرك.");

                continue;
            }

            $this->writeConfig($rootId, $childId, $serviceId, [
                'item_groups' => $groups->pluck('id')->all(),
                'allowed_item_types' => $types->all(),
            ]);

            $set++;
        }

        return ['set' => $set, 'off' => $off];
    }

    /** @return array{created:int,updated:int} */
    private function openDirectBooking(array $refs): array
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $defaultType = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->where('key', BusinessServicePrice::DEFAULT_ITEM_TYPE)
            ->value('key');

        if (! $serviceId || ! $defaultType) {
            $this->command?->warn('  ! نوع العنصر الافتراضي للحجز غير موجود — تُرك الحجز المباشر.');

            return ['created' => 0, 'updated' => 0];
        }

        $created = $updated = 0;

        foreach ($refs as $ref) {
            [$rootId, $childId] = $this->resolveRef($ref);

            if (! $rootId) {
                continue;
            }

            $existed = DB::table('category_service_configs')
                ->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)->exists();

            $this->writeConfig($rootId, $childId, $serviceId, [
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

            $existed ? $updated++ : $created++;
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /** Merge into the stored config so unrelated keys survive. */
    private function writeConfig(int $rootId, int $childId, int $serviceId, array $config): void
    {
        $row = DB::table('category_service_configs')
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->first(['id', 'config']);

        if ($row) {
            $stored = json_decode($row->config ?: '{}', true) ?: [];

            DB::table('category_service_configs')->where('id', $row->id)->update([
                'config' => json_encode(array_merge($stored, $config), JSON_UNESCAPED_UNICODE),
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

    /** @return array{0:?int,1:int} */
    private function resolveRef(string $ref): array
    {
        [$slug, $childId] = explode(':', $ref);

        $rootId = DB::table('categories')->where('slug', $slug)->value('id');

        if (! $rootId) {
            $this->command?->warn("  ! جذر «{$slug}» غير موجود.");
        }

        return [$rootId ? (int) $rootId : null, (int) $childId];
    }

    /** Active configs that still allow nothing — the shape of the original bug. */
    private function emptyActiveConfigs(): int
    {
        $active = DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('c.is_active', 1)
            ->where('s.is_active', 1)
            ->get(['c.config']);

        return $active->filter(function ($row) {
            $cfg = json_decode($row->config ?: '{}', true) ?: [];

            return empty($cfg['allowed_item_types']);
        })->count();
    }
}
