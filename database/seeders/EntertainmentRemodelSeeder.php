<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Cleans up root 9 «فنون و ترفية» — the inverse of the Health case. Most of the
 * children here were already right (a bowling alley IS a business type); the
 * damage was in the item-type axis, which carried venues (اكوا بارك، صالة
 * ألعاب، منطقه للأطفال) instead of things you can put a price on. See
 * data/entertainment_taxonomy.php.
 *
 *   php artisan db:seed --class=EntertainmentRemodelSeeder
 *
 * Same safety contract as the other remodel seeders: nothing is deleted —
 * venue item types are DEACTIVATED and unlinked from their branch rather than
 * dropped, child master rows survive detachment, and a child still carrying an
 * account is never detached. Re-running reports zero changes.
 */
class EntertainmentRemodelSeeder extends Seeder
{
    private const ENTERTAINMENT_ROOT_ID = 9;

    /** «أنماط خدمة وتجارية» — a new child without these has no commerce facets. */
    private const UNIVERSAL_OPTION_GROUP_ID = 12;

    public function run(): void
    {
        $data = require __DIR__ . '/data/entertainment_taxonomy.php';

        DB::transaction(function () use ($data) {
            $childIds = $this->upsertChildren($data['children']);
            $universal = $this->attachUniversalOptions($childIds);
            $retired = $this->retireVenueItemTypes($data);
            $priced = $this->addItemTypes($data['branch_key'], $data['priced_item_types']);
            $trips = $this->addItemTypes($data['trip_branch_key'], $data['trip_item_types']);
            $moved = $this->migrateBusinesses($data, $childIds);
            $detached = $this->detachTripChildren($data, $childIds);

            $this->command?->info('Entertainment remodel applied:');
            $this->command?->line('  - business-type children : ' . count($childIds));
            $this->command?->line('  - universal commerce-option links added : ' . $universal);
            $this->command?->line('  - venue item types retired : ' . $retired);
            $this->command?->line('  - priced item types in the entertainment branch : ' . $priced);
            $this->command?->line('  - trip item types in the tourism branch : ' . $trips);
            $this->command?->line('  - trip children detached : ' . $detached);
            $this->command?->line('  - accounts re-pointed : ' . count($moved));

            foreach ($moved as $row) {
                $this->command?->line("      #{$row['id']} {$row['name']} : {$row['from']} → {$row['to']}");
            }
        });
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
                ['parent_id' => self::ENTERTAINMENT_ROOT_ID, 'child_id' => $id],
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
     * A venue is not a priceable thing. Unlink these from the branch and mark
     * them inactive — never delete, so any config or listing that still names
     * one stays readable while it drops out of the pickers.
     */
    private function retireVenueItemTypes(array $data): int
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');
        $groupId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->where('key', $data['branch_key'])
            ->value('id');

        if (! $serviceId || ! $groupId) {
            return 0;
        }

        $retired = 0;

        foreach ($data['venue_item_types'] as $key) {
            $typeId = DB::table('platform_service_item_types')
                ->where('platform_service_id', $serviceId)
                ->where('key', $key)
                ->value('id');

            if (! $typeId) {
                continue;
            }

            DB::table('platform_service_item_group_type')
                ->where('group_id', $groupId)
                ->where('item_type_id', (int) $typeId)
                ->delete();

            DB::table('platform_service_item_types')
                ->where('id', (int) $typeId)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            // A retired key must not keep being offered to merchants.
            $this->stripKeyFromConfigs($serviceId, $key);

            $retired++;
        }

        return $retired;
    }

    /**
     * Drop a retired key out of every `config.allowed_item_types` for the
     * service, so no merchant is still offered something that no longer exists.
     */
    private function stripKeyFromConfigs(int $serviceId, string $key): void
    {
        $rows = DB::table('category_service_configs')
            ->where('platform_service_id', $serviceId)
            ->get(['id', 'config']);

        foreach ($rows as $row) {
            $config = json_decode((string) $row->config, true);

            if (! is_array($config) || ! isset($config['allowed_item_types']) || ! is_array($config['allowed_item_types'])) {
                continue;
            }

            if (! in_array($key, $config['allowed_item_types'], true)) {
                continue;
            }

            $config['allowed_item_types'] = array_values(array_filter(
                $config['allowed_item_types'],
                fn ($k) => $k !== $key
            ));

            DB::table('category_service_configs')
                ->where('id', $row->id)
                ->update(['config' => json_encode($config, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
    }

    /** Create item types and file them into a branch, reusing any that exist. */
    private function addItemTypes(string $branchKey, array $types): int
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');
        $groupId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->where('key', $branchKey)
            ->value('id');

        if (! $serviceId || ! $groupId) {
            $this->command?->warn("  ! branch «{$branchKey}» not found — skipped.");

            return 0;
        }

        $sort = (int) DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->max('sort_order');

        foreach ($types as $key => [$ar, $en]) {
            $typeId = DB::table('platform_service_item_types')
                ->where('platform_service_id', $serviceId)
                ->where('key', $key)
                ->value('id');

            if (! $typeId) {
                $typeId = DB::table('platform_service_item_types')->insertGetId([
                    'platform_service_id' => $serviceId,
                    'key' => $key,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'is_active' => 1,
                    'sort_order' => ++$sort,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('platform_service_item_group_type')->updateOrInsert(
                ['group_id' => $groupId, 'item_type_id' => (int) $typeId],
                []
            );
        }

        return count($types);
    }

    /** @return array<int, array{id:int,name:string,from:string,to:string}> */
    private function migrateBusinesses(array $data, array $childIds): array
    {
        $targetName = $data['business_migration_target'];
        $targetId = $childIds[$targetName] ?? null;

        if (! $targetId) {
            return [];
        }

        $moved = [];

        foreach ($data['detach_children'] as $name) {
            $childId = DB::table('category_children_master')->where('name_ar', $name)->value('id');

            if (! $childId) {
                continue;
            }

            $accounts = DB::table('users')
                ->where('category_child_id', $childId)
                ->where('category_id', self::ENTERTAINMENT_ROOT_ID)
                ->where('type', 'business')
                ->get(['id', 'name']);

            foreach ($accounts as $account) {
                DB::table('users')->where('id', $account->id)->update([
                    'category_child_id' => $targetId,
                ]);

                $moved[] = [
                    'id' => (int) $account->id,
                    'name' => (string) $account->name,
                    'from' => $name,
                    'to' => $targetName,
                ];
            }
        }

        return $moved;
    }

    private function detachTripChildren(array $data, array $childIds): int
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
                ->where('parent_id', self::ENTERTAINMENT_ROOT_ID)
                ->where('child_id', $childId)
                ->delete();
        }

        return $detached;
    }
}
