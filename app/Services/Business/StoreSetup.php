<?php

namespace App\Services\Business;

use App\Models\User;
use App\Services\Menu\StoreTerms;
use Illuminate\Support\Facades\DB;

/**
 * «اجعل الحساب لا يمكن أن يعرض منتجات دون اختيار طرق الاستلام والتسليم» — المالك، 2026-10-06. A business account is
 * not COMPLETE until it has said how it delivers and hands over orders (the «التسليم والاستلام» terms its trade asks
 * of it). Until then its products are not shown to customers and cannot be ordered; the account's own screens keep
 * working and say what is missing.
 */
final class StoreSetup
{
    public const FULFILLMENT = 'fulfillment';

    private const GROUP_NAME = 'التسليم والاستلام';

    public function __construct(private readonly StoreTerms $terms)
    {
    }

    /** The «التسليم والاستلام» group — the one question a business must answer before it can show products. */
    public function groupId(): int
    {
        return (int) DB::table('option_groups')->where('name_ar', self::GROUP_NAME)->value('id');
    }

    /** @return list<string> what this business still has to set up (empty = complete) */
    public function missing(User|int $business): array
    {
        $id = $business instanceof User ? (int) $business->id : $business;
        $group = $this->groupId();

        if ($group <= 0 || ! $this->terms->asks($id, $group)) {
            return [];
        }

        $ticked = DB::table('option_user as ou')->join('options as o', 'o.id', '=', 'ou.option_id')
            ->where('ou.user_id', $id)->where('o.group_id', $group)->exists();

        return $ticked ? [] : [self::FULFILLMENT];
    }

    public function isComplete(User|int $business): bool
    {
        return $this->missing($business) === [];
    }

    /**
     * Every business that cannot show products yet — for the readers that list many shops at once.
     *
     * @return list<int>
     */
    public function incompleteIds(): array
    {
        $group = $this->groupId();
        if ($group <= 0) {
            return [];
        }

        $silent = DB::table('users as u')
            ->where('u.type', 'business')->whereNotNull('u.category_child_id')
            ->whereNotExists(function ($q) use ($group) {
                $q->selectRaw('1')->from('option_user as ou')->join('options as o', 'o.id', '=', 'ou.option_id')
                    ->whereColumn('ou.user_id', 'u.id')->where('o.group_id', $group);
            })
            ->get(['u.id', 'u.category_child_id']);

        $asks = [];
        $ids = [];
        foreach ($silent as $row) {
            $child = (int) $row->category_child_id;
            $asks[$child] ??= in_array($group, $this->terms->groupIdsForChild($child), true);
            if ($asks[$child]) {
                $ids[] = (int) $row->id;
            }
        }

        return $ids;
    }

    /** Narrow a query to businesses that may show products: drops the incomplete ones (never the viewer's own). */
    public function onlyComplete($query, string $businessColumn, ?int $exceptBusinessId = null)
    {
        $ids = array_values(array_diff($this->incompleteIds(), $exceptBusinessId ? [$exceptBusinessId] : []));

        return $ids === [] ? $query : $query->whereNotIn($businessColumn, $ids);
    }
}
