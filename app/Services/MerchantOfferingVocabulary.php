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
 * **A merchant with no PRICEABLE ticks gets his child's full priceable list.**
 * Narrowing to an empty answer sheet would leave a business unable to price
 * anything at all, which is worse than a long list — and that applies just as
 * much to a merchant who ticked only descriptive things about himself as to one
 * who ticked nothing.
 *
 * ## The role is a platform default, not a verdict on the child
 *
 * «أنواع الأخشاب» is a `modifier` because a wood species usually qualifies a
 * piece of furniture. Under «أخشاب» it is not a qualifier at all — زان and MDF
 * and كونتر are the entire product line, sold by the metre. That child has no
 * `line` group and never will, so the strict reading left a timber merchant
 * unable to name a single thing he sells.
 *
 * So the roles are read as a PREFERENCE, not a gate: a child with no `line`
 * group sells its modifiers, and a child with nothing but descriptive words
 * sells those. Nothing is invented and nothing widens — the merchant still
 * sees only what his own ticks (or his child's list) already contained.
 * The promoted rows stay available as modifiers too, so he decides which of
 * his ticks is the product and which merely qualifies it.
 */
class MerchantOfferingVocabulary
{
    /**
     * Groups that describe the DEAL, never the thing being sold.
     *
     * They are the widest on the platform — «حالة المنتج» reaches 101 trades,
     * «الدفع والسداد» 181 — and a word a hundred different trades say is nobody's
     * product line. Promotion skips them, so a timber yard is offered زان and
     * MDF and not «جديد» or «كاش». They remain perfectly good MODIFIERS: a
     * plank really can be sold new or used.
     */
    private const BUSINESS_LEVEL = [
        // descriptive
        'الدفع والسداد',
        'التسليم والاستلام',
        'نطاق التعامل',
        'الاستبدال والإرجاع',
        'الحد الأدنى للطلب',
        'نوع العملاء',
        'ملاءمة المكان',
        // modifier — about the transaction, not the goods
        'حالة المنتج',
        'نظام التصنيع',
        'نمط تقديم الخدمة',
        'نظام التعاقد',
        'نوع التعامل',
        'وحدة البيع',
        'سرعة الشحن',
        'نطاق الشحن',
        'مكان العقد',
        'الجمهور المستهدف',
    ];

    public function __construct(private readonly CategoryChildOptionScope $scope)
    {
    }

    /**
     * @return array{lines:\Illuminate\Support\Collection,modifiers:\Illuminate\Support\Collection,narrowed:bool,promoted:?string}
     *         each collection keyed by group name => Collection<option rows>
     */
    public function for(int $businessId, int $childId, int $rootId = 0): array
    {
        $allowed = $this->scope->idsFor($childId, $rootId);

        if ($allowed->isEmpty()) {
            return ['lines' => collect(), 'modifiers' => collect(), 'narrowed' => false, 'promoted' => null];
        }

        $ticked = DB::table('option_user')
            ->where('user_id', $businessId)
            ->pluck('option_id')
            ->map(fn ($id) => (int) $id);

        $own = $ticked->intersect($allowed);

        // Narrow only when the merchant's own ticks contain something SELLABLE.
        // Ticking «واي فاي» is not an answer about what you sell, and treating
        // it as one silenced the merchant completely: `narrowed` went true and
        // the priceable list came back empty — the exact outcome the paragraph
        // above refuses for a merchant who ticked nothing at all. A hotel that
        // had ticked one descriptive option could not name a single room kind.
        [$options, $narrowed] = $this->sellable($own, $allowed);

        $lines = $options->where('price_role', OptionGroup::ROLE_LINE)->values();
        $modifiers = $options->where('price_role', OptionGroup::ROLE_MODIFIER)->values();
        $promoted = null;

        if ($lines->isEmpty()) {
            $candidates = $modifiers->isNotEmpty() ? $modifiers : $options->values();
            $lines = $candidates->reject(
                fn ($o) => in_array($o->group_name, self::BUSINESS_LEVEL, true)
            )->values();

            $promoted = $lines->isEmpty()
                ? null
                : ($modifiers->isNotEmpty() ? OptionGroup::ROLE_MODIFIER : OptionGroup::ROLE_DESCRIPTIVE);
        }

        return [
            'lines' => $this->group($lines),
            'modifiers' => $this->group($modifiers),
            'narrowed' => $narrowed,
            'promoted' => $promoted,
        ];
    }

    /**
     * What this merchant may put in each slot, as plain ids.
     *
     * Callers used to pair `allowedIds()` with `roleOf()`, which reads the
     * group's PLATFORM role and so refused every promoted line — the timber
     * merchant's «زان» posted back as «not a line» and vanished. The role a
     * given option plays depends on the child, so it is answered here, once,
     * by the same pass that built the screen.
     *
     * @return array{lines:\Illuminate\Support\Collection<int,int>,modifiers:\Illuminate\Support\Collection<int,int>}
     */
    public function pickableIds(int $businessId, int $childId, int $rootId = 0): array
    {
        $vocabulary = $this->for($businessId, $childId, $rootId);

        $ids = fn (string $slot) => collect($vocabulary[$slot])->flatten()
            ->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        return ['lines' => $ids('lines'), 'modifiers' => $ids('modifiers')];
    }

    /**
     * The merchant's own answer sheet if it says anything sellable, else his
     * child's whole list — trying `line`/`modifier` first and falling back to
     * the child's own descriptive words only when it has nothing else.
     *
     * @return array{0:Collection,1:bool} the options, and whether they are his own
     */
    private function sellable(Collection $own, Collection $allowed): array
    {
        $priceable = [OptionGroup::ROLE_LINE, OptionGroup::ROLE_MODIFIER];

        foreach ([$priceable, [OptionGroup::ROLE_DESCRIPTIVE]] as $roles) {
            foreach ([[$own, true], [$allowed, false]] as [$ids, $narrowed]) {
                $options = $this->optionsIn($ids, $roles);

                if ($options->isNotEmpty()) {
                    return [$options, $narrowed];
                }
            }
        }

        return [collect(), false];
    }

    /** Option ids this merchant may attach to an offering, in any role. */
    public function allowedIds(int $businessId, int $childId, int $rootId = 0): Collection
    {
        $picks = $this->pickableIds($businessId, $childId, $rootId);

        return $picks['lines']->merge($picks['modifiers'])->unique()->values();
    }

    /**
     * Which role an option plays PLATFORM-WIDE.
     *
     * Not the question a merchant screen is asking — see `pickableIds()` for
     * the per-child answer, which is the one that decides what he may post.
     */
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

    /**
     * @param  \Illuminate\Support\Collection<int,int>  $ids
     * @param  array<int,string>  $roles
     */
    private function optionsIn(Collection $ids, array $roles): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $options = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->whereIn('o.id', $ids)
            ->where('g.is_active', 1)
            ->whereIn('g.price_role', $roles)
            ->orderByRaw(OptionGroup::displayOrderSql('g'))
            ->orderByRaw('COALESCE(g.reorder, 999999) ASC')
            ->orderBy('o.id')
            ->get(['o.id', 'o.name_ar', 'o.name_en', 'g.id as group_id', 'g.name_ar as group_name', 'g.price_role']);

        if ($roles !== [OptionGroup::ROLE_DESCRIPTIVE]) {
            return $options;
        }

        return $options->reject(fn ($o) => in_array($o->group_name, self::BUSINESS_LEVEL, true))->values();
    }

    private function group(Collection $options): Collection
    {
        return $options->groupBy('group_name');
    }
}
