<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «سيارة من المالك» — the side of the car market with no showroom behind it.
 *
 *     php artisan db:seed --class=CarOwnerListingSeeder
 *
 * Owner, 2026-08-17: «وايضا عرض سيارة للبيع من المالك أو الايجار» → «ابن جديد
 * سيارة من المالك».
 *
 * **A child, not an option, and the platform had already answered this once.**
 * `real_estate_taxonomy.php` says it in as many words about property: «مالك
 * عقار is the owner listing their own unit with no broker in between — the «من
 * المالك» side of the market that buyers filter for explicitly». A private
 * seller is a different ACCOUNT from a dealer — different trust, different
 * history, different vocabulary — not a tick a showroom puts on itself. Put in
 * «نوع التعامل» instead, a معرض could label itself «من المالك» and the filter
 * every Egyptian car buyer uses first would be worthless.
 *
 * ── What it gets, and what it does not ───────────────────────────────────────
 *
 * It takes «معرض سيارات» #188's vocabulary minus the two things only a trader
 * says:
 *
 *   نوع المركبة       line       سيدان · SUV · بيك أب — the thing listed
 *   ماركات السيارات   modifier   all 43; a marque is a price either way
 *   حالة المنتج       modifier   جديد · مستعمل · كسر زيرو
 *   نوع التعامل       modifier   بيع · إيجار · تبديل — but NOT «شراء»
 *   الدفع والسداد     descriptive كاش · تقسيط
 *
 * «شراء» is the showroom's own offer — «بنشترى عربيتك» on the window — and an
 * individual selling his car is not in the business of buying yours. It is the
 * half of the split made on the same day, and this is the child that shows why
 * the split was worth making: merged into «بيع وشراء» the two sides of the
 * market could not be told apart.
 *
 * «تقسيط» was left off at first, on the reading that instalments are a dealer's
 * facility. `PaymentTermsScopeTest` is the reason it is here: the platform's
 * rule is that كاش and تقسيط travel TOGETHER unless a decision separates them —
 * «these carry one payment term without the other and nobody decided that» — so
 * dropping one by hand in a seeder is making his ruling for him. Both are
 * granted and the withdrawal screen is where one comes off.
 *
 * ── Services ─────────────────────────────────────────────────────────────────
 *
 * `menu` on the `menu_vehicles` kind, copied row for row from #188, because
 * `listing_service_children.php` already decided the shape: «a car is never two
 * of a kind, so it is a listing, not a catalogue product». Plus
 * `business_offers`. NOT `retail` — a retail shelf is a catalogue of stock and
 * this child has one car.
 *
 * Idempotent, and additive only: it never unlinks anything.
 */
class CarOwnerListingSeeder extends Seeder
{
    private const NAME_AR = 'سيارة من المالك';

    private const NAME_EN = 'Car From Owner';

    private const ROOT_SLUG = 'cars';

    /** The showroom it borrows its vocabulary and its listing service from. */
    private const DONOR_CHILD = 188;

    private const SERVICES = ['menu', 'business_offers'];

    /** group name_ar => option name_ar list, or 'all' for the whole group. */
    private const VOCABULARY = [
        'نوع المركبة' => 'all',
        'ماركات السيارات' => 'all',
        'حالة المنتج' => ['جديد', 'مستعمل', 'كسر زيرو'],
        'نوع التعامل' => ['بيع', 'إيجار', 'تبديل'],
        'الدفع والسداد' => ['كاش', 'تقسيط'],
    ];

    public function run(): void
    {
        $rootId = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->value('id');

        if ($rootId <= 0) {
            $this->command?->warn('  ! جذر «سيارات» غير موجود — لم يُنفَّذ شيء.');

            return;
        }

        DB::transaction(function () use ($rootId) {
            $childId = $this->child();

            DB::table('category_parent_child')->insertOrIgnore([
                'parent_id' => $rootId,
                'child_id' => $childId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $linked = $this->vocabulary($childId);
            [$services, $configs] = $this->services($childId, $rootId);

            $this->command?->info('Car owner listing:');
            $this->command?->line("  - الابن : «" . self::NAME_AR . "» #{$childId} تحت الجذر {$rootId}");
            $this->command?->line("  - روابط خيارات أُضيفت : {$linked}");
            $this->command?->line("  - خدمات : {$services} · إعدادات : {$configs}");
        });
    }

    private function child(): int
    {
        $id = (int) DB::table('category_children_master')->where('name_ar', self::NAME_AR)->value('id');

        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('category_children_master')->insertGetId([
            'name_ar' => self::NAME_AR,
            'name_en' => self::NAME_EN,
            'reorder' => (int) DB::table('category_children_master')->max('reorder') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Shared rows (`category_id = 0`). The child stands under one root and a
     * shared row is every root's, so nothing is decided by scoping it — and if
     * he ever files it under a second root it arrives speaking.
     */
    private function vocabulary(int $childId): int
    {
        $added = 0;

        foreach (self::VOCABULARY as $groupAr => $wanted) {
            $ids = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', $groupAr)
                ->when($wanted !== 'all', fn ($q) => $q->whereIn('o.name_ar', $wanted))
                ->pluck('o.id');

            if ($wanted !== 'all' && $ids->count() !== count($wanted)) {
                $missing = count($wanted) - $ids->count();
                $this->command?->warn("  ! «{$groupAr}» → {$missing} اسمًا لا يطابق شيئًا.");
            }

            foreach ($ids as $optionId) {
                $added += DB::table('category_child_option')->insertOrIgnore([
                    'child_id' => $childId,
                    'category_id' => 0,
                    'option_id' => (int) $optionId,
                    'reorder' => 0,
                ]);
            }
        }

        return $added;
    }

    /**
     * Copied from the donor rather than written out, so the listing kind and
     * its `allowed_item_types` stay whatever #188's are — one place decides
     * what a car listing looks like.
     *
     * @return array{0:int,1:int}
     */
    private function services(int $childId, int $rootId): array
    {
        $serviceIds = DB::table('platform_services')->whereIn('key', self::SERVICES)->pluck('id');

        $links = $configs = 0;

        foreach ($serviceIds as $serviceId) {
            $donorLink = DB::table('category_platform_services')
                ->where('child_id', self::DONOR_CHILD)
                ->where('platform_service_id', $serviceId)
                ->first();

            if ($donorLink) {
                $links += DB::table('category_platform_services')->insertOrIgnore([
                    'category_id' => $rootId,
                    'child_id' => $childId,
                    'platform_service_id' => $serviceId,
                    'is_active' => $donorLink->is_active,
                    'sort_order' => $donorLink->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $donorConfig = DB::table('category_service_configs')
                ->where('child_id', self::DONOR_CHILD)
                ->where('platform_service_id', $serviceId)
                ->first();

            if ($donorConfig) {
                $configs += DB::table('category_service_configs')->insertOrIgnore([
                    'category_id' => $rootId,
                    'child_id' => $childId,
                    'platform_service_id' => $serviceId,
                    'config' => $donorConfig->config,
                    'is_active' => $donorConfig->is_active,
                    'sort_order' => $donorConfig->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return [$links, $configs];
    }
}
