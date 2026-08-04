<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Breaks the «أنماط خدمة وتجارية» grab-bag apart and gives every child only the
 * option groups its trade can actually answer.
 *
 *   php artisan db:seed --class=ChildOptionGroupsSeeder
 *
 * That one group held 24 unrelated options and sat on 247 of the 253 children —
 * 7,634 of the platform's 10,328 option links. Because it mixed trade scope,
 * product condition, delivery, payment, returns and audience into a single
 * list, a نقاش was asked whether he does «تصدير» and «تسليم أرض المصنع», and a
 * hotel whether it is «بدون مبيدات». The filter screens inherited the noise.
 *
 * The 24 are re-filed into eight groups that each ask ONE question, and the map
 * in data/child_option_groups.php says which questions each child faces.
 *
 * Two things worth knowing about the shape of the data:
 *
 * 1. This map is written per CHILD, so it grants SHARED links
 *    (`category_child_option.category_id = 0`) — the option applies under every
 *    root the child sits beneath. A child row IS shared: «آثاث» is a workshop
 *    under ورش and a showroom under معارض, and a keyword-level rule genuinely
 *    has nothing to say about which of them is meant.
 *
 *    Per-root divergence is possible now (App\Services\CategoryChildOptionScope)
 *    but it is a hand edit, not something a bulk map should guess. Note this
 *    seeder removes by (child, option) regardless of root: an option it says a
 *    child must not carry is withdrawn from every root at once, by design.
 *
 * 2. A link is never removed if a business under that child has already chosen
 *    it (`option_user`). Merchant answers outrank this map.
 *
 * Only the eight managed groups are touched, plus the four explicit domain
 * corrections. Every other group is left exactly as it is.
 */
class ChildOptionGroupsSeeder extends Seeder
{
    /** @var array<string,int> group key => option_groups.id */
    private array $groupIds = [];

    public function run(): void
    {
        $map = require database_path('seeders/data/child_option_groups.php');

        DB::transaction(function () use ($map) {
            $this->ensureGroups($map['groups']);
            $moved = $this->refileOptions($map['groups']);
            $retired = $this->retireOptions($map['retire_options']);

            [$added, $removed, $kept] = $this->applyChildTargets($map);

            $domain = $this->applyDomainCorrections($map);

            $this->report($moved, $retired, $added, $removed, $kept, $domain);
        });
    }

    /** Create the eight groups if they are not already there. */
    private function ensureGroups(array $groups): void
    {
        foreach ($groups as $key => $g) {
            $id = DB::table('option_groups')->where('name_ar', $g['name_ar'])->value('id');

            if (! $id) {
                $id = DB::table('option_groups')->insertGetId([
                    'name_ar' => $g['name_ar'],
                    'name_en' => $g['name_en'],
                    'reorder' => $g['reorder'],
                    'is_active' => 1,
                ]);
            }

            $this->groupIds[$key] = (int) $id;
        }
    }

    /** Move the existing option rows out of the grab-bag into their new group. */
    private function refileOptions(array $groups): int
    {
        $moved = 0;

        foreach ($groups as $key => $g) {
            $moved += DB::table('options')
                ->whereIn('id', $g['options'])
                ->where(fn ($q) => $q->where('group_id', '!=', $this->groupIds[$key])->orWhereNull('group_id'))
                ->update(['group_id' => $this->groupIds[$key]]);
        }

        return $moved;
    }

    /** Options that describe every business describe none; unlink and unfile. */
    private function retireOptions(array $optionIds): int
    {
        $inUse = DB::table('option_user')->whereIn('option_id', $optionIds)->pluck('option_id')->unique();

        $safe = array_values(array_diff($optionIds, $inUse->all()));

        foreach ($inUse as $id) {
            $this->command?->warn("  ! الخيار #{$id} اختاره تاجر بالفعل — لم يُتقاعد.");
        }

        if (! $safe) {
            return 0;
        }

        $removed = DB::table('category_child_option')->whereIn('option_id', $safe)->delete();
        DB::table('options')->whereIn('id', $safe)->update(['group_id' => null]);

        return $removed;
    }

    /**
     * @return array{0:int,1:int,2:int} [links added, links removed, links kept because a merchant chose them]
     */
    private function applyChildTargets(array $map): array
    {
        $optionsOf = [];
        foreach ($map['groups'] as $key => $g) {
            $optionsOf[$key] = $g['options'];
        }

        $managed = array_unique(array_merge(...array_values($optionsOf)));

        $targets = $this->resolveTargets($map);

        $added = $removed = $kept = 0;

        foreach ($targets as $childId => $groupKeys) {
            $desired = [];
            foreach ($groupKeys as $key) {
                $desired = array_merge($desired, $optionsOf[$key]);
            }
            $desired = array_unique($desired);

            $existing = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', $managed)
                ->pluck('option_id')
                ->all();

            $toAdd = array_diff($desired, $existing);
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

            foreach (array_chunk(array_values($toAdd), 200) as $chunk) {
                DB::table('category_child_option')->insert(
                    array_map(fn ($o) => ['child_id' => $childId, 'option_id' => $o], $chunk)
                );
            }

            $added += count($toAdd);

            if ($toDrop) {
                $removed += DB::table('category_child_option')
                    ->where('child_id', $childId)
                    ->whereIn('option_id', $toDrop)
                    ->delete();
            }
        }

        return [$added, $removed, $kept];
    }

    /**
     * Fold every root a child sits under into one set of group keys.
     *
     * @return array<int,string[]>
     */
    private function resolveTargets(array $map): array
    {
        $rows = DB::table('category_parent_child as pc')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->get(['r.slug', 'pc.child_id']);

        $targets = [];

        foreach ($rows as $row) {
            $slug = $row->slug;
            $childId = (int) $row->child_id;

            if (! isset($map['root_defaults'][$slug])) {
                continue; // a root outside the taxonomy this map covers
            }

            $keys = $map['child_overrides']["{$slug}:{$childId}"] ?? $map['root_defaults'][$slug];

            if (in_array($childId, $map['produce_children'][$slug] ?? [], true)) {
                $keys[] = 'produce_quality';
            }

            if (in_array($childId, $map['condition_children'][$slug] ?? [], true)) {
                $keys[] = 'product_condition';
            }

            $targets[$childId] = array_values(array_unique(array_merge($targets[$childId] ?? [], $keys)));
        }

        return $targets;
    }

    /**
     * The four links that were wrong regardless of the grab-bag: furniture
     * carrying vehicle and property options, a car showroom carrying property
     * options, and a car child with no options at all.
     *
     * @return array{added:int,removed:int}
     */
    private function applyDomainCorrections(array $map): array
    {
        $added = $removed = 0;

        foreach ($map['domain_strips'] as $childId => $groupNames) {
            $optionIds = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereIn('g.name_ar', $groupNames)
                ->pluck('o.id');

            $chosen = DB::table('option_user as ou')
                ->join('users as u', 'u.id', '=', 'ou.user_id')
                ->where('u.category_child_id', $childId)
                ->whereIn('ou.option_id', $optionIds)
                ->pluck('ou.option_id');

            $removed += DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', $optionIds)
                ->whereNotIn('option_id', $chosen->isEmpty() ? [0] : $chosen->all())
                ->delete();
        }

        foreach ($map['domain_adds'] as $childId => $groupNames) {
            $optionIds = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereIn('g.name_ar', $groupNames)
                ->pluck('o.id');

            $existing = DB::table('category_child_option')->where('child_id', $childId)
                ->whereIn('option_id', $optionIds)->pluck('option_id')->all();

            $rows = [];
            foreach ($optionIds->diff($existing) as $optionId) {
                $rows[] = ['child_id' => $childId, 'option_id' => $optionId];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('category_child_option')->insert($chunk);
            }

            $added += count($rows);
        }

        return ['added' => $added, 'removed' => $removed];
    }

    private function report(int $moved, int $retired, int $added, int $removed, int $kept, array $domain): void
    {
        $total = DB::table('category_child_option')->count();

        $leftovers = DB::table('options')
            ->where('group_id', function ($q) {
                $q->from('option_groups')->select('id')->where('name_ar', 'أنماط خدمة وتجارية');
            })->count();

        $this->command?->info('Child option groups:');
        $this->command?->line("  - خيارات أُعيد تصنيفها : {$moved}");
        $this->command?->line("  - خيارات متقاعدة (روابط أُزيلت) : {$retired}");
        $this->command?->line("  - روابط أُضيفت : {$added}");
        $this->command?->line("  - روابط أُزيلت : {$removed}");
        $this->command?->line("  - روابط أُبقيت لأن تاجرًا اختارها : {$kept}");
        $this->command?->line("  - تصحيحات مجموعات النطاق : +{$domain['added']} / -{$domain['removed']}");
        $this->command?->line("  - ما تبقّى في «أنماط خدمة وتجارية» : {$leftovers}");
        $this->command?->line("  - إجمالي روابط الخيارات الآن : {$total}");
    }
}
