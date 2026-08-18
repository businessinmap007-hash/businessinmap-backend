<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «بيع أم إيجار» is the same question about a flat and about a car.
 *
 * Real estate already had the axis — group «نوع التعامل العقاري», a MODIFIER,
 * because شقة بيع and شقة إيجار are two prices for one property. A car showroom
 * asks exactly that and had no way to say it: its whole vocabulary was نوع
 * المركبة (line) × ماركة × حالة, so a showroom that also rents could price the
 * sale and nothing else. «تأجير سيارات» exists nowhere in the taxonomy either,
 * which is why renting had no home at all.
 *
 * So the group stops being about property. It is renamed «نوع التعامل» and the
 * three vehicle showrooms are given it — one axis, two verticals, rather than a
 * second group repeating بيع/إيجار in different words (owner call 2026-08-08).
 *
 * **«تبديل» is vehicles-only.** A group is shared but a CHILD's view of it is
 * not: `category_child_option` is the gate, so the four real-estate children
 * keep the pair they were given and never see the trade-in.
 *
 * What this does NOT do: renting a NAMED car on given dates. A modifier makes
 * the rental priceable and listable; reserving car #7 for Thursday still needs
 * `requires_bookable_item` and registered units, the way a hotel names room 101
 * — see BookableItem::displayLabel() and the unit-discovery endpoint.
 *
 * ⚠ The group is resolved BY NAME everywhere (option_groups has no key column),
 * so the rename had to land in `option_group_splits.php` and
 * `option_price_roles.php` in the same commit. Leaving either behind would have
 * had the next run create «نوع التعامل العقاري» a second time and move بيع
 * وشراء / إيجار into it, splitting the axis in two.
 *
 * Idempotent, and additive only: it never unlinks an option from a child.
 */
class VehicleDealTypeSeeder extends Seeder
{
    private const NAME_AR = 'نوع التعامل';

    private const NAME_EN = 'Deal Type';

    /** What it used to be called, so a re-run finds it either way. */
    private const FORMER_NAME_AR = 'نوع التعامل العقاري';

    /**
     * «إيجار» — the row every child on this axis answers identically.
     *
     * «بيع وشراء» #53 used to be here beside it, and on 2026-08-17 the owner
     * split it: «والتقسيم على الكل». A trader who BUYS from you — «بنشترى
     * عربيتك» on the showroom window, «بنشترى الذهب» over the goldsmith's
     * counter — is making a different offer from one who sells to you, and a
     * customer looks for exactly one of the two. Merged into a single row they
     * could not be told apart, and every child on the axis was making both
     * claims whether it meant them or not.
     *
     * ON THE WHOLE AXIS, not the vehicles. That is the instruction and it is
     * also the only coherent reading: the split exists so a merchant TICKS what
     * he does, so an owner listing his own flat says «بيع» and nothing else,
     * while the office two doors down says both. Leaving nine children merged
     * would have kept the ambiguity exactly where the private seller is.
     */
    private const SHARED_OPTIONS = [302];

    /** The two halves «بيع وشراء» was hiding. */
    private const SALE_OPTIONS = [
        ['بيع', 'For Sale'],
        ['شراء', 'We Buy'],
    ];

    /** The row the split replaces, everywhere it is linked. */
    private const MERGED_SALE_OPTION = 53;

    /**
     * The showrooms that sell a vehicle, not the shops that service one.
     *
     * «سيارات» #53 — the CHILD, not to be confused with option #53 above — was
     * folded into «معرض سيارات» #188 on 2026-08-17 and is retired. #188 moved
     * from «معارض» to «سيارات» in the same change, which is why the root that
     * is named for cars now contains one.
     */
    private const VEHICLE_CHILDREN = [
        188, // معرض سيارات
        189, // معرض موتوسيكلات
    ];

    public function run(): void
    {
        $groupId = (int) DB::table('option_groups')
            ->whereIn('name_ar', [self::NAME_AR, self::FORMER_NAME_AR])
            ->value('id');

        if ($groupId <= 0) {
            $this->command?->warn('The deal-type group is missing — nothing to do.');

            return;
        }

        DB::transaction(function () use ($groupId) {
            DB::table('option_groups')->where('id', $groupId)->update([
                'name_ar' => self::NAME_AR,
                'name_en' => self::NAME_EN,
                'price_role' => 'modifier',
                'updated_at' => now(),
            ]);

            $tradeIn = $this->option($groupId, 'تبديل', 'Trade-in');

            $sale = [];
            foreach (self::SALE_OPTIONS as [$ar, $en]) {
                $sale[] = $this->option($groupId, $ar, $en);
            }

            $linked = 0;

            foreach (self::VEHICLE_CHILDREN as $childId) {
                foreach (array_merge(self::SHARED_OPTIONS, $sale, [$tradeIn]) as $optionId) {
                    /*
                     * category_id = 0 means «under every root this child sits
                     * under». A showroom answers بيع/إيجار the same way whatever
                     * root it is reached through, so there is nothing to split.
                     */
                    $exists = DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->where('option_id', $optionId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('category_child_option')->insert([
                        'child_id' => $childId,
                        'category_id' => 0,
                        'option_id' => $optionId,
                        'reorder' => 0,
                    ]);

                    $linked++;
                }
            }

            $migrated = $this->splitMergedSale($sale);
            $unmerged = $this->retireMergedSale();

            $this->command?->info(
                'Deal type: group «' . self::NAME_AR . "», تبديل #{$tradeIn}, {$linked} new child link(s), "
                . "{$migrated} link(s) migrated to بيع/شراء, «بيع وشراء» removed from {$unmerged} child(ren)."
            );
        });
    }

    /** Created once, found forever after — matched inside its own group. */
    private function option(int $groupId, string $ar, string $en): int
    {
        $id = (int) DB::table('options')
            ->where('group_id', $groupId)
            ->where('name_ar', $ar)
            ->value('id');

        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => $ar,
            'name_en' => $en,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Give «بيع» and «شراء» to everyone «بيع وشراء» reached.
     *
     * AT THE SAME SCOPE, row for row. The merged option is held at four
     * different scopes — shared for the property children, root 14 for
     * «اكسسوار» and «ملابس» and «جلود», root 21 for «آثاث», root 17 for «ذهب» —
     * and each of those is a decision somebody made about which storefront the
     * word belongs in. Granting the halves shared would quietly widen every one
     * of them, which is the leak this taxonomy keeps closing.
     *
     * The withdrawal ledger still has the last word: a child that was refused
     * the merged row under some root is refused both halves there too.
     *
     * @param  array<int,int>  $sale
     */
    private function splitMergedSale(array $sale): int
    {
        $blocked = app(ChildOptionDecisions::class)->blockedByChild();

        $rows = DB::table('category_child_option')
            ->where('option_id', self::MERGED_SALE_OPTION)
            ->get(['child_id', 'category_id', 'reorder']);

        $added = 0;

        foreach ($rows as $row) {
            $childId = (int) $row->child_id;

            foreach ($sale as $optionId) {
                if (isset($blocked[$childId][$optionId])) {
                    continue;
                }

                $exists = DB::table('category_child_option')
                    ->where('child_id', $childId)
                    ->where('category_id', (int) $row->category_id)
                    ->where('option_id', $optionId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('category_child_option')->insert([
                    'child_id' => $childId,
                    'category_id' => (int) $row->category_id,
                    'option_id' => $optionId,
                    'reorder' => (int) $row->reorder,
                ]);

                $added++;
            }
        }

        return $added;
    }

    /**
     * Take «بيع وشراء» off every child, now that both halves are there.
     *
     * Without this a showroom would hold all three and be asked the same
     * question twice, which is worse than the merged row was.
     *
     * The option ROW is not deleted and not unfiled. It stays in «نوع التعامل»
     * with no child link — unreachable by `idsFor()`, which is what retirement
     * means here, and readable as the record of what the two halves came from.
     *
     * A merchant's own tick outranks all of it: none had ticked it when this
     * ran, and if one ever has, that child's link stays and is reported rather
     * than cut from under a live answer.
     */
    private function retireMergedSale(): int
    {
        $chosen = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('ou.option_id', self::MERGED_SALE_OPTION)
            ->pluck('u.category_child_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($chosen as $childId) {
            $this->command?->warn("  ! تاجر على الابن #{$childId} مؤشّر على «بيع وشراء» — تُرك له.");
        }

        /*
         * A pin is spared exactly as a merchant's tick is.
         *
         * «ملابس» #59 and «ذهب» #127 were both pinned on the merged row by hand
         * — the 14th and the 16th — and this method deleted it on every run
         * regardless, so the seeder and the ledger took turns and neither ever
         * won. Sparing only merchants who ticked the option reads the ledger in
         * one direction: a withdrawal refuses a grant, but a pin was not
         * allowed to refuse a deletion.
         *
         * What that leaves behind is a child holding all three of «بيع وشراء»،
         * «بيع» and «شراء», which looks wrong and is not: the merged row is a
         * live answer somebody chose to keep, and unpinning it is a decision
         * for the admin screen, not for a seeder running unattended.
         */
        $pinned = app(ChildOptionDecisions::class)
            ->byChild(ChildOptionDecisions::PINNED);

        $spared = $chosen->merge(collect($pinned)
            ->filter(fn ($opts) => isset($opts[self::MERGED_SALE_OPTION]))
            ->keys())->unique();

        foreach ($spared->diff($chosen) as $childId) {
            $this->command?->warn("  ! «بيع وشراء» مثبّتة على الابن #{$childId} — تُركت.");
        }

        return DB::table('category_child_option')
            ->where('option_id', self::MERGED_SALE_OPTION)
            ->when($spared->isNotEmpty(), fn ($q) => $q->whereNotIn('child_id', $spared->all()))
            ->delete();
    }
}
