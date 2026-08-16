<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Two gaps the units audit left open (owner call 2026-08-02):
 *
 * 1. Coworking was borrowing the halls branch, so it rented «قاعة أفراح»-style
 *    classes instead of what it actually sells. It gets its own `coworking`
 *    branch — a desk, a private office, a meeting room — and منطقة عمل مشتركة
 *    is re-pointed onto it.
 *
 * 2. The hotel branch's 15 room types were sound but incomplete: no quad, no
 *    connecting rooms, no bungalow/tent for resorts and desert camps, no
 *    accessible room. Added. What is NOT added is deliberate — sea view, meal
 *    plan and smoking are ATTRIBUTES of a stay, not units to reserve, so they
 *    belong on the option axis (see «مرافق الإقامة» below).
 *
 *   php artisan db:seed --class=CoworkingAndHotelUnitsSeeder
 *   php artisan db:seed --class=BookingChildModesSeeder
 *
 * Idempotent; nothing deleted.
 */
class CoworkingAndHotelUnitsSeeder extends Seeder
{
    private const COWORKING_BRANCH = ['key' => 'coworking', 'name_ar' => 'مساحات عمل مشتركة', 'name_en' => 'Coworking'];

    private const COWORKING_TYPES = [
        'hot_desk' => ['مقعد مشترك (يومي)', 'Hot Desk'],
        'dedicated_desk' => ['مكتب ثابت', 'Dedicated Desk'],
        'private_office' => ['مكتب خاص', 'Private Office'],
        'meeting_room' => ['غرفة اجتماعات', 'Meeting Room'],
        'event_space' => ['مساحة فعاليات', 'Event Space'],
        'podcast_studio' => ['استوديو تسجيل', 'Podcast Studio'],
    ];

    private const HOTEL_TYPES = [
        'quad_room' => ['غرفة رباعية', 'Quad Room'],
        'connecting_rooms' => ['غرف متصلة', 'Connecting Rooms'],
        'accessible_room' => ['غرفة لذوي الاحتياجات الخاصة', 'Accessible Room'],
        'bungalow' => ['بنجالو', 'Bungalow'],
        'cabin_tent' => ['كابينة / خيمة', 'Cabin / Tent'],
        'pool_villa' => ['فيلا بمسبح خاص', 'Private Pool Villa'],
    ];

    /** Stay attributes — the axis rule says these describe, they are not units. */
    private const STAY_ATTRIBUTES = [
        'إطلالة بحرية' => 'Sea View',
        'إطلالة على المسبح' => 'Pool View',
        'شامل الإفطار' => 'Breakfast Included',
        'إقامة كاملة' => 'Full Board',
        'نصف إقامة' => 'Half Board',
        'غرف غير المدخنين' => 'Non-Smoking',
        'مسموح بالحيوانات الأليفة' => 'Pets Allowed',
        'واي فاي مجاني' => 'Free WiFi',
        'موقف سيارات' => 'Parking',
        'مسبح' => 'Swimming Pool',
        'جيم' => 'Gym Facility',
        'سبا' => 'Spa',
        'مطعم داخلي' => 'On-site Restaurant',
        'خدمة الغرف' => 'Room Service',
        'نقل من المطار' => 'Airport Transfer',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

            $coworking = $this->buildCoworkingBranch($serviceId);
            $hotel = $this->addTypesToBranch($serviceId, 'hotel', self::HOTEL_TYPES);
            $attrs = $this->addStayAttributes();

            $this->command?->info('Coworking + hotel units:');
            $this->command?->line("  - coworking branch types : {$coworking}");
            $this->command?->line("  - hotel room types added : {$hotel}");
            $this->command?->line("  - stay attribute options : {$attrs}");
            $this->command?->line('  NEXT: db:seed --class=BookingChildModesSeeder (re-scopes coworking)');
        });
    }

    private function buildCoworkingBranch(int $serviceId): int
    {
        $groupId = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->where('key', self::COWORKING_BRANCH['key'])->value('id');

        if (! $groupId) {
            $groupId = DB::table('platform_service_item_groups')->insertGetId([
                'platform_service_id' => $serviceId,
                'key' => self::COWORKING_BRANCH['key'],
                'name_ar' => self::COWORKING_BRANCH['name_ar'],
                'name_en' => self::COWORKING_BRANCH['name_en'],
                'is_active' => 1,
                'sort_order' => 1 + (int) DB::table('platform_service_item_groups')
                    ->where('platform_service_id', $serviceId)->max('sort_order'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->addTypesToBranch($serviceId, self::COWORKING_BRANCH['key'], self::COWORKING_TYPES);
    }

    private function addTypesToBranch(int $serviceId, string $branchKey, array $types): int
    {
        $groupId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)->where('key', $branchKey)->value('id');

        if (! $groupId) {
            return 0;
        }

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
            }

            DB::table('platform_service_item_group_type')->updateOrInsert(
                ['group_id' => $groupId, 'item_type_id' => (int) $typeId], []
            );
        }

        return count($types);
    }

    /** Attached to every hotel child — this is what a guest actually filters by. */
    private function addStayAttributes(): int
    {
        $groupId = DB::table('option_groups')->where('name_ar', 'مرافق الإقامة')->value('id')
            ?: DB::table('option_groups')->insertGetId([
                'name_ar' => 'مرافق الإقامة', 'name_en' => 'Stay Amenities',
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
            ]);

        $hotelChildIds = DB::table('category_parent_child as pc')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->where('r.slug', 'tourist-hotels')->pluck('pc.child_id');

        $added = 0;

        foreach (self::STAY_ATTRIBUTES as $ar => $en) {
            /*
             * Looked up ANYWHERE first, and only then in this group.
             *
             * Six of these fifteen no longer live in «مرافق الإقامة»:
             * OptionGroupSplitSeeder moved the two views and the three meal
             * plans out on 2026-08-08 and «نقل من المطار» out on 2026-08-16,
             * because a view, a board basis and a paid transfer are three
             * questions the amenity list was answering as though they were one.
             *
             * Scoped to the group, this loop could not see any of them. It would
             * have built six fresh duplicates inside «مرافق الإقامة» — «Sea View
             * (Stay)» beside «Sea View» — and linked all six to every hotel
             * child, undoing both splits in one run and leaving each question
             * answerable twice. The seeder is in no seeder list, which is the
             * only reason it had not fired yet.
             *
             * A row that exists is a row this seeder links; creating is for a
             * word the platform does not have.
             */
            $optionId = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id')
                ?: DB::table('options')->where('name_ar', $ar)->value('id');

            if (! $optionId) {
                if (DB::table('options')->where('name_en', $en)->exists()) {
                    $en .= ' (Stay)';
                }

                $optionId = DB::table('options')->insertGetId(['group_id' => $groupId, 'name_ar' => $ar, 'name_en' => $en]);
                $added++;
            }

            foreach ($hotelChildIds as $childId) {
                DB::table('category_child_option')->updateOrInsert(
                    ['child_id' => (int) $childId, 'option_id' => (int) $optionId], ['reorder' => 0]
                );
            }
        }

        return $added;
    }
}
