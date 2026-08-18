<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «مستكشف لاعبين» — the trade that reads a young player's card.
 *
 *     php artisan db:seed --class=PlayerScoutSeeder
 *
 * Owner, 2026-08-18: «مستكشف لاعبين … المستكشف يحدد الرياضات الخاصة به ومن
 * الممكن ان تكون لكرة القدم فقط».
 *
 * ── Why the scout is a child and the youngster is not ────────────────────────
 *
 * A scout is a business: an academy's recruiter, an agent, a club's kashaf. He
 * advertises, he is booked, he is paid and he is rated — everything a taxonomy
 * child exists to carry. The talented youngster is a PERSON offering himself,
 * sells nothing, and is a `posts` row with `type = 'talent'` instead; see
 * 2026_08_18_000000_add_talent_posts and {@see \App\Models\TalentPost}. Filing
 * him as a child would have handed a fifteen-year-old a catalog, a booking
 * service and a wallet, all of them permanently empty.
 *
 * ── Services copied from «أكاديمية رياضية» #520 ──────────────────────────────
 *
 * The nearest trade on the root and the one a scout actually works beside: both
 * are booked for an appointment, both sell training-shaped work, neither
 * delivers anything. Naming a donor keeps the copy reproducible, the same way
 * `services_from` does in the workshop remodel.
 *
 * The vocabulary — his priced services and the sports he covers — is declared in
 * `data/stray_child_vocabularies.php`, which is where this root's words live.
 *
 * Idempotent.
 */
class PlayerScoutSeeder extends Seeder
{
    private const NAME_AR = 'مستكشف لاعبين';

    private const NAME_EN = 'Player Scout';

    private const DONOR_NAME_AR = 'أكاديمية رياضية';

    private const ROOT_SLUG = 'sports';

    public function run(): void
    {
        $rootId = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->value('id');

        if ($rootId <= 0) {
            $this->command?->warn('  ! جذر «الرياضة» غير موجود.');

            return;
        }

        DB::transaction(function () use ($rootId) {
            $childId = $this->child();
            $donorId = (int) DB::table('category_children_master')
                ->where('name_ar', self::DONOR_NAME_AR)->value('id');

            $attached = DB::table('category_parent_child')->insertOrIgnore([
                'parent_id' => $rootId,
                'child_id' => $childId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $services = $donorId > 0 ? $this->copyServices($childId, $donorId, $rootId) : 0;

            $this->command?->info('Player scout:');
            $this->command?->line("  - الابن #{$childId} : " . ($attached ? 'أُضيف إلى «الرياضة»' : 'كان موجودًا'));
            $this->command?->line("  - صفوف خدمات وإعدادات : {$services}");
        });
    }

    /** Found by name so a re-run never mints a second one. */
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
     * Both halves together, always.
     *
     * A link with no config, or a config with no link, is the half-wiring
     * `ServiceWiringIntegrityTest` exists to catch — a service the child is
     * said to sell and cannot, or one it can sell and is not offered.
     */
    private function copyServices(int $childId, int $donorId, int $rootId): int
    {
        $n = 0;

        foreach (['category_platform_services', 'category_service_configs'] as $table) {
            foreach (DB::table($table)->where('child_id', $donorId)->where('category_id', $rootId)->get() as $row) {
                $row = (array) $row;
                unset($row['id']);
                $row['child_id'] = $childId;
                $row['created_at'] = $row['created_at'] ?? now();
                $row['updated_at'] = now();

                $exists = DB::table($table)->where('child_id', $childId)
                    ->where('category_id', $rootId)
                    ->where('platform_service_id', $row['platform_service_id'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table($table)->insert($row);
                $n++;
            }
        }

        return $n;
    }
}
