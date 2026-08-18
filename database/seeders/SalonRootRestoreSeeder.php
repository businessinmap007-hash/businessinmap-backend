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

            $attached = $options = $services = $configs = 0;

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
            }

            $this->command?->info('Salon root restore:');
            $this->command?->line('  - الجذر أُعيد تفعيله : ' . ($reactivated ? 'نعم' : 'كان مفعّلًا'));
            $this->command?->line("  - أبناء أُعيد ربطهم : {$attached}");
            $this->command?->line("  - خيارات أُعيدت : {$options}");
            $this->command?->line("  - روابط خدمات أُعيدت : {$services}");
            $this->command?->line("  - إعدادات أُعيد تفعيلها : {$configs}");
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

        foreach (DB::table('category_service_configs')
            ->where('child_id', $childId)
            ->where('category_id', self::ROOT_ID)
            ->where('is_active', 0)
            ->get(['id', 'config']) as $cfg) {
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
