<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The approved services reform (2026-08-02): apply the price test to booking's
 * item types themselves. What is priced stays; what merely describes moves to
 * the option axis; duplicates of business-type children retire. ~115 of 243
 * active types retire — every one of them carried zero prices and zero
 * bookable items, so nothing live is touched.
 *
 *   php artisan db:seed --class=ServicesReformSeeder
 *   php artisan db:seed --class=BookingChildBranchesSeeder   # re-derive configs
 *
 * "Retire" = is_active=0 + unlink from the branch + strip from every
 * config.allowed_item_types — NEVER a hard delete, so the whole reform is
 * reversible.
 *
 * SHARED-KEY RULE (the landmine this file must respect): an item type row is
 * unique per (service, key) and may be linked to SEVERAL branches. Keys like
 * `apartment` (training + hotel + real_estate), `house_visit` / `medical_tourism`
 * (health_medical + clinic / tourism), `quality_management` etc. (technology +
 * consulting) are only UNLINKED from the branch being cleaned — deactivating
 * them would kill the healthy branch's copy too.
 */
class ServicesReformSeeder extends Seeder
{
    /** Branch => keys to fully retire (deactivate + unlink + strip). */
    private const RETIRE = [
        // 64 craft specialties duplicating the مهن وحرفيين children; the
        // specialty lives on the child, what a craftsman sells is a task.
        'services_tasks' => [
            'armored_doors', 'grinding_floors', 'lambposts', 'steel', 'said_walk', 'epoxy', 'parquet',
            'marine', 'machine_programming', 'brick_building', 'mason_decoration_stones', 'pvc',
            'cooling', 'freezing', 'ducting_prepare', 'landscape', 'chowen', 'interior_designing',
            'demolition_worker', 'air_conditioning', 'air_conditioning_system', 'gypsm_boards',
            'gypsm_decorations', 'grc_and_decorations', 'blacksmith_building', 'digging',
            'digging_and_lining', 'swimming_pool', 'concrete', 'telephone_line', 'gas_line',
            'electric_line', 'engineered_stone', 'marble_granite', 'plumbing', 'car_upholstery_worker',
            'scaffolding', 'electric_ladder', 'vehicle_sheet_metal_worker', 'electromechanical',
            'ceramic_porcelain', 'maintenance', 'chef', 'aviation', 'cleaner', 'waterproofing',
            'vehicle_suspensions_worker', 'cleaners', 'core_slots', 'installing_and_uninstalling',
            'electrical', 'car_electrician', 'plasting', 'civil', 'home_helper', 'elevators',
            'heating_equipments', 'solar_energy_equipments', 'mechanist', 'baby_sitter',
            'building_carpenter', 'painting', 'transport_sand_and_building_waste', 'chowen_crane',
        ],
        // fields/languages → options in مجالات التدريب; sessions stay
        'training' => [
            'spanish', 'german', 'english', 'business_english', 'english_language_for_kids',
            'italian', 'chinese', 'arabic', 'japanese', 'russian', 'french', 'conversation',
            'ielts_preparation', 'toefl_ibt_preparation', 'toefl_itp_preparation', 'icdl_training',
            'marketing', 'selling_skills', 'project_management', 'crisis_management', 'risk_management',
            'accounting_diploma', 'a_detailed_course_for_specialists', 'chemical', 'mechanical',
            'survaying', 'programming_languages', 'coach', 'companies_service',
        ],
        // software skills = teaching fields; internet cafe = a business type
        'technology_digital' => [
            '3d_max', 'archicad', 'revit', 'solid_works', 'autocad', 'photoshop', 'web_design',
            'mobile_apps', 'icdl_test', 'it_computer_science', 'internet_cafe',
        ],
        // the event lives on the option axis (#29) now; hall classes stay
        'halls_events' => ['wedding_hall', 'engagement_hall', 'concerting'],
        // business types already rescued to children; retail product leaves booking
        'health_medical' => ['clinic', 'hospital', 'health_club', 'pharmaceutical_materials'],
        // generic duplicates of their specific siblings + a vague business type
        'entertainment_leisure' => ['playstation', 'ping_pong', 'bowling', 'club'],
        'business_consulting' => ['customer_service'],
    ];

    /**
     * Stray ACTIVE types with no branch at all (found by the owner's duplicate
     * audit): descriptors now covered by option pools, zero prices. `category`
     * («افتراضي») is deliberately ungrouped and must NEVER appear here — it is
     * BusinessServicePrice::DEFAULT_ITEM_TYPE, the direct-booking price slot.
     */
    private const RETIRE_UNGROUPED = [
        'architectural', 'exterior_designing', 'fire_fighting_system', 'manufacture_all_type_of_models',
    ];

    /** Branch => shared keys to UNLINK ONLY (alive elsewhere — see class doc). */
    private const UNLINK_ONLY = [
        'training' => ['apartment'],
        'technology_digital' => ['quality_management', 'warehouse_management', 'strategic_planning', 'xbox'],
        'health_medical' => ['house_visit', 'medical_tourism'],
    ];

    /** Branch => new types [key => [ar, en]]. */
    private const ADD = [
        // the generic tasks every craftsman prices, replacing the 64
        'services_tasks' => [
            'inspection_visit' => ['زيارة معاينة', 'Inspection Visit'],
            'small_task' => ['مهمة صغيرة', 'Small Task'],
            'work_day' => ['يوم عمل', 'Work Day'],
            'quoted_job' => ['تشغيل بالمقايسة', 'Quoted Job'],
            'emergency_task' => ['طوارئ / مستعجل', 'Emergency Task'],
            'periodic_maintenance' => ['صيانة دورية', 'Periodic Maintenance'],
        ],
        'training' => [
            'diploma' => ['دبلومة', 'Diploma'],
        ],
        // xbox re-homed next to the playstations
        'entertainment_leisure' => [
            'xbox' => ['إكس بوكس', 'Xbox'],
        ],
    ];

    /** Fields added to مجالات التدريب so the retired languages stay filterable. */
    private const NEW_TRAINING_FIELDS = [
        'إنجليزي' => 'English', 'ألماني' => 'German', 'فرنسي' => 'French',
        'إسباني' => 'Spanish', 'إيطالي' => 'Italian', 'صيني' => 'Chinese',
        'ياباني' => 'Japanese', 'روسي' => 'Russian',
        'تحضير اختبارات دولية' => 'International Test Prep',
        'مهارات بيع' => 'Sales Skills', 'إدارة مشاريع' => 'Project Management',
        // owner correction 2026-08-02: these are COURSE fields under مركز
        // تدريب, not consulting forms — the item types stayed retired, the
        // fields live here.
        'إدارة الجودة' => 'Quality Management',
        'التخطيط الاستراتيجي' => 'Strategic Planning',
        'إدارة المخازن' => 'Warehouse Management',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');
            $groups = DB::table('platform_service_item_groups')
                ->where('platform_service_id', $serviceId)->pluck('id', 'key');

            $added = $this->addTypes($serviceId, $groups);
            $retired = $this->retire($serviceId, $groups, self::RETIRE, true);
            $unlinked = $this->retire($serviceId, $groups, self::UNLINK_ONLY, false);

            $strays = 0;
            foreach (self::RETIRE_UNGROUPED as $key) {
                $strays += DB::table('platform_service_item_types')
                    ->where('platform_service_id', $serviceId)->where('key', $key)
                    ->where('is_active', 1)->update(['is_active' => 0, 'updated_at' => now()]);
                $this->stripKeyFromConfigs($serviceId, $key);
            }

            // the branch is now empty of purpose — close it (tolerate the row
            // having been hand-deleted from the admin panel since)
            if ($groups->has('health_medical')) {
                DB::table('platform_service_item_groups')
                    ->where('id', (int) $groups['health_medical'])
                    ->update(['is_active' => 0, 'updated_at' => now()]);
            }

            $fields = $this->addTrainingFields();

            $this->command?->info('Services reform applied:');
            $this->command?->line("  - types added : {$added}");
            $this->command?->line("  - types retired : {$retired}");
            $this->command?->line("  - shared keys unlinked only : {$unlinked}");
            $this->command?->line("  - stray ungrouped types retired : {$strays}");
            $this->command?->line("  - training-field options added : {$fields}");
            $this->command?->line('  - health_medical branch closed');
            $this->command?->line('  NEXT: php artisan db:seed --class=BookingChildBranchesSeeder');
        });
    }

    private function addTypes(int $serviceId, $groups): int
    {
        $added = 0;

        foreach (self::ADD as $branch => $types) {
            $groupId = (int) $groups[$branch];
            $sort = (int) DB::table('platform_service_item_types')
                ->where('platform_service_id', $serviceId)->max('sort_order');

            foreach ($types as $key => [$ar, $en]) {
                $typeId = DB::table('platform_service_item_types')
                    ->where('platform_service_id', $serviceId)->where('key', $key)->value('id');

                if (! $typeId) {
                    $typeId = DB::table('platform_service_item_types')->insertGetId([
                        'platform_service_id' => $serviceId, 'key' => $key,
                        'name_ar' => $ar, 'name_en' => $en, 'is_active' => 1,
                        'sort_order' => ++$sort, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $added++;
                } else {
                    // e.g. xbox moving home: make sure it is alive
                    DB::table('platform_service_item_types')->where('id', $typeId)
                        ->update(['is_active' => 1, 'updated_at' => now()]);
                }

                DB::table('platform_service_item_group_type')->updateOrInsert(
                    ['group_id' => $groupId, 'item_type_id' => (int) $typeId], []
                );
            }
        }

        return $added;
    }

    private function retire(int $serviceId, $groups, array $map, bool $deactivate): int
    {
        $count = 0;

        foreach ($map as $branch => $keys) {
            $groupId = (int) $groups->get($branch, 0); // branch may have been hand-deleted since

            foreach ($keys as $key) {
                $typeId = DB::table('platform_service_item_types')
                    ->where('platform_service_id', $serviceId)->where('key', $key)->value('id');

                if (! $typeId) {
                    continue;
                }

                DB::table('platform_service_item_group_type')
                    ->where('group_id', $groupId)->where('item_type_id', (int) $typeId)->delete();

                if ($deactivate) {
                    DB::table('platform_service_item_types')->where('id', (int) $typeId)
                        ->update(['is_active' => 0, 'updated_at' => now()]);
                    $this->stripKeyFromConfigs($serviceId, $key);
                }

                $count++;
            }
        }

        return $count;
    }

    /** A retired key must stop being offered — same guard the tests assert. */
    private function stripKeyFromConfigs(int $serviceId, string $key): void
    {
        foreach (DB::table('category_service_configs')->where('platform_service_id', $serviceId)->get(['id', 'config']) as $row) {
            $config = json_decode((string) $row->config, true);
            $allowed = is_array($config) ? ($config['allowed_item_types'] ?? null) : null;

            if (! is_array($allowed) || ! in_array($key, $allowed, true)) {
                continue;
            }

            $config['allowed_item_types'] = array_values(array_filter($allowed, fn ($k) => $k !== $key));
            DB::table('category_service_configs')->where('id', $row->id)
                ->update(['config' => json_encode($config, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        }
    }

    private function addTrainingFields(): int
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', 'مجالات التدريب')->value('id');

        if (! $groupId) {
            return 0;
        }

        $added = 0;
        // every child already carrying this pool gets the new fields too
        $childIds = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->where('o.group_id', $groupId)->distinct()->pluck('co.child_id');

        foreach (self::NEW_TRAINING_FIELDS as $ar => $en) {
            $id = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if (! $id) {
                $id = DB::table('options')->insertGetId(['group_id' => $groupId, 'name_ar' => $ar, 'name_en' => $en]);
                $added++;
            }

            foreach ($childIds as $childId) {
                DB::table('category_child_option')->updateOrInsert(
                    ['child_id' => (int) $childId, 'option_id' => (int) $id], ['reorder' => 0]
                );
            }
        }

        return $added;
    }
}
