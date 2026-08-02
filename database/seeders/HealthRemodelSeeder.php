<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-files the Health section onto the three correct axes (see
 * data/health_taxonomy.php for the why). In short: the 41 medical specialties
 * stop being category children (axis 1) and become options (axis 2), so a
 * hospital can finally carry ten of them at once instead of registering under
 * exactly one.
 *
 *   php artisan db:seed --class=HealthRemodelSeeder
 *
 * Idempotent and NON-DESTRUCTIVE by design:
 *   - specialty rows in `category_children_master` are never deleted, only
 *     their `category_parent_child` link to the Health root is removed, so the
 *     whole move is reversible by re-inserting those pivot rows;
 *   - businesses are re-pointed only if they still sit on a specialty child,
 *     and their former specialty is written into `option_user` first, so no
 *     information is lost;
 *   - every write is updateOrInsert / firstOrCreate keyed on a natural key.
 *
 * Run `--class=HealthRemodelSeeder` twice: the second run reports the same
 * totals and changes nothing.
 */
class HealthRemodelSeeder extends Seeder
{
    /** The Health root category. */
    private const HEALTH_ROOT_ID = 20;

    /**
     * «أنماط خدمة وتجارية» — the universal commerce-mode options every child
     * must carry (asserted by CategoryChildOptionLinkingTest). Newly created
     * children have to be linked to them too, or they silently lose the
     * delivery/payment/service-mode facets every other child has.
     */
    private const UNIVERSAL_OPTION_GROUP_ID = 12;

    public function run(): void
    {
        $data = require __DIR__ . '/data/health_taxonomy.php';

        DB::transaction(function () use ($data) {
            $groupId = $this->upsertSpecialtyGroup($data['specialty_group']);
            $optionIds = $this->upsertSpecialties($data['specialties'], $groupId);
            $childIds = $this->upsertChildren($data['children']);
            $universal = $this->attachUniversalOptions($childIds);
            $this->attachSpecialtyPool($data['children'], $childIds, $optionIds);
            $imaging = $this->attachImagingModalities($data, $childIds);
            $labs = $this->attachLabTests($data, $childIds);
            $moved = $this->migrateBusinesses($data, $childIds, $optionIds);
            $detached = $this->detachSpecialtyChildren($data, $childIds);

            $this->command?->info('Health remodel applied:');
            $this->command?->line('  - specialty options : ' . count($optionIds) . " (group #{$groupId})");
            $this->command?->line('  - business-type children : ' . count($childIds));
            $this->command?->line('  - universal commerce-option links added : ' . $universal);
            $this->command?->line('  - imaging modalities on مراكز أشعة : ' . $imaging);
            $this->command?->line('  - lab tests on معمل تحاليل/مستشفى : ' . $labs);
            $this->command?->line('  - specialties detached from the Health root : ' . $detached);
            $this->command?->line('  - businesses re-pointed : ' . count($moved));

            foreach ($moved as $row) {
                $this->command?->line("      #{$row['id']} {$row['name']} : {$row['from']} → {$row['to']} (+ خيار: {$row['specialty']})");
            }
        });
    }

    /** The option group the specialties live under (mirrors group #25). */
    private function upsertSpecialtyGroup(array $group): int
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

    /**
     * Create one option per specialty. Keyed on (group, name_ar) so a re-run
     * reuses the same rows rather than duplicating them.
     *
     * @return array<string, int> name_ar => option id
     */
    private function upsertSpecialties(array $specialties, int $groupId): array
    {
        $ids = [];
        $order = 0;

        foreach ($specialties as $ar => $en) {
            $id = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if (! $id) {
                $id = DB::table('options')->insertGetId([
                    'group_id' => $groupId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                ]);
            }

            $ids[$ar] = (int) $id;
            $order++;
        }

        return $ids;
    }

    /**
     * Ensure every business-type child exists in the master list and is linked
     * to the Health root. Children flagged `existing` are already correct and
     * are only looked up.
     *
     * @return array<string, int> name_ar => child id
     */
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
                ['parent_id' => self::HEALTH_ROOT_ID, 'child_id' => $id],
                ['updated_at' => now()]
            );
        }

        return $ids;
    }

    /**
     * Link every child this seeder owns to the universal commerce-mode
     * options, so a newly created child starts out with the same baseline
     * facets (توصيل، تقسيط، أونلاين…) every other child already carries.
     */
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
     * Attach the whole specialty pool to the children that carry specialties
     * (مستشفى / عيادة / مركز طبي). This is what a business picks FROM at
     * signup — the per-business selection itself lives in `option_user`.
     */
    private function attachSpecialtyPool(array $children, array $childIds, array $optionIds): void
    {
        foreach ($children as $child) {
            if (empty($child['carries_specialties'])) {
                continue;
            }

            $childId = $childIds[$child['name_ar']] ?? null;

            if (! $childId) {
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

    /**
     * Build the imaging-modality pool and attach it to مراكز أشعة only. What a
     * centre OWNS (رنين، مقطعية، سونار…) is descriptive and multi-select, so it
     * belongs on the option axis next to the specialties; the thing that
     * carries a price stays the `clinic` item type «أشعة / تصوير».
     */
    private function attachImagingModalities(array $data, array $childIds): int
    {
        $childId = $childIds[$data['imaging_child']] ?? null;

        if (! $childId) {
            return 0;
        }

        $groupId = $this->upsertSpecialtyGroup($data['imaging_group']);
        $optionIds = $this->upsertSpecialties($data['imaging_modalities'], $groupId);
        $order = 0;

        foreach ($optionIds as $optionId) {
            DB::table('category_child_option')->updateOrInsert(
                ['child_id' => $childId, 'option_id' => $optionId],
                ['reorder' => ++$order]
            );
        }

        return count($optionIds);
    }

    /**
     * The lab-test pool — same axis logic as imaging: what a lab PERFORMS is
     * descriptive/multi-select; the priced offering stays «تحليل / اختبار».
     * Attached to معمل تحاليل and مستشفى (hospitals run their own labs).
     */
    private function attachLabTests(array $data, array $childIds): int
    {
        $groupId = $this->upsertSpecialtyGroup($data['lab_group']);
        $optionIds = $this->upsertSpecialties($data['lab_tests'], $groupId);

        foreach ($data['lab_children'] as $childName) {
            $childId = $childIds[$childName]
                ?? DB::table('category_children_master')->where('name_ar', $childName)->value('id');

            if (! $childId) {
                continue;
            }

            $order = 0;

            foreach ($optionIds as $optionId) {
                DB::table('category_child_option')->updateOrInsert(
                    ['child_id' => (int) $childId, 'option_id' => $optionId],
                    ['reorder' => ++$order]
                );
            }
        }

        return count($optionIds);
    }

    /**
     * Move any business still registered under a specialty child onto the
     * target business-type child, recording its former specialty in
     * `option_user` first so the information survives the move.
     *
     * @return array<int, array{id:int,name:string,from:string,to:string,specialty:string}>
     */
    private function migrateBusinesses(array $data, array $childIds, array $optionIds): array
    {
        $targetName = $data['business_migration_target'];
        $targetId = $childIds[$targetName] ?? null;

        if (! $targetId) {
            return [];
        }

        $moved = [];

        foreach (array_keys($data['specialties']) as $specialty) {
            $specialtyChildId = DB::table('category_children_master')->where('name_ar', $specialty)->value('id');
            $optionId = $optionIds[$specialty] ?? null;

            if (! $specialtyChildId || ! $optionId) {
                continue;
            }

            $businesses = DB::table('users')
                ->where('category_child_id', $specialtyChildId)
                ->where('type', 'business')
                ->get(['id', 'name']);

            foreach ($businesses as $business) {
                // Preserve the specialty on the business itself before moving.
                DB::table('option_user')->updateOrInsert(
                    ['user_id' => (int) $business->id, 'option_id' => $optionId],
                    []
                );

                DB::table('users')->where('id', $business->id)->update([
                    'category_child_id' => $targetId,
                    'category_id' => self::HEALTH_ROOT_ID,
                ]);

                $moved[] = [
                    'id' => (int) $business->id,
                    'name' => (string) $business->name,
                    'from' => $specialty,
                    'to' => $targetName,
                    'specialty' => $specialty,
                ];
            }
        }

        return $moved;
    }

    /**
     * Remove the specialty children from the Health root so they stop being
     * offered as business types. The master rows survive untouched — this is
     * the one step that must stay reversible.
     */
    private function detachSpecialtyChildren(array $data, array $childIds): int
    {
        $keepIds = array_values($childIds);
        $detached = 0;

        foreach (array_keys($data['specialties']) as $specialty) {
            $childId = DB::table('category_children_master')->where('name_ar', $specialty)->value('id');

            if (! $childId || in_array((int) $childId, $keepIds, true)) {
                continue;
            }

            // Never strand a business: if one still sits here, leave the link.
            $stillUsed = DB::table('users')
                ->where('category_child_id', $childId)
                ->where('type', 'business')
                ->exists();

            if ($stillUsed) {
                $this->command?->warn("  ! تُرك «{$specialty}» مرتبطًا — ما زال عليه نشاط.");
                continue;
            }

            $detached += DB::table('category_parent_child')
                ->where('parent_id', self::HEALTH_ROOT_ID)
                ->where('child_id', $childId)
                ->delete();
        }

        return $detached;
    }
}
