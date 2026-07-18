<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Closes the 14-item gap found when the pre-cleanup `options` dump (381 rows,
 * 2026-06-30) was compared against the live options/item-type tables: 367
 * already existed somewhere, 14 existed nowhere. The owner corrected the
 * first read of that list — بترول/كهرباء/غزل ونسيج are not products, they
 * are ENGINEERING CONSULTING SPECIALIZATIONS (a law office has محاماة; an
 * engineering office has several fields under one "استشارة" item type) — and
 * that reshaped how the rest were read too.
 *
 * Final disposition:
 *  - كهرباء / بترول / غزل ونسيج -> OPTIONS on «هندسية» (child 123): fields of
 *    engineering consulting, not separately-priced products. + a new
 *    "استشارة هندسية" item type (booking already has قانونية/محاسبية/
 *    تسويقية/تقنية/أعمال consultations in group 30 — engineering was the
 *    missing peer).
 *  - حجز طيران / حجز فنادق / سياحة داخلية -> item types in Booking's
 *    «سياحة ورحلات» branch (group 22), peers of the سياحة علاجية/حج و عمرة/
 *    سياحة دولية already there (سياحة، child 279, is booking-enabled already).
 *  - مساعدة فى البيت -> one generic item type in «خدمات ومهمات» (group 12).
 *    شغالة/دادة أطفال were NOT actually missing — عاملة نظافة/ناني اطفال
 *    already cover them under different wording, just never reachable.
 *  - إقامة ولائم -> already covered by «إقامة حفلات» (id 298). No-op.
 *  - أخشاب -> already its own category_children_master specialty (id 301),
 *    not an item-type gap. No-op.
 *  - خدمة مدارس, الكريتال -> genuinely ambiguous, left out for the owner.
 *  - خدمات, spear 1 -> junk, discarded.
 *
 * Also enables Booking for «هندسية» (123) and «خدمات منزلية» (144) — both
 * were only wired to delivery/business_offers, so none of this was reachable
 * even where an item type already existed. Idempotent, additive.
 */
class LegacyOptionGapsSeeder extends Seeder
{
    private const ENGINEERING_CHILD_ID = 123;

    private const HOME_SERVICES_CHILD_ID = 144;

    private const OFFICES_CATEGORY_ID = 19;

    private const BOOKING_SERVICE_ID = 1;

    private const ENGINEERING_FIELDS = [
        ['كهرباء', 'Electrical'],
        ['بترول', 'Petroleum'],
        ['غزل ونسيج', 'Textile Engineering'],
    ];

    private const CONSULTATION_GROUP_ID = 30; // استشارات وأعمال

    private const TOURISM_GROUP_ID = 22; // سياحة ورحلات

    private const TASKS_GROUP_ID = 12; // خدمات ومهمات

    public function run(): void
    {
        $this->addEngineeringOptions();
        $this->enableBooking(self::ENGINEERING_CHILD_ID);
        $this->enableBooking(self::HOME_SERVICES_CHILD_ID);

        $this->addItemType('engineering_consultation', 'استشارة هندسية', 'Engineering Consultation', self::CONSULTATION_GROUP_ID);
        $this->addItemType('flight_booking', 'حجز طيران', 'Flight Booking', self::TOURISM_GROUP_ID);
        $this->addItemType('hotel_booking_service', 'حجز فنادق', 'Hotel Booking Service', self::TOURISM_GROUP_ID);
        $this->addItemType('domestic_tourism', 'سياحة داخلية', 'Domestic Tourism', self::TOURISM_GROUP_ID);
        $this->addItemType('home_helper', 'مساعدة فى البيت', 'Home Helper', self::TASKS_GROUP_ID);
    }

    private function addEngineeringOptions(): void
    {
        $groupId = DB::table('option_groups')->where('name_ar', 'تخصصات استشارية')->value('id');

        if (! $groupId) {
            $groupId = DB::table('option_groups')->insertGetId([
                'name_ar' => 'تخصصات استشارية',
                'name_en' => 'Consulting Specializations',
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
            ]);
        }

        foreach (self::ENGINEERING_FIELDS as [$ar, $en]) {
            $optionId = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if (! $optionId) {
                $optionId = DB::table('options')->insertGetId([
                    'group_id' => $groupId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                ]);
            }

            $linked = DB::table('category_child_option')
                ->where('child_id', self::ENGINEERING_CHILD_ID)
                ->where('option_id', $optionId)
                ->exists();

            if (! $linked) {
                DB::table('category_child_option')->insert([
                    'child_id' => self::ENGINEERING_CHILD_ID,
                    'option_id' => $optionId,
                    'reorder' => 0,
                ]);
            }
        }
    }

    private function enableBooking(int $childId): void
    {
        $exists = DB::table('category_platform_services')
            ->where('child_id', $childId)
            ->where('platform_service_id', self::BOOKING_SERVICE_ID)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('category_platform_services')->insert([
            'category_id' => self::OFFICES_CATEGORY_ID,
            'child_id' => $childId,
            'platform_service_id' => self::BOOKING_SERVICE_ID,
            'is_active' => 1,
            'sort_order' => 1 + (int) DB::table('category_platform_services')->where('child_id', $childId)->max('sort_order'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addItemType(string $key, string $ar, string $en, int $groupId): void
    {
        $typeId = DB::table('platform_service_item_types')->where('key', $key)->value('id');

        if (! $typeId) {
            $typeId = DB::table('platform_service_item_types')->insertGetId([
                'platform_service_id' => self::BOOKING_SERVICE_ID,
                'key' => $key,
                'name_ar' => $ar,
                'name_en' => $en,
                'is_default' => 0,
                'is_active' => 1,
                'sort_order' => 1 + (int) DB::table('platform_service_item_types')->max('sort_order'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $linked = DB::table('platform_service_item_group_type')
            ->where('group_id', $groupId)
            ->where('item_type_id', $typeId)
            ->exists();

        if (! $linked) {
            DB::table('platform_service_item_group_type')->insert([
                'group_id' => $groupId,
                'item_type_id' => $typeId,
            ]);
        }
    }
}
