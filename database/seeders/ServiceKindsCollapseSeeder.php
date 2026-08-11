<?php

namespace Database\Seeders;

use App\Models\BusinessServicePrice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
 * The old types are first DEACTIVATED, never deleted outright: the columns that
 * hold a type are free-text strings with no foreign key, several seeders still
 * name the old keys, and a deleted row would take the audit trail of any
 * historic price with it. Live rows are migrated onto the new keys here.
 *
 * A retired row that nothing references at all is then PRUNED — see prune(),
 * which re-checks every reference itself and refuses anything still in use.
 * Deactivating alone was not enough because the branch seeders that created
 * these types are still in DatabaseSeeder: BookingBranchesSeeder recreates 294
 * of them on any full seed. That is why this seeder now runs there too, right
 * after them — create, collapse, prune, and the database lands clean whichever
 * order it is built in.
 *
 * Idempotent, and it repairs: a re-run rewrites every config to the kinds the
 * map implies, so a hand-edit that reintroduced an old key is corrected. That
 * idempotence was not free — the first version read the kind from a config's
 * `item_groups` and then overwrote `item_groups`, so a second run had nothing
 * left to read and reset all 307 booking configs to the default. A kind already
 * stored is now honoured; see the fallback in collapse().
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

            $this->prune();
        });
    }

    /**
     * Free columns holding an item type as a plain string, with no foreign key
     * to declare the relationship.
     *
     * Listed by hand on purpose. Matching on column NAME across the schema is
     * what made the parallel options sweep refuse seven healthy rows: the
     * paused taxonomy-lab clone carries `*_new` tables whose ids belong to
     * their own clone, and a name match cannot tell the two apart. Add a column
     * here when one is introduced; the tests will not know about it otherwise.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const TYPE_KEY_COLUMNS = [
        ['business_service_prices', 'bookable_item_type'],
        ['bookable_items', 'item_type'],
        ['menu_items', 'item_type'],
    ];

    /**
     * Deletes the retired types and the branches they emptied — but only those
     * nothing can still reach.
     *
     * The collapse left 366 deactivated types under 20 branches with no live
     * type in them. Invisible in a picker, but not harmless: every admin branch
     * board lists those 20 as categories a merchant has simply not filled in
     * yet, and `platform_service_item_group_type` keeps 366 pivot rows alive
     * behind them.
     *
     * Three things are protected and will never be deleted:
     *
     *   1. `BusinessServicePrice::DEFAULT_ITEM_TYPE` — id 1, key `category`,
     *      which the collapse deactivated along with the rest. It is the string
     *      the price resolver falls back to when a price names no type at all,
     *      named in PHP rather than by a foreign key, so nothing in the
     *      database would have stopped the delete. It survives with no branch,
     *      which is correct: it is a sentinel, not something a merchant picks.
     *      (`is_default` is NOT a protection — it is an admin-panel flag with
     *      no runtime meaning, and eighteen retired rows still carried it.)
     *   2. Any key still stored in a price, a bookable unit or a menu item, or
     *      still offered by a config's allowed_item_types — the latter matched
     *      WITHIN ITS OWN SERVICE. `platform_service_item_types` is unique on
     *      (platform_service_id, key), so `frozen` is dead menu junk and at the
     *      same time the live «مجمدات» under retail; an unscoped match let four
     *      retail keys shield four dead menu rows.
     *   3. Any type a trip schedule points at (that FK is ON DELETE SET NULL,
     *      so the schedule would lose its vehicle silently rather than fail).
     *
     * A branch is DEACTIVATED, not deleted, once it holds no type at all and no
     * config names it in `item_groups`. Deleting was tried and is wrong: eight
     * pre-collapse seeders still resolve a branch by key and file types into it,
     * and LegacyOptionGapsSeeder died on a foreign key the moment «استشارات
     * وأعمال» went missing. The row is cheap; the crash is not. `is_active = 0`
     * takes it out of every picker, which was the whole point.
     *
     * (An empty branch a config still points at is left alone either way — that
     * is a merchant staring at an empty picker, a bug to fix, not junk.)
     */
    private function prune(): void
    {
        $usedKeys = collect();

        foreach (self::TYPE_KEY_COLUMNS as [$table, $column]) {
            if (Schema::hasTable($table)) {
                $usedKeys = $usedKeys->merge(
                    DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column)
                );
            }
        }

        $usedKeys = $usedKeys->map(fn ($k) => (string) $k)->unique();

        // Offered keys, kept per service — see point 2 in the docblock.
        $offered = [];

        foreach (DB::table('category_service_configs')->get(['platform_service_id', 'config']) as $row) {
            $config = json_decode((string) $row->config, true);
            $allowed = is_array($config) ? ($config['allowed_item_types'] ?? []) : [];

            if (! is_array($allowed)) {
                continue;
            }

            foreach ($allowed as $key) {
                $offered[(int) $row->platform_service_id][(string) $key] = true;
            }
        }

        $scheduled = Schema::hasTable('trip_schedules')
            ? DB::table('trip_schedules')->whereNotNull('vehicle_type_id')->pluck('vehicle_type_id')->map(fn ($i) => (int) $i)
            : collect();

        $doomed = DB::table('platform_service_item_types')
            ->where('is_active', 0)
            ->get(['id', 'platform_service_id', 'key'])
            ->reject(fn ($t) => (string) $t->key === BusinessServicePrice::DEFAULT_ITEM_TYPE
                || $usedKeys->contains((string) $t->key)
                || isset($offered[(int) $t->platform_service_id][(string) $t->key])
                || $scheduled->contains((int) $t->id));

        // CASCADE clears platform_service_item_group_type for us.
        $typesDropped = $doomed->isEmpty() ? 0
            : DB::table('platform_service_item_types')->whereIn('id', $doomed->pluck('id'))->delete();

        $named = collect();

        foreach (DB::table('category_service_configs')->pluck('config') as $json) {
            $config = json_decode((string) $json, true);
            $groups = is_array($config) ? ($config['item_groups'] ?? []) : [];

            if (is_array($groups)) {
                $named = $named->merge(array_map('intval', $groups));
            }
        }

        $emptyBranches = DB::table('platform_service_item_groups')
            ->where('is_active', 1)
            ->whereNotExists(fn ($q) => $q->from('platform_service_item_group_type')
                ->whereColumn('platform_service_item_group_type.group_id', 'platform_service_item_groups.id'))
            ->pluck('name_ar', 'id')
            ->reject(fn ($name, $id) => $named->contains((int) $id));

        $branchesDropped = $emptyBranches->isEmpty() ? 0
            : DB::table('platform_service_item_groups')->whereIn('id', $emptyBranches->keys())
                ->update(['is_active' => 0, 'updated_at' => now()]);

        $kept = DB::table('platform_service_item_types')->where('is_active', 0)->count();

        $this->command?->info('تنظيف:');
        $this->command?->line("  - أنواع متقاعدة حُذفت : {$typesDropped}  (بقيت محميّة: {$kept})");
        $this->command?->line("  - فروع فارغة أُخملت : {$branchesDropped}"
            . ($emptyBranches->isEmpty() ? '' : ' → ' . $emptyBranches->values()->implode('، ')));
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
        $approved = $this->kindsFromDataFile($spec);

        foreach (
            DB::table('category_service_configs')
                ->where('platform_service_id', $serviceId)
                ->get(['id', 'category_id', 'child_id', 'config']) as $row
        ) {
            $config = json_decode((string) $row->config, true) ?: [];

            $scope = (int) $row->category_id . ':' . (int) $row->child_id;

            $stored = collect($config['allowed_item_types'] ?? [])
                ->filter(fn ($t) => isset($spec['kinds'][$t]))
                ->unique()
                ->values();

            /*
             * An explicit per-child assignment wins outright and is not merged
             * with anything. Merging would union it with the root fallback, so
             * a clinic given كشف and متابعة would keep a bare «حجز موعد» beside
             * them — the branch and root sources answer «appointment» for every
             * one of these children, which is exactly what the owner replaced.
             */
            $explicit = $spec['children'][(int) $row->child_id] ?? null;

            if ($explicit) {
                $kinds = collect($explicit);
            } elseif (isset($this->fromRootFallback[$scope]) && $stored->isNotEmpty()) {
                /*
                 * The root fallback is «every child of this root that nobody
                 * named answers X» — a guess, and the weakest source here. It
                 * must not overwrite an answer already on the config.
                 *
                 * «طباعة» under شركات is the case: the file never names it, so
                 * the fallback said «حجز موعد» and a re-run would have taken
                 * back the استشارة and الاستشارة أونلاين it holds. Same shape as
                 * the menu default that put a food menu on a car showroom —
                 * an absence of knowledge, applied as though it were knowledge.
                 */
                $kinds = $stored;
            } else {
                $kinds = collect($approved[$scope] ?? [])
                    ->merge(
                        collect($config['item_groups'] ?? [])
                            ->map(fn ($id) => $spec['map'][$branchKeyOf[$id] ?? ''] ?? null)
                    )
                    ->merge(
                        collect($config['allowed_item_types'] ?? [])
                            ->map(fn ($t) => $spec['by_type'][$t] ?? null)
                    )
                    ->filter()
                    ->unique()
                    ->values();
            }

            if ($kinds->isEmpty()) {
                /*
                 * Nothing to translate — which on a SECOND run is the normal
                 * case, not an error: the first run replaced item_groups with
                 * the single new branch and allowed_item_types with kinds, so
                 * the inputs this reads from are gone. Falling straight through
                 * to the default here is what flattened all four booking kinds
                 * onto «حجز موعد» and all five menu surfaces onto «منيو».
                 *
                 * A kind already stored IS the answer; keep it.
                 */
                $kinds = $stored;
            }

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
     * child_id => kinds, resolved from the approved child→branch data file.
     *
     * The authoritative source, and the only one that survives a re-run: the
     * branch rows a config used to name are deleted by prune(), and the config's
     * own `item_groups` is overwritten with the new branch below. The file is
     * keyed exactly as the branch seeders key it — root SLUG, then child
     * name_ar — so the same child name under two roots stays two answers.
     *
     * …which is what this returned until 2026-08-11: it read a per-root file
     * and returned a per-CHILD array, so the two answers became one and the
     * last root read won. «حلويات» is named under المحلات as bakery_sweets and
     * not named under شركات at all, so a sweets trading company kept being
     * handed «منيو» — the shops' answer, on the wholesaler's config. Keyed
     * «rootId:childId» now, the way the docblock always said.
     *
     * @return array<string, list<string>> "rootId:childId" => kinds
     */
    private function kindsFromDataFile(array $spec): array
    {
        $file = __DIR__ . '/data/' . ($spec['child_branches'] ?? '');

        if (! isset($spec['child_branches']) || ! is_file($file)) {
            return [];
        }

        $kinds = [];

        foreach (require $file as $rootSlug => $children) {
            $rootId = (int) DB::table('categories')
                ->where('parent_id', 0)->where('slug', $rootSlug)->value('id');

            if (! $rootId) {
                continue;
            }

            foreach ($children as $childName => $branchKeys) {
                $mapped = collect($branchKeys)
                    ->map(fn ($key) => $spec['map'][$key] ?? null)
                    ->filter()
                    ->unique()
                    ->values();

                if ($mapped->isEmpty()) {
                    continue;
                }

                $childIds = DB::table('category_parent_child as pc')
                    ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
                    ->where('pc.parent_id', $rootId)
                    ->where('ch.name_ar', $childName)
                    ->pluck('ch.id');

                foreach ($childIds as $childId) {
                    $key = $rootId . ':' . (int) $childId;

                    $kinds[$key] = collect($kinds[$key] ?? [])
                        ->merge($mapped)->unique()->values()->all();
                }
            }
        }

        // Then the root fallback, for every child the file never named.
        foreach ($spec['roots'] ?? [] as $rootSlug => $kind) {
            $rootId = (int) DB::table('categories')
                ->where('parent_id', 0)->where('slug', $rootSlug)->value('id');

            if (! $rootId) {
                continue;
            }

            foreach (
                DB::table('category_parent_child')->where('parent_id', $rootId)->pluck('child_id') as $childId
            ) {
                $kinds[$rootId . ':' . (int) $childId] ??= [$kind];
                $this->fromRootFallback[$rootId . ':' . (int) $childId] = true;
            }
        }

        return $kinds;
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

    /**
     * "rootId:childId" scopes whose kind came from the ROOT fallback rather
     * than from the approved file — i.e. nobody named them, so the answer is a
     * guess and must yield to whatever the config already holds.
     *
     * @var array<string,bool>
     */
    private array $fromRootFallback = [];

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
