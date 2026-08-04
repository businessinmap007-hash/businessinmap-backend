<?php

namespace App\Services;

use App\Models\OptionGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The words a particular merchant may price in.
 *
 * A hospital must not be shown 41 specialties when it practises four. The
 * catalogue narrows twice before it reaches the pricing screen:
 *
 *   1. to the merchant's (root, child) — what a business of this kind may
 *      carry at all, per [[per-root-child-options]];
 *   2. to what THIS merchant ticked about itself (`option_user`).
 *
 * Then it splits by `option_groups.price_role`: `line` groups are what may be
 * sold, `modifier` groups are what may qualify it, and `descriptive` groups —
 * «كاش», «ممنوع التدخين», the widest on the platform — never appear.
 *
 * **A merchant who has ticked nothing gets his child's full priceable list.**
 * Narrowing to an empty answer sheet would leave a new business unable to price
 * anything at all, which is worse than a long list.
 */
class MerchantOfferingVocabulary
{
    public function __construct(private readonly CategoryChildOptionScope $scope)
    {
    }

    /**
     * @return array{lines:\Illuminate\Support\Collection,modifiers:\Illuminate\Support\Collection,narrowed:bool}
     *         each collection keyed by group name => Collection<option rows>
     */
    public function for(int $businessId, int $childId, int $rootId = 0): array
    {
        $allowed = $this->scope->idsFor($childId, $rootId);

        if ($allowed->isEmpty()) {
            return ['lines' => collect(), 'modifiers' => collect(), 'narrowed' => false];
        }

        $ticked = DB::table('option_user')
            ->where('user_id', $businessId)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id);

        $own = $ticked->intersect($allowed);
        $narrowed = $own->isNotEmpty();

        $options = $this->priceableOptions($narrowed ? $own : $allowed);

        return [
            'lines' => $this->group($options->where('price_role', OptionGroup::ROLE_LINE)),
            'modifiers' => $this->group($options->where('price_role', OptionGroup::ROLE_MODIFIER)),
            'narrowed' => $narrowed,
        ];
    }

    /** Option ids this merchant may attach to an offering, in any role. */
    public function allowedIds(int $businessId, int $childId, int $rootId = 0): Collection
    {
        $vocabulary = $this->for($businessId, $childId, $rootId);

        return $vocabulary['lines']->flatten()
            ->merge($vocabulary['modifiers']->flatten())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /** Which role an option may play, or null when it may not be priced at all. */
    public function roleOf(int $optionId): ?string
    {
        $role = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('o.id', $optionId)
            ->value('g.price_role');

        return in_array($role, [OptionGroup::ROLE_LINE, OptionGroup::ROLE_MODIFIER], true)
            ? $role
            : null;
    }

    /** @param  \Illuminate\Support\Collection<int,int>  $ids */
    private function priceableOptions(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('o.id', $ids)
            ->where('g.is_active', 1)
            ->whereIn('g.price_role', [OptionGroup::ROLE_LINE, OptionGroup::ROLE_MODIFIER])
            ->orderBy('g.reorder')
            ->orderBy('o.id')
            ->get(['o.id', 'o.name_ar', 'o.name_en', 'g.id as group_id', 'g.name_ar as group_name', 'g.price_role']);
    }

    private function group(Collection $options): Collection
    {
        return $options->groupBy('group_name');
    }
}
