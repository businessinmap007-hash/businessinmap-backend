<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Turns a bench into an option and a garage into a child.
 *
 *     php artisan db:seed --class=WorkshopRemodelSeeder
 *
 * See data/workshop_taxonomy.php for the owner's words and the four domains.
 *
 * The order is the one every remodel here uses, and each step exists because
 * skipping it loses something:
 *
 *   1. the domain child and its VOCABULARY first — the workshop must be able to
 *      say «سمكرة» before a سمكري is moved onto it;
 *   2. the folded child's OTHER options are carried too (payment, delivery, the
 *      appliance list) — a merchant that arrives saying less than it used to has
 *      been demoted, not remodelled;
 *   3. accounts next: the trade is written to `option_user` and only THEN is the
 *      account re-pointed, so a crash between the two loses nothing;
 *   4. detachment last, only for a child holding no account, and only from THIS
 *      root — «تبريد وتكييف» also stands under شركات and must not be stripped
 *      there by a change to what ورش means.
 *
 * Idempotent: a second run reports zero of everything.
 */
class WorkshopRemodelSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/workshop_taxonomy.php';

        DB::transaction(function () use ($data) {
            $rootId = (int) DB::table('categories')->where('slug', $data['root_slug'])->value('id');

            if ($rootId <= 0) {
                $this->command?->warn("  ! الجذر «{$data['root_slug']}» غير موجود.");

                return;
            }

            $this->command?->info('Workshop remodel:');

            $renamed = $this->applyOptionRenames($data['option_renames'] ?? []);

            if ($renamed > 0) {
                $this->command?->line("  - مقاعد أُعيدت تسميتها : {$renamed}");
            }

            foreach ($data['domains'] as $domain) {
                $this->applyDomain($rootId, $domain);
            }
        });
    }

    /**
     * Move a bench's name instead of minting a second row under the new one.
     *
     * `upsertOptions()` finds a bench by `name_ar` within its group, so editing
     * a name in `workshop_taxonomy.php` alone creates a NEW option and leaves
     * every child linked to the old one — two benches for one job, and the
     * merchant who priced the first keeps a row nobody can find. This runs
     * first so the lists below match what is already there.
     *
     * Scoped to the group, because a bench name is only unique inside one:
     * «تشغيل CNC» in the metal shop and «راوتر CNC» in the joinery are two
     * different machines that must never collapse into each other.
     *
     * Idempotent — a row already carrying the new name is left alone, and its
     * English half is corrected if only the Arabic moved.
     *
     * @param  array<string,array<string,array{0:string,1:string}>>  $renames
     */
    private function applyOptionRenames(array $renames): int
    {
        $moved = 0;

        foreach ($renames as $groupNameAr => $pairs) {
            $groupId = (int) DB::table('option_groups')->where('name_ar', $groupNameAr)->value('id');

            if ($groupId <= 0) {
                continue;
            }

            foreach ($pairs as $from => [$toAr, $toEn]) {
                $already = DB::table('options')->where('group_id', $groupId)->where('name_ar', $toAr)->first(['id', 'name_en']);

                if ($already) {
                    if ($already->name_en !== $toEn) {
                        DB::table('options')->where('id', $already->id)
                            ->update(['name_en' => $toEn, 'updated_at' => now()]);
                    }

                    continue;
                }

                $moved += DB::table('options')
                    ->where('group_id', $groupId)
                    ->where('name_ar', $from)
                    ->update(['name_ar' => $toAr, 'name_en' => $toEn, 'updated_at' => now()]);
            }
        }

        return $moved;
    }

    /** @param array<string,mixed> $domain */
    private function applyDomain(int $rootId, array $domain): void
    {
        $childId = $this->upsertChild($rootId, $domain);
        $groupId = $this->upsertGroup($domain);

        $options = $this->upsertOptions($groupId, $domain);
        $linked = $this->linkOptions($childId, array_values($options));
        $carried = $this->carryVocabulary(
            $rootId,
            $childId,
            array_keys($domain['folds']),
            $domain['carry_exclude_groups'] ?? []
        );
        $services = $this->copyServices($childId, $rootId, (string) $domain['services_from']);

        $moved = $this->moveAccounts($rootId, $childId, $domain, $options);
        $detached = $this->detach($rootId, array_keys($domain['folds']));

        $this->command?->line("  «{$domain['name_ar']}» #{$childId}");
        $this->command?->line("      خيارات المجموعة : " . count($options) . " · رُبطت : {$linked}");
        $this->command?->line("      خيارات نُقلت من الأبناء : {$carried} · خدمات نُسخت : {$services}");
        $this->command?->line("      حسابات نُقلت : " . count($moved) . " · أبناء فُصلوا : {$detached}");

        foreach ($moved as $row) {
            $this->command?->line("        #{$row['id']} {$row['name']} : {$row['from']} → {$row['option']}");
        }
    }

    /** @param array<string,mixed> $domain */
    private function upsertChild(int $rootId, array $domain): int
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', $domain['name_ar'])->value('id');

        if ($childId <= 0) {
            $childId = (int) DB::table('category_children_master')->insertGetId([
                'name_ar' => $domain['name_ar'],
                'name_en' => $domain['name_en'],
                'reorder' => 1 + (int) DB::table('category_children_master')->max('reorder'),
            ]);
        }

        DB::table('category_parent_child')->updateOrInsert(
            ['parent_id' => $rootId, 'child_id' => $childId],
            ['updated_at' => now()]
        );

        return $childId;
    }

    /** @param array<string,mixed> $domain */
    private function upsertGroup(array $domain): int
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $domain['group_name_ar'])->value('id');

        if ($groupId > 0) {
            return $groupId;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => $domain['group_name_ar'],
            'name_en' => $domain['group_name_en'],
            'price_role' => $domain['price_role'],
            'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The folded children's names, then the benches nobody had a child for.
     *
     * @param  array<string,mixed>  $domain
     * @return array<string,int> option name_ar => option id
     */
    private function upsertOptions(int $groupId, array $domain): array
    {
        $wanted = [];

        foreach ($domain['folds'] as [$ar, $en]) {
            $wanted[$ar] = $en;
        }

        foreach ($domain['extra_options'] ?? [] as $ar => $en) {
            $wanted[$ar] = $en;
        }

        $out = [];

        foreach ($wanted as $ar => $en) {
            // Scoped to the GROUP, never to the whole table: «زجاج سيارات» and
            // «ميكانيكا» already exist as spare-part and engineering options, and
            // a global lookup by name_ar would tick a merchant into a vocabulary
            // that has nothing to do with his bench.
            $id = (int) DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if ($id <= 0) {
                // `options.name_en` is UNIQUE across the whole table.
                if (DB::table('options')->where('name_en', $en)->exists()) {
                    $this->command?->warn("      ! «{$en}» مستخدم في مجموعة أخرى — «{$ar}» لم تُضف.");

                    continue;
                }

                $id = (int) DB::table('options')->insertGetId([
                    'group_id' => $groupId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $out[$ar] = $id;
        }

        return $out;
    }

    /**
     * Everything the folded children could say and the domain child cannot.
     * «تصليح أجهزة كهربائية» carries the eighteen appliance types; arriving on a
     * workshop that cannot name a fridge is a downgrade dressed as a merge.
     *
     * @param  array<int,string>  $foldNames
     * @param  array<int,string>  $excludeGroups
     */
    private function carryVocabulary(int $rootId, int $childId, array $foldNames, array $excludeGroups = []): int
    {
        $ids = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('cco.child_id', $this->childIds($rootId, $foldNames))
            ->when($excludeGroups !== [], fn ($q) => $q->whereNotIn('g.name_ar', $excludeGroups))
            ->pluck('cco.option_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $withdrawn = $excludeGroups === [] ? 0 : $this->withdrawGroups($childId, $excludeGroups);

        if ($withdrawn > 0) {
            $this->command?->line("      خيارات سُحبت (مجموعات مستبعدة) : {$withdrawn}");
        }

        return $this->linkOptions($childId, $ids);
    }

    /**
     * An exclusion added after the fact must also UNDO what a previous run
     * carried, or the file stops describing the database — the whole failure
     * mode that add-only seeders are notorious for here.
     *
     * @param  array<int,string>  $groupNames
     */
    private function withdrawGroups(int $childId, array $groupNames): int
    {
        $ids = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('g.name_ar', $groupNames)
            ->pluck('o.id');

        if ($ids->isEmpty()) {
            return 0;
        }

        // A word the merchant has already SOLD in outranks the list: removing it
        // strands a priced row he can no longer edit ([[seeder-must-withdraw]]).
        $sold = DB::table('business_service_prices')->where('child_id', $childId)
            ->whereNotNull('line_option_id')->pluck('line_option_id');

        return DB::table('category_child_option')
            ->where('child_id', $childId)
            ->whereIn('option_id', $ids)
            ->whereNotIn('option_id', $sold->isEmpty() ? [0] : $sold->all())
            ->delete();
    }

    /** @param array<int,int> $optionIds */
    private function linkOptions(int $childId, array $optionIds): int
    {
        $have = DB::table('category_child_option')->where('child_id', $childId)
            ->pluck('option_id')->map(fn ($id) => (int) $id)->all();

        $order = (int) DB::table('category_child_option')->where('child_id', $childId)->max('reorder');
        $added = 0;

        foreach ($optionIds as $optionId) {
            if (in_array($optionId, $have, true)) {
                continue;
            }

            DB::table('category_child_option')->insert([
                'child_id' => $childId,
                // Shared. The domain child stands under one root today, and
                // scoping only invites a second copy the day it stands under two.
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => ++$order,
            ]);

            $have[] = $optionId;
            $added++;
        }

        return $added;
    }

    /** Copied from a named sibling, never invented — see TradeVocabularySeeder. */
    private function copyServices(int $childId, int $rootId, string $donorNameAr): int
    {
        $donorId = (int) DB::table('category_children_master')->where('name_ar', $donorNameAr)->value('id');

        if ($donorId <= 0) {
            return 0;
        }

        $writer = app(ChildServiceWriter::class);
        $copied = 0;

        foreach (
            DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', $donorId)->where('is_active', 1)
                ->pluck('platform_service_id') as $serviceId
        ) {
            $serviceId = (int) $serviceId;

            $already = DB::table('category_platform_services')
                ->where('category_id', $rootId)->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)->where('is_active', 1)->exists();

            if ($already) {
                continue;
            }

            $config = json_decode((string) DB::table('category_service_configs')
                ->where('category_id', $rootId)->where('child_id', $donorId)
                ->where('platform_service_id', $serviceId)->value('config'), true) ?: [];

            $writer->enable($rootId, $childId, $serviceId, $config, null, null, 'workshop-remodel');
            $copied++;
        }

        return $copied;
    }

    /**
     * @param  array<string,mixed>  $domain
     * @param  array<string,int>  $options
     * @return array<int,array{id:int,name:string,from:string,option:string}>
     */
    private function moveAccounts(int $rootId, int $childId, array $domain, array $options): array
    {
        $moved = [];

        foreach ($domain['folds'] as $childName => [$optionAr, $optionEn]) {
            $foldId = (int) ($this->childIds($rootId, [$childName])[0] ?? 0);
            $optionId = $options[$optionAr] ?? 0;

            if ($foldId <= 0 || $foldId === $childId) {
                continue;
            }

            foreach (DB::table('users')->where('category_child_id', $foldId)->get(['id', 'name']) as $account) {
                // Written BEFORE the move: an account that lands on «ورشة
                // سيارات» with nothing ticked has lost the only thing its old
                // child was telling anyone about it.
                if ($optionId > 0) {
                    DB::table('option_user')->updateOrInsert(
                        ['user_id' => (int) $account->id, 'option_id' => $optionId],
                        []
                    );
                }

                DB::table('users')->where('id', $account->id)->update([
                    'category_child_id' => $childId,
                    'category_id' => $rootId,
                ]);

                $moved[] = [
                    'id' => (int) $account->id,
                    'name' => (string) $account->name,
                    'from' => $childName,
                    'option' => $optionAr,
                ];
            }
        }

        return $moved;
    }

    /**
     * Detach from THIS root only.
     *
     * The platform's debris rule deletes a child's option rows and wiring — but
     * that rule is written for a child no root can reach. «تبريد وتكييف» stands
     * under شركات as well, where it means the company that SELLS the unit, and
     * wiping its rows because ورش changed its mind would take a trade off a root
     * nobody touched. So the wiring goes per-root, and the option rows go only
     * once the child hangs from nothing.
     *
     * @param  array<int,string>  $foldNames
     */
    private function detach(int $rootId, array $foldNames): int
    {
        $detached = 0;

        foreach ($this->childIds($rootId, $foldNames) as $foldId) {
            if (DB::table('users')->where('category_child_id', $foldId)->exists()) {
                $this->command?->warn("      ! تُرك #{$foldId} مرتبطًا — ما زال عليه نشاط.");

                continue;
            }

            $gone = DB::table('category_parent_child')
                ->where('parent_id', $rootId)->where('child_id', $foldId)->delete();

            if ($gone === 0) {
                continue;
            }

            $detached += $gone;

            DB::table('category_service_configs')
                ->where('category_id', $rootId)->where('child_id', $foldId)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            foreach (['category_platform_services', 'category_child_service_fees'] as $table) {
                DB::table($table)->where('category_id', $rootId)->where('child_id', $foldId)->delete();
            }

            $stillRooted = DB::table('category_parent_child')->where('child_id', $foldId)->exists();

            if (! $stillRooted) {
                DB::table('category_child_option')->where('child_id', $foldId)->delete();
            } else {
                // Root-scoped option rows for a root it no longer stands under.
                DB::table('category_child_option')
                    ->where('child_id', $foldId)->where('category_id', $rootId)->delete();
            }
        }

        return $detached;
    }

    /**
     * The rows of those names that stand under THIS root.
     *
     * Never a bare `where(name_ar)`: «حداد» is two rows — #31 the workshop here
     * and #259 the tradesman under مهن وحرفيين, which the owner ruled on
     * 2026-08-09 must stay apart. A name-wide lookup would have carried the
     * tradesman's vocabulary into the workshop and, on a bad day, moved him.
     *
     * On a second run the folded children no longer stand here, so every step
     * that uses this finds nothing — which is exactly what idempotent means.
     *
     * @param  array<int,string>  $names
     * @return array<int,int>
     */
    private function childIds(int $rootId, array $names): array
    {
        return DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $rootId)
            ->whereIn('c.name_ar', $names)
            ->pluck('c.id')->map(fn ($id) => (int) $id)->all();
    }
}
