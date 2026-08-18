<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Folds the «كوافير» trade-root back into مهن وحرفيين.
 *
 *     php artisan db:seed --class=SalonRemodelSeeder
 *
 * See data/salon_taxonomy.php for what was wrong and why this is the shape of
 * the fix. In short: root #443 was a TRADE sitting among DOMAINS, it split one
 * trade across three children, and the child that should have held the salon's
 * priced services held none of them.
 *
 * Order matters and is the same in every remodel here:
 *
 *   1. vocabulary first — the keeper must be able to say everything the retired
 *      children could, BEFORE anything moves onto it;
 *   2. accounts next — the audience is written to `option_user` and only then is
 *      the account re-pointed, so a crash between the two loses nothing;
 *   3. detachment last, and only for a child that no longer holds an account.
 *
 * Idempotent: a second run reports zero of everything.
 */
class SalonRemodelSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/salon_taxonomy.php';

        DB::transaction(function () use ($data) {
            $keeper = (int) $data['keep_child_id'];
            $target = (int) $data['target_root_id'];
            $retiring = (int) $data['retire_root_id'];

            $vocab = $this->giveKeeperTheVocabulary($data, $keeper);
            $services = $this->carryServices($retiring, $target, $keeper);
            $moved = $this->moveAccounts($data, $keeper, $target, $retiring);
            /*
             * The last two steps are REVERSED as of 2026-08-17 — see
             * `root_restored_on` in the data file. Everything above stays: the
             * keeper's vocabulary, the carried services and the moved accounts
             * with their audience written out are all still right, and all
             * still idempotent. Only the teardown stops, because the owner
             * ruled the root a real place of work. Left running, it would
             * detach the two children on every seed and SalonRootRestoreSeeder
             * would re-attach them on the next line.
             */
            $restored = ! empty($data['root_restored_on']);

            $detached = $restored ? 0 : $this->detach($data, $retiring);
            $rootOff = $restored ? false : $this->retireRoot($retiring);

            $this->command?->info('Salon remodel applied:');
            $this->command?->line("  - خيارات أُضيفت إلى «كوافير» #{$keeper} : {$vocab}");
            $this->command?->line("  - خدمات نُقلت إلى الجذر #{$target} : {$services}");
            $this->command?->line('  - حسابات نُقلت : ' . count($moved));
            $this->command?->line("  - أبناء فُصلوا عن #{$retiring} : {$detached}");
            $this->command?->line('  - الجذر «كوافير» عُطّل : ' . ($rootOff ? 'نعم' : 'كان معطّلًا'));

            foreach ($moved as $row) {
                $this->command?->line("      #{$row['id']} {$row['name']} : {$row['from']} → كوافير (+ {$row['option']})");
            }
        });
    }

    /**
     * Everything the two retired children could say, said by the one that stays:
     * the priced salon services, the audience axis, and the two loose facets.
     */
    private function giveKeeperTheVocabulary(array $data, int $keeper): int
    {
        $optionIds = collect()
            ->merge($this->groupOptions($data['line_group_name_ar']))
            ->merge($this->groupOptions($data['audience_group_name_ar']))
            ->merge(DB::table('options')->whereIn('name_ar', $data['carry_options'])->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique();

        $added = 0;
        $order = (int) DB::table('category_child_option')->where('child_id', $keeper)->max('reorder');

        foreach ($optionIds as $optionId) {
            $exists = DB::table('category_child_option')
                ->where('child_id', $keeper)
                ->where('option_id', $optionId)
                ->exists();

            if ($exists) {
                continue;
            }

            // category_id 0 = shared with every root this child belongs to. It
            // belongs to one, and scoping it would only invite a second copy
            // the day it belongs to two.
            DB::table('category_child_option')->insert([
                'child_id' => $keeper,
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => ++$order,
            ]);

            $added++;
        }

        return $added;
    }

    /** @return \Illuminate\Support\Collection<int,int> */
    private function groupOptions(string $groupNameAr)
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $groupNameAr)->value('id');

        if ($groupId <= 0) {
            $this->command?->warn("  ! مجموعة «{$groupNameAr}» غير موجودة — تُخطّت.");

            return collect();
        }

        return DB::table('options')->where('group_id', $groupId)->pluck('id');
    }

    /**
     * Whatever the retired root offered, the keeper must offer under its own
     * root — otherwise a moved account keeps its trade and loses its services.
     * The config is COPIED, not invented, so booking keeps the exact shape the
     * salons were already sold under.
     */
    private function carryServices(int $retiring, int $target, int $keeper): int
    {
        $writer = app(ChildServiceWriter::class);
        $carried = 0;

        $rows = DB::table('category_platform_services')
            ->where('category_id', $retiring)
            ->where('is_active', 1)
            ->get(['child_id', 'platform_service_id']);

        foreach ($rows as $row) {
            $serviceId = (int) $row->platform_service_id;

            $already = DB::table('category_platform_services')
                ->where('category_id', $target)
                ->where('child_id', $keeper)
                ->where('platform_service_id', $serviceId)
                ->where('is_active', 1)
                ->exists();

            if ($already) {
                continue;
            }

            $config = json_decode((string) DB::table('category_service_configs')
                ->where('category_id', $retiring)
                ->where('child_id', (int) $row->child_id)
                ->where('platform_service_id', $serviceId)
                ->value('config'), true) ?: [];

            $writer->enable($target, $keeper, $serviceId, $config, null, null, 'salon-remodel');
            $carried++;
        }

        return $carried;
    }

    /**
     * @return array<int, array{id:int,name:string,from:string,option:string}>
     */
    private function moveAccounts(array $data, int $keeper, int $target, int $retiring): array
    {
        $moved = [];

        foreach ($data['retire'] as $childName => $audience) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $childName)->value('id');
            $optionId = (int) DB::table('options')->where('name_ar', $audience)->value('id');

            if ($childId <= 0) {
                continue;
            }

            $accounts = DB::table('users')->where('category_child_id', $childId)->get(['id', 'name']);

            foreach ($accounts as $account) {
                // The claim is recorded BEFORE the move: an account that lands
                // on «كوافير» with nothing ticked has lost the only thing its
                // old child was telling anyone.
                if ($optionId > 0) {
                    DB::table('option_user')->updateOrInsert(
                        ['user_id' => (int) $account->id, 'option_id' => $optionId],
                        []
                    );
                }

                DB::table('users')->where('id', $account->id)->update([
                    'category_child_id' => $keeper,
                    'category_id' => $target,
                ]);

                $moved[] = [
                    'id' => (int) $account->id,
                    'name' => (string) $account->name,
                    'from' => $childName,
                    'option' => $audience,
                ];
            }
        }

        // Anyone filed straight on the root without a child comes along too.
        DB::table('users')->where('category_id', $retiring)->update(['category_id' => $target]);

        return $moved;
    }

    private function detach(array $data, int $retiring): int
    {
        $detached = 0;

        foreach (array_keys($data['retire']) as $childName) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $childName)->value('id');

            if ($childId <= 0) {
                continue;
            }

            if (DB::table('users')->where('category_child_id', $childId)->exists()) {
                $this->command?->warn("  ! تُرك «{$childName}» مرتبطًا — ما زال عليه نشاط.");

                continue;
            }

            $detached += DB::table('category_parent_child')
                ->where('parent_id', $retiring)
                ->where('child_id', $childId)
                ->delete();

            // Wiring for a child no root can reach is debris, and the platform
            // already has one rule for it (OrphanChildLinksCleanupSeeder): the
            // links, fees and option rows GO, the config is only deactivated —
            // it holds the admin's work — and the master row is untouched, which
            // is what keeps the detachment undoable.
            DB::table('category_service_configs')
                ->where('child_id', $childId)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            foreach (['category_platform_services', 'category_child_service_fees', 'category_child_option'] as $table) {
                DB::table($table)->where('child_id', $childId)->delete();
            }
        }

        return $detached;
    }

    /** Deactivated, not deleted — the row is half the undo record. */
    private function retireRoot(int $retiring): bool
    {
        if (DB::table('category_parent_child')->where('parent_id', $retiring)->exists()) {
            $this->command?->warn("  ! الجذر #{$retiring} ما زال عليه أبناء — تُرك مفعّلًا.");

            return false;
        }

        return DB::table('categories')
            ->where('id', $retiring)
            ->where('is_active', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]) > 0;
    }
}
