<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Splits «أصناف الخضار والفاكهة» into the two stalls it has always been.
 *
 *     php artisan db:seed --class=ProduceAisleSplitSeeder
 *
 * See data/produce_aisle_split.php for the two sets, where the line falls, and
 * the one row worth arguing about.
 *
 * Same promise as MenuBandSplitSeeder and GroceryAisleSplitSeeder, which this
 * follows: only `options.group_id` moves. No option is created or deleted and
 * no `category_child_option` row is touched, so a greengrocer who carried 45
 * words still carries 45 — under two titles instead of one.
 *
 * Idempotent. A second run moves nothing and says so.
 */
class ProduceAisleSplitSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/produce_aisle_split.php';

        DB::transaction(function () use ($data) {
            $this->applyRenames($data['renames'] ?? []);

            $sourceId = (int) DB::table('option_groups')
                ->where('name_ar', $data['source_group'])
                ->value('id');

            if ($sourceId <= 0) {
                $this->command?->warn("  ! «{$data['source_group']}» غير موجودة — لا شيء ليُقسم.");

                return;
            }

            $this->command?->info('Produce aisle split:');

            $moved = 0;

            foreach ($data['groups'] as $nameAr => $group) {
                $moved += $this->moveInto($nameAr, $group, $sourceId);
            }

            $this->reportLeftovers($sourceId);
            $this->retireEmptySource($sourceId, $data['source_group']);

            $this->command?->line("  - خيارات نُقلت : {$moved}");
        });
    }

    /** @param array{name_en:string,reorder:int,options:array<int,string>} $group */
    private function moveInto(string $nameAr, array $group, int $sourceId): int
    {
        $groupId = $this->group($nameAr, $group['name_en'], $group['reorder']);

        // Only options standing in the SOURCE group move. One that has since
        // been filed somewhere else on purpose stays there — this splits one
        // list, it does not collect a list from the whole table by name.
        $moved = DB::table('options')
            ->where('group_id', $sourceId)
            ->whereIn('name_ar', $group['options'])
            ->update(['group_id' => $groupId]);

        if ($moved > 0) {
            $this->command?->line("  - «{$nameAr}» ← {$moved}");
        }

        return $moved;
    }

    private function group(string $nameAr, string $nameEn, int $reorder): int
    {
        $id = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($id > 0) {
            // Keep the English in step with a rename: only the Arabic is keyed,
            // so without this a renamed group keeps describing itself to every
            // English reader of the admin under its old name.
            DB::table('option_groups')->where('id', $id)->where('name_en', '!=', $nameEn)
                ->update(['name_en' => $nameEn, 'updated_at' => now()]);

            return $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            // Declared here AND in data/option_price_roles.php. A group missing
            // from that file is pushed back to `descriptive` on the next run of
            // OptionPriceRolesSeeder — which would turn a greengrocer's crop
            // into a search filter he cannot price.
            'price_role' => 'line',
            'reorder' => $reorder,
            'is_active' => 1,
        ]);
    }

    /** @param array<string,string> $renames */
    private function applyRenames(array $renames): void
    {
        foreach ($renames as $from => $to) {
            $fromId = (int) DB::table('option_groups')->where('name_ar', $from)->value('id');

            if ($fromId <= 0) {
                continue;
            }

            // Both names present means somebody created the new one by hand.
            // Merging them is a decision, not a rename — say so and stop.
            if (DB::table('option_groups')->where('name_ar', $to)->exists()) {
                $this->command?->warn("  ! «{$from}» و«{$to}» موجودتان معًا — الدمج قرار وليس إعادة تسمية.");

                continue;
            }

            DB::table('option_groups')->where('id', $fromId)
                ->update(['name_ar' => $to, 'updated_at' => now()]);

            $this->command?->line("  - «{$from}» ← «{$to}»");
        }
    }

    private function reportLeftovers(int $sourceId): void
    {
        $left = DB::table('options')->where('group_id', $sourceId)->pluck('name_ar');

        if ($left->isEmpty()) {
            return;
        }

        $this->command?->warn('  ! بقي في المصدر بلا مجموعة جديدة : ' . $left->implode('، '));
    }

    private function retireEmptySource(int $sourceId, string $nameAr): void
    {
        if (DB::table('options')->where('group_id', $sourceId)->exists()) {
            return;
        }

        $updated = DB::table('option_groups')
            ->where('id', $sourceId)->where('is_active', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        if ($updated > 0) {
            $this->command?->line("  - «{$nameAr}» فرغت وأُوقفت — تبقى سجلًّا لما خرج منها.");
        }
    }
}
