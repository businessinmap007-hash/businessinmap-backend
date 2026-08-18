<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «مركز خدمة متكامل» is a workshop's SIZE, not one of its benches.
 *
 *     php artisan db:seed --class=FullServiceCentreSeeder
 *
 * Owner, 2026-08-17: «مركز خدمة متكامل يجب ان يكون ابن منفصل يحمل كل خيارات
 * ورشة سيارات، لان ورشة سيارات ممكن ان تكون ميكانيكا وتعليق فقط، اما مركز
 * سيارات متكامل يمكن ان يحتوى على جميع الخدمات».
 *
 * The 2026-08-10 remodel folded «مركز سيارات» #39 into «ورشة سيارات» #543 as
 * bench #1204, on the reading that it had only ever been «the only way to say I
 * do more than one of the above» — which multi-select had just made
 * unnecessary. That reading missed what the row actually claims. The other
 * thirteen benches name a JOB: سمكرة، فرامل وتعليق، فحص كمبيوتر. «مركز خدمة
 * متكامل» names none of them — it says «all of them, under one roof», which is
 * a different KIND of business from the two-bay garage that does mechanics and
 * suspension. Left as a bench it is a job whose meaning is «every other job on
 * this list», and a customer filtering for it gets whoever ticked it loosely.
 *
 * ── Revived, not minted ──────────────────────────────────────────────────────
 *
 * #39 IS that child. It has stood rootless since the fold — the taxonomy's undo
 * record — and rebuilding by the ORIGINAL id is the rule this platform learned
 * the hard way: a new id strands every config, price and decision keyed to the
 * old one. It is renamed to the owner's words and put back under «ورش ومراكز
 * صيانة».
 *
 * ── It carries the garage's whole vocabulary ─────────────────────────────────
 *
 * «يحمل كل خيارات ورشة سيارات» — every option #543 holds, AT THE SAME SCOPE,
 * minus bench #1204 itself. A full-service centre needs no bench saying it is
 * one; that is what the child now says.
 *
 * ── The two merchants who had already said it ────────────────────────────────
 *
 * «الراعى» and «الحرفيين لصيانه السيارات» ticked #1204 by hand. They are the
 * only two on the platform who did, they are exactly the businesses this child
 * is for, and a merchant's own answer outranks the map everywhere else in this
 * codebase — so they move with the word rather than lose it. The tick is
 * dropped only after they are re-filed, and only for them.
 *
 * Idempotent, and the bench is never taken off «ورشة سيارات» while a merchant
 * still standing there claims it.
 */
class FullServiceCentreSeeder extends Seeder
{
    private const CHILD_ID = 39;

    private const NAME_AR = 'مركز خدمة متكامل';

    private const NAME_EN = 'Full Service Centre';

    private const DONOR_CHILD = 543;

    private const BENCH_OPTION = 1204;

    private const ROOT_SLUG = 'workshops';

    public function run(): void
    {
        $rootId = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->value('id');

        if ($rootId <= 0) {
            $this->command?->warn('  ! جذر «ورش ومراكز صيانة» غير موجود.');

            return;
        }

        DB::transaction(function () use ($rootId) {
            DB::table('category_children_master')->where('id', self::CHILD_ID)
                ->update(['name_ar' => self::NAME_AR, 'name_en' => self::NAME_EN, 'updated_at' => now()]);

            DB::table('category_parent_child')->insertOrIgnore([
                'parent_id' => $rootId,
                'child_id' => self::CHILD_ID,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $linked = $this->carryVocabulary();
            $services = $this->copyServices($rootId);
            $moved = $this->moveCentres($rootId);
            $unbenched = $this->retireBench();

            $this->command?->info('Full service centre:');
            $this->command?->line("  - خيارات نُسخت من «ورشة سيارات» : {$linked}");
            $this->command?->line("  - صفوف خدمات وإعدادات : {$services}");
            $this->command?->line("  - تجّار نُقلوا : {$moved}");
            $this->command?->line("  - المقعد أُزيل من الورشة : {$unbenched}");
        });
    }

    /** Every row #543 holds, at the same scope, except the bench it replaces. */
    private function carryVocabulary(): int
    {
        $blocked = app(ChildOptionDecisions::class)->blockedByChild();

        $added = 0;

        foreach (DB::table('category_child_option')->where('child_id', self::DONOR_CHILD)
            ->get(['option_id', 'category_id', 'reorder']) as $row) {
            $optionId = (int) $row->option_id;

            if ($optionId === self::BENCH_OPTION || isset($blocked[self::CHILD_ID][$optionId])) {
                continue;
            }

            $exists = DB::table('category_child_option')
                ->where('child_id', self::CHILD_ID)
                ->where('category_id', (int) $row->category_id)
                ->where('option_id', $optionId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('category_child_option')->insert([
                'child_id' => self::CHILD_ID,
                'category_id' => (int) $row->category_id,
                'option_id' => $optionId,
                'reorder' => (int) $row->reorder,
            ]);

            $added++;
        }

        return $added;
    }

    /** The same services the garage sells, wired the same way. */
    private function copyServices(int $rootId): int
    {
        $n = 0;

        foreach (['category_platform_services', 'category_service_configs'] as $table) {
            foreach (DB::table($table)->where('child_id', self::DONOR_CHILD)->where('category_id', $rootId)->get() as $row) {
                $row = (array) $row;
                unset($row['id']);
                $row['child_id'] = self::CHILD_ID;
                $row['updated_at'] = now();

                $mine = DB::table($table)->where('child_id', self::CHILD_ID)
                    ->where('category_id', $rootId)
                    ->where('platform_service_id', $row['platform_service_id'])
                    ->first();

                /*
                 * ALIGNED, not merely created. #39 kept three configs from
                 * before the fold — all inactive, all two remodels stale — and
                 * skipping them left the child with live service LINKS over dead
                 * CONFIGS, which is the exact half-wiring
                 * `service-wiring-integrity` exists to catch: the row says the
                 * service is sold and the config says it cannot be.
                 */
                if ($mine) {
                    $stale = array_diff_assoc(
                        array_intersect_key($row, ['config' => 1, 'is_active' => 1, 'sort_order' => 1]),
                        array_intersect_key((array) $mine, ['config' => 1, 'is_active' => 1, 'sort_order' => 1])
                    );

                    if ($stale === []) {
                        continue;
                    }

                    DB::table($table)->where('id', $mine->id)->update($stale + ['updated_at' => now()]);
                    $n++;

                    continue;
                }

                DB::table($table)->insert($row);
                $n++;
            }
        }

        return $n;
    }

    /** The merchants who had already ticked the bench, and only those. */
    private function moveCentres(int $rootId): int
    {
        $ids = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('ou.option_id', self::BENCH_OPTION)
            ->where('u.category_child_id', self::DONOR_CHILD)
            ->pluck('u.id');

        if ($ids->isEmpty()) {
            return 0;
        }

        DB::table('users')->whereIn('id', $ids)
            ->update(['category_id' => $rootId, 'category_child_id' => self::CHILD_ID]);

        // The tick was their claim to BE this. The child says it now.
        DB::table('option_user')->whereIn('user_id', $ids)
            ->where('option_id', self::BENCH_OPTION)->delete();

        return $ids->count();
    }

    /**
     * Take the bench off «ورشة سيارات».
     *
     * The option ROW stays in «تخصصات ورش السيارات», unlinked and unreachable —
     * which is what retirement means here, and readable as the record of what
     * #39 was folded into. Refused while a merchant still standing on the garage
     * claims it.
     */
    private function retireBench(): int
    {
        $stillClaimed = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('ou.option_id', self::BENCH_OPTION)
            ->where('u.category_child_id', self::DONOR_CHILD)
            ->exists();

        if ($stillClaimed) {
            $this->command?->warn('  ! تاجر ما زال مؤشّرًا على المقعد — تُرك مكانه.');

            return 0;
        }

        return DB::table('category_child_option')
            ->where('child_id', self::DONOR_CHILD)
            ->where('option_id', self::BENCH_OPTION)
            ->delete();
    }
}
