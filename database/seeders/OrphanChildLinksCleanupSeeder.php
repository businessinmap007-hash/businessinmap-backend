<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Clears the option links and service configs held by children that belong to
 * no root at all.
 *
 *   php artisan db:seed --class=OrphanChildLinksCleanupSeeder
 *
 * 80 children sit outside every root — retired star ratings, duplicates that
 * were unlinked, rows superseded by a remodel — and between them they still
 * held 1,810 option links and 97 service configs. None of it is reachable
 * today, which is exactly what makes it dangerous: the moment one of those
 * children is linked to a root again it arrives carrying a stale answer sheet.
 * That is the same failure the hotels hit from the other direction, where the
 * facility options stayed behind on the detached star children while the live
 * ones had none.
 *
 * Two guards:
 *
 * 1. A rootless child that still holds ACCOUNTS is never stripped. Where an
 *    identically-named child IS linked under that account's own root, the
 *    account is moved there first and the row then cleans normally — «رخام
 *    وجرانيت» exists twice, and the two factories on the rootless copy belong
 *    on the linked one.
 *
 * 2. Service configs are deactivated, not deleted, so the admin screens can
 *    still show and revive them.
 *
 * Service FEES are deleted outright, not deactivated: a fee row is money
 *    config, the fee screens list it whether or not it is on, and the five
 *    retired hotel star ratings were still being given 10 EGP booking fees in
 *    July — months after nothing could reach them.
 *
 * The master rows themselves are never touched; re-linking a child to a root
 * gives it a clean sheet rather than a resurrected one.
 */
class OrphanChildLinksCleanupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $refiled = $this->refileStrandedAccounts();

            $orphans = $this->rootless();

            $held = DB::table('users')
                ->whereIn('category_child_id', $orphans)
                ->select('category_child_id')
                ->distinct()
                ->pluck('category_child_id');

            $safe = $orphans->diff($held);

            $options = DB::table('category_child_option')->whereIn('child_id', $safe)->delete();

            $configs = DB::table('category_service_configs')
                ->whereIn('child_id', $safe)
                ->where('is_active', 1)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            $fees = DB::table('category_child_service_fees')->whereIn('child_id', $safe)->delete();
            $links = DB::table('category_platform_services')->whereIn('child_id', $safe)->delete();

            $this->command?->info('Orphan child links cleanup:');
            $this->command?->line("  - حسابات نُقلت إلى التوأم المرتبط : {$refiled}");
            $this->command?->line('  - أبناء بلا جذر : ' . $orphans->count() . " (نُظّف منها {$safe->count()})");
            $this->command?->line("  - روابط خيارات أُزيلت : {$options}");
            $this->command?->line("  - إعدادات خدمات عُطّلت : {$configs}");
            $this->command?->line("  - روابط خدمات أُزيلت : {$links}");
            $this->command?->line("  - رسوم خدمات أُزيلت : {$fees}");

            foreach ($held as $childId) {
                $name = DB::table('category_children_master')->where('id', $childId)->value('name_ar');
                $count = DB::table('users')->where('category_child_id', $childId)->count();
                $this->command?->warn("  ! «{$name}» #{$childId} ما زال عليه {$count} حساب — تُرك كما هو.");
            }
        });
    }

    /** @return \Illuminate\Support\Collection<int,int> */
    private function rootless()
    {
        $linked = DB::table('category_parent_child')->distinct()->pluck('child_id');

        return DB::table('category_children_master')->whereNotIn('id', $linked)->pluck('id');
    }

    /**
     * Move an account off a rootless child onto the identically-named child
     * that IS linked under the root the account already sits in. Same name and
     * same root means the same trade — no judgement call is being made here.
     */
    private function refileStrandedAccounts(): int
    {
        $orphans = $this->rootless();

        $stranded = DB::table('users as u')
            ->join('category_children_master as ch', 'ch.id', '=', 'u.category_child_id')
            ->whereIn('u.category_child_id', $orphans)
            ->get(['u.id', 'u.category_id', 'ch.name_ar']);

        $moved = 0;

        foreach ($stranded as $user) {
            $twin = DB::table('category_parent_child as pc')
                ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
                ->where('pc.parent_id', $user->category_id)
                ->where('ch.name_ar', $user->name_ar)
                ->orderBy('ch.id')
                ->value('ch.id');

            if (! $twin) {
                continue; // no identically-named child under its root; leave it alone
            }

            DB::table('users')->where('id', $user->id)->update(['category_child_id' => $twin]);
            $moved++;
        }

        return $moved;
    }
}
