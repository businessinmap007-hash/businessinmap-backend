<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;

/**
 * What a merchant has committed to an option, so no seeder can take it away.
 *
 * The rule «a link is never removed if a business under that child has already
 * chosen it» has been in this codebase since the first taxonomy sweep, and every
 * seeder that drops a link implements it — against `option_user`, and only
 * against `option_user`.
 *
 * That is the weaker of the two commitments. Ticking an option says «this
 * describes me». Putting a PRICE on it says «this is what I sell and here is
 * what it costs», and `business_service_prices.line_option_id` is where that
 * lives. A tick was protected and a price was not.
 *
 * The bill for that arrived as «فندق الاندلس»: 2,000 on «شقة» and 5,000 on
 * «ڤيلا», both still in `business_service_prices`, both pointing at options the
 * hotel child stopped offering when the hotels were narrowed to «الغرف». Two
 * priced rows the merchant can no longer reach and nothing told him.
 *
 * `WorkshopRemodelSeeder` got this right on its own back in the workshop fold.
 * This is that guard, stated once, for everybody else.
 */
class MerchantOptionCommitments
{
    /**
     * Of `$optionIds`, the ones a merchant STANDING ON THIS CHILD has ticked or
     * priced. What the per-child drop paths need.
     *
     * @param  iterable<int>  $optionIds
     * @return array<int,int>
     */
    public function forChild(int $childId, iterable $optionIds): array
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $ticked = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('u.category_child_id', $childId)
            ->whereIn('ou.option_id', $ids)
            ->pluck('ou.option_id');

        $priced = DB::table('business_service_prices as p')
            ->join('users as u', 'u.id', '=', 'p.business_id')
            ->where('u.category_child_id', $childId)
            ->whereIn('p.line_option_id', $ids)
            ->pluck('p.line_option_id');

        return $ticked->merge($priced)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Of `$optionIds`, the ones ANY merchant has ticked or priced, whatever
     * child they stand on. What the whole-table retirement paths need — those
     * unlink an option everywhere, so the question is not «on this child» but
     * «anywhere at all».
     *
     * @param  iterable<int>  $optionIds
     * @return array<int,int>
     */
    public function anywhere(iterable $optionIds): array
    {
        $ids = collect($optionIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $ticked = DB::table('option_user')->whereIn('option_id', $ids)->pluck('option_id');

        $priced = DB::table('business_service_prices')
            ->whereIn('line_option_id', $ids)->pluck('line_option_id');

        return $ticked->merge($priced)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Every (child, option) pair a merchant has committed to and the taxonomy no
     * longer offers. The detector that found «فندق الاندلس», kept so a test can
     * hold the line rather than a one-off script.
     *
     * @return array<int,object{child_id:int,option_id:int,price_rows:int}>
     */
    public function orphaned(): array
    {
        return DB::table('business_service_prices as p')
            ->join('users as u', 'u.id', '=', 'p.business_id')
            // `> 0`, not `NOT NULL`. Zero is the direct-booking price — the row
            // is on the CHILD itself with no line behind it, which is what
            // `requires_bookable_item: false` produces and is not an orphan.
            // Six of the eleven priced rows on the platform are that shape.
            ->where('p.line_option_id', '>', 0)
            ->whereNotNull('u.category_child_id')
            ->whereNotExists(fn ($q) => $q->from('category_child_option as cco')
                ->whereColumn('cco.child_id', 'u.category_child_id')
                ->whereColumn('cco.option_id', 'p.line_option_id'))
            ->groupBy('u.category_child_id', 'p.line_option_id')
            ->select('u.category_child_id as child_id', 'p.line_option_id as option_id', DB::raw('COUNT(*) as price_rows'))
            ->get()
            ->all();
    }
}
