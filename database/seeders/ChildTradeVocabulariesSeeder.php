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
        'entertainment_child_vocabularies.php',
        'stray_child_vocabularies.php',
        'health_child_vocabularies.php',
        'hall_child_vocabularies.php',
        'workshop_child_vocabularies.php',
        'shipping_child_vocabularies.php',
        'agriculture_child_vocabularies.php',
        'property_child_vocabularies.php',
        'hospitality_child_vocabularies.php',
        'restaurant_child_vocabularies.php',
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

            $this->regroup($map['regroup'] ?? []);

            $created = 0;
            $linked = 0;
            $refused = 0;
            $pruned = 0;

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
                /*
                 * A child may be named instead of numbered, and one has to be:
                 * «كابلات وقواطع كهرباء» is created by its own seeder and has no
                 * id to write down here. A name resolves at run time and an
                 * unknown one is said out loud — a silent miss would leave the
                 * child this group was written for holding nothing.
                 */
                $children = $spec['children'] === 'all'
                    ? DB::table('category_parent_child')->where('parent_id', $rootId)->pluck('child_id')->all()
                    : array_values(array_filter(array_map(function ($child) {
                        if (! is_string($child)) {
                            return (int) $child;
                        }

                        $id = (int) DB::table('category_children_master')->where('name_ar', $child)->value('id');

                        if ($id <= 0) {
                            $this->command?->warn("  ! ابن «{$child}» غير موجود — تُخطّى.");
                        }

                        return $id;
                    }, $spec['children'])));

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

            /*
             * A narrowing that applies UNDER THIS ROOT ONLY.
             *
             * `child_option_scopes.php` narrows a child everywhere at once and
             * `category_child_option_decisions` withdraws everywhere at once,
             * so neither can say «the FACTORY answers less than the SHOP».
             * This can, because the link column already carries the root.
             *
             * Only rows written against this root are touched — a SHARED row
             * (category_id = 0) is every root's and is never pruned from here.
             */
            foreach (($map['prune_links'] ?? []) as $childId => $groups) {
                foreach ($groups as $groupName => $keep) {
                    $doomed = DB::table('category_child_option as co')
                        ->join('options as o', 'o.id', '=', 'co.option_id')
                        ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                        ->where('co.child_id', (int) $childId)
                        ->where('co.category_id', $this->rootId((string) ($map['root'] ?? '')))
                        ->where('g.name_ar', $groupName)
                        ->whereNotIn('o.name_ar', $keep)
                        ->pluck('co.id');

                    // A merchant's own tick outranks the map, as everywhere else.
                    $ticked = DB::table('option_user as ou')
                        ->join('users as u', 'u.id', '=', 'ou.user_id')
                        ->where('u.category_child_id', (int) $childId)
                        ->pluck('ou.option_id');

                    $doomed = DB::table('category_child_option')
                        ->whereIn('id', $doomed)->whereNotIn('option_id', $ticked)->pluck('id');

                    $pruned += DB::table('category_child_option')->whereIn('id', $doomed)->delete();
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
            $this->command?->line("  - روابط ضُيّقت على هذا الجذر : {$pruned}");
        });
    }

    /**
     * Take rows OUT of a group this file already wrote and into a new one.
     *
     * A group carries one price role, so a list that turned out to be two
     * lists cannot be fixed by editing it: «مرافق النادي الرياضي» described a
     * gym's rooms AND the three things it bills for, and while they shared a
     * group the trainer could not be priced at all. Splitting it in `groups`
     * alone would leave the four rows in the old group and CREATE four more in
     * the new one — `option()` matches on group and Arabic name — so the child
     * links would stay behind on the originals and the new rows would reach
     * nobody.
     *
     * Runs after `rename` and before everything else, for the same reason
     * `rename` runs first: nothing may look a group up by name until the groups
     * are where this file says they are.
     *
     * Only `options.group_id` moves — the promise MenuBandSplitSeeder and
     * GroceryAisleSplitSeeder both make. No `category_child_option` row is
     * touched, so a merchant keeps every row he had under a new heading, and
     * every withdrawal the owner has made still points at the same option id.
     *
     * Scoped to the SOURCE group, which makes it idempotent and makes it safe:
     * a row already moved stays moved, and an option of the same name living in
     * some other group is none of this file's business.
     *
     * @param  array<string,array<string,mixed>>  $regroups
     */
    private function regroup(array $regroups): void
    {
        foreach ($regroups as $nameAr => $spec) {
            $sourceId = (int) DB::table('option_groups')->where('name_ar', $spec['from'])->value('id');

            if ($sourceId <= 0) {
                $this->command?->warn("  ! «{$spec['from']}» غير موجودة — لا شيء ليُقسم.");

                continue;
            }

            $targetId = $this->group($nameAr, $spec['name_en'], $spec['price_role']);

            $moving = DB::table('options')
                ->where('group_id', $sourceId)
                ->whereIn('name_ar', $spec['options'])
                ->pluck('name_ar');

            if ($moving->isEmpty()) {
                continue;
            }

            DB::table('options')
                ->where('group_id', $sourceId)
                ->whereIn('name_ar', $spec['options'])
                ->update(['group_id' => $targetId, 'updated_at' => now()]);

            $this->command?->line("  - «{$nameAr}» ← «{$spec['from']}» : " . $moving->implode('، '));
        }
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

        /*
         * `options.name_en` collides across groups — «تصوير وإنتاج» already
         * belongs to the advertising list, «كاميرات مراقبة» to two trades that
         * fit them. Same handling as the hotel seeder.
         *
         * Numbered when the suffixed name is taken too, because one escape
         * hatch is not enough and the failure is a crash rather than a
         * duplicate. «دعاسات» was spelled with a ع here and with a و in the
         * database, so this method could never find its own row: the first run
         * made «Door Mats (Factory)», and the second had nowhere left to go and
         * took the whole seeder down on a unique-key violation.
         *
         * Reaching this loop at all means a by-name map has drifted from the
         * data, which is a defect to fix in the file — the counter keeps the
         * chain running while somebody does.
         */
        if (DB::table('options')->where('name_en', $en)->exists()) {
            $base = "{$en} ({$this->suffix})";
            $en = $base;

            for ($n = 2; DB::table('options')->where('name_en', $en)->exists(); $n++) {
                $en = "{$base} {$n}";
            }
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

    /**
     * group name_ar => child id => the option ids that child may answer.
     *
     * `child_option_scopes.php` is the file that says «this child can only use
     * a slice of that group». Two other seeders already honour it; this one did
     * not, so every run handed the farm-equipment list back whole and
     * ChildOptionScopeSeeder took it away again — a pair of seeders undoing
     * each other on every seed, which the idempotency test caught.
     *
     * @var array<string,array<int,array<int,int>>>|null
     */
    private ?array $scopes = null;

    /** @return array<string,array<int,array<int,int>>> */
    private function scopes(): array
    {
        return $this->scopes ??= require database_path('seeders/data/child_option_scopes.php');
    }

    /** True when the scope file lets this child answer this option. */
    private function inScope(int $childId, int $optionId): bool
    {
        $group = (string) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('o.id', $optionId)->value('g.name_ar');

        $allowed = $this->scopes()[$group][$childId] ?? null;

        // Absent from the map means «no narrowing», never «narrow to nothing» —
        // the same reading ChildOptionScopeSeeder gives it.
        return $allowed === null || in_array($optionId, $allowed, true);
    }

    /** @param array<int,array<int,mixed>> $blocked */
    private function link(int $childId, int $optionId, array $blocked, int &$linked, int &$refused, int $scope = 0): void
    {
        if (isset($blocked[$childId][$optionId]) || ! $this->inScope($childId, $optionId)) {
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
