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

            $created = 0;
            $linked = 0;
            $refused = 0;

            foreach (($map['groups'] ?? []) as $nameAr => $spec) {
                $groupId = $this->group($nameAr, $spec['name_en'], $spec['price_role']);

                foreach ($spec['options'] as $ar => $en) {
                    $optionId = $this->option($groupId, $ar, $en, $created);

                    foreach ($spec['children'] as $childId) {
                        $this->link((int) $childId, $optionId, $blocked, $linked, $refused);
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

            foreach (($map['links'] ?? []) as $childId => $groups) {
                foreach ($groups as $groupName => $optionNames) {
                    $optionIds = DB::table('options as o')
                        ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                        ->where('g.name_ar', $groupName)
                        ->whereIn('o.name_ar', $optionNames)
                        ->pluck('o.id');

                    $missing = count($optionNames) - $optionIds->count();

                    if ($missing > 0) {
                        // A name that matches nothing is a typo or a row since
                        // renamed, and it silently narrows the child instead of
                        // widening it. Say so rather than link what did match.
                        $this->command?->warn("  ! «{$groupName}» → {$missing} اسمًا لا يطابق شيئًا (ابن {$childId}).");
                    }

                    foreach ($optionIds as $optionId) {
                        $this->link((int) $childId, (int) $optionId, $blocked, $linked, $refused);
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

    /** @param array<int,array<int,mixed>> $blocked */
    private function link(int $childId, int $optionId, array $blocked, int &$linked, int &$refused): void
    {
        if (isset($blocked[$childId][$optionId])) {
            $refused++;

            return;
        }

        if (
            DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->exists()
        ) {
            return;
        }

        // SHARED (category_id = 0): تنسيق حفلات، طباعة and أمن each stand under
        // «شركات» as well, and a printing house prints the same things under
        // either root.
        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => 0,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        $linked++;
    }
}
