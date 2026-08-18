<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puts «كوافير» #443 back, because a salon is a PLACE and not only a trade.
 *
 *     php artisan db:seed --class=SalonRootRestoreSeeder
 *
 * Owner, 2026-08-17: «الاب كوافير حذف بالخطأ وهو محل عمل بالفعل. اما الموجود
 * فى مهن وحرفيين هو الفني نفسه، ويمكن ان يكون بيعمل بالمهمة او قام بالتسجيل
 * حتى يتلقى عرض وظائف».
 *
 * ── Why this overrules the 2026-08-09 remodel ────────────────────────────────
 *
 * `salon_taxonomy.php` retired the root on the rule that every root answers
 * WHERE a business stands while #443 answered WHAT it does. The rule is right;
 * the reading of this trade was not. «كوافير» is BOTH — a shop with chairs and
 * a rent, and a person with a kit — and the platform already keeps that pair
 * apart everywhere else: `workshop_taxonomy.php` says in as many words that
 * «حداد» there is the WORKSHOP while #259 under مهن وحرفيين is the tradesman,
 * and that the owner ruled on 2026-08-09 — the SAME DAY — that the two rows
 * stay apart. The salon remodel collapsed the pair that the metal shop kept.
 *
 * A technician who works بالمهمة, or who registered only to be offered work,
 * is not a salon and must not be returned to someone searching for one.
 *
 * ── What the undo actually costs ─────────────────────────────────────────────
 *
 * The retired file claims «re-inserting two pivot rows and one flag undoes the
 * whole thing». It does not. `SalonRemodelSeeder::detach()` DELETED each
 * child's rows in `category_child_option`, `category_platform_services` and
 * `category_child_service_fees`, and only deactivated the configs. So the two
 * children come back mute and unwired unless their vocabulary is rebuilt, which
 * is what this does — from «كوافير» #136, the keeper that was given the salon's
 * priced services in the remodel and still holds them.
 *
 * ── What it deliberately does NOT do ─────────────────────────────────────────
 *
 * It moves no account. The six now standing on #136 were re-pointed there in
 * August and none of them reads as a salon — «رحيق فاشون»، «Oriflame»، «بيع
 * ملابس»، «خلفاوى للتجارة والتوزيع» — so #443 is restored empty and receives
 * salons from here on. Moving live merchants is the owner's call and is one
 * command away once he makes it.
 *
 * It also leaves «الجمهور المستهدف» exactly where the remodel put it. The two
 * children are named for gender and the axis says it too — a real duplication,
 * and the one part of the remodel worth keeping — but collapsing them into a
 * single «صالون كوافير» is a redesign, not an undo, and the instruction was
 * that the root was removed BY MISTAKE.
 *
 * Idempotent.
 */
class SalonRootRestoreSeeder extends Seeder
{
    private const ROOT_ID = 443;

    /** The keeper whose vocabulary the salon children are rebuilt from. */
    private const DONOR_CHILD = 136;

    private const CHILDREN = [164, 184]; // كوافير حريمى، كوافير رجالي

    /**
     * The services a salon actually sells through — named, not inferred.
     *
     * A chair is booked by appointment and that is the whole surface. Every
     * other service on these children is a config the detach left behind, and
     * a sleeping config is not evidence that the trade wanted it.
     */
    private const SELLS = ['booking'];

    /**
     * Not this seeder's to decide, whatever the salon sells.
     *
     * `business_offers` is DERIVED every run from «does this child have an
     * active typed service» (BusinessOffersEnablementSeeder), so sleeping it
     * here does not settle anything — the next derivation switches it straight
     * back on, and the two seeders take turns undoing each other. Booking is
     * live on these children, so the derivation is right and this is simply
     * not the file that owns the answer.
     */
    private const DERIVED = ['business_offers'];

    /** Where the keeper stands, and the mark the carry leaves on a config. */
    private const KEEPER_ROOT = 6;

    private const CARRY_SOURCE = 'salon-remodel';

    public function run(): void
    {
        DB::transaction(function () {
            $root = DB::table('categories')->where('id', self::ROOT_ID)->first();

            if (! $root) {
                $this->command?->warn('  ! الجذر #' . self::ROOT_ID . ' غير موجود — لا شىء يُستعاد.');

                return;
            }

            $reactivated = DB::table('categories')->where('id', self::ROOT_ID)
                ->where('is_active', 0)
                ->update(['is_active' => 1, 'updated_at' => now()]);

            $attached = $options = $services = $configs = $slept = 0;

            foreach (self::CHILDREN as $childId) {
                $attached += DB::table('category_parent_child')->insertOrIgnore([
                    'parent_id' => self::ROOT_ID,
                    'child_id' => $childId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $options += $this->rebuildVocabulary($childId);
                $services += $this->rebuildServiceLinks($childId);

                $configs += $this->reactivateBoundedConfigs($childId);
                $slept += $this->sleepWhatItDoesNotSell($childId);
            }

            $slept += $this->sleepWhatTheCarryBrought();

            $this->command?->info('Salon root restore:');
            $this->command?->line('  - الجذر أُعيد تفعيله : ' . ($reactivated ? 'نعم' : 'كان مفعّلًا'));
            $this->command?->line("  - أبناء أُعيد ربطهم : {$attached}");
            $this->command?->line("  - خيارات أُعيدت : {$options}");
            $this->command?->line("  - روابط خدمات أُعيدت : {$services}");
            $this->command?->line("  - إعدادات أُعيد تفعيلها : {$configs}");
            $this->command?->line("  - خدمات لا يبيعها أُنيمت : {$slept}");
        });
    }

    /**
     * Wake the configs the detach put to sleep — but only the ones that BIND.
     *
     * The detach deactivated every config rather than deleting it, because the
     * admin's own work is in them. Two of them per child were never bounded:
     * `delivery` and `schedules` carry an empty `allowed_item_types`, which on
     * this platform means «offer every type the service has» — a salon listing
     * freight lorries. Two separate guards forbid it
     * (`BoundUnboundedConfigsTest`, `ChildOptionRedistributionTest`), and
     * waking them blind turned both red and then spread: SalonRemodelSeeder
     * carries whatever is LIVE on these children onto «كوافير» #136, so three
     * unbounded configs landed on the keeper too.
     *
     * So a sleeping config stays asleep unless it says what it may list. The
     * owner can bound and enable either one from the admin screen whenever a
     * salon actually delivers something.
     */
    private function reactivateBoundedConfigs(int $childId): int
    {
        $woken = 0;

        foreach (DB::table('category_service_configs as c')
            ->join('platform_services as s', 's.id', '=', 'c.platform_service_id')
            ->where('c.child_id', $childId)
            ->where('c.category_id', self::ROOT_ID)
            ->where('c.is_active', 0)
            ->get(['c.id', 'c.config', 's.key']) as $cfg) {
            if (! in_array((string) $cfg->key, self::SELLS, true)) {
                continue;
            }

            $allowed = json_decode((string) $cfg->config, true)['allowed_item_types'] ?? [];

            if ($allowed === []) {
                continue;
            }

            $woken += DB::table('category_service_configs')->where('id', $cfg->id)
                ->update(['is_active' => 1, 'updated_at' => now()]);
        }

        return $woken;
    }

    /**
     * Put back to sleep anything this seeder woke that the salon does not sell.
     *
     * The bounded rule above was necessary and not sufficient. `menu` on these
     * children was bounded — to `menu_food` — and woke on the first run, so a
     * hairdresser was offered a FOOD menu, and SalonRemodelSeeder then carried
     * it onto «كوافير» #136 exactly as it carried the unbounded ones. Being
     * bounded says a config names its types; it says nothing about whether the
     * types belong to this trade.
     *
     * Both halves go down together. A live link over a sleeping config is a
     * service that says it is sold and cannot be — `ServiceWiringIntegrityTest`
     * fails on precisely that, and it is how a dormant service wakes up half
     * wired.
     *
     * Scoped to the salon's own root, and to the two children this seeder
     * touched: whatever another root asks of these ids is that root's business.
     */
    private function sleepWhatItDoesNotSell(int $childId): int
    {
        $ids = DB::table('platform_services')
            ->whereNotIn('key', array_merge(self::SELLS, self::DERIVED))->pluck('id');

        $n = 0;

        foreach (['category_service_configs', 'category_platform_services'] as $table) {
            $n += DB::table($table)
                ->where('child_id', $childId)
                ->where('category_id', self::ROOT_ID)
                ->whereIn('platform_service_id', $ids)
                ->where('is_active', 1)
                ->update(['is_active' => 0, 'updated_at' => now()]);
        }

        return $n;
    }

    /**
     * Follow the mistake to where it was copied.
     *
     * `SalonRemodelSeeder::carryServices()` puts whatever is LIVE on the
     * retiring root's children onto the keeper, so the food menu this seeder
     * woke on #164 was carried to «كوافير» #136 under مهن وحرفيين — a second
     * child holding a menu it cannot cook, in a root this seeder never touches.
     * Sleeping the source is not enough: the carry already happened, and it
     * copies rather than syncs, so nothing later takes it back.
     *
     * Matched by the mark the carry leaves — `config_source` — and not by
     * service alone. That is the difference between undoing this seeder's own
     * consequence and reaching into wiring somebody else decided: #136 also
     * holds `booking` from services_bulk and `business_offers` derived every
     * run, and neither is any of this seeder's business.
     */
    private function sleepWhatTheCarryBrought(): int
    {
        $ids = DB::table('platform_services')
            ->whereNotIn('key', array_merge(self::SELLS, self::DERIVED))->pluck('id');

        $carried = DB::table('category_service_configs')
            ->where('child_id', self::DONOR_CHILD)
            ->where('category_id', self::KEEPER_ROOT)
            ->whereIn('platform_service_id', $ids)
            ->where('is_active', 1)
            ->where('config', 'like', '%"config_source":"' . self::CARRY_SOURCE . '"%')
            ->pluck('platform_service_id');

        if ($carried->isEmpty()) {
            return 0;
        }

        $n = 0;

        foreach (['category_service_configs', 'category_platform_services'] as $table) {
            $n += DB::table($table)
                ->where('child_id', self::DONOR_CHILD)
                ->where('category_id', self::KEEPER_ROOT)
                ->whereIn('platform_service_id', $carried)
                ->where('is_active', 1)
                ->update(['is_active' => 0, 'updated_at' => now()]);
        }

        return $n;
    }

    /**
     * Copy the keeper's vocabulary onto the salon child.
     *
     * SHARED rows only. The keeper's links are shared, and a salon says the same
     * things whichever door it is reached through — there is nothing here that
     * differs per root, unlike a furniture child that means one thing in a
     * workshop and another in a showroom.
     */
    private function rebuildVocabulary(int $childId): int
    {
        $added = 0;

        foreach (DB::table('category_child_option')->where('child_id', self::DONOR_CHILD)
            ->where('category_id', 0)->get(['option_id', 'reorder']) as $row) {
            $exists = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->where('option_id', (int) $row->option_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('category_child_option')->insert([
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => (int) $row->option_id,
                'reorder' => (int) $row->reorder,
            ]);

            $added++;
        }

        return $added;
    }

    /** One link per config the child still carries, so neither half stands alone. */
    private function rebuildServiceLinks(int $childId): int
    {
        $added = 0;

        foreach (DB::table('category_service_configs')->where('child_id', $childId)
            ->where('category_id', self::ROOT_ID)->get(['platform_service_id', 'is_active', 'sort_order']) as $cfg) {
            $exists = DB::table('category_platform_services')
                ->where('child_id', $childId)
                ->where('category_id', self::ROOT_ID)
                ->where('platform_service_id', $cfg->platform_service_id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('category_platform_services')->insert([
                'category_id' => self::ROOT_ID,
                'child_id' => $childId,
                'platform_service_id' => $cfg->platform_service_id,
                'is_active' => 1,
                'sort_order' => $cfg->sort_order ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $added++;
        }

        return $added;
    }
}
