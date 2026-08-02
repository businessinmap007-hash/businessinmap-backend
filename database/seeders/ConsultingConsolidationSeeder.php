<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Owner-directed consolidation (2026-08-02), the last step of the services
 * reformulation: now that booking relies on the CHILD + its option pools, the
 * per-field consultation types are redundant — «استشارة قانونية» is just
 * استشارة sold by a محاماه child. business_consulting shrinks to three generic
 * priced forms: استشارة بالحضور (new), استشارة أونلاين (the existing shared
 * online_session), معاينة وتقييم بالموقع.
 *
 * Also merges legacy option group #25 «تخصصات استشارية» (كهرباء، بترول، غزل
 * ونسيج) into «تخصصات الهندسة»: the two non-duplicates move over, كهرباء's
 * child/business links re-point to the engineering copy, and the old group is
 * deactivated (options have no is_active column, so the emptied group is the
 * retirement boundary).
 *
 *   php artisan db:seed --class=ConsultingConsolidationSeeder
 *   php artisan db:seed --class=BookingChildBranchesSeeder
 *   php artisan db:seed --class=NewChildrenBranchesSeeder
 *
 * online_session is SHARED with the training branch — it stays active and
 * linked in both. Idempotent; nothing hard-deleted except superseded pivot
 * links, which the engineering copies replace one-to-one.
 */
class ConsultingConsolidationSeeder extends Seeder
{
    /** The specialty consultation types the child+options now express. */
    private const RETIRE_CONSULTING = [
        'business_consultation', 'marketing_consultation', 'technical_it_consultation',
        'legal_consultation', 'accounting_tax_consultation', 'engineering_consultation',
        'strategic_planning', 'quality_management', 'warehouse_management', 'consultation_slot',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');
            $groupId = (int) DB::table('platform_service_item_groups')
                ->where('platform_service_id', $serviceId)->where('key', 'business_consulting')->value('id');

            // 1) the new in-person form
            $sort = (int) DB::table('platform_service_item_types')->where('platform_service_id', $serviceId)->max('sort_order');
            $inPerson = DB::table('platform_service_item_types')
                ->where('platform_service_id', $serviceId)->where('key', 'in_person_consultation')->value('id')
                ?: DB::table('platform_service_item_types')->insertGetId([
                    'platform_service_id' => $serviceId, 'key' => 'in_person_consultation',
                    'name_ar' => 'استشارة بالحضور', 'name_en' => 'In-Person Consultation',
                    'is_active' => 1, 'sort_order' => ++$sort, 'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('platform_service_item_types')->where('id', $inPerson)->update(['is_active' => 1]);
            DB::table('platform_service_item_group_type')->updateOrInsert(
                ['group_id' => $groupId, 'item_type_id' => (int) $inPerson], []
            );

            // 2) retire the per-field forms
            $retired = 0;
            foreach (self::RETIRE_CONSULTING as $key) {
                $typeId = DB::table('platform_service_item_types')
                    ->where('platform_service_id', $serviceId)->where('key', $key)->value('id');
                if (! $typeId) {
                    continue;
                }
                DB::table('platform_service_item_group_type')->where('group_id', $groupId)->where('item_type_id', $typeId)->delete();
                DB::table('platform_service_item_types')->where('id', $typeId)->update(['is_active' => 0, 'updated_at' => now()]);

                // strip from EVERY config — matrix-authored ones aren't covered
                // by the child-branch data files' re-derivation
                foreach (DB::table('category_service_configs')->where('platform_service_id', $serviceId)->get(['id', 'config']) as $row) {
                    $config = json_decode((string) $row->config, true);
                    $allowed = is_array($config) ? ($config['allowed_item_types'] ?? null) : null;

                    if (! is_array($allowed) || ! in_array($key, $allowed, true)) {
                        continue;
                    }

                    $config['allowed_item_types'] = array_values(array_diff($allowed, [$key]));
                    DB::table('category_service_configs')->where('id', $row->id)
                        ->update(['config' => json_encode($config, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
                }

                $retired++;
            }

            // 3) merge option group #25 into تخصصات الهندسة
            $oldGroup = DB::table('option_groups')->where('name_ar', 'تخصصات استشارية')->first();
            $engGroup = (int) DB::table('option_groups')->where('name_ar', 'تخصصات الهندسة')->value('id');
            $moved = 0;

            if ($oldGroup && $engGroup) {
                foreach (DB::table('options')->where('group_id', $oldGroup->id)->get(['id', 'name_ar']) as $opt) {
                    $existing = DB::table('options')->where('group_id', $engGroup)
                        ->where('name_ar', trim($opt->name_ar))->value('id');

                    if ($existing) {
                        // duplicate (كهرباء): re-point links to the engineering copy
                        foreach (DB::table('category_child_option')->where('option_id', $opt->id)->get() as $link) {
                            DB::table('category_child_option')->updateOrInsert(
                                ['child_id' => $link->child_id, 'option_id' => $existing],
                                ['reorder' => $link->reorder]
                            );
                            DB::table('category_child_option')->where('id', $link->id)->delete();
                        }
                        foreach (DB::table('option_user')->where('option_id', $opt->id)->get() as $link) {
                            DB::table('option_user')->updateOrInsert(
                                ['user_id' => $link->user_id, 'option_id' => $existing], []
                            );
                            DB::table('option_user')->where('id', $link->id)->delete();
                        }
                    } else {
                        DB::table('options')->where('id', $opt->id)->update(['group_id' => $engGroup]);
                        $moved++;
                    }
                }

                DB::table('option_groups')->where('id', $oldGroup->id)->update(['is_active' => 0]);
            }

            $this->command?->info("Consulting consolidation: retired={$retired}, merged-into-engineering={$moved}, group #25 closed.");
            $this->command?->line('  NEXT: re-run BookingChildBranchesSeeder + NewChildrenBranchesSeeder.');
        });
    }
}
