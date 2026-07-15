<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Database\Seeder;

/**
 * The platform-standard vehicle/cargo classes for the scheduling service, as
 * PlatformServiceItemType rows keyed to the `schedules` service. Each class is
 * formally grouped under its trip mode via a PlatformServiceItemGroup branch
 * (the same mechanism booking uses: hotel/clinic/…). `meta.mode` is kept as a
 * lightweight mirror; `meta.scope` marks international-only classes (containers
 * / LCL); `meta.default_unit` seeds the capacity unit. Re-runnable.
 */
class ScheduleVehicleTypesSeeder extends Seeder
{
    /** mode => [name_ar, name_en, sort_order] */
    private const MODE_GROUPS = [
        'passenger' => ['نقل الركاب', 'Passenger transport', 1],
        'limousine' => ['ليموزين', 'Limousine', 2],
        'freight' => ['شحن البضائع', 'Freight', 3],
        'distribution' => ['التوزيع', 'Distribution', 4],
    ];

    public function run(): void
    {
        $service = PlatformService::query()->where('key', PlatformService::KEY_SCHEDULES)->first();

        if (! $service) {
            return; // PlatformServiceSeeder must run first.
        }

        // Formal mode branches (PlatformServiceItemGroup), one per trip mode.
        $groups = [];
        foreach (self::MODE_GROUPS as $mode => [$ar, $en, $order]) {
            $groups[$mode] = PlatformServiceItemGroup::updateOrCreate(
                ['platform_service_id' => (int) $service->id, 'key' => 'mode_'.$mode],
                ['name_ar' => $ar, 'name_en' => $en, 'sort_order' => $order, 'is_active' => true]
            );
        }

        $types = [
            // Passenger transport
            ['passenger_individual', 'نقل أفراد / خاص', 'Individual ride', 'passenger', 'domestic', 'seat', true],
            ['passenger_bus', 'نقل جماعي - أتوبيس', 'Group bus', 'passenger', 'domestic', 'seat', false],
            ['passenger_minibus', 'ميني باص / ميكروباص', 'Minibus', 'passenger', 'domestic', 'seat', false],
            ['passenger_vip', 'ليموزين VIP', 'VIP limousine', 'limousine', 'domestic', 'seat', true],

            // Domestic freight
            ['freight_pickup', 'ربع نقل', 'Pickup', 'freight', 'domestic', 'parcel', true],
            ['freight_box_truck', 'نقل مغلق', 'Box truck', 'freight', 'domestic', 'parcel', false],
            ['freight_refrigerated', 'نقل مبرد', 'Refrigerated', 'freight', 'domestic', 'kg', false],
            ['freight_flatbed', 'نقل مسطح / مقطورة', 'Flatbed / trailer', 'freight', 'domestic', 'pallet', false],

            // International freight (containers / consolidation)
            ['container_20ft', 'كونتينر 20 قدم', '20ft container', 'freight', 'international', 'cbm', false],
            ['container_40ft', 'كونتينر 40 قدم', '40ft container', 'freight', 'international', 'cbm', false],
            ['lcl_consolidation', 'شحن مجمّع LCL', 'LCL consolidation', 'freight', 'international', 'cbm', false],
            ['air_freight', 'شحن جوي', 'Air freight', 'freight', 'international', 'kg', false],

            // Distribution
            ['distribution_van', 'سيارة توزيع', 'Distribution van', 'distribution', 'domestic', 'parcel', true],
            ['distribution_refrigerated', 'توزيع مبرد', 'Refrigerated distribution', 'distribution', 'domestic', 'kg', false],
        ];

        foreach ($types as $i => [$key, $ar, $en, $mode, $scope, $unit, $isDefault]) {
            $type = PlatformServiceItemType::updateOrCreate(
                ['platform_service_id' => (int) $service->id, 'key' => $key],
                [
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'is_default' => $isDefault,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                    'meta' => ['mode' => $mode, 'scope' => $scope, 'default_unit' => $unit],
                ]
            );

            // Attach the class to its formal mode branch.
            if (isset($groups[$mode])) {
                $type->groups()->sync([(int) $groups[$mode]->id]);
            }
        }
    }
}
