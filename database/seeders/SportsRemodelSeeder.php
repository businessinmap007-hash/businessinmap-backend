<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Applies the Health three-axis remodel to root 7 «الرياضة»: the eleven sports
 * that were masquerading as category children become multi-select options, so a
 * club can carry squash AND swimming AND padel instead of registering under
 * exactly one of them. See data/sports_taxonomy.php for the reasoning.
 *
 *   php artisan db:seed --class=SportsRemodelSeeder
 *
 * Same safety contract as the Health and Real-Estate seeders: master rows are
 * never deleted (only the root-7 pivot link is dropped, so the move reverses by
 * re-inserting it), a child still carrying an account is never detached, and an
 * account is only moved after its sport has been written to `option_user`.
 * Re-running reports zero detached / zero moved.
 */
class SportsRemodelSeeder extends Seeder
{
    private const SPORTS_ROOT_ID = 7;

    /** «أنماط خدمة وتجارية» — a new child without these has no commerce facets. */
    private const UNIVERSAL_OPTION_GROUP_ID = 12;

    public function run(): void
    {
        $data = require __DIR__ . '/data/sports_taxonomy.php';

        DB::transaction(function () use ($data) {
            $groupId = $this->upsertGroup($data['activity_group']);
            $optionIds = $this->upsertActivities($data['activities'], $groupId);
            $childIds = $this->upsertChildren($data['children']);
            $universal = $this->attachUniversalOptions($childIds);
            $this->attachActivityPool($data, $childIds, $optionIds);
            $moved = $this->migrateBusinesses($data, $childIds, $optionIds);
            $detached = $this->detachSportChildren($data, $childIds);

            $this->command?->info('Sports remodel applied:');
            $this->command?->line('  - activity options : ' . count($optionIds) . " (group #{$groupId})");
            $this->command?->line('  - business-type children : ' . count($childIds));
            $this->command?->line('  - universal commerce-option links added : ' . $universal);
            $this->command?->line('  - sports detached from the root : ' . $detached);
            $this->command?->line('  - accounts re-pointed : ' . count($moved));

            foreach ($moved as $row) {
                $this->command?->line("      #{$row['id']} {$row['name']} : {$row['from']} → {$row['to']} (+ نشاط: {$row['activity']})");
            }
        });
    }

    private function upsertGroup(array $group): int
    {
        $existing = DB::table('option_groups')->where('name_ar', $group['name_ar'])->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => $group['name_ar'],
            'name_en' => $group['name_en'],
            'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
            'is_active' => 1,
        ]);
    }

    /** @return array<string, int> name_ar => option id */
    private function upsertActivities(array $activities, int $groupId): array
    {
        $ids = [];

        foreach ($activities as $ar => $en) {
            $id = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if (! $id) {
                $id = DB::table('options')->insertGetId([
                    'group_id' => $groupId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                ]);
            }

            $ids[$ar] = (int) $id;
        }

        return $ids;
    }

    /** @return array<string, int> name_ar => child id */
    private function upsertChildren(array $children): array
    {
        $ids = [];

        foreach ($children as $child) {
            $id = DB::table('category_children_master')->where('name_ar', $child['name_ar'])->value('id');

            if (! $id) {
                $id = DB::table('category_children_master')->insertGetId([
                    'name_ar' => $child['name_ar'],
                    'name_en' => $child['name_en'],
                    'reorder' => 1 + (int) DB::table('category_children_master')->max('reorder'),
                ]);
            }

            $id = (int) $id;
            $ids[$child['name_ar']] = $id;

            DB::table('category_parent_child')->updateOrInsert(
                ['parent_id' => self::SPORTS_ROOT_ID, 'child_id' => $id],
                ['updated_at' => now()]
            );
        }

        return $ids;
    }

    private function attachUniversalOptions(array $childIds): int
    {
        $universalIds = DB::table('options')
            ->where('group_id', self::UNIVERSAL_OPTION_GROUP_ID)
            ->pluck('id');

        $added = 0;

        foreach ($childIds as $childId) {
            foreach ($universalIds as $optionId) {
                $exists = DB::table('category_child_option')
                    ->where('child_id', $childId)
                    ->where('option_id', $optionId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('category_child_option')->insert([
                    'child_id' => $childId,
                    'option_id' => $optionId,
                    'reorder' => 0,
                ]);

                $added++;
            }
        }

        return $added;
    }

    /**
     * The pool a venue picks its sports FROM at signup.
     *
     * A venue that can only host part of the pool gets only that part
     * (`child_activity_pools`); the rest is withdrawn, because handing all 45 to
     * every child had a gym declaring water polo. Withdrawal stops at anything a
     * merchant has already ticked — their answer outranks the map.
     */
    private function attachActivityPool(array $data, array $childIds, array $optionIds): void
    {
        $skip = $data['skip_activity_pool'] ?? [];
        $pools = $data['child_activity_pools'] ?? [];

        // option id => activity name, so a pool can be written in Arabic names
        $names = DB::table('options')->whereIn('id', $optionIds)->pluck('name_ar', 'id');

        foreach ($childIds as $name => $childId) {
            if (in_array($name, $skip, true)) {
                continue;
            }

            $wanted = isset($pools[$name])
                ? $names->filter(fn ($activity) => in_array($activity, $pools[$name], true))->keys()->all()
                : $optionIds;

            $order = 0;

            foreach ($wanted as $optionId) {
                DB::table('category_child_option')->updateOrInsert(
                    ['child_id' => $childId, 'option_id' => $optionId],
                    ['reorder' => ++$order]
                );
            }

            $this->withdrawUnusedActivities($childId, array_diff($optionIds, $wanted));
        }
    }

    /** Remove pool entries this venue cannot host, unless a merchant chose one. */
    private function withdrawUnusedActivities(int $childId, array $optionIds): void
    {
        if (! $optionIds) {
            return;
        }

        $chosen = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('u.category_child_id', $childId)
            ->whereIn('ou.option_id', $optionIds)
            ->pluck('ou.option_id')
            ->unique()
            ->all();

        DB::table('category_child_option')
            ->where('child_id', $childId)
            ->whereIn('option_id', array_diff($optionIds, $chosen))
            ->delete();
    }

    /**
     * Move accounts off the sport children, writing the sport into `option_user`
     * first so the account keeps what it was registered for.
     *
     * @return array<int, array{id:int,name:string,from:string,to:string,activity:string}>
     */
    private function migrateBusinesses(array $data, array $childIds, array $optionIds): array
    {
        $targetName = $data['business_migration_target'];
        $targetId = $childIds[$targetName] ?? null;

        if (! $targetId) {
            return [];
        }

        $moved = [];

        foreach ($data['detach_children'] as $childName) {
            $childId = DB::table('category_children_master')->where('name_ar', $childName)->value('id');

            if (! $childId) {
                continue;
            }

            $activity = $data['child_to_activity'][$childName] ?? null;
            $optionId = $activity ? ($optionIds[$activity] ?? null) : null;

            $accounts = DB::table('users')
                ->where('category_child_id', $childId)
                ->where('category_id', self::SPORTS_ROOT_ID)
                ->where('type', 'business')
                ->get(['id', 'name']);

            foreach ($accounts as $account) {
                if ($optionId) {
                    DB::table('option_user')->updateOrInsert(
                        ['user_id' => (int) $account->id, 'option_id' => $optionId],
                        []
                    );
                }

                DB::table('users')->where('id', $account->id)->update([
                    'category_child_id' => $targetId,
                ]);

                $moved[] = [
                    'id' => (int) $account->id,
                    'name' => (string) $account->name,
                    'from' => $childName,
                    'to' => $targetName,
                    'activity' => (string) ($activity ?? '—'),
                ];
            }
        }

        return $moved;
    }

    /** Drop the root-7 link only; the master row and other roots survive. */
    private function detachSportChildren(array $data, array $childIds): int
    {
        $keepIds = array_values($childIds);
        $detached = 0;

        foreach ($data['detach_children'] as $name) {
            $childId = DB::table('category_children_master')->where('name_ar', $name)->value('id');

            if (! $childId || in_array((int) $childId, $keepIds, true)) {
                continue;
            }

            $stillUsed = DB::table('users')
                ->where('category_child_id', $childId)
                ->where('type', 'business')
                ->exists();

            if ($stillUsed) {
                $this->command?->warn("  ! تُرك «{$name}» مرتبطًا — ما زال عليه نشاط.");
                continue;
            }

            $detached += DB::table('category_parent_child')
                ->where('parent_id', self::SPORTS_ROOT_ID)
                ->where('child_id', $childId)
                ->delete();
        }

        return $detached;
    }
}
