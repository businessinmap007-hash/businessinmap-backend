<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Applies the three-axis remodel to the last two sections carrying the disease:
 * root 11 «قاعات» (event types were the children) and root 12 «دورات و تدريب»
 * (teaching fields were children). See data/halls_training_taxonomy.php.
 *
 *   php artisan db:seed --class=HallsTrainingRemodelSeeder
 *
 * Same safety contract as the other remodel seeders: master rows survive
 * detachment, a child still carrying an account is never detached, accounts
 * keep their information (the event/field is written to `option_user` before
 * the move), and re-running reports zero changes.
 */
class HallsTrainingRemodelSeeder extends Seeder
{
    /** «أنماط خدمة وتجارية» — a new child without these has no commerce facets. */
    private const UNIVERSAL_OPTION_GROUP_ID = 12;

    public function run(): void
    {
        $data = require __DIR__ . '/data/halls_training_taxonomy.php';

        $this->applySection('قاعات', $data['halls'], 'event_group', 'events', 'skip_event_pool', 'child_to_event');
        $this->applySection('تدريب', $data['training'], 'field_group', 'fields', 'skip_field_pool', 'child_to_field');
    }

    private function applySection(
        string $label,
        array $section,
        string $groupKey,
        string $itemsKey,
        string $skipKey,
        string $mapKey
    ): void {
        DB::transaction(function () use ($label, $section, $groupKey, $itemsKey, $skipKey, $mapKey) {
            $rootId = (int) $section['root_id'];
            $groupId = $this->upsertGroup($section[$groupKey]);
            $optionIds = $this->upsertOptions($section[$itemsKey], $groupId);
            $childIds = $this->upsertChildren($section['children'], $rootId);
            $universal = $this->attachUniversalOptions($childIds);
            $this->attachPool($section, $childIds, $optionIds, $skipKey);
            $moved = $this->migrateBusinesses($section, $childIds, $optionIds, $rootId, $mapKey);
            $detached = $this->detachChildren($section, $childIds, $rootId);

            $this->command?->info("{$label} remodel applied:");
            $this->command?->line('  - options : ' . count($optionIds) . " (group #{$groupId})");
            $this->command?->line('  - business-type children : ' . count($childIds));
            $this->command?->line('  - universal commerce-option links added : ' . $universal);
            $this->command?->line('  - children detached : ' . $detached);
            $this->command?->line('  - accounts re-pointed : ' . count($moved));

            foreach ($moved as $row) {
                $this->command?->line("      #{$row['id']} {$row['name']} : {$row['from']} → {$row['to']}" . ($row['option'] ? " (+ {$row['option']})" : ''));
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
    private function upsertOptions(array $options, int $groupId): array
    {
        $ids = [];

        foreach ($options as $ar => $en) {
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
    private function upsertChildren(array $children, int $rootId): array
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
                ['parent_id' => $rootId, 'child_id' => $id],
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

    private function attachPool(array $section, array $childIds, array $optionIds, string $skipKey): void
    {
        $skip = $section[$skipKey] ?? [];

        foreach ($childIds as $name => $childId) {
            if (in_array($name, $skip, true)) {
                continue;
            }

            $order = 0;

            foreach ($optionIds as $optionId) {
                DB::table('category_child_option')->updateOrInsert(
                    ['child_id' => $childId, 'option_id' => $optionId],
                    ['reorder' => ++$order]
                );
            }
        }
    }

    /** @return array<int, array{id:int,name:string,from:string,to:string,option:?string}> */
    private function migrateBusinesses(array $section, array $childIds, array $optionIds, int $rootId, string $mapKey): array
    {
        $targetName = $section['business_migration_target'];
        $targetId = $childIds[$targetName] ?? null;

        if (! $targetId) {
            return [];
        }

        $moved = [];

        foreach ($section['detach_children'] as $childName) {
            $childId = DB::table('category_children_master')->where('name_ar', $childName)->value('id');

            if (! $childId) {
                continue;
            }

            $optionName = $section[$mapKey][$childName] ?? null;
            $optionId = $optionName ? ($optionIds[$optionName] ?? null) : null;

            $accounts = DB::table('users')
                ->where('category_child_id', $childId)
                ->where('category_id', $rootId)
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
                    'option' => $optionName,
                ];
            }
        }

        return $moved;
    }

    private function detachChildren(array $section, array $childIds, int $rootId): int
    {
        $keepIds = array_values($childIds);
        $detached = 0;

        foreach ($section['detach_children'] as $name) {
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
                ->where('parent_id', $rootId)
                ->where('child_id', $childId)
                ->delete();
        }

        return $detached;
    }
}
