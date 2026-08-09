<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One trade, several roots, one vocabulary.
 *
 *     php artisan db:seed --class=TradeAxesSeeder
 *
 * See data/trade_axes.php for the owner's statement of the problem. In short: a
 * factory, a distributor and a shop in the same trade are three businesses under
 * three roots, and the platform had modelled that as three child rows with three
 * different vocabularies — so a spare-parts FACTORY could not name a single car
 * brand while the SHOP next door could name 43.
 *
 * What this does, in the order it must happen:
 *
 *   1. creates any missing axis group and its options;
 *   2. gives the surviving child every axis the trade needs, and anything a
 *      retiring twin could say that it could not;
 *   3. attaches the survivor to every root the trade belongs under, copying the
 *      service wiring from whatever already stood there — so the trade arrives
 *      offering what that root was already offering, not a guess;
 *   4. only then detaches the twins, and never one that still holds an account.
 *
 * Idempotent. Non-destructive: a retired twin keeps its master row.
 */
class TradeAxesSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/trade_axes.php';

        DB::transaction(function () use ($data) {
            $groups = $this->upsertGroups($data['new_groups'] ?? []);

            $this->command?->info('Trade axes:');
            $this->command?->line("  - مجموعات خيارات جديدة/محدّثة : {$groups}");

            foreach ($data['trades'] as $label => $trade) {
                $keeper = (int) $trade['keep_child_id'];

                $axes = $this->attachAxes($keeper, $trade['axis_groups'] ?? []);
                $carried = $this->carryTwinVocabulary($keeper, $trade['retire_children'] ?? []);
                $rooted = $this->attachRoots($keeper, $trade['roots'], $trade['retire_children'] ?? []);
                $detached = $this->detachTwins($trade['retire_children'] ?? []);

                $this->command?->line("  «{$label}» → #{$keeper}");
                $this->command?->line("      محاور أُضيفت : {$axes}");
                $this->command?->line("      خيارات نُقلت من التوائم : {$carried}");
                $this->command?->line("      جذور رُبطت (خدمات) : {$rooted}");
                $this->command?->line("      توائم فُصلت : {$detached}");
            }
        });
    }

    /** @param array<string,array<string,mixed>> $groups */
    private function upsertGroups(array $groups): int
    {
        $touched = 0;

        foreach ($groups as $nameAr => $spec) {
            $groupId = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

            if ($groupId <= 0) {
                $groupId = (int) DB::table('option_groups')->insertGetId([
                    'name_ar' => $nameAr,
                    'name_en' => $spec['name_en'],
                    'price_role' => $spec['price_role'],
                    'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $touched++;
            }

            foreach ($spec['options'] as $ar => $en) {
                $exists = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->exists();

                if ($exists) {
                    continue;
                }

                // `options.name_en` is unique across the whole table, and the
                // same Arabic word can legitimately mean two trades. Refuse
                // loudly rather than crash the seeder or, worse, silently hand
                // this group an option that belongs to another one.
                if (DB::table('options')->where('name_en', $en)->exists()) {
                    $this->command?->warn("      ! «{$en}» مستخدم في مجموعة أخرى — «{$ar}» لم تُضف.");

                    continue;
                }

                DB::table('options')->insert([
                    'group_id' => $groupId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $touched;
    }

    /** @param array<int,string> $groupNames */
    private function attachAxes(int $childId, array $groupNames): int
    {
        $optionIds = collect();

        foreach ($groupNames as $name) {
            $groupId = (int) DB::table('option_groups')->where('name_ar', $name)->value('id');

            if ($groupId <= 0) {
                $this->command?->warn("      ! مجموعة «{$name}» غير موجودة.");

                continue;
            }

            $optionIds = $optionIds->merge(DB::table('options')->where('group_id', $groupId)->pluck('id'));
        }

        return $this->linkOptions($childId, $optionIds->map(fn ($id) => (int) $id)->unique());
    }

    /**
     * Anything a twin could say and the survivor could not. Without this the
     * merge is a quiet downgrade: #7 «أجهزة رياضية» offered كاش and تقسيط, and
     * #24 — the row that stays — offered neither.
     *
     * @param  array<int,int>  $twins
     */
    private function carryTwinVocabulary(int $keeper, array $twins): int
    {
        if ($twins === []) {
            return 0;
        }

        $theirs = DB::table('category_child_option')->whereIn('child_id', $twins)->pluck('option_id')->unique();

        return $this->linkOptions($keeper, $theirs->map(fn ($id) => (int) $id));
    }

    /** @param \Illuminate\Support\Collection<int,int> $optionIds */
    private function linkOptions(int $childId, $optionIds): int
    {
        $have = DB::table('category_child_option')->where('child_id', $childId)->pluck('option_id')
            ->map(fn ($id) => (int) $id)->all();

        $order = (int) DB::table('category_child_option')->where('child_id', $childId)->max('reorder');
        $added = 0;

        foreach ($optionIds as $optionId) {
            if (in_array($optionId, $have, true)) {
                continue;
            }

            // category_id 0 = shared by every root this child belongs to, which
            // is the whole point: the factory, the distributor and the shop all
            // name the same brands.
            DB::table('category_child_option')->insert([
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => ++$order,
            ]);

            $have[] = $optionId;
            $added++;
        }

        return $added;
    }

    /**
     * Put the trade under every root it belongs to, offering there whatever that
     * root already offered for it — copied from the twin that stood there, or
     * from the survivor's own existing wiring when the root is genuinely new.
     *
     * @param  array<int,int>  $roots
     * @param  array<int,int>  $twins
     */
    private function attachRoots(int $keeper, array $roots, array $twins): int
    {
        $writer = app(ChildServiceWriter::class);
        $wired = 0;

        foreach ($roots as $rootId) {
            $rootId = (int) $rootId;

            DB::table('category_parent_child')->updateOrInsert(
                ['parent_id' => $rootId, 'child_id' => $keeper],
                ['updated_at' => now()]
            );

            foreach ($this->donorWiring($keeper, $rootId, $twins) as $serviceId => $config) {
                $already = DB::table('category_platform_services')
                    ->where('category_id', $rootId)
                    ->where('child_id', $keeper)
                    ->where('platform_service_id', $serviceId)
                    ->where('is_active', 1)
                    ->exists();

                if ($already) {
                    continue;
                }

                $writer->enable($rootId, $keeper, $serviceId, $config, null, null, 'trade-axes');
                $wired++;
            }
        }

        return $wired;
    }

    /**
     * Which services to offer under a root, and with what config.
     *
     * @param  array<int,int>  $twins
     * @return array<int,array<string,mixed>> service id => config
     */
    private function donorWiring(int $keeper, int $rootId, array $twins): array
    {
        // A root where the trade already stands is the admin's own work. Adding
        // to it is not "attaching a root", it is overruling him.
        $standing = DB::table('category_platform_services')
            ->where('category_id', $rootId)
            ->where('child_id', $keeper)
            ->where('is_active', 1)
            ->exists();

        if ($standing) {
            return [];
        }

        // A twin standing under this root is the most faithful donor: whatever
        // it offered here is what this root already meant by this trade.
        $donors = DB::table('category_platform_services')
            ->where('category_id', $rootId)
            ->whereIn('child_id', $twins)
            ->where('is_active', 1)
            ->pluck('child_id', 'platform_service_id');

        $out = [];

        foreach ($donors as $serviceId => $childId) {
            $out[(int) $serviceId] = $this->configOf((int) $rootId, (int) $childId, (int) $serviceId);
        }

        if ($out !== []) {
            return $out;
        }

        // A root the trade has never stood under. Take the INTERSECTION of what
        // it offers under the roots it does stand in — what the trade offers
        // everywhere, not what one root happens to add on top. «معارض» lets you
        // book a viewing of sports equipment; a factory does not, and the union
        // would have handed booking to the factory.
        $byRoot = [];

        foreach (DB::table('category_platform_services')
            ->where('child_id', $keeper)
            ->where('is_active', 1)
            ->get(['category_id', 'platform_service_id']) as $row) {
            $byRoot[(int) $row->category_id][] = (int) $row->platform_service_id;
        }

        if ($byRoot === []) {
            return [];
        }

        $shared = array_values(array_intersect(...array_values($byRoot)));

        foreach ($shared as $serviceId) {
            $donorRoot = (int) array_key_first($byRoot);
            $out[$serviceId] = $this->configOf($donorRoot, $keeper, $serviceId);
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function configOf(int $rootId, int $childId, int $serviceId): array
    {
        return json_decode((string) DB::table('category_service_configs')
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->value('config'), true) ?: [];
    }

    /** @param array<int,int> $twins */
    private function detachTwins(array $twins): int
    {
        $detached = 0;

        foreach ($twins as $twinId) {
            $twinId = (int) $twinId;

            if (DB::table('users')->where('category_child_id', $twinId)->exists()) {
                $this->command?->warn("      ! التوأم #{$twinId} ما زال عليه نشاط — تُرك.");

                continue;
            }

            if (DB::table('business_service_prices')->where('child_id', $twinId)->exists()) {
                $this->command?->warn("      ! التوأم #{$twinId} عليه صفوف مسعّرة — تُرك.");

                continue;
            }

            $detached += DB::table('category_parent_child')->where('child_id', $twinId)->delete();

            // The same debris rule the platform uses everywhere else
            // (OrphanChildLinksCleanupSeeder): links, fees and option rows go,
            // the config is only deactivated, the master row is untouched.
            DB::table('category_service_configs')->where('child_id', $twinId)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            foreach (['category_platform_services', 'category_child_service_fees', 'category_child_option'] as $table) {
                DB::table($table)->where('child_id', $twinId)->delete();
            }
        }

        return $detached;
    }
}
