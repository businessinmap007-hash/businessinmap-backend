<?php

namespace App\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «كل منتج ممكن يكون له 5 اسعار: جديد - مستعمل - كسر زيرو - كاش - قسط» —
 * المالك، 2026-09-24. The two price dimensions a retail listing can carry:
 * its condition and how it is paid for. Each combination a business holds is
 * its own listing row (own price, stock, description).
 *
 * The options a business may use are the ones its child carries in those two
 * groups (category_child_option, under its root), so a shop is never offered a
 * condition its trade does not have.
 */
final class RetailPriceVariants
{
    public const CONDITION_GROUP = 'حالة المنتج';

    public const PAYMENT_GROUP = 'الدفع والسداد';

    /**
     * @return array{condition: Collection<int,object>, payment: Collection<int,object>} option rows (id, name_ar, name_en)
     */
    public function optionsFor(int $childId, int $rootId): array
    {
        return [
            'condition' => $this->optionsIn(self::CONDITION_GROUP, $childId, $rootId),
            'payment' => $this->optionsIn(self::PAYMENT_GROUP, $childId, $rootId),
        ];
    }

    /** @return Collection<int,object> */
    private function optionsIn(string $groupName, int $childId, int $rootId): Collection
    {
        return DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $groupName)
            ->where('cco.child_id', $childId)
            ->when($rootId > 0, fn ($q) => $q->whereIn('cco.category_id', [0, $rootId]))
            ->distinct()
            ->orderBy('o.id')
            ->get(['o.id', 'o.name_ar', 'o.name_en']);
    }

    /** Every option id of both dimension groups, for public filters (not scoped to a child). */
    public function allOptions(): array
    {
        $rows = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('g.name_ar', [self::CONDITION_GROUP, self::PAYMENT_GROUP])
            ->get(['o.id', 'o.name_ar', 'o.name_en', 'g.name_ar as group_name']);

        return [
            'condition' => $rows->where('group_name', self::CONDITION_GROUP)->values(),
            'payment' => $rows->where('group_name', self::PAYMENT_GROUP)->values(),
        ];
    }
}
