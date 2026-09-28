<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «المنجو هي صنف بحد ذاتها لا يضاف فيه اصناف لان لدينا انواع المانجو كل
 *  واحد صنف منفرد، واعشاب وورقيات كلما اضيف صنف يضاف فى قائمة اخرى» —
 *  المالك، 2026-09-29. Two separate leftovers in «الفواكه»/«الخضروات»,
 *  both visible on the فهيم demo account (child #114 «خضار وفاكهة» + its
 *  siblings #109/#149/#185/#272 — hyper/mini/super market, مواد غذائية).
 *
 * 1. GENERIC FRUIT catch-alls (مانجو #2398, برتقال #2401, عنب #2400…) that
 *    FoodRangesExpansionSeeder's own comment already states the rule for
 *    («لأن المانجو أنواع كتيرة فالأفضل يكون كل نوع منفرد يُسعّر ويكون له
 *    كمية») — that seeder ADDED the named varieties (مانجو عويس، مانجو
 *    فص…) but never withdrew the generic entry it was meant to replace, so
 *    both sat side by side and a merchant could still file an item under
 *    plain "Mango" instead of a real variety. Withdrawn here for the 11
 *    fruits that now have full variety coverage; a fruit with no variety
 *    breakdown at all (فراولة، كمثرى، جريب فروت…) is left untouched — there
 *    is nothing to redirect it to.
 *
 * 2. A rogue option named «أعشاب وورقيات» (#10232) sitting INSIDE «الخضروات»
 *    group (#825) — a name collision with the real, dedicated «أعشاب
 *    وورقيات» GROUP (#993, its own بقدونس/كزبرة/شبت… options).
 *    `MenuItem::heading()` reads the OPTION'S GROUP name for its heading
 *    label, not the option's own name — so an item filed under the rogue
 *    option surfaces under "الخضروات", not "أعشاب وورقيات": exactly «يضاف
 *    فى قائمة اخرى». Every child that carried the rogue option already
 *    carries the real group's options in full, so nothing is lost by
 *    withdrawing it.
 *
 * Same shape as GroceryAisleSplitSeeder::finishRangeMove(): withdraw
 * through the decision ledger, then drop the link — never delete the
 * option row itself (its `name_en` stays reserved platform-wide, and the
 * row is the historical record of what a child used to offer). فهيم's own
 * three wrong demo items are reassigned to a real variety rather than left
 * pointing at a link that no longer exists.
 *
 *     php artisan db:seed --class=ProduceVarietyCleanupSeeder
 *
 * Idempotent: a second run withdraws nothing and reassigns nothing.
 */
class ProduceVarietyCleanupSeeder extends Seeder
{
    /** Generic fruit catch-alls superseded by named varieties in «الفواكه». */
    private const GENERIC_FRUITS = [2398, 2400, 2401, 2402, 2403, 2404, 2405, 2406, 2407, 2408, 2412];

    /** «أعشاب وورقيات» option sitting inside «الخضروات», duplicating group #993. */
    private const HERBS_DUPLICATE_IN_VEGETABLES = 10232;

    /**
     * فهيم's own generic-fruit self-description (option_user, from
     * registration) and items, reassigned to a real named variety — one per
     * fruit, so nothing is left pointing at a link this seeder just removed.
     * Only 3 of these (مانجو، برتقال، تفاح) had become actual menu_items;
     * the rest were ticked at registration and never turned into a listing.
     */
    private const DEMO_REASSIGN = [
        2398 => ['name_ar' => 'مانجو فص', 'name_en' => 'Fass Mango'],
        2400 => ['name_ar' => 'عنب بناتي', 'name_en' => 'Banati Grapes'],
        2401 => ['name_ar' => 'برتقال بلدي', 'name_en' => 'Baladi Orange'],
        2402 => ['name_ar' => 'يوسفي بلدي', 'name_en' => 'Baladi Mandarin'],
        2403 => ['name_ar' => 'ليمون بلدي', 'name_en' => 'Baladi Lime'],
        2404 => ['name_ar' => 'موز بلدي', 'name_en' => 'Baladi Bananas'],
        2405 => ['name_ar' => 'تفاح أحمر', 'name_en' => 'Red Apples'],
        2406 => ['name_ar' => 'جوافة بلدي', 'name_en' => 'Baladi Guava'],
        2407 => ['name_ar' => 'رمان بناتي', 'name_en' => 'Banati Pomegranate'],
        2408 => ['name_ar' => 'بلح زغلول', 'name_en' => 'Zaghloul Dates'],
        2412 => ['name_ar' => 'بطيخ بلدي', 'name_en' => 'Baladi Watermelon'],
    ];

    public function run(): void
    {
        $decisions = app(ChildOptionDecisions::class);

        // The demo items go first: their option_user ticks are the only
        // thing that could block withdrawal below, on this database.
        $demoFixed = $this->fixDemoItems();

        $withdrawn = 0;
        foreach ([...self::GENERIC_FRUITS, self::HERBS_DUPLICATE_IN_VEGETABLES] as $optionId) {
            $withdrawn += $this->withdrawFromEveryChild($decisions, $optionId);
        }

        $this->command?->info("Produce variety cleanup: {$withdrawn} (child,option) links withdrawn, {$demoFixed} demo items reassigned to a real variety.");
    }

    private function withdrawFromEveryChild(ChildOptionDecisions $decisions, int $optionId): int
    {
        $childIds = DB::table('category_child_option')->where('option_id', $optionId)->pluck('child_id')->unique();

        $n = 0;

        foreach ($childIds as $childId) {
            $childId = (int) $childId;

            $chosen = DB::table('option_user as ou')
                ->join('users as u', 'u.id', '=', 'ou.user_id')
                ->where('u.category_child_id', $childId)->where('ou.option_id', $optionId)
                ->exists();

            if ($chosen) {
                $this->command?->warn("  ! child #{$childId}: option #{$optionId} still ticked by a merchant — kept.");
                continue;
            }

            $decisions->record($childId, ChildOptionDecisions::ALL_ROOTS, [$optionId], 'produce_variety_cleanup');

            $n += DB::table('category_child_option')->where('child_id', $childId)->where('option_id', $optionId)->delete();
        }

        return $n;
    }

    private function fixDemoItems(): int
    {
        $n = 0;

        foreach (self::DEMO_REASSIGN as $genericOptionId => $to) {
            $variety = DB::table('options')->where('name_ar', $to['name_ar'])->first(['id']);
            if (! $variety) {
                continue;
            }

            $itemIds = DB::table('offering_options')
                ->where('option_id', $genericOptionId)
                ->where('offering_type', MenuItem::class)
                ->pluck('offering_id');

            foreach ($itemIds as $itemId) {
                DB::table('offering_options')
                    ->where('offering_id', $itemId)
                    ->where('offering_type', MenuItem::class)
                    ->where('option_id', $genericOptionId)
                    ->update(['option_id' => $variety->id]);

                DB::table('menu_items')->where('id', $itemId)->update([
                    'name_ar' => $to['name_ar'],
                    'name_en' => $to['name_en'],
                    'updated_at' => now(),
                ]);

                $n++;
            }

            $ticked = DB::table('option_user')->where('option_id', $genericOptionId)->pluck('user_id');
            foreach ($ticked as $userId) {
                $alreadyTicked = DB::table('option_user')->where('user_id', $userId)->where('option_id', $variety->id)->exists();
                if (! $alreadyTicked) {
                    DB::table('option_user')->insert(['user_id' => $userId, 'option_id' => $variety->id]);
                }
            }
            DB::table('option_user')->where('option_id', $genericOptionId)->delete();
        }

        return $n;
    }
}
