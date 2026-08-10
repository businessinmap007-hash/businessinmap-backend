<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionWithdrawals;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Splits «مركبات ونقل» into car marques, motorcycle marques and transport
 * bodies, and gives each child only the list its trade reads.
 *
 *   php artisan db:seed --class=VehicleOptionGroupsSeeder
 *
 * Same disease as the commerce grab-bag, one axis over: 68 options of three
 * different kinds in one group, all 68 on all 20 children that carry it. A
 * motorcycle showroom was asked about Bentley; a parking garage about Maserati.
 *
 * The map lives in data/vehicle_option_groups.php. Only options inside the old
 * group are touched, and a link a merchant has already chosen is never removed.
 * Retired rows keep their master row and simply lose their group and links, the
 * same retirement the rest of the taxonomy uses.
 */
class VehicleOptionGroupsSeeder extends Seeder
{
    private const OLD_GROUP = 'مركبات ونقل';

    private const SERVICE_MODE_GROUP = 'نمط تقديم الخدمة';

    /** @var array<string,int> */
    private array $groupIds = [];

    public function run(): void
    {
        $map = require database_path('seeders/data/vehicle_option_groups.php');

        DB::transaction(function () use ($map) {
            $oldIds = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', self::OLD_GROUP)
                ->pluck('o.id');

            $this->ensureGroups($map['groups']);
            $twins = $this->ensureMotorcycleTwins($map['motorcycle_twins']);

            $filed = $this->fileOptions($map['groups'], $twins);
            $moved = $this->moveToServiceMode($map['move_to_service_mode']);
            $retired = $this->retire($map['retire']);

            [$added, $removed, $kept] = $this->applyChildren($map, $twins);

            $leftovers = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', self::OLD_GROUP)
                ->count();

            $this->command?->info('Vehicle option groups:');
            $this->command?->line("  - خيارات صُنّفت في المجموعات الثلاث : {$filed}");
            $this->command?->line("  - توأم الموتوسيكلات (هوندا/سوزوكي) : {$twins->count()}");
            $this->command?->line("  - نُقلت إلى «نمط تقديم الخدمة» : {$moved}");
            $this->command?->line("  - متقاعدة (روابط أُزيلت) : {$retired}");
            $this->command?->line("  - روابط أُضيفت : {$added} / أُزيلت : {$removed} / أُبقيت لاختيار تاجر : {$kept}");
            $this->command?->line("  - ما تبقّى في «{$this->maskedOldGroup()}» : {$leftovers}");
        });
    }

    private function maskedOldGroup(): string
    {
        return self::OLD_GROUP;
    }

    private function ensureGroups(array $groups): void
    {
        foreach ($groups as $key => $group) {
            $id = DB::table('option_groups')->where('name_ar', $group['name_ar'])->value('id');

            if (! $id) {
                $id = DB::table('option_groups')->insertGetId([
                    'name_ar' => $group['name_ar'],
                    'name_en' => $group['name_en'],
                    'reorder' => $group['reorder'],
                    'is_active' => 1,
                ]);
            }

            $this->groupIds[$key] = (int) $id;
        }
    }

    /** @return \Illuminate\Support\Collection<int,int> */
    private function ensureMotorcycleTwins(array $twins)
    {
        $ids = collect();

        foreach ($twins as $twin) {
            $id = DB::table('options')->where('name_en', $twin['name_en'])->value('id');

            if (! $id) {
                $id = DB::table('options')->insertGetId([
                    'group_id' => $this->groupIds['motorcycle_brands'],
                    'name_ar' => $twin['name_ar'],
                    'name_en' => $twin['name_en'],
                ]);
            }

            $ids->push((int) $id);
        }

        return $ids;
    }

    private function fileOptions(array $groups, $twins): int
    {
        $filed = 0;

        foreach ($groups as $key => $group) {
            $ids = $group['options'];

            if ($key === 'motorcycle_brands') {
                $ids = array_merge($ids, $twins->all());
            }

            $filed += DB::table('options')
                ->whereIn('id', $ids)
                ->where(fn ($q) => $q->where('group_id', '!=', $this->groupIds[$key])->orWhereNull('group_id'))
                ->update(['group_id' => $this->groupIds[$key]]);
        }

        return $filed;
    }

    /** With-driver / without-driver is a service mode, not a vehicle. */
    private function moveToServiceMode(array $optionIds): int
    {
        $groupId = DB::table('option_groups')->where('name_ar', self::SERVICE_MODE_GROUP)->value('id');

        if (! $groupId) {
            $this->command?->warn('  ! مجموعة «' . self::SERVICE_MODE_GROUP . '» غير موجودة — تُركت خيارات السائق.');

            return 0;
        }

        return DB::table('options')
            ->whereIn('id', $optionIds)
            ->where('group_id', '!=', $groupId)
            ->update(['group_id' => (int) $groupId]);
    }

    private function retire(array $optionIds): int
    {
        $inUse = DB::table('option_user')->whereIn('option_id', $optionIds)->pluck('option_id')->unique();

        foreach ($inUse as $id) {
            $this->command?->warn("  ! الخيار #{$id} اختاره تاجر — لم يُتقاعد.");
        }

        $safe = array_values(array_diff($optionIds, $inUse->all()));

        if (! $safe) {
            return 0;
        }

        $removed = DB::table('category_child_option')->whereIn('option_id', $safe)->delete();
        DB::table('options')->whereIn('id', $safe)->update(['group_id' => null]);

        return $removed;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function applyChildren(array $map, $twins): array
    {
        $optionsOf = [];

        foreach ($map['groups'] as $key => $group) {
            $optionsOf[$key] = $key === 'motorcycle_brands'
                ? array_merge($group['options'], $twins->all())
                : $group['options'];
        }

        // Everything this seeder owns, derived from the MAP rather than from the
        // old group — after the first run that group is empty, and reading it
        // would make every later run a no-op that silently skips removals.
        $managed = collect($map['groups'])
            ->flatMap(fn ($g) => $g['options'])
            ->merge($twins)
            ->merge($map['move_to_service_mode'])
            ->unique();

        $targets = [];

        foreach ($map['children'] as $key => $childIds) {
            foreach ($childIds as $childId) {
                $targets[$childId] = array_unique(array_merge($targets[$childId] ?? [], $optionsOf[$key]));
            }
        }

        // A child that can only answer part of a list says so in one place; this
        // seeder assigns groups wholesale and must not undo that narrowing —
        // a car wash takes a van, not a trailer.
        $targets = $this->applyScopes($targets, $map['groups']);

        // with-driver / without-driver moved groups but stayed on all 20 vehicle
        // children, which would ask a car wash whether it comes with a driver
        foreach ($map['driver_children'] as $childId) {
            $targets[$childId] = array_unique(array_merge($targets[$childId] ?? [], $map['move_to_service_mode']));
        }

        $added = $removed = $kept = 0;
        $withdrawn = app(ChildOptionWithdrawals::class)->blockedByChild();

        foreach ($this->childrenHolding($managed) as $childId) {
            $desired = $targets[$childId] ?? [];

            $existing = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', $managed)
                ->pluck('option_id')
                ->all();

            $toDrop = array_diff($existing, $desired);

            if ($toDrop) {
                $chosen = DB::table('option_user as ou')
                    ->join('users as u', 'u.id', '=', 'ou.user_id')
                    ->where('u.category_child_id', $childId)
                    ->whereIn('ou.option_id', $toDrop)
                    ->pluck('ou.option_id')
                    ->unique()
                    ->all();

                $kept += count($chosen);
                $toDrop = array_diff($toDrop, $chosen);
            }

            if ($toDrop) {
                $removed += DB::table('category_child_option')
                    ->where('child_id', $childId)
                    ->whereIn('option_id', $toDrop)
                    ->delete();
            }
        }

        // additions run over the map itself, so a child that never carried the
        // old group (a courier that should list its vehicle bodies) still gets one
        foreach ($targets as $childId => $desired) {
            $existing = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', $desired)
                ->pluck('option_id')
                ->all();

            $toAdd = array_values(array_diff($desired, $existing, array_keys($withdrawn[$childId] ?? [])));

            foreach (array_chunk($toAdd, 200) as $chunk) {
                DB::table('category_child_option')->insert(
                    array_map(fn ($id) => ['child_id' => $childId, 'option_id' => $id], $chunk)
                );
            }

            $added += count($toAdd);
        }

        return [$added, $removed, $kept];
    }


    /**
     * Intersect each child's target list with its declared slice.
     *
     * @param  array<int,int[]>  $targets
     * @return array<int,int[]>
     */
    private function applyScopes(array $targets, array $groups): array
    {
        $scopes = require database_path('seeders/data/child_option_scopes.php');

        foreach ($groups as $group) {
            $slice = $scopes[$group['name_ar']] ?? null;

            if (! $slice) {
                continue;
            }

            foreach ($slice as $childId => $allowed) {
                if (! isset($targets[$childId])) {
                    continue;
                }

                $outside = array_diff($group['options'], $allowed);
                $targets[$childId] = array_values(array_diff($targets[$childId], $outside));
            }
        }

        return $targets;
    }

    /** @return array<int,int> */
    private function childrenHolding($optionIds): array
    {
        return DB::table('category_child_option')
            ->whereIn('option_id', $optionIds)
            ->distinct()
            ->pluck('child_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
