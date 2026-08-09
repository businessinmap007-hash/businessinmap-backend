<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives a trade the words it deals in — the «ماركات السيارات» pattern, reused.
 *
 *     php artisan db:seed --class=TradeVocabularySeeder
 *
 * See data/trade_vocabularies.php for the four groups and why three of them are
 * `modifier` while «خدمات الحجامة» is `line`.
 *
 * ADD-ONLY, and deliberately so: it never withdraws an option from a child. A
 * merchant's own ticks live in `option_user` and a curated child's list is the
 * admin's work — a seeder that syncs would undo both every time it ran
 * ([[seeder-must-withdraw]] is about the opposite direction; this is the pair of
 * it). Options are linked SHARED (`category_id = 0`) so a trade says the same
 * thing under مصانع, شركات and المحلات alike.
 */
class TradeVocabularySeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/trade_vocabularies.php';

        DB::transaction(function () use ($data) {
            $this->command?->info('Trade vocabularies:');

            foreach ($data['new_children'] ?? [] as $child) {
                $this->upsertChild($child);
            }

            foreach ($data['groups'] as $group) {
                $this->applyGroup($group);
            }
        });
    }

    /** @param array<string,string> $child */
    private function upsertChild(array $child): void
    {
        $rootId = (int) DB::table('categories')->where('slug', $child['root_slug'])->value('id');

        if ($rootId <= 0) {
            $this->command?->warn("  ! الجذر «{$child['root_slug']}» غير موجود.");

            return;
        }

        $childId = (int) DB::table('category_children_master')->where('name_ar', $child['name_ar'])->value('id');
        $created = false;

        if ($childId <= 0) {
            $childId = (int) DB::table('category_children_master')->insertGetId([
                'name_ar' => $child['name_ar'],
                'name_en' => $child['name_en'],
                'reorder' => 1 + (int) DB::table('category_children_master')->max('reorder'),
            ]);

            $created = true;
        }

        DB::table('category_parent_child')->updateOrInsert(
            ['parent_id' => $rootId, 'child_id' => $childId],
            ['updated_at' => now()]
        );

        $services = $this->copyServices($childId, $rootId, $child['copy_services_from']);

        $this->command?->line("  - «{$child['name_ar']}» #{$childId} "
            . ($created ? 'أُنشئ' : 'موجود') . " تحت «{$child['root_slug']}» · خدمات نُسخت : {$services}");
    }

    /**
     * A new child that offers nothing cannot be sold through, and inventing a
     * booking config is how two children in one root end up disagreeing about
     * what booking means there. So it is copied, whole, from a named sibling.
     */
    private function copyServices(int $childId, int $rootId, string $donorNameAr): int
    {
        $donorId = (int) DB::table('category_children_master')->where('name_ar', $donorNameAr)->value('id');

        if ($donorId <= 0) {
            $this->command?->warn("  ! «{$donorNameAr}» غير موجود — لم تُنسخ خدمات.");

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

            $writer->enable($rootId, $childId, $serviceId, $config, null, null, 'trade-vocabulary');
            $copied++;
        }

        return $copied;
    }

    /** @param array<string,mixed> $group */
    private function applyGroup(array $group): void
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $group['name_ar'])->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => $group['name_ar'],
                'name_en' => $group['name_en'],
                'price_role' => $group['price_role'],
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $optionIds = [];
        $created = 0;

        foreach ($group['options'] as $ar => $en) {
            $id = (int) DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if ($id <= 0) {
                // `options.name_en` is unique across the WHOLE table. Refusing
                // loudly beats crashing the seeder, and beats silently borrowing
                // a row that belongs to another group's meaning.
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

                $created++;
            }

            $optionIds[] = $id;
        }

        $linked = 0;
        $reached = 0;

        foreach ($group['children'] as $name) {
            $childId = $this->liveChildId($name);

            if ($childId <= 0) {
                $this->command?->warn("      ! الابن «{$name}» غير موجود تحت أي جذر — تُخطّي.");

                continue;
            }

            $reached++;
            $linked += $this->link($childId, $optionIds);
        }

        $this->command?->line("  - [{$group['name_ar']}] ({$group['price_role']}) خيارات: "
            . count($optionIds) . " (جديدة {$created}) · أبناء: {$reached} · روابط أُضيفت: {$linked}");
    }

    /**
     * The child of that name that a customer can actually REACH.
     *
     * Several names have two master rows — «أجهزة رياضية» #7 and #24, «قطع غيار
     * سيارات» #43 and #44 — because a retired twin keeps its row as the undo
     * record. A plain `where(name_ar)->value('id')` returns the LOWEST id, which
     * is the retired one, and that is exactly what happened: fifteen sports
     * options were attached to a child hanging from no root, invisible to
     * everyone, while the live child stayed silent.
     *
     * So the lookup is «attached to a root», tie-broken by who holds accounts.
     */
    private function liveChildId(string $nameAr): int
    {
        return (int) DB::table('category_children_master as c')
            ->join('category_parent_child as p', 'p.child_id', '=', 'c.id')
            ->where('c.name_ar', $nameAr)
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM users u WHERE u.category_child_id = c.id)'))
            ->orderBy('c.id')
            ->value('c.id');
    }

    /** @param array<int,int> $optionIds */
    private function link(int $childId, array $optionIds): int
    {
        $have = DB::table('category_child_option')->where('child_id', $childId)
            ->pluck('option_id')->map(fn ($id) => (int) $id)->all();

        $order = (int) DB::table('category_child_option')->where('child_id', $childId)->max('reorder');
        $rows = [];

        foreach ($optionIds as $optionId) {
            if (in_array($optionId, $have, true)) {
                continue;
            }

            $rows[] = [
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => ++$order,
            ];
        }

        $added = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            $added += DB::table('category_child_option')->insertOrIgnore($chunk);
        }

        return $added;
    }
}
