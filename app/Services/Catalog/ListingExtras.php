<?php

namespace App\Services\Catalog;

use App\Models\BusinessCatalogListingExtra as Extra;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The add-ons of retail listings: what a customer is shown, what a merchant
 * saves, and what the cart may price in. One place for all three so the rule
 * «a pick-one group takes at most one» cannot drift between them.
 */
final class ListingExtras
{
    /**
     * Customer-facing payload of many listings at once (no N+1).
     *
     * @param  array<int,int>  $listingIds
     * @return array<int, list<array{id:int,name:string,price:float,group:?string,selection:string}>>  keyed by listing id
     */
    public function forListings(array $listingIds): array
    {
        $listingIds = array_values(array_unique(array_filter(array_map('intval', $listingIds))));
        if ($listingIds === []) {
            return [];
        }

        $out = array_fill_keys($listingIds, []);

        foreach (Extra::query()->whereIn('listing_id', $listingIds)->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $x) {
            $out[(int) $x->listing_id][] = [
                'id' => (int) $x->id,
                'name' => $x->displayName(),
                'price' => (float) $x->price,
                'group' => trim((string) $x->group_name_ar) !== '' ? (string) $x->group_name_ar : null,
                'selection' => (string) $x->selection_type,
            ];
        }

        return $out;
    }

    /**
     * Replace a listing's add-ons with the merchant's submitted set. A row with
     * an id keeps it; rows left out are removed (past orders hold their own
     * snapshot, so nothing is lost). Rows of one group share the type of the
     * group's first row.
     *
     * @param  list<array<string,mixed>>  $rows
     */
    public function sync(int $listingId, array $rows): void
    {
        $groupType = [];
        $clean = [];

        foreach ($rows as $i => $row) {
            $group = trim((string) ($row['group_name_ar'] ?? ''));
            $type = ($row['selection_type'] ?? Extra::SELECTION_MULTIPLE) === Extra::SELECTION_SINGLE
                ? Extra::SELECTION_SINGLE
                : Extra::SELECTION_MULTIPLE;

            if ($group !== '') {
                $type = $groupType[$group] ??= $type;
            } else {
                $type = Extra::SELECTION_MULTIPLE;
            }

            $clean[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name_ar' => trim((string) $row['name_ar']),
                'name_en' => trim((string) ($row['name_en'] ?? '')) ?: null,
                'price' => round((float) $row['price'], 2),
                'group_name_ar' => $group,
                'selection_type' => $type,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => $i,
            ];
        }

        DB::transaction(function () use ($listingId, $clean) {
            $keep = [];

            foreach ($clean as $row) {
                $id = $row['id'];
                unset($row['id']);

                $model = $id > 0 ? Extra::query()->where('listing_id', $listingId)->find($id) : null;
                $model ??= new Extra(['listing_id' => $listingId]);
                $model->fill($row)->save();

                $keep[] = (int) $model->id;
            }

            Extra::query()->where('listing_id', $listingId)->whereNotIn('id', $keep ?: [0])->delete();
        });
    }

    /**
     * The cart's view: validate the customer's picks against the listing and
     * return the snapshot lines [['id','name','price','qty'], ...] — priced
     * server-side, never from the client.
     *
     * @param  mixed  $extras  list of ids or [id, qty] pairs, as for menu extras
     * @return list<array{id:int,name:string,price:float,qty:int}>
     */
    public function resolve(int $listingId, $extras): array
    {
        if (! is_array($extras) || $extras === []) {
            return [];
        }

        $wanted = [];
        foreach ($extras as $e) {
            $id = (int) (is_array($e) ? ($e['id'] ?? 0) : $e);
            if ($id > 0) {
                $wanted[$id] = true;
            }
        }

        if ($wanted === []) {
            return [];
        }

        $rows = Extra::query()
            ->where('listing_id', $listingId)
            ->where('is_active', true)
            ->whereIn('id', array_keys($wanted))
            ->orderBy('id')
            ->get();

        if ($rows->count() !== count($wanted)) {
            throw ValidationException::withMessages(['extras' => __('إحدى الإضافات المختارة غير متاحة.')]);
        }

        $singles = $rows->where('selection_type', Extra::SELECTION_SINGLE)->where('group_name_ar', '!=', '')->groupBy('group_name_ar');
        foreach ($singles as $group => $picked) {
            if ($picked->count() > 1) {
                throw ValidationException::withMessages(['extras' => __('يجب اختيار خيار واحد فقط من :group.', ['group' => $group])]);
            }
        }

        return $rows->map(fn (Extra $x) => [
            'id' => (int) $x->id,
            'name' => $x->displayName(),
            'price' => round((float) $x->price, 2),
            'qty' => 1,
        ])->values()->all();
    }
}
