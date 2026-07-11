<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Database\Seeder;

/**
 * Delivery branches taxonomy — see docs/delivery-branches-taxonomy.md.
 *
 * Splits the Delivery service into 6 branches (platform_service_item_groups)
 * that fit the different business subcategories, and creates the new item types
 * each branch needs. Idempotent: safe to re-run.
 *
 * Branch 1 reuses the existing `delivery` group (repurposed as last-mile) so we
 * don't duplicate the consumer types; branches 2–6 are new. Finally it removes
 * the accidental cross-listing of delivery types under the Menu "supermarket"
 * branch, so the Delivery picker shows only these 6 branches.
 */
class DeliveryBranchesSeeder extends Seeder
{
    public function run(): void
    {
        $delivery = PlatformService::where('key', 'delivery')->first();

        if (! $delivery) {
            return;
        }

        $serviceId = (int) $delivery->id;

        /*
        | key => [
        |   name_ar, name_en,
        |   existing => [item-type keys already present to attach here],
        |   new      => [[key, name_ar, name_en], ...],
        | ]
        */
        $branches = [
            'delivery' => [
                'name_ar' => 'توصيل استهلاكي (ميل أخير)',
                'name_en' => 'Last-mile Delivery',
                'existing' => [
                    'delivery',
                    'restaurant_delivery',
                    'grocery_delivery',
                    'pharmacy_delivery',
                    'scheduled_delivery',
                    'express_delivery',
                ],
                'new' => [],
            ],
            'delivery_freight' => [
                'name_ar' => 'شحن بضائع ثقيل / حمولات',
                'name_en' => 'Freight & Heavy Cargo',
                'existing' => [],
                'new' => [
                    ['full_truckload', 'حمولة كاملة', 'Full Truckload'],
                    ['partial_load', 'حمولة جزئية', 'Partial Load'],
                    ['crane_winch', 'نقل بونش / رافعة', 'Crane / Winch Transport'],
                    ['bulk_reservation', 'حجز حمولة مسبق', 'Advance Bulk Reservation'],
                ],
            ],
            'delivery_international' => [
                'name_ar' => 'شحن دولي / استيراد وتصدير',
                'name_en' => 'International / Import-Export',
                'existing' => [],
                'new' => [
                    ['sea_freight', 'شحن بحري', 'Sea Freight'],
                    ['air_freight', 'شحن جوي', 'Air Freight'],
                    ['land_freight', 'شحن بري', 'Land Freight'],
                    ['customs_clearance', 'تخليص جمركي', 'Customs Clearance'],
                ],
            ],
            'delivery_coldchain' => [
                'name_ar' => 'سلسلة تبريد / مبرّد',
                'name_en' => 'Cold Chain',
                'existing' => [],
                'new' => [
                    ['refrigerated_delivery', 'توصيل مبرّد', 'Refrigerated Delivery'],
                    ['frozen_transport', 'نقل مجمّد', 'Frozen Transport'],
                    ['medical_sample_courier', 'نقل عينات طبية', 'Medical Sample Courier'],
                ],
            ],
            'delivery_courier_ondemand' => [
                'name_ar' => 'مناديب ومهمّات (عند الطلب)',
                'name_en' => 'On-demand Courier & Errands',
                'existing' => [],
                'new' => [
                    ['rep_errand', 'مندوب / مشوار', 'Rep Errand'],
                    ['same_day_pickup', 'استلام وتسليم بنفس اليوم', 'Same-day Pickup'],
                ],
            ],
            'delivery_documents' => [
                'name_ar' => 'مستندات وطرود صغيرة',
                'name_en' => 'Documents & Small Parcels',
                'existing' => [],
                'new' => [
                    ['document_courier', 'كوريير مستندات', 'Document Courier'],
                    ['small_parcel', 'طرد صغير', 'Small Parcel'],
                ],
            ],
        ];

        $groupSort = 1;
        $typeSort = 100; // new delivery types sort after the existing (< 100) ones

        foreach ($branches as $key => $def) {
            $group = PlatformServiceItemGroup::updateOrCreate(
                ['key' => $key],
                [
                    'platform_service_id' => $serviceId,
                    'name_ar' => $def['name_ar'],
                    'name_en' => $def['name_en'],
                    'sort_order' => $groupSort++,
                    'is_active' => 1,
                ]
            );

            $typeIds = [];

            foreach ($def['existing'] as $typeKey) {
                $type = PlatformServiceItemType::where('platform_service_id', $serviceId)
                    ->where('key', $typeKey)
                    ->first();

                if ($type) {
                    $typeIds[] = (int) $type->id;
                }
            }

            foreach ($def['new'] as [$typeKey, $nameAr, $nameEn]) {
                $type = PlatformServiceItemType::updateOrCreate(
                    ['platform_service_id' => $serviceId, 'key' => $typeKey],
                    [
                        'name_ar' => $nameAr,
                        'name_en' => $nameEn,
                        'is_active' => 1,
                        'sort_order' => $typeSort++,
                    ]
                );

                $typeIds[] = (int) $type->id;
            }

            if (! empty($typeIds)) {
                $group->itemTypes()->syncWithoutDetaching($typeIds);
            }
        }

        // Cleanup: the 6 consumer delivery types were cross-listed under the Menu
        // "supermarket" branch. Detach them so the Delivery picker is clean.
        $supermarket = PlatformServiceItemGroup::where('key', 'supermarket')->first();

        if ($supermarket) {
            $deliveryTypeIds = PlatformServiceItemType::where('platform_service_id', $serviceId)
                ->pluck('id')
                ->all();

            if (! empty($deliveryTypeIds)) {
                $supermarket->itemTypes()->detach($deliveryTypeIds);
            }
        }
    }
}
