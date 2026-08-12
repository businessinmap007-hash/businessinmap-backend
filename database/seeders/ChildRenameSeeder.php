<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Widens a child's name after it has swallowed a sibling.
 *
 *     php artisan db:seed --class=ChildRenameSeeder
 *
 * See data/child_renames.php for the list and the reasoning. A rename keeps the
 * id, so every option link, service config, price and account travels with it —
 * which is why a merge keeps a child rather than creating one.
 *
 * Idempotent, and it says which case it is on every run: already renamed, or
 * renamed now, or a name that matches neither and is therefore left alone.
 * That last case matters — a partly-applied rename is how a child ends up
 * unwired from the branch maps that look it up by name.
 *
 * MUST run before ChildRootDetachSeeder, which names its destinations by the
 * NEW name.
 */
class ChildRenameSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/child_renames.php';

        $this->command?->info('Child renames:');

        DB::transaction(function () use ($data) {
            foreach ($data as $entry) {
                $this->apply($entry);
            }
        });
    }

    /** @param array<string,mixed> $entry */
    private function apply(array $entry): void
    {
        $id = (int) $entry['id'];
        $row = DB::table('category_children_master')->where('id', $id)->first(['id', 'name_ar', 'name_en']);

        if ($row === null) {
            $this->command?->warn("  ! الابن #{$id} غير موجود — يُتخطّى.");

            return;
        }

        if ((string) $row->name_ar === (string) $entry['to_ar']) {
            $this->command?->line("  - #{$id} «{$entry['to_ar']}» بالفعل.");

            return;
        }

        if ((string) $row->name_ar !== (string) $entry['from_ar']) {
            // Somebody renamed it to a third thing. Say so rather than
            // overwrite: the entry was written about the name it HAD.
            $this->command?->warn("  ! #{$id} اسمه «{$row->name_ar}» لا «{$entry['from_ar']}» — لم يُعد تسميته.");

            return;
        }

        $clash = DB::table('category_children_master')
            ->where('name_ar', $entry['to_ar'])->where('id', '!=', $id)->value('id');

        if ($clash) {
            // Two rows of one name is how a lookup-by-name picks the wrong one,
            // and the platform already carries retired twins for several names.
            $this->command?->warn("  ! «{$entry['to_ar']}» يحمله الابن #{$clash} — لم يُعد تسميته.");

            return;
        }

        DB::table('category_children_master')->where('id', $id)->update([
            'name_ar' => $entry['to_ar'],
            'name_en' => $entry['to_en'],
            'updated_at' => now(),
        ]);

        $this->command?->line("  - #{$id} : «{$entry['from_ar']}» → «{$entry['to_ar']}»");
    }
}
