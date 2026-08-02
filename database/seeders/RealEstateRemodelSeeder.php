<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Applies the three-axis remodel to root 18 «عقارات و أراضي» — the same move
 * HealthRemodelSeeder made, except property types land on the ITEM TYPE axis
 * rather than the option axis, because a listed property is priced. See
 * data/real_estate_taxonomy.php for the reasoning.
 *
 *   php artisan db:seed --class=RealEstateRemodelSeeder
 *
 * Same safety contract as the Health seeder: master rows are never deleted,
 * only the root-18 pivot link is removed; a child that still carries a
 * business is never detached; every write is keyed on a natural key so
 * re-running changes nothing.
 *
 * The one case that needs care is «مكتب», which root 18 SHARES with root 5
 * «شحن وتوصيل» — 14 delivery companies sit on it. Only accounts whose
 * `category_id` is 18 are moved; the delivery ones are left completely alone,
 * and the child itself stays attached to both roots.
 */
class RealEstateRemodelSeeder extends Seeder
{
    private const REAL_ESTATE_ROOT_ID = 18;

    /** «أنماط خدمة وتجارية» — see HealthRemodelSeeder for why this is required. */
    private const UNIVERSAL_OPTION_GROUP_ID = 12;

    public function run(): void
    {
        $data = require __DIR__ . '/data/real_estate_taxonomy.php';

        DB::transaction(function () use ($data) {
            $childIds = $this->upsertChildren($data['children']);
            $universal = $this->attachUniversalOptions($childIds);
            $types = $this->upsertPropertyTypes($data);
            $shared = $this->splitSharedChild($data, $childIds);
            $moved = $this->migrateBusinesses($data, $childIds);
            $detached = $this->detachPropertyChildren($data, $childIds);

            $this->command?->info('Real-estate remodel applied:');
            $this->command?->line('  - business-type children : ' . count($childIds));
            $this->command?->line('  - universal commerce-option links added : ' . $universal);
            $this->command?->line('  - property item types in the real_estate branch : ' . $types);
            $this->command?->line('  - accounts split off the shared «مكتب» child : ' . count($shared));
            $this->command?->line('  - accounts moved off property-type children : ' . count($moved));
            $this->command?->line('  - property children detached from root 18 : ' . $detached);

            foreach (array_merge($shared, $moved) as $row) {
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
                ['parent_id' => self::REAL_ESTATE_ROOT_ID, 'child_id' => $id],
                ['updated_at' => now()]
            );
        }

        return $ids;
    }

    /** A new child with no commerce options is a child missing every facet. */
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
     * File every property type into booking's `real_estate` branch. Types that
     * already exist there (apartment/villa/studio/chalet) are reused by key, so
     * nothing is duplicated and no existing listing is orphaned.
     */
    private function upsertPropertyTypes(array $data): int
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');
        $groupId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->where('key', $data['branch_key'])
            ->value('id');

        if (! $serviceId || ! $groupId) {
            $this->command?->warn('  ! booking/real_estate branch not found — property types skipped.');

            return 0;
        }

        $sort = (int) DB::table('platform_service_item_types')->where('platform_service_id', $serviceId)->max('sort_order');

        foreach ($data['property_types'] as $key => [$ar, $en]) {
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

        return count($data['property_types']);
    }

    /**
     * «مكتب» belongs to two roots at once. Move only the accounts registered
     * under root 18 onto مكتب عقاري and leave the delivery accounts — and the
     * child's link to root 5 — entirely untouched.
     *
     * @return array<int, array{id:int,name:string,from:string,to:string}>
     */
    private function splitSharedChild(array $data, array $childIds): array
    {
        $shared = $data['shared_child'];
        $sharedId = DB::table('category_children_master')->where('name_ar', $shared['name_ar'])->value('id');
        $targetId = $childIds[$shared['move_to']] ?? null;

        if (! $sharedId || ! $targetId) {
            return [];
        }

        $accounts = DB::table('users')
            ->where('category_child_id', $sharedId)
            ->where('category_id', self::REAL_ESTATE_ROOT_ID)
            ->where('type', 'business')
            ->get(['id', 'name']);

        foreach ($accounts as $account) {
            DB::table('users')->where('id', $account->id)->update([
                'category_child_id' => $targetId,
            ]);
        }

        return $accounts->map(fn ($a) => [
            'id' => (int) $a->id,
            'name' => (string) $a->name,
            'from' => $shared['name_ar'] . ' (مشترك)',
            'to' => $shared['move_to'],
        ])->all();
    }

    /**
     * Move accounts sitting on a property-type child onto the real business
     * type, so the child can be detached without stranding anyone.
     *
     * @return array<int, array{id:int,name:string,from:string,to:string}>
     */
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
                ->where('category_id', self::REAL_ESTATE_ROOT_ID)
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

    /**
     * Drop the root-18 link for the property-type children. Master rows and any
     * links to OTHER roots survive — several of these children legitimately
     * exist under معارض/محلات as business types there.
     */
    private function detachPropertyChildren(array $data, array $childIds): int
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
                ->where('category_id', self::REAL_ESTATE_ROOT_ID)
                ->where('type', 'business')
                ->exists();

            if ($stillUsed) {
                $this->command?->warn("  ! تُرك «{$name}» مرتبطًا — ما زال عليه نشاط على الجذر 18.");
                continue;
            }

            $detached += DB::table('category_parent_child')
                ->where('parent_id', self::REAL_ESTATE_ROOT_ID)
                ->where('child_id', $childId)
                ->delete();
        }

        return $detached;
    }
}
