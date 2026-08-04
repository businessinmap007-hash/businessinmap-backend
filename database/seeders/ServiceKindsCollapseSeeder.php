<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Collapses booking's 294 item types and menu's 45 into kinds.
 *
 *   php artisan db:seed --class=ServiceKindsCollapseSeeder
 *
 * The item type was once the only place a merchant could say what he sold, so
 * it grew a full vocabulary: 294 rows for booking alone. That vocabulary now
 * lives in `offering_options` — «كشف عظام»، «غرفة نوم — مودرن»، «سيدان — BMW»،
 * «مشويات» — and what stayed in the types was a coarser second copy of it,
 * which is what made a customer narrow twice for one question.
 *
 * So the type stops saying WHAT and says only HOW: 4 kinds of booking and 5
 * selling surfaces. See data/service_kinds.php for the map and why retail is
 * excluded.
 *
 * The old types are DEACTIVATED, never deleted: `bookable_item_type` and
 * `bookable_items.item_type` are free-text strings, several seeders still name
 * the old keys, and a deleted row would take the audit trail of any historic
 * price with it. Live rows are migrated onto the new keys here.
 *
 * Idempotent, and it repairs: a re-run rewrites every config to the kinds the
 * map implies, so a hand-edit that reintroduced an old key is corrected.
 */
class ServiceKindsCollapseSeeder extends Seeder
{
    public function run(): void
    {
        $map = require __DIR__ . '/data/service_kinds.php';

        DB::transaction(function () use ($map) {
            foreach ($map as $serviceKey => $spec) {
                $this->collapse((string) $serviceKey, $spec);
            }
        });
    }

    private function collapse(string $serviceKey, array $spec): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', $serviceKey)->value('id');

        if (! $serviceId) {
            $this->command?->warn("  ! خدمة «{$serviceKey}» غير موجودة.");

            return;
        }

        $before = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)->where('is_active', 1)->count();

        $branchId = $this->branch($serviceId, $spec['branch']);
        $kindIds = [];

        foreach ($spec['kinds'] as $key => [$ar, $en]) {
            $kindIds[$key] = $this->kind($serviceId, (string) $key, $ar, $en, $branchId);
        }

        // branch id => old branch key, so a config's stored item_groups can be read
        $branchKeyOf = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->pluck('key', 'id');

        $rewritten = 0;

        foreach (
            DB::table('category_service_configs')
                ->where('platform_service_id', $serviceId)
                ->get(['id', 'config']) as $row
        ) {
            $config = json_decode((string) $row->config, true) ?: [];

            $kinds = collect($config['item_groups'] ?? [])
                ->map(fn ($id) => $spec['map'][$branchKeyOf[$id] ?? ''] ?? null)
                ->merge(
                    collect($config['allowed_item_types'] ?? [])
                        ->map(fn ($t) => $spec['by_type'][$t] ?? null)
                )
                ->filter()
                ->unique()
                ->values();

            if ($kinds->isEmpty()) {
                $kinds = collect([$spec['default']]);
            }

            $config['allowed_item_types'] = $kinds->all();
            $config['item_groups'] = [$branchId];

            DB::table('category_service_configs')->where('id', $row->id)->update([
                'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            $rewritten++;
        }

        // Old types go quiet, not away — see the class docblock.
        $retired = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->whereNotIn('key', array_keys($spec['kinds']))
            ->where('is_active', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        $moved = $this->migrateLiveRows($serviceId, $spec, $branchKeyOf);

        $this->command?->info("{$serviceKey}:");
        $this->command?->line("  - أنواع : {$before} ← " . count($spec['kinds']) . "  (أُخملت: {$retired})");
        $this->command?->line("  - إعدادات أُعيدت كتابتها : {$rewritten}");
        $this->command?->line("  - صفوف حيّة رُحّلت : {$moved}");

        if ($this->stranded !== []) {
            $this->command?->warn(
                '  ! أسعار بقيت على مفتاحها القديم (يلزمها خيار يميّزها): '
                . implode('، ', array_unique($this->stranded))
            );
        }
    }

    /**
     * Move the handful of live rows off the retired keys. A price or a bookable
     * unit whose type no longer resolves would vanish from the owner's screen
     * with no way to tell why.
     */
    private function migrateLiveRows(int $serviceId, array $spec, $branchKeyOf): int
    {
        $kindOfOldType = [];

        foreach (
            DB::table('platform_service_item_group_type as gt')
                ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
                ->where('t.platform_service_id', $serviceId)
                ->get(['t.key', 'gt.group_id']) as $row
        ) {
            $kind = $spec['by_type'][$row->key] ?? ($spec['map'][$branchKeyOf[$row->group_id] ?? ''] ?? null);

            if ($kind) {
                $kindOfOldType[$row->key] = $kind;
            }
        }

        $moved = 0;
        $this->stranded = [];

        /*
         * A hotel priced six room types. All six collapse onto «حجز فندق», and
         * `bsp_business_child_service_item_line_unique` allows one row per
         * (business, child, service, kind, LINE OPTION) — so they collide
         * unless the room kind moved into an option first, which for that
         * business it has not.
         *
         * Such a row keeps its retired key rather than being merged or dropped:
         * merging would silently destroy five prices, and a price is the one
         * thing a merchant notices missing. They are reported instead.
         */
        foreach (
            DB::table('business_service_prices')->where('service_id', $serviceId)
                ->whereNotIn('bookable_item_type', array_keys($spec['kinds']))
                ->get(['id', 'business_id', 'child_id', 'bookable_item_type', 'line_option_id']) as $row
        ) {
            $new = $kindOfOldType[(string) $row->bookable_item_type] ?? $spec['default'];

            $taken = DB::table('business_service_prices')
                ->where('business_id', $row->business_id)
                ->where('child_id', $row->child_id)
                ->where('service_id', $serviceId)
                ->where('bookable_item_type', $new)
                ->where('line_option_id', (int) $row->line_option_id)
                ->exists();

            if ($taken) {
                $this->stranded[] = (string) $row->bookable_item_type;
                continue;
            }

            DB::table('business_service_prices')->where('id', $row->id)
                ->update(['bookable_item_type' => $new, 'updated_at' => now()]);
            $moved++;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('bookable_items')) {
            foreach (
                DB::table('bookable_items')->where('service_id', $serviceId)
                    ->whereNotIn('item_type', array_keys($spec['kinds']))
                    ->get(['id', 'item_type']) as $row
            ) {
                // A bookable unit has no unique key on its type — «A1» and «B2»
                // are told apart by title, so they collapse cleanly.
                DB::table('bookable_items')->where('id', $row->id)->update([
                    'item_type' => $kindOfOldType[(string) $row->item_type] ?? $spec['default'],
                    'updated_at' => now(),
                ]);
                $moved++;
            }
        }

        return $moved;
    }

    /** @var array<int,string> old keys left in place because the kind was taken */
    private array $stranded = [];

    private function branch(int $serviceId, array $spec): int
    {
        // Matched per SERVICE as well as by key: platform_service_item_groups.key
        // is not globally unique, and a bare key match has silently reassigned a
        // live branch from one service to another before.
        $id = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->where('key', $spec['key'])
            ->value('id');

        if ($id) {
            DB::table('platform_service_item_groups')->where('id', $id)
                ->update(['is_active' => 1, 'sort_order' => 0, 'updated_at' => now()]);

            return (int) $id;
        }

        return (int) DB::table('platform_service_item_groups')->insertGetId([
            'platform_service_id' => $serviceId,
            'key' => $spec['key'],
            'name_ar' => $spec['name_ar'],
            'name_en' => $spec['name_en'],
            'sort_order' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function kind(int $serviceId, string $key, string $ar, string $en, int $branchId): int
    {
        $id = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->where('key', $key)
            ->value('id');

        if ($id) {
            DB::table('platform_service_item_types')->where('id', $id)
                ->update(['name_ar' => $ar, 'name_en' => $en, 'is_active' => 1, 'updated_at' => now()]);
        } else {
            $id = DB::table('platform_service_item_types')->insertGetId([
                'platform_service_id' => $serviceId,
                'key' => $key,
                'name_ar' => $ar,
                'name_en' => $en,
                'is_default' => 0,
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('platform_service_item_group_type')->insertOrIgnore([
            'group_id' => $branchId,
            'item_type_id' => (int) $id,
        ]);

        return (int) $id;
    }
}
