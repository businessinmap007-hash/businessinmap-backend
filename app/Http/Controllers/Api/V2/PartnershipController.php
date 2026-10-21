<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BusinessPartnership;
use App\Models\User;
use App\Services\Partnerships\AllocationService;
use App\Services\Partnerships\PartnershipService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * «الشراكات والحصص» — the partnership core as a business uses it. Everything here is for the TWO parties only: a stranger (another
 * business, a customer) gets 404 on every route, because a 403 would confirm that the partnership exists.
 */
class PartnershipController extends Controller
{
    public function __construct(
        private readonly PartnershipService $partnerships,
        private readonly AllocationService $allocations
    ) {
    }

    /** GET business/partnerships — mine, as owner or as partner. */
    public function index(Request $request)
    {
        $me = BusinessContext::id($request);

        return response()->json(['success' => true, 'data' => ['partnerships' => $this->partnerships->mine($me)->map(fn ($p) => $this->partnerships->present($p, $me))->values()]]);
    }

    /** GET business/partnerships/lookup?q= — businesses to invite, by name. */
    public function lookup(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $me = BusinessContext::id($request);

        $rows = mb_strlen($q) < 2 ? collect() : User::query()->live()->where('type', User::TYPE_BUSINESS)->where('id', '!=', $me)->searchByName($q)
            ->limit(15)->get(['id', 'name', 'logo']);

        return response()->json(['success' => true, 'data' => ['businesses' => $rows->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name, 'logo' => $u->logo])->values()]]);
    }

    /** POST business/partnerships {partner_business_id, type?, terms?} — the owner invites. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'partner_business_id' => ['required', 'integer'],
            'type' => ['nullable', Rule::in(array_keys(BusinessPartnership::relationshipTypes()))],
            'terms' => ['nullable', 'array'],
            'terms.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $p = $this->partnerships->invite(BusinessContext::business($request), (int) $data['partner_business_id'], $data);

        return response()->json(['success' => true, 'message' => __('أُرسلت الدعوة.'), 'data' => ['partnership' => $this->partnerships->present($p, BusinessContext::id($request))]], 201);
    }

    /** GET business/partnerships/{id} — the partnership with its allocations, for a party. */
    public function show(Request $request, int $partnership)
    {
        $me = BusinessContext::id($request);
        $p = $this->partnerships->forParty($partnership, $me);

        return response()->json(['success' => true, 'data' => $this->detail($p, $me)]);
    }

    /** POST business/partnerships/{id}/accept|decline — the invited business answers. */
    public function respond(Request $request, int $partnership, string $answer)
    {
        abort_unless(in_array($answer, ['accept', 'decline'], true), 404);

        $me = BusinessContext::id($request);
        $p = $this->partnerships->respond($this->partnerships->forParty($partnership, $me), $me, $answer === 'accept');

        return response()->json(['success' => true, 'message' => $answer === 'accept' ? __('قبلتَ الشراكة.') : __('رفضتَ الدعوة.'), 'data' => $this->detail($p, $me)]);
    }

    /** POST business/partnerships/{id}/pause|resume|end — either party. */
    public function change(Request $request, int $partnership, string $action)
    {
        abort_unless(in_array($action, ['pause', 'resume', 'end'], true), 404);

        $me = BusinessContext::id($request);
        $p = $this->partnerships->change($this->partnerships->forParty($partnership, $me), $action);

        return response()->json(['success' => true, 'data' => $this->detail($p, $me)]);
    }

    /** POST business/partnerships/{id}/allocations — the owner grants a quantity of one of its rooms. */
    public function grant(Request $request, int $partnership)
    {
        $me = BusinessContext::id($request);
        $p = $this->ownedBy($partnership, $me);

        $data = $request->validate([
            'bookable_item_id' => ['required', 'integer'],
            'quantity_total' => ['required', 'integer', 'min:1', 'max:1000'],
            'contract_price' => ['required', 'numeric', 'min:0.01'],
            'allocation_type' => ['nullable', Rule::in(array_keys(BookableAllocation::allocationTypes()))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'min_nights' => ['nullable', 'integer', 'min:1'],
            'max_nights' => ['nullable', 'integer', 'min:1'],
            'release_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $a = $this->allocations->grant($p, $data);

        return response()->json(['success' => true, 'message' => __('مُنحت الحصة.'), 'data' => ['allocation' => $this->allocations->present($a)]], 201);
    }

    /** PUT business/partnerships/{id}/allocations/{allocation} — the owner changes the terms. */
    public function updateAllocation(Request $request, int $partnership, int $allocation)
    {
        $me = BusinessContext::id($request);
        $a = $this->allocationOf($this->ownedBy($partnership, $me), $allocation);

        $data = $request->validate([
            'quantity_total' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'contract_price' => ['nullable', 'numeric', 'min:0.01'],
            'allocation_type' => ['nullable', Rule::in(array_keys(BookableAllocation::allocationTypes()))],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'min_nights' => ['nullable', 'integer', 'min:1'],
            'max_nights' => ['nullable', 'integer', 'min:1'],
            'release_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        return response()->json(['success' => true, 'data' => ['allocation' => $this->allocations->present($this->allocations->update($a, $data))]]);
    }

    /** POST business/partnerships/{id}/allocations/{allocation}/stop|activate — the owner. */
    public function allocationStatus(Request $request, int $partnership, int $allocation, string $action)
    {
        abort_unless(in_array($action, ['stop', 'activate'], true), 404);

        $me = BusinessContext::id($request);
        $p = $this->ownedBy($partnership, $me);
        $a = $this->allocationOf($p, $allocation);

        if ($action === 'activate' && $p->status !== BusinessPartnership::STATUS_ACTIVE) {
            abort(422, __('الشراكة غير فعّالة.'));
        }

        $a = $this->allocations->setStatus($a, $action === 'stop' ? BookableAllocation::STATUS_STOPPED : BookableAllocation::STATUS_ACTIVE);

        return response()->json(['success' => true, 'data' => ['allocation' => $this->allocations->present($a)]]);
    }

    /** PUT business/partnerships/{id}/allocations/{allocation}/pricing {markup_type, markup_value} — the PARTNER prices what it was given. */
    public function priceAllocation(Request $request, int $partnership, int $allocation)
    {
        $me = BusinessContext::id($request);
        $p = $this->partnerships->forParty($partnership, $me);
        abort_if($this->partnerships->isOwner($p, $me), 404);

        $data = $request->validate(['markup_type' => ['required', Rule::in(['none', 'fixed', 'percent'])], 'markup_value' => ['nullable', 'numeric', 'min:0']]);
        $a = $this->allocations->price($this->allocationOf($p, $allocation), $data['markup_type'], (float) ($data['markup_value'] ?? 0));

        return response()->json(['success' => true, 'message' => __('حُفظ سعر البيع.'), 'data' => ['allocation' => $this->allocations->present($a)]]);
    }

    /** GET business/partnerships/{id}/available-rooms — the owner's own rooms it can grant (with what is still ungranted). */
    public function grantable(Request $request, int $partnership)
    {
        $me = BusinessContext::id($request);
        $this->ownedBy($partnership, $me);

        $units = BookableItem::query()->with('lineOption:id,name_ar,name_en')->where('business_id', $me)->where('is_active', 1)->orderBy('line_option_id')->orderBy('code')->get();
        $given = BookableAllocation::query()->whereIn('status', [BookableAllocation::STATUS_ACTIVE, BookableAllocation::STATUS_PAUSED])->whereIn('bookable_item_id', $units->pluck('id'))
            ->selectRaw('bookable_item_id, SUM(quantity_total) AS given')->groupBy('bookable_item_id')->pluck('given', 'bookable_item_id');

        return response()->json(['success' => true, 'data' => ['rooms' => $units->map(function (BookableItem $u) use ($given) {
            $capacity = max((int) $u->quantity, (int) $u->rooms()->count(), 1);

            return $this->allocations->card($u) + ['capacity_total' => $capacity, 'ungranted' => max($capacity - (int) ($given[$u->id] ?? 0), 0)];
        })->values()]]);
    }

    /* ------------------------------------------------------------------ helpers */

    /** @return array<string,mixed> */
    private function detail(BusinessPartnership $p, int $me): array
    {
        return [
            'partnership' => $this->partnerships->present($p, $me),
            'allocations' => $this->allocations->of($p)->map(fn (BookableAllocation $a) => $this->allocations->present($a))->values(),
        ];
    }

    /** The partnership, only when the caller is its OWNER (the other party gets 404 here, like a stranger). */
    private function ownedBy(int $id, int $me): BusinessPartnership
    {
        $p = $this->partnerships->forParty($id, $me);
        abort_unless($this->partnerships->isOwner($p, $me), 404);

        return $p;
    }

    private function allocationOf(BusinessPartnership $p, int $allocationId): BookableAllocation
    {
        return BookableAllocation::query()->where('partnership_id', $p->id)->with('bookableItem.lineOption:id,name_ar,name_en')->findOrFail($allocationId);
    }
}
