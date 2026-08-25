<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «راجع باقي مجموعات الخيارات وأضف إليها ما ينقصها مثل الفواكه والخضروات»
 *  — المالك، 2026-08-25.
 *
 *     php artisan db:seed --class=OptionGroupGapsSeeder
 *
 * The lists, and the reasoning behind every name in them, are in
 * data/option_group_gaps.php (things) and data/option_group_gaps_services.php
 * (work). Two files, one pass: goods and services were reviewed a day apart
 * and each file argues its own half, but they extend the same table the same
 * way and there is no reason to run them separately.
 *
 * ── A row that reaches nobody is not an addition ────────────────────────────
 *
 * Rows and links are two tables, and the merchant's screen reads the LINKS:
 * `MerchantOfferingVocabulary` narrows to `category_child_option` per OPTION,
 * not per group. So a row written into «أنواع السجاد» and left unlinked is
 * invisible to every carpet shop on the platform — the frozen-pin failure this
 * taxonomy has produced three times before ([[pin-freezes-a-growing-group]]):
 * the row is neither granted nor refused, it simply never arrives, and it
 * reads exactly like a deliberate absence.
 *
 * So there are two steps, and the second is the one that matters:
 *
 *   1. add   — write the rows the list names
 *   2. follow — hand each new row to the children that already carry the group
 *
 * ── Whose children, exactly ─────────────────────────────────────────────────
 *
 * Only the ones already carrying that group, and with the same `category_id`
 * they carry it under — 0 for «under every root», a real root for «under that
 * root alone». Nobody new is given anything: a child that did not carry
 * «أنواع السجاد» yesterday still does not.
 *
 * A child carrying only PART of a group still gets the new rows. Its missing
 * row is the owner's ruling on THAT row and says nothing about a name he has
 * never been shown — and refusing on its behalf is precisely how a list
 * freezes.
 *
 * The withdrawal ledger is consulted anyway. Nothing can be blocked on the
 * first run (the rows are minutes old), but the day the owner takes «سجاد
 * تركي» off a child, this must not hand it back on the next seed.
 *
 * A row already present is left exactly as it is — this never renames, never
 * re-points, never deletes. Idempotent: a second run adds nothing and links
 * nothing.
 */
class OptionGroupGapsSeeder extends Seeder
{
    /** @var array<int,string> */
    private const FILES = [
        'option_group_gaps.php',
        'option_group_gaps_services.php',
    ];

    public function run(): void
    {
        $map = ['extend' => []];

        foreach (self::FILES as $file) {
            $part = require __DIR__ . '/data/' . $file;

            // Merged per GROUP, so both files may extend the same list without
            // one silently replacing the other's rows.
            foreach (($part['extend'] ?? []) as $group => $rows) {
                $map['extend'][$group] = array_merge($map['extend'][$group] ?? [], $rows);
            }
        }

        DB::transaction(function () use ($map) {
            $created = 0;
            $missing = 0;
            $refused = 0;
            $touched = [];

            foreach (($map['extend'] ?? []) as $groupName => $options) {
                $groupId = (int) DB::table('option_groups')->where('name_ar', $groupName)->value('id');

                if ($groupId <= 0) {
                    $missing++;
                    $this->command?->warn("  ! مجموعة «{$groupName}» غير موجودة — تُخطّى.");

                    continue;
                }

                $before = $created;

                foreach ($options as $ar => $en) {
                    $this->option($groupId, (string) $ar, (string) $en, $created, $refused);
                }

                if ($created > $before) {
                    $touched[$groupName] = $created - $before;
                }
            }

            [$linked, $blocked, $pairs] = $this->follow($map['extend'] ?? []);

            $this->command?->info('Option group gaps:');
            $this->command?->line('  - خيارات أُضيفت : ' . $created);
            $this->command?->line('  - مجموعات اتّسعت : ' . count($touched));
            $this->command?->line('  - مجموعات غير موجودة : ' . $missing);
            $this->command?->line('  - أسماء إنجليزية مأخوذة — لم تُكتب : ' . $refused);
            $this->command?->line('  - أبناء يحملون المجموعات : ' . $pairs);
            $this->command?->line('  - روابط أُضيفت : ' . $linked);
            $this->command?->line('  - روابط رفضها سجل السحب : ' . $blocked);

            foreach ($touched as $name => $n) {
                $this->command?->line("      «{$name}» +{$n}");
            }
        });
    }

    /**
     * `options.name_en` is unique platform-wide. A taken name means the thing
     * already exists SOMEWHERE — a second row for it would be the same word
     * wearing a suffix, and the merchant would have two identical lines to
     * choose between. So this refuses and says which row holds the name.
     */
    private function option(int $groupId, string $ar, string $en, int &$created, int &$refused): void
    {
        $here = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->exists();

        if ($here) {
            return;
        }

        $taken = DB::table('options')->where('name_en', $en)->first(['id', 'name_ar', 'group_id']);

        if ($taken !== null) {
            $refused++;
            $holder = (string) DB::table('option_groups')->where('id', $taken->group_id)->value('name_ar');
            $this->command?->warn("  ! «{$ar}»: «{$en}» مأخوذ بـ«{$taken->name_ar}» فى «{$holder}» — لم يُكتب.");

            return;
        }

        DB::table('options')->insert([
            'group_id' => $groupId,
            'name_ar' => $ar,
            'name_en' => $en,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $created++;
    }

    /**
     * Step two: the new rows go wherever the group already goes.
     *
     * The «old» rows are every row in the group this file does NOT name, so
     * the carriage being mirrored is the one that existed before this pass —
     * on a second run the same rows are still «new» and the same links are
     * still there, and nothing happens.
     *
     * @param  array<string,array<string,string>>  $extend
     * @return array{0:int,1:int,2:int}  linked, blocked, carrier pairs
     */
    private function follow(array $extend): array
    {
        $blockedByChild = app(ChildOptionDecisions::class)->blockedByChild();

        $linked = 0;
        $blocked = 0;
        $pairs = 0;

        foreach ($extend as $groupName => $rows) {
            $groupId = (int) DB::table('option_groups')->where('name_ar', $groupName)->value('id');

            if ($groupId <= 0) {
                continue;
            }

            $named = array_map('strval', array_keys($rows));

            $newIds = DB::table('options')->where('group_id', $groupId)
                ->whereIn('name_ar', $named)->pluck('id');

            $oldIds = DB::table('options')->where('group_id', $groupId)
                ->whereNotIn('name_ar', $named)->pluck('id');

            if ($newIds->isEmpty() || $oldIds->isEmpty()) {
                // A group whose every row this file names has no carriage to
                // copy — there is nobody to ask.
                continue;
            }

            $carriers = DB::table('category_child_option')
                ->whereIn('option_id', $oldIds)
                ->select('child_id', 'category_id')
                ->distinct()
                ->get();

            foreach ($carriers as $carrier) {
                $pairs++;
                $childId = (int) $carrier->child_id;
                $rootId = (int) $carrier->category_id;

                foreach ($newIds as $optionId) {
                    $optionId = (int) $optionId;

                    if (isset($blockedByChild[$childId][$optionId])) {
                        $blocked++;

                        continue;
                    }

                    $already = DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->where('option_id', $optionId)
                        ->where('category_id', $rootId)
                        ->exists();

                    if ($already) {
                        continue;
                    }

                    DB::table('category_child_option')->insert([
                        'child_id' => $childId,
                        'category_id' => $rootId,
                        'option_id' => $optionId,
                        'reorder' => 0,
                    ]);

                    $linked++;
                }
            }
        }

        return [$linked, $blocked, $pairs];
    }
}
