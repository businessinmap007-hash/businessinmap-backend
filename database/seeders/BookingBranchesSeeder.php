<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Database\Seeder;

/**
 * Booking branches taxonomy — the Booking counterpart of DeliveryBranchesSeeder.
 *
 * Completes the Booking branch division to 13 branches: the 10 existing ones
 * (clinic / hotel / restaurant_table / sports / training / services_tasks /
 * halls_events / health_medical / technology_digital / entertainment_leisure)
 * plus 3 new (tourism_travel / real_estate / beauty_care), then files every
 * previously-ungrouped active booking item type into its branch and adds the
 * cross-listings (a type can live in several branches). Idempotent.
 *
 * The legacy placeholder type `category` («افتراضي») is intentionally left
 * ungrouped.
 */
class BookingBranchesSeeder extends Seeder
{
    public function run(): void
    {
        $booking = PlatformService::where('key', 'booking')->first();

        if (! $booking) {
            return;
        }

        $serviceId = (int) $booking->id;

        // ---- 3 new branches (global pool, platform_service_id nullable) ----
        $newBranches = [
            'tourism_travel' => ['سياحة ورحلات', 'Tourism & Travel'],
            'real_estate' => ['عقارات ووحدات', 'Real Estate & Units'],
            'beauty_care' => ['تجميل وعناية', 'Beauty & Care'],
        ];

        $sort = 1 + (int) PlatformServiceItemGroup::max('sort_order');

        foreach ($newBranches as $key => [$ar, $en]) {
            PlatformServiceItemGroup::updateOrCreate(
                ['key' => $key],
                [
                    'platform_service_id' => $serviceId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'sort_order' => $sort++,
                    'is_active' => 1,
                ]
            );
        }

        // ---- new beauty types (the كوافير root has none today) ----
        $newTypes = [
            ['mens_haircut', 'حلاقة رجالي', 'Men\'s Haircut'],
            ['womens_hairstyling', 'تصفيف حريمي', 'Women\'s Hairstyling'],
            ['hair_coloring', 'صبغة وميش', 'Hair Coloring'],
            ['makeup_session', 'جلسة مكياج', 'Makeup Session'],
            ['bridal_package', 'باكدج عرايس', 'Bridal Package'],
            ['spa_massage', 'سبا ومساج', 'Spa & Massage'],
        ];

        $typeSort = 1 + (int) PlatformServiceItemType::where('platform_service_id', $serviceId)->max('sort_order');

        foreach ($newTypes as [$key, $ar, $en]) {
            PlatformServiceItemType::updateOrCreate(
                ['platform_service_id' => $serviceId, 'key' => $key],
                ['name_ar' => $ar, 'name_en' => $en, 'is_active' => 1, 'sort_order' => $typeSort++]
            );
        }

        /*
        | branch key => item-type keys to attach (syncWithoutDetaching — existing
        | memberships stay). Covers the 77 ungrouped types + cross-listings.
        */
        $assign = [
            'clinic' => [
                'consultation_slot', 'clinic_consultation', 'clinic_follow_up',
                'clinic_session', 'clinic_procedure', 'telemedicine',
                'lab_test', 'imaging_scan', 'house_visit',
            ],
            'restaurant_table' => [
                'table_2', 'table_4', 'table_6', 'vip_table', 'restaurant_table',
                'indoor_table', 'outdoor_table', 'family_table', 'private_room',
            ],
            'hotel' => ['executive_suite', 'royal_suite', 'apartment'],
            'sports' => [
                'padel_court', 'football_5_field', 'football_7_field',
                'football_11_field', 'tennis_court', 'basketball_court',
                'volleyball_court', 'swimming_lane',
            ],
            'training' => [
                'training_course', 'workshop', 'private_lesson', 'group_class',
                'online_session', 'toefl_ibt_preparation', 'coach',
            ],
            'halls_events' => [
                'hall_standard', 'hall_vip', 'concerting',
                'from_10_to_20_person', 'from_20_to_40_person',
                'from_100_to_150_person', 'from_150_to_200_person',
                'from_200_to_300_person',
            ],
            'services_tasks' => [
                'customer_service', 'quality_management', 'strategic_planning',
                'warehouse_management', 'air_conditioning', 'air_conditioning_system',
                'architectural', 'armored_doors', 'athletic_devices',
                'blacksmith_building', 'brick_building', 'building_carpenter',
                'car_electrician', 'car_upholstery_worker', 'ceramic_porcelain',
                'chef', 'chemical_wash', 'chowen',
            ],
            'technology_digital' => [
                'mobil', 'mobil_accessories', 'mobile', 'mobile_apps',
                'electronics_home_appliances',
            ],
            'entertainment_leisure' => [
                'aqua_park', 'bowling', 'club', 'games_hall', 'kids_erea',
                'ping_pong', 'playstation', 'playstation_3', 'playstation_4',
            ],
            'tourism_travel' => [
                'hajj_and_umrah', 'international_tourism', 'medical_tourism',
            ],
            'real_estate' => ['apartment', 'villa', 'chalet', 'studio'],
            'beauty_care' => [
                'mens_haircut', 'womens_hairstyling', 'hair_coloring',
                'makeup_session', 'bridal_package', 'spa_massage',
            ],
        ];

        foreach ($assign as $groupKey => $typeKeys) {
            $group = PlatformServiceItemGroup::where('key', $groupKey)->first();

            if (! $group) {
                continue;
            }

            $typeIds = PlatformServiceItemType::query()
                ->where('platform_service_id', $serviceId)
                ->whereIn('key', $typeKeys)
                ->pluck('id')
                ->all();

            if (! empty($typeIds)) {
                $group->itemTypes()->syncWithoutDetaching($typeIds);
            }
        }
    }
}
