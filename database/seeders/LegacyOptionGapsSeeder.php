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
 *    engineering consulting, not separately-priced products.
 *  - استشارة هندسية / حجز طيران / حجز فنادق / سياحة داخلية / مساعدة فى البيت
 *    -> were item types in Booking's branches 30/22/12. Withdrawn 2026-08-04
 *    when the kinds collapse took vocabulary out of the item type; see run().
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



    public function run(): void
    {
        $this->addEngineeringOptions();
        $this->enableBooking(self::ENGINEERING_CHILD_ID);
        $this->enableBooking(self::HOME_SERVICES_CHILD_ID);

        /*
         * The five item types this used to add are gone (2026-08-04).
         *
         * «استشارة هندسية»، «حجز طيران»، «حجز فنادق»، «سياحة داخلية»، «مساعدة
         * فى البيت» are all WHAT a business sells, and the kinds collapse moved
         * that out of the item type for good — the type now says only HOW a
         * thing is booked, and all five of these are booked the same way, by
         * appointment. Leaving them here made this seeder write rows that
         * ServiceKindsCollapseSeeder retires and its prune then deletes, on
         * every single run: an add/delete loop with no reader at either end.
         *
         * The option half of this seeder is untouched and is the pattern the
         * item-type half should have followed — addEngineeringOptions() puts
         * كهرباء / بترول / غزل ونسيج where a merchant can price against them.
         *
         * ⚠ Tourism has no option group yet, so «شركة سياحة» can say it takes
         * appointments but not that it sells flights. That gap needs an owner-
         * approved list of travel offerings; it is not invented here.
         */
    }

    private function addEngineeringOptions(): void
    {
        /*
         * Resolved lazily, and only if a field actually needs a home. Creating
         * it up front left «تخصصات استشارية» standing empty on every run: the
         * loop below matches an option by NAME, so after the 2026-08-02
         * consolidation moved all three into «تخصصات الهندسة» there was never
         * anything left to put in the group this had just made.
         */
        $groupId = null;

        foreach (self::ENGINEERING_FIELDS as [$ar, $en]) {
            // Resolve by NAME, not by group: the 2026-08-02 consolidation folded
            // «تخصصات استشارية» into «تخصصات الهندسة», so these rows now live in
            // the engineering group. Scoping to $groupId here would miss them and
            // re-insert, which the global options.name_en unique index rejects.
            $optionId = DB::table('options')->where('name_ar', $ar)->value('id');

            if (! $optionId) {
                $groupId ??= DB::table('option_groups')->where('name_ar', 'تخصصات استشارية')->value('id')
                    ?: DB::table('option_groups')->insertGetId([
                        'name_ar' => 'تخصصات استشارية',
                        'name_en' => 'Consulting Specializations',
                        'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                        'is_active' => 1,
                    ]);

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

}
