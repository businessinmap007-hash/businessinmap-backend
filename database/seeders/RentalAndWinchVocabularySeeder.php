<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Two small vocabularies that let a business PRICE what it could only describe before (owner, 2026-10-18/19).
 *
 *     php artisan db:seed --class=RentalAndWinchVocabularySeeder
 *
 * 1. A car for rent — «مع سائق» and the insurance were descriptive fields of the listing (the 7 rental fields of
 *    CarRentalSpecsSeeder). A showroom or a private owner also SELLS them: a driver, comprehensive insurance, the car
 *    brought to the door, a child seat, a tracker — each at its own price per day, chosen by the renter like the meal
 *    plan of a hotel. One group of add-ons for «معرض سيارات» #188 and «سيارة من المالك» #549.
 * 2. A furniture hoist — the price of lifting a sofa depends on the floor and on the way in (balcony, window, roof).
 *    Two groups for «ونش رفع أثاث» #556: the floor (one choice) and the way (any).
 *
 * The groups are plain option groups (`modifier`); BookingVocabularyRoles::PLATFORM_DEFAULTS says they are add-ons the
 * guest picks, so no merchant has to declare it. They are linked like every other vocabulary row — through
 * ChildOptionDecisions, so a ruling that withdrew a word from a child is respected ([[seeder-must-withdraw]]) — and
 * they sit outside every closed authority map (the grab-bag split and the vehicle split manage other groups only).
 * Idempotent: a group, an option or a link that exists is left as it is.
 */
class RentalAndWinchVocabularySeeder extends Seeder
{
    /**
     * group name → [English name, order, children it goes to, options [Arabic, English]].
     *
     * @var array<string,array{0:string,1:int,2:list<int>,3:list<array{0:string,1:string}>}>
     */
    private const GROUPS = [
        'إضافات تأجير السيارة' => ['Car Rental Add-ons', 10710, [188, 549], [
            ['مع سائق', 'Rental With Driver'],
            ['تأمين شامل', 'Rental Comprehensive Insurance'],
            ['توصيل واستلام السيارة', 'Car Delivery And Pickup'],
            ['كرسي أطفال', 'Child Seat'],
            ['جهاز تتبع GPS', 'GPS Tracker'],
        ]],
        'ارتفاع رفع الأثاث' => ['Furniture Lift Height', 10711, [556], [
            ['حتى الدور الثالث', 'Lift Up To Third Floor'],
            ['من الدور الرابع إلى السادس', 'Lift Fourth To Sixth Floor'],
            ['من الدور السابع إلى العاشر', 'Lift Seventh To Tenth Floor'],
            ['أعلى من الدور العاشر', 'Lift Above Tenth Floor'],
        ]],
        'طريقة رفع الأثاث' => ['Furniture Lift Method', 10712, [556], [
            ['من البلكونة', 'Lift Through The Balcony'],
            ['من الشباك', 'Lift Through The Window'],
            ['إلى السطح', 'Lift To The Roof'],
        ]],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $made = 0;
            $linked = 0;

            foreach (self::GROUPS as $name => [$english, $order, $children, $options]) {
                $groupId = (int) DB::table('option_groups')->where('name_ar', $name)->value('id');

                if ($groupId <= 0) {
                    $groupId = (int) DB::table('option_groups')->insertGetId([
                        'name_ar' => $name, 'name_en' => $english, 'reorder' => $order, 'is_active' => 1,
                        'price_role' => 'modifier', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $made++;
                }

                $optionIds = [];

                foreach ($options as $i => [$ar, $en]) {
                    $id = (int) DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

                    if ($id <= 0) {
                        $id = (int) DB::table('options')->insertGetId([
                            'group_id' => $groupId, 'sort_order' => $i + 1, 'name_ar' => $ar, 'name_en' => $en,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }

                    $optionIds[] = $id;
                }

                foreach ($children as $childId) {
                    if (! DB::table('category_children_master')->where('id', $childId)->exists()) {
                        continue;
                    }

                    // one root per child here (cars / shipping-delivery); a ruling is read per (child, root)
                    $rootId = (int) DB::table('category_parent_child')->where('child_id', $childId)->orderBy('parent_id')->value('parent_id');

                    foreach (app(ChildOptionDecisions::class)->filter($childId, $rootId, $optionIds) as $optionId) {
                        if (DB::table('category_child_option')->where('child_id', $childId)->where('category_id', 0)->where('option_id', $optionId)->exists()) {
                            continue;
                        }

                        DB::table('category_child_option')->insert(['child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId, 'reorder' => 0]);
                        $linked++;
                    }
                }
            }

            // the groups sit in the alphabet of their role like every other (DisplayOrderSeeder numbers them)
            app(DisplayOrderSeeder::class)->run();

            $this->command?->info("Rental & winch vocabulary: groups {$made} · links {$linked}");
        });
    }
}
