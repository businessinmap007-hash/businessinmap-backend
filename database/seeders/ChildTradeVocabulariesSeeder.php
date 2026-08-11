<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives a child that cannot name its trade a vocabulary to name it with.
 *
 *     php artisan db:seed --class=ChildTradeVocabulariesSeeder
 *
 * One file per root, because the gap turns up a root at a time: six of the
 * thirteen children of «مكاتب» carried «نمط تقديم الخدمة», a payment group and
 * no `line` group at all, and every one of «تكنولوجيا»'s three did. They could
 * describe HOW they work and never WHAT they do.
 *
 * See each data file for its lists and for what is deliberately left out.
 *
 * It CONSULTS the withdrawal record before linking anything. The owner was
 * curating «خدمات منزلية» by hand minutes before he asked for this — a seeder
 * that hands back what he has just taken off is the failure mode five other
 * option seeders had to be taught out of.
 *
 * Idempotent; nothing is deleted.
 */
class ChildTradeVocabulariesSeeder extends Seeder
{
    /** Disambiguates a `name_en` that another group already owns. */
    private string $suffix = 'Trade';

    private const FILES = [
        'office_child_vocabularies.php',
        'technology_child_vocabularies.php',
        'factory_child_vocabularies.php',
        'company_child_vocabularies.php',
        'exhibition_child_vocabularies.php',
        'crafts_child_vocabularies.php',
        'shop_child_vocabularies.php',
    ];

    public function run(): void
    {
        foreach (self::FILES as $file) {
            $this->apply(require __DIR__ . '/data/' . $file);
        }
    }

    /** @param array<string,mixed> $map */
    private function apply(array $map): void
    {
        $this->suffix = (string) ($map['name_en_suffix'] ?? 'Trade');

        DB::transaction(function () use ($map) {
            $blocked = app(ChildOptionDecisions::class)->blockedByChild();

            /*
             * Before anything looks a group up by name. A rename that runs
             * second would find nothing under the new name and CREATE it,
             * leaving two groups where there was one and splitting the
             * children between them.
             */
            foreach (($map['rename'] ?? []) as $from => [$toAr, $toEn]) {
                if (DB::table('option_groups')->where('name_ar', $toAr)->exists()) {
                    // Already renamed, or the new name was taken first. Either
                    // way the English half may still be the old one, because a
                    // rename that only moved name_ar leaves the admin showing
                    // two different names for one group.
                    DB::table('option_groups')->where('name_ar', $toAr)
                        ->where('name_en', '!=', $toEn)
                        ->update(['name_en' => $toEn, 'updated_at' => now()]);

                    continue;
                }

                DB::table('option_groups')->where('name_ar', $from)
                    ->update(['name_ar' => $toAr, 'name_en' => $toEn, 'updated_at' => now()]);
            }

            $created = 0;
            $linked = 0;
            $refused = 0;

            foreach (($map['groups'] ?? []) as $nameAr => $spec) {
                $groupId = $this->group($nameAr, $spec['name_en'], $spec['price_role']);

                /*
                 * `scope: root` writes `category_child_option.category_id =`
                 * this root instead of 0. CategoryChildOptionScope states the
                 * rule and its example is this very case: «آثاث» is a factory,
                 * a showroom, a workshop and a wholesaler, and only the factory
                 * answers about production runs. A shared row would ask a
                 * furniture SHOP what its minimum order quantity is.
                 */
                $rootId = $this->rootId((string) ($map['root'] ?? ''));

                $scope = ($spec['scope'] ?? 'shared') === 'root' ? $rootId : 0;

                // `children: all` follows the root's membership rather than a
                // list, so a child added to it tomorrow inherits the axis. Safe
                // only for an axis the ROOT asks — which is why the two that
                // use it are also the two that are root-scoped.
                $children = $spec['children'] === 'all'
                    ? DB::table('category_parent_child')->where('parent_id', $rootId)->pluck('child_id')->all()
                    : $spec['children'];

                foreach ($spec['options'] as $ar => $en) {
                    $optionId = $this->option($groupId, $ar, $en, $created);

                    foreach ($children as $childId) {
                        $this->link((int) $childId, $optionId, $blocked, $linked, $refused, $scope);
                    }
                }
            }

            foreach (($map['extend'] ?? []) as $nameAr => $options) {
                $groupId = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

                if ($groupId <= 0) {
                    $this->command?->warn("  ! مجموعة «{$nameAr}» غير موجودة — تُخطّى.");

                    continue;
                }

                foreach ($options as $ar => $en) {
                    $this->option($groupId, $ar, $en, $created);
                }
            }

            /*
             * A child that can name its trade under one root and not under
             * this one. Copies the option ids it ALREADY holds in that group
             * elsewhere, which preserves any narrowing — naming the group
             * would hand back everything the scope had cut away.
             */
            foreach (($map['mirror_links'] ?? []) as $childId => $groupNames) {
                $held = DB::table('category_child_option as co')
                    ->join('options as o', 'o.id', '=', 'co.option_id')
                    ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                    ->where('co.child_id', (int) $childId)
                    ->whereIn('g.name_ar', $groupNames)
                    ->distinct()->pluck('o.id');

                if ($held->isEmpty()) {
                    $this->command?->warn("  ! ابن {$childId}: لا شيء ليُنسخ من " . implode('، ', $groupNames));
                }

                foreach ($held as $optionId) {
                    $this->link((int) $childId, (int) $optionId, $blocked, $linked, $refused, $this->rootId((string) ($map['root'] ?? '')));
                }
            }

            // `links` are shared; `root_links` are written against this root
            // alone. A furniture showroom part-exchanges a sofa and the same
            // child under مصانع does not.
            $linkSets = [
                0 => $map['links'] ?? [],
                $this->rootId((string) ($map['root'] ?? '')) => $map['root_links'] ?? [],
            ];

            foreach ($linkSets as $linkScope => $set) {
              foreach ($set as $childId => $groups) {
                foreach ($groups as $groupName => $optionNames) {
                    $optionIds = DB::table('options as o')
                        ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                        ->where('g.name_ar', $groupName)
                        ->when($optionNames !== 'all', fn ($q) => $q->whereIn('o.name_ar', $optionNames))
                        ->pluck('o.id');

                    $missing = $optionNames === 'all' ? 0 : count($optionNames) - $optionIds->count();

                    if ($missing > 0) {
                        // A name that matches nothing is a typo or a row since
                        // renamed, and it silently narrows the child instead of
                        // widening it. Say so rather than link what did match.
                        $this->command?->warn("  ! «{$groupName}» → {$missing} اسمًا لا يطابق شيئًا (ابن {$childId}).");
                    }

                    foreach ($optionIds as $optionId) {
                        $this->link((int) $childId, (int) $optionId, $blocked, $linked, $refused, (int) $linkScope);
                    }
                }
              }
            }

            $this->command?->info('Child trade vocabularies — ' . ($map['root'] ?? '?') . ':');
            $this->command?->line("  - خيارات أُنشئت : {$created}");
            $this->command?->line("  - روابط أُضيفت : {$linked}");
            $this->command?->line("  - روابط رفضها سجل السحب : {$refused}");
        });
    }

    private function group(string $nameAr, string $nameEn, string $role): int
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Written here so a standalone run produces a working pricing screen,
        // and declared in data/option_price_roles.php so the next run of
        // OptionPriceRolesSeeder does not reset it to `descriptive`.
        DB::table('option_groups')->where('id', $groupId)
            ->update(['price_role' => $role, 'updated_at' => now()]);

        return $groupId;
    }

    private function option(int $groupId, string $ar, string $en, int &$created): int
    {
        $optionId = (int) DB::table('options')
            ->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

        if ($optionId > 0) {
            return $optionId;
        }

        // `options.name_en` collides across groups — «تصوير وإنتاج» already
        // belongs to the advertising list, «كاميرات مراقبة» to two trades that
        // fit them. Same handling as the hotel seeder.
        if (DB::table('options')->where('name_en', $en)->exists()) {
            $en .= " ({$this->suffix})";
        }

        $created++;

        return (int) DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => $ar,
            'name_en' => $en,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function rootId(string $slug): int
    {
        return (int) DB::table('categories')->where('slug', $slug)->value('id');
    }

    /** @param array<int,array<int,mixed>> $blocked */
    private function link(int $childId, int $optionId, array $blocked, int &$linked, int &$refused, int $scope = 0): void
    {
        if (isset($blocked[$childId][$optionId])) {
            $refused++;

            return;
        }

        // A shared row already covers every root, so a per-root row on top of
        // it is a duplicate that says nothing new. The reverse is not true: a
        // root-scoped row does not satisfy a request for a shared one.
        $already = DB::table('category_child_option')
            ->where('child_id', $childId)->where('option_id', $optionId)
            ->whereIn('category_id', array_unique([0, $scope]))
            ->exists();

        if ($already) {
            return;
        }

        // Shared (0) is the normal state: تنسيق حفلات، طباعة and أمن each stand
        // under «شركات» as well, and a printing house prints the same things
        // under either root. Only a genuine per-root disagreement gets an id.
        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => $scope,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        $linked++;
    }
}
