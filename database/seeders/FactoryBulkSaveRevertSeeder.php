<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Undoes one bulk save on «مصانع» that gave every factory child the doors list.
 *
 *     php artisan db:seed --class=FactoryBulkSaveRevertSeeder
 *
 * On 2026-08-11 between 22:45:33 and 22:46:06 a services-bulk save ran against
 * root 23 and wrote 1,685 decisions in four batches. What it did, read off
 * `category_child_option_decisions`:
 *
 *   pinned      «أنواع الأبواب والشبابيك»   672 rows across 42 children
 *   withdrawn   every child's OWN trade list  540 rows, one group per child
 *
 * So a food factory sold «شاتر كهربائي» and could no longer say «معلبات»;
 * «طباعة العبوات والتغليف» dropped to ZERO holders platform-wide; and the
 * «درجة قطعة الغيار» axis built an hour earlier was withdrawn from #44.
 *
 * This is the same screen that switched booking on for all seventy «شركات»
 * children on 2026-08-11 03:01 — a picker in replace mode writes ONE vocabulary
 * onto every selected child and withdraws whatever each of them was saying.
 *
 * What is reverted, and what is deliberately NOT:
 *
 *   - the doors LINKS at root scope, for every child except «باب وشباك» #50,
 *     which is the doors trade and held that list since 2026-08-10;
 *   - the `pinned` records that back them, so the pin does not re-assert;
 *   - the 540 `withdrawn` records, because the withdrawal record OUTRANKS every
 *     seeder — leave them and the vocabularies can never be restored.
 *
 *   - the universal-axis pins from the same save (نطاق التعامل، حالة المنتج،
 *     الاستبدال والإرجاع، التسليم والاستلام) are LEFT STANDING. They are the
 *     same evening-out this walk has been doing child by child, and the owner's
 *     hand outranks an inference of mine about a kiln or an accessories maker.
 *
 * A merchant's own tick is never touched: the only three accounts holding a
 * door option are the UPVC merchants who moved onto #50 an hour before.
 *
 * Idempotent — a second run reports zero of everything.
 */
class FactoryBulkSaveRevertSeeder extends Seeder
{
    private const FROM = '2026-08-11 22:45:33';
    private const TO = '2026-08-11 22:46:06';

    /** The doors trade itself. It keeps the list. */
    private const DOORS_TRADE = 50;

    private const DOORS_GROUP = 'أنواع الأبواب والشبابيك';

    public function run(): void
    {
        $rootId = (int) DB::table('categories')->where('slug', 'factories')->value('id');

        DB::transaction(function () use ($rootId) {
            $doorOptions = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', self::DOORS_GROUP)->pluck('o.id');

            /*
             * Whatever a merchant ticked stays — but the tick protects the
             * option ON THAT MERCHANT'S CHILD, not everywhere. A flat
             * `whereNotIn(option_id, ticked)` spared «بي في سي (UPVC)» on all
             * forty-three, because three accounts under «باب وشباك» hold it:
             * one door row survived on every factory in the root.
             */
            // Collected first and deleted by id: MariaDB refuses a DELETE whose
            // FROM carries an alias, and the correlated guard below needs one.
            $doomed = DB::table('category_child_option as co')
                ->whereIn('co.option_id', $doorOptions)
                ->where('co.category_id', $rootId)
                ->where('co.child_id', '!=', self::DOORS_TRADE)
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('option_user as ou')
                    ->join('users as u', 'u.id', '=', 'ou.user_id')
                    ->whereColumn('ou.option_id', 'co.option_id')
                    ->whereColumn('u.category_child_id', 'co.child_id'))
                ->pluck('co.id');

            $links = DB::table('category_child_option')->whereIn('id', $doomed)->delete();

            $pins = DB::table('category_child_option_decisions as d')
                ->join('options as o', 'o.id', '=', 'd.option_id')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereBetween('d.created_at', [self::FROM, self::TO])
                ->where('d.kind', 'pinned')
                ->where('g.name_ar', self::DOORS_GROUP)
                ->where('d.child_id', '!=', self::DOORS_TRADE)
                ->pluck('d.id');

            $pins = DB::table('category_child_option_decisions')->whereIn('id', $pins)->delete();

            // The withdrawals go last and go whole: every seeder consults this
            // record first, so a vocabulary cannot come back while its own
            // withdrawal still stands.
            $withdrawals = DB::table('category_child_option_decisions')
                ->whereBetween('created_at', [self::FROM, self::TO])
                ->where('kind', 'withdrawn')
                ->delete();

            $this->command?->info('Factory bulk-save revert:');
            $this->command?->line("  - روابط أبواب أُزيلت : {$links}");
            $this->command?->line("  - تثبيتات أبواب أُزيلت : {$pins}");
            $this->command?->line("  - سجلات سحب أُزيلت : {$withdrawals}");
            $this->command?->line('  - أعد تشغيل بذور المعاجم بعد هذه لاستعادة القوائم.');
        });
    }
}
