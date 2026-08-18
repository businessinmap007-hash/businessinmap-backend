<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «ناشئ موهوب» — the other side of the scouting pair, and it has to be a child.
 *
 *     php artisan db:seed --class=TalentChildSeeder
 *
 * Owner, 2026-08-18: «فى bim لا يستطيع احد كتابة منشور غير الحسابات البزنس
 * فقط، فكيف لناشئ سيكتب بوست ويعرض فيه موهبته؟ اعتقد ان لابد ان يكون له حساب
 * بزنس… ويكون من خياراته موهوب فى: كرة القدم - مصارعة - تنس الخ».
 *
 * This overturns the shape recommended a day earlier, and the owner's objection
 * is the decisive one: every author on this platform is an account with a child
 * and a vocabulary. A talent card written by a `client` would be the only post
 * on BIM with no trade behind it — no options screen, no discovery filter, and
 * nothing for a scout's search to match against. So the boy gets a business
 * account whose CHILD says what he is.
 *
 * ── One list of sports, not two ──────────────────────────────────────────────
 *
 * «الرياضات المستهدفة» #730 already holds the eighteen — كرة قدم، مصارعة،
 * تنس… — and was written for «مستكشف لاعبين» #550 hours earlier. Minting a
 * second identical list under «موهوب فى» would be the two half-populated
 * versions of one idea that `option_group_splits.php` names as this taxonomy's
 * oldest disease, and it would make the match that the whole feature exists for
 * — the scout's sports against the boy's — a join across two vocabularies.
 *
 * The group is renamed «الرياضات» instead, which is the only heading that reads
 * correctly on both screens: on a scout it is the sports he covers, on a boy it
 * is the sport he plays. `descriptive` on both, because neither is priced BY
 * sport — a scout's trial costs what it costs whether the boy plays football or
 * fences.
 *
 * ── What it does NOT get ─────────────────────────────────────────────────────
 *
 * No `line`, and that is deliberate rather than an omission: the boy sells
 * nothing. His card is a post, the scout pays for it (see
 * TalentScoutingService), and giving him a priced list would hand a fourteen
 * year old an empty catalogue and a pricing screen. The one child on the
 * platform whose whole purpose is to be FOUND rather than to sell.
 *
 * Idempotent.
 */
class TalentChildSeeder extends Seeder
{
    private const NAME_AR = 'ناشئ موهوب';

    private const NAME_EN = 'Young Talent';

    private const ROOT_SLUG = 'sports';

    private const SPORTS_GROUP_WAS = 'الرياضات المستهدفة';

    private const SPORTS_GROUP = 'الرياضات';

    public function run(): void
    {
        $rootId = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->value('id');

        if ($rootId <= 0) {
            $this->command?->warn('  ! جذر «الرياضة» غير موجود.');

            return;
        }

        DB::transaction(function () use ($rootId) {
            $renamed = $this->renameSportsGroup();
            $childId = $this->child();

            DB::table('category_parent_child')->insertOrIgnore([
                'parent_id' => $rootId,
                'child_id' => $childId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $linked = $this->linkSports($childId);
            $wired = $this->wireServices($childId, $rootId);

            $this->command?->info('Talent child:');
            $this->command?->line('  - مجموعة الرياضات أُعيد تسميتها : ' . ($renamed ? 'نعم' : 'كانت باسمها'));
            $this->command?->line("  - «ناشئ موهوب» #{$childId}");
            $this->command?->line("  - رياضات رُبطت : {$linked}");
            $this->command?->line("  - خدمات وُصلت : {$wired}");
        });
    }

    /**
     * «الرياضات المستهدفة» → «الرياضات».
     *
     * Resolved by name everywhere, so the rename is the whole move — there is
     * no key column to update. Idempotent in both directions: if the new name
     * already exists the old row is left alone rather than merged, because two
     * groups of the same eighteen sports is precisely what this avoids.
     */
    private function renameSportsGroup(): bool
    {
        if (DB::table('option_groups')->where('name_ar', self::SPORTS_GROUP)->exists()) {
            return false;
        }

        return DB::table('option_groups')
            ->where('name_ar', self::SPORTS_GROUP_WAS)
            ->update(['name_ar' => self::SPORTS_GROUP, 'name_en' => 'Sports', 'updated_at' => now()]) > 0;
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
     * `booking`, bounded to a trial appointment.
     *
     * A live child must have something it can sell under — two guards say so —
     * and the first attempt used `business_offers` because the boy sells
     * nothing. That service is switched OFF platform-wide, so it satisfies
     * nothing; the guard was right to keep failing.
     *
     * `booking` is the honest answer and not a workaround. It is what every
     * other child of «الرياضة» carries, and it is the very next step of this
     * feature: the scout pays to reveal, contacts him, and books a trial. What
     * he does NOT get is `training` — he trains nobody — nor any priced list.
     *
     * `allowed_item_types` is bound to one type on purpose: an empty array
     * means «offer every type this service has», which is how a boy ends up
     * listed as a hotel stay. `requires_bookable_item` is false — there is no
     * unit to reserve, only him.
     *
     * Both halves together, always: a link with no config is a service that
     * says it is sold and cannot be, and a config with no link is the reverse.
     */
    private function wireServices(int $childId, int $rootId): int
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->where('is_active', 1)->value('id');

        if ($serviceId <= 0) {
            return 0;
        }

        $n = 0;

        foreach (['category_platform_services', 'category_service_configs'] as $table) {
            $exists = DB::table($table)->where('child_id', $childId)
                ->where('category_id', $rootId)->where('platform_service_id', $serviceId)->exists();

            if ($exists) {
                continue;
            }

            $row = [
                'category_id' => $rootId,
                'child_id' => $childId,
                'platform_service_id' => $serviceId,
                'is_active' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($table === 'category_service_configs') {
                $row['config'] = json_encode([
                    'booking_modes' => [],
                    'requires_bookable_item' => false,
                    'requires_start_end' => true,
                    'supports_quantity' => false,
                    'supports_guest_count' => false,
                    'supports_extras' => false,
                    'required_fields' => [],
                    'item_groups' => [],
                    'allowed_item_types' => ['booking_appointment'],
                    'config_source' => 'talent',
                    'config_updated_at' => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE);
            }

            DB::table($table)->insert($row);
            $n++;
        }

        return $n;
    }

    /** Every sport, because a boy may play one and the list is his to pick from. */
    private function linkSports(int $childId): int
    {
        $optionIds = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::SPORTS_GROUP)
            ->pluck('o.id');

        $existing = DB::table('category_child_option')->where('child_id', $childId)
            ->whereIn('option_id', $optionIds)->pluck('option_id')->all();

        $rows = [];

        foreach ($optionIds->diff($existing) as $optionId) {
            $rows[] = ['child_id' => $childId, 'category_id' => 0, 'option_id' => (int) $optionId, 'reorder' => 0];
        }

        if ($rows === []) {
            return 0;
        }

        DB::table('category_child_option')->insert($rows);

        return count($rows);
    }
}
