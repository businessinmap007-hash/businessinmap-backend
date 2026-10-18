<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «تأجير سيارات» — a trade that rents a car for a day, a week or a month, the way an estate office rents a flat or a
 * shop.
 *
 *     php artisan db:seed --class=CarRentalChildSeeder
 *
 * ── Why a trade of its own ──────────────────────────────────────────────────
 *
 * A showroom (#188) SELLS cars and, as a side line, rents one now and then; the owner's two pictures are different
 * businesses — «سيارات للبيع» and «سيارات للتأجير». A rental company has no cars for sale, no «مستعمل/جديد»
 * condition and no «بيع/إيجار/تبديل» deal type: every unit is for rent, and what it sells is TIME.
 *
 * ── How it is built ─────────────────────────────────────────────────────────
 *
 * • No new engine. Renting a flat already holds a NAMED unit for a period (`booking_stay`, `requires_bookable_item`,
 *   a `bookable_items` row per unit whose `line_option_id` says what kind it is): the business registers each car as a
 *   unit, names its kind (سيدان / SUV / بيك أب …) and prices a day; the customer picks from-to dates and the engine
 *   multiplies, checks overlap and honours blocked days and season price rules. A week or a month is just a longer
 *   date range.
 * • Vocabulary = the showroom's, minus what only a sale needs (condition, deal type, returns): the vehicle kinds
 *   (lines), the brands, and how it is handed over / paid. The showroom's RULINGS are copied with them, so a broad
 *   group seeder cannot hand back the same day what the owner took off ([[seeder-must-withdraw]]).
 * • Booking = the estate office's period config, written through the one writer that keeps link and config in step.
 *   The id is FIXED (`CHILD_ID`) because `data/booking_patterns.php` names children by id.
 *
 * Idempotent.
 */
class CarRentalChildSeeder extends Seeder
{
    /** Fixed: the booking-pattern map is keyed by child id. */
    public const CHILD_ID = 555;

    private const NAME_AR = 'تأجير سيارات';

    private const NAME_EN = 'Car rental';

    private const ROOT_SLUG = 'cars';

    private const MIRRORS = 'معرض سيارات';

    /** What a rental company says about its cars: the kinds it rents (lines) and the brands. */
    private const LINE_GROUP = 'نوع المركبة';

    private const BRAND_GROUP = 'ماركات السيارات';

    /** How a car is handed over and paid for — the showroom's own words. */
    private const HANDOVER_GROUPS = ['التسليم والاستلام', 'الدفع والسداد'];

    public function run(): void
    {
        DB::transaction(function () {
            $rootId = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->where('parent_id', 0)->value('id');

            if ($rootId <= 0) {
                $this->command?->warn('  ! جذر السيارات غير موجود.');

                return;
            }

            $childId = $this->child();

            if ($childId <= 0) {
                return;
            }

            DB::table('category_parent_child')->insertOrIgnore([
                'parent_id' => $rootId,
                'child_id' => $childId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->mirrorRulings($childId);
            $linked = $this->linkOptions($childId, $rootId);
            $wired = $this->wireServices($childId, $rootId);

            $this->command?->info('Car rental child:');
            $this->command?->line("  - «" . self::NAME_AR . "» #{$childId}");
            $this->command?->line("  - خيارات رُبطت : {$linked}");
            $this->command?->line("  - خدمات وُصلت : {$wired}");
        });
    }

    private function child(): int
    {
        $byName = (int) DB::table('category_children_master')->where('name_ar', self::NAME_AR)->value('id');

        if ($byName > 0) {
            return $byName;
        }

        if (DB::table('category_children_master')->where('id', self::CHILD_ID)->exists()) {
            $this->command?->warn('  ! المعرّف ' . self::CHILD_ID . ' مشغولٌ بابنٍ آخر — اضبط CHILD_ID وملف booking_patterns.php معًا.');

            return 0;
        }

        DB::table('category_children_master')->insert([
            'id' => self::CHILD_ID,
            'name_ar' => self::NAME_AR,
            'name_en' => self::NAME_EN,
            'reorder' => (int) DB::table('category_children_master')->max('reorder') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::CHILD_ID;
    }

    private function mirrorId(): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', self::MIRRORS)->value('id');
    }

    /** The showroom's rulings come with the words they rule on (see the class note). */
    private function mirrorRulings(int $childId): int
    {
        $mirrorId = $this->mirrorId();

        if ($mirrorId <= 0) {
            return 0;
        }

        $n = 0;

        foreach (DB::table('category_child_option_decisions')->where('child_id', $mirrorId)->get() as $ruling) {
            $exists = DB::table('category_child_option_decisions')
                ->where('child_id', $childId)->where('category_id', $ruling->category_id)
                ->where('option_id', $ruling->option_id)->where('kind', $ruling->kind)->exists();

            if ($exists) {
                continue;
            }

            DB::table('category_child_option_decisions')->insert([
                'child_id' => $childId,
                'category_id' => $ruling->category_id,
                'option_id' => $ruling->option_id,
                'kind' => $ruling->kind,
                'source' => 'car-rental-child',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $n++;
        }

        $forbidden = DB::table('category_child_option_decisions')
            ->where('child_id', $childId)->where('kind', 'withdrawn')->pluck('option_id');

        if ($forbidden->isNotEmpty()) {
            DB::table('category_child_option')->where('child_id', $childId)->whereIn('option_id', $forbidden)->delete();
        }

        return $n;
    }

    private function linkOptions(int $childId, int $rootId): int
    {
        $mirrorId = $this->mirrorId();

        if ($mirrorId <= 0) {
            return 0;
        }

        // only what the showroom already answers, and only the groups a rental company shares with it
        $groups = array_merge([self::LINE_GROUP, self::BRAND_GROUP], self::HANDOVER_GROUPS);

        $wanted = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $mirrorId)
            ->whereIn('g.name_ar', $groups)
            ->pluck('o.id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $allowed = app(ChildOptionDecisions::class)->filter($childId, $rootId, $wanted);

        $n = 0;

        foreach ($allowed as $optionId) {
            $exists = DB::table('category_child_option')
                ->where('child_id', $childId)->where('category_id', 0)->where('option_id', $optionId)->exists();

            if ($exists) {
                continue;
            }

            DB::table('category_child_option')->insert([
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => 0,
            ]);
            $n++;
        }

        return $n;
    }

    /** Booking by the period (the estate office's own shape) + offers like every shop beside it. */
    private function wireServices(int $childId, int $rootId): int
    {
        $writer = app(ChildServiceWriter::class);
        $n = 0;

        $plan = [
            'booking' => [
                'booking_modes' => [],
                'item_family' => null,
                'requires_bookable_item' => true,
                'requires_start_end' => true,
                'supports_quantity' => false,
                'supports_guest_count' => true,
                'supports_extras' => false,
                'required_fields' => ['date_range'],
                'item_groups' => [84],
                'allowed_item_types' => ['booking_stay'],
                'bookable_item_kinds' => ['booking_stay'],
                'booking_pattern' => 'stay',
                'booking_patterns' => ['stay'],
            ],
            'business_offers' => [],
        ];

        foreach ($plan as $key => $patch) {
            $serviceId = (int) DB::table('platform_services')->where('key', $key)->value('id');

            if ($serviceId <= 0) {
                $this->command?->warn("  ! خدمة «{$key}» غير موجودة.");

                continue;
            }

            $writer->enable($rootId, $childId, $serviceId, $patch, null, null, 'car-rental-child');
            $n++;
        }

        return $n;
    }
}
