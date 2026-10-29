<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableAllocation;
use App\Models\OfferingOption;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookableAvailabilityService;
use App\Services\BusinessServicePriceResolver;
use App\Services\ServiceExecutionEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * «غرف الفنادق بتصنيف معين» — a customer picks the hotel CLASS (the stars), the governorate and the city, and sees the
 * rooms of the hotels that match, across hotels, cheapest first.
 *
 * The class is a property of the HOTEL (the business ticks «تصنيف الإقامة»); every room it lets inherits it, so nothing
 * new is asked of the merchant. A hotel that ticked nothing is read as «غير مصنّف».
 *
 * A room is shown as a TYPE per hotel («غرفة مزدوجة · إطلالة» at the hotel), the way the hotel page shows it, with the
 * price its own price line gives. A partner company that holds an ALLOCATION of that room and offers it at its own price
 * (a `bookable_allocations` row, read as it is) is listed beside it as an alternative — the comparison the owner asked for.
 *
 * Public, like the rest of discovery.
 */
final class HotelRoomSearchController extends Controller
{
    private const ROOT_SLUG = 'tourist-hotels';

    private const CLASS_GROUP = 'تصنيف الإقامة';

    public function __construct(
        private readonly BusinessServicePriceResolver $prices,
        private readonly BookableAvailabilityService $availability,
        private readonly ServiceExecutionEngine $engine,
    ) {}

    /** GET /api/v2/discovery/hotel-rooms/filters?governorate_id= — only what a live hotel offers. */
    public function filters(Request $request)
    {
        $data = $request->validate(['governorate_id' => ['nullable', 'integer', 'min:1']]);
        $english = app()->getLocale() === 'en';

        $hotels = $this->hotels();
        $ids = $hotels->pluck('id')->all();
        $classOf = $this->classOf($ids);

        $classes = $this->classOptions();
        $counts = collect($ids)->map(fn ($id) => $classOf[$id] ?? $this->unclassifiedId())->countBy();

        $governorates = DB::table('governorates')->whereIn('id', $hotels->pluck('governorate_id')->filter()->unique())->get(['id', 'name_ar', 'name_en']);
        $byGov = $hotels->countBy('governorate_id');

        $cities = collect();
        if (! empty($data['governorate_id'])) {
            $inGov = $hotels->where('governorate_id', (int) $data['governorate_id']);
            $byCity = $inGov->countBy('city_id');
            $cities = DB::table('cities')->whereIn('id', $inGov->pluck('city_id')->filter()->unique())->get(['id', 'name_ar', 'name_en']);
            $cities = $cities->map(fn ($c) => ['id' => (int) $c->id, 'name' => $this->name($c, $english), 'count' => (int) ($byCity[$c->id] ?? 0)]);
        }

        $prices = $this->roomRows($hotels, $classOf)->pluck('price')->filter();

        return response()->json(['success' => true, 'data' => [
            'classes' => $classes->map(fn ($o) => ['id' => (int) $o->id, 'name' => $this->name($o, $english), 'count' => (int) ($counts[$o->id] ?? 0)])->filter(fn ($c) => $c['count'] > 0)->values()->all(),
            'governorates' => $governorates->map(fn ($g) => ['id' => (int) $g->id, 'name' => $this->name($g, $english), 'count' => (int) ($byGov[$g->id] ?? 0)])->sortBy('name')->values()->all(),
            'cities' => $cities->sortBy('name')->values()->all(),
            'price' => ['min' => $prices->min(), 'max' => $prices->max()],
        ]]);
    }

    /** GET /api/v2/discovery/hotel-rooms?class_option_id=&governorate_id=&city_id=&price_min=&price_max=&sort= */
    public function index(Request $request)
    {
        $data = $request->validate([
            'class_option_id' => ['nullable', 'integer', 'min:1'],
            'governorate_id' => ['nullable', 'integer', 'min:1'],
            'city_id' => ['nullable', 'integer', 'min:1'],
            // one hotel's rooms (the hotel list, once dates are picked, opens this)
            'business_id' => ['nullable', 'integer', 'min:1'],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:price_asc,price_desc'],
            // the stay: with it only rooms that are FREE for these nights are listed, with the total of the stay
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'guest_origin' => ['nullable', 'in:local,foreign'],
        ]);

        $origin = $data['guest_origin'] ?? null;

        $window = (! empty($data['starts_at']) && ! empty($data['ends_at']))
            ? [Carbon::parse($data['starts_at'])->startOfDay(), Carbon::parse($data['ends_at'])->startOfDay()]
            : null;

        $hotels = $this->hotels()
            ->when(! empty($data['governorate_id']), fn (Collection $c) => $c->where('governorate_id', (int) $data['governorate_id']))
            ->when(! empty($data['city_id']), fn (Collection $c) => $c->where('city_id', (int) $data['city_id']))
            ->when(! empty($data['business_id']), fn (Collection $c) => $c->where('id', (int) $data['business_id']));

        $classOf = $this->classOf($hotels->pluck('id')->all());
        $unclassified = $this->unclassifiedId();

        if (! empty($data['class_option_id'])) {
            $wanted = (int) $data['class_option_id'];
            $hotels = $hotels->filter(fn ($h) => ($classOf[$h->id] ?? $unclassified) === $wanted);
        }

        $rows = $this->roomRows($hotels, $classOf, $window, $origin);

        if (isset($data['price_min'])) {
            $rows = $rows->filter(fn ($r) => $r['best_price'] !== null && $r['best_price'] >= (float) $data['price_min']);
        }

        if (isset($data['price_max'])) {
            $rows = $rows->filter(fn ($r) => $r['best_price'] !== null && $r['best_price'] <= (float) $data['price_max']);
        }

        $desc = ($data['sort'] ?? 'price_asc') === 'price_desc';
        $rows = $rows->sortBy(fn ($r) => $r['best_price'] ?? PHP_INT_MAX, SORT_REGULAR, $desc)->values();

        $per = (int) ($data['per_page'] ?? 20);
        $page = max((int) $request->get('page', 1), 1);

        return response()->json(['success' => true, 'data' => [
            'data' => $rows->forPage($page, $per)->values()->all(),
            'current_page' => $page,
            'last_page' => max((int) ceil($rows->count() / $per), 1),
            'total' => $rows->count(),
        ]]);
    }

    // ---- hotels ----------------------------------------------------------------------------------------------------

    /** The hotels that let at least one active stay unit. @return Collection<int,object> */
    private function hotels(): Collection
    {
        $root = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->where('parent_id', 0)->value('id');

        return DB::table('users as u')
            ->where('u.type', 'business')->where('u.category_id', $root)
            ->tap(fn ($live) => User::applyLive($live, 'u'))->whereNull('u.anonymized_at')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('bookable_items as b')
                ->whereColumn('b.business_id', 'u.id')->where('b.item_type', 'booking_stay')->where('b.is_active', 1))
            ->get(['u.id', 'u.name', 'u.logo', 'u.governorate_id', 'u.city_id']);
    }

    /** @return Collection<int,object> the options of «تصنيف الإقامة» in their order */
    private function classOptions(): Collection
    {
        return DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', self::CLASS_GROUP)->orderBy('o.id')->get(['o.id', 'o.name_ar', 'o.name_en']);
    }

    private function unclassifiedId(): int
    {
        return (int) ($this->classOptions()->firstWhere('name_ar', 'غير مصنّف')->id ?? 0);
    }

    /** @param list<int> $hotelIds @return array<int,int> hotel id → its class option id */
    private function classOf(array $hotelIds): array
    {
        $order = $this->classOptions()->pluck('id')->flip();

        return DB::table('option_user')->whereIn('user_id', $hotelIds)->whereIn('option_id', $order->keys())
            ->get(['user_id', 'option_id'])->groupBy('user_id')
            ->map(fn ($rows) => (int) $rows->sortBy(fn ($r) => $order[$r->option_id])->first()->option_id)->all();
    }

    // ---- rooms -----------------------------------------------------------------------------------------------------

    /**
     * One row per room TYPE per hotel, with its price and the partner offers on it.
     *
     * @param  Collection<int,object>  $hotels
     * @param  array<int,int>  $classOf
     * @param  array{0:Carbon,1:Carbon}|null  $window  check-in and check-out
     * @return Collection<int,array<string,mixed>>
     */
    private function roomRows(Collection $hotels, array $classOf, ?array $window = null, ?string $origin = null)
    {
        if ($hotels->isEmpty()) {
            return collect();
        }

        $english = app()->getLocale() === 'en';
        $classes = $this->classOptions()->keyBy('id');
        $unclassified = $this->unclassifiedId();
        $govs = DB::table('governorates')->whereIn('id', $hotels->pluck('governorate_id')->filter()->unique())->get(['id', 'name_ar', 'name_en'])->keyBy('id');
        $cities = DB::table('cities')->whereIn('id', $hotels->pluck('city_id')->filter()->unique())->get(['id', 'name_ar', 'name_en'])->keyBy('id');

        $units = BookableItem::query()
            ->with(['lineOption:id,name_ar,name_en', 'images', 'offeringOptions', 'service'])
            ->withCount('rooms')
            ->whereIn('business_id', $hotels->pluck('id'))->where('item_type', 'booking_stay')->where('is_active', 1)
            ->where('status', '!=', BookableItem::STATUS_MAINTENANCE)
            ->orderBy('id')->get()
            ->filter(fn (BookableItem $u) => $origin === null || $u->allowsOrigin($origin))
            ->groupBy('business_id');

        $nights = $window ? max((int) $window[0]->diffInDays($window[1]), 1) : null;

        // the free-or-not question for every type of every hotel is read here once, not once per type
        if ($window) {
            $this->availability->preload($units->flatten(), $window[0], $window[1]);
        }

        $offers = $this->partnerOffers($units->flatten()->pluck('id')->all(), $nights, $window);
        $rows = collect();

        foreach ($hotels as $hotel) {
            $classId = $classOf[$hotel->id] ?? $unclassified;

            foreach (($units[$hotel->id] ?? collect())->groupBy(fn (BookableItem $u) => (int) ($u->line_option_id ?? 0)) as $kindId => $inKind) {
                /** @var BookableItem $first */
                $first = $inKind->first();
                $price = $this->prices->resolveForBookableItem($first);

                if (! $price) {
                    continue; // an unpriced kind cannot be sold: the hotel still has to finish it
                }

                // With a stay, the room must be FREE for it: the first free unit of the type is the one offered.
                $unit = $first;

                if ($window) {
                    $unit = $inKind->first(fn (BookableItem $u) => (bool) $this->availability->check($u, $window[0], $window[1])['available']);

                    if (! $unit) {
                        continue;
                    }
                }

                // the price the hotel page shows: the room's own modifiers («إطلالة») are part of it
                $breakdown = $this->engine->resolvePriceBreakdown(
                    service: $unit->service ?: new PlatformService(),
                    businessPrice: $price,
                    bookable: $unit,
                    quantity: 1,
                    pricingDate: $window[0] ?? null,
                    optionIds: $unit->offeringOptions->where('role', OfferingOption::ROLE_MODIFIER)->pluck('option_id')->map(fn ($id) => (int) $id)->all(),
                    until: $window[1] ?? null,
                );
                $own = round((float) $breakdown['unit_price'], 2);
                $partner = $inKind->flatMap(fn (BookableItem $u) => $offers[$u->id] ?? [])->unique('offer_id')->sortBy('price')->values();
                $best = $partner->isNotEmpty() ? min($own, (float) $partner->first()['price']) : $own;

                $rows->push([
                    'hotel' => [
                        'id' => (int) $hotel->id,
                        'name' => (string) $hotel->name,
                        'logo' => $hotel->logo,
                        'class' => $classes->has($classId) ? ['id' => (int) $classId, 'name' => $this->name($classes[$classId], $english)] : null,
                        'governorate' => isset($govs[$hotel->governorate_id]) ? $this->name($govs[$hotel->governorate_id], $english) : null,
                        'city' => isset($cities[$hotel->city_id]) ? $this->name($cities[$hotel->city_id], $english) : null,
                    ],
                    'room' => [
                        'unit_id' => (int) $unit->id,
                        // what a booking of it is sent with, so the app can open the booking form on this very room
                        'offering_id' => (int) $price->id,
                        'service_id' => (int) $price->service_id,
                        'item_type' => (string) $unit->item_type,
                        'line_option_id' => $kindId ?: null,
                        'name' => $this->kindName($first),
                        'capacity' => $first->capacity !== null ? (int) $first->capacity : null,
                        'max_children' => $first->max_children !== null ? (int) $first->max_children : null,
                        'guest_scope' => (string) ($first->guest_scope ?: 'all'),
                        'image' => $unit->imagePayload()[0] ?? null,
                    ],
                    'price' => $own,
                    // the stay's total and nights when dates were given
                    'total' => $window ? round((float) $breakdown['final_price'], 2) : null,
                    'nights' => $window ? (int) ($breakdown['periods_count'] ?? $nights) : null,
                    'currency' => (string) ($price->currency ?: 'EGP'),
                    // the cheapest of the hotel's own price and the partners' — what the list is sorted on
                    'best_price' => $best,
                    'partner_offers' => $partner->all(),
                ]);
            }
        }

        $this->availability->forget();

        return $rows;
    }

    /**
     * The partner companies' offers on these units — an allocation the hotel gave a company, which the company sells at its own
     * price (the contract price plus its markup). Only ones still active, in their window, with quantity left and a stay length
     * the allocation allows.
     *
     * @param  list<int>  $unitIds
     * @return array<int,list<array<string,mixed>>> unit id → offers
     */
    private function partnerOffers(array $unitIds, ?int $nights = null, ?array $window = null): array
    {
        if ($unitIds === []) {
            return [];
        }

        $allocations = BookableAllocation::query()->active()->whereIn('bookable_item_id', $unitIds)->get();
        $sellers = User::query()->whereIn('id', $allocations->pluck('partner_business_id')->filter())->get(['id', 'name'])->keyBy('id');
        $out = [];
        $lefts = $window ? BookableAllocation::availableForMany($allocations, \Illuminate\Support\Carbon::parse($window[0]), \Illuminate\Support\Carbon::parse($window[1])) : [];

        foreach ($allocations as $allocation) {
            // rooms per night: with a stay, what its busiest night leaves
            $left = $window ? ($lefts[(int) $allocation->id] ?? 0) : $allocation->availableQuantity();

            if ($left <= 0) {
                continue;
            }

            // a stay shorter or longer than the allocation allows is not an offer for it
            if ($nights !== null && (($allocation->min_nights && $nights < (int) $allocation->min_nights) || ($allocation->max_nights && $nights > (int) $allocation->max_nights))) {
                continue;
            }

            $out[(int) $allocation->bookable_item_id][] = [
                'allocation_id' => (int) $allocation->id,
                'seller' => ['id' => (int) $allocation->partner_business_id, 'name' => $sellers[$allocation->partner_business_id]->name ?? null],
                'price' => round((float) $allocation->finalPrice(), 2),
                'currency' => (string) ($allocation->currency ?: 'EGP'),
                'available_quantity' => $left,
            ];
        }

        return $out;
    }

    private function kindName(BookableItem $unit): string
    {
        $o = $unit->lineOption;

        return (string) (($o ? (app()->getLocale() === 'en' ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en)) : null) ?: $unit->displayLabel());
    }

    private function name(object $row, bool $english): string
    {
        return (string) ($english ? ($row->name_en ?: $row->name_ar) : ($row->name_ar ?: $row->name_en));
    }
}
