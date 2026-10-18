<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestOffer;
use App\Models\User;
use App\Services\Requests\RideQuote;
use App\Services\Requests\ServiceRequestService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The requests-and-offers core over HTTP. The customer posts a request (a ride, today), watches the offers come in and
 * picks one; the business sees the open requests it may answer and answers with a price. Accepting an offer makes the
 * platform's ordinary operation (a booking) — from there on it is that operation's own story.
 */
class ServiceRequestController extends Controller
{
    public function __construct(private readonly ServiceRequestService $service) {}

    // ---- the customer ----------------------------------------------------------------------------------------------

    /** POST service-requests/quote — the distance and the suggested price of a ride, before it is asked for. */
    public function quote(Request $request)
    {
        $data = Validator::make($request->all(), [
            'origin_city_id' => ['required', 'integer', 'exists:cities,id'],
            'destination_city_id' => ['required', 'integer', 'exists:cities,id'],
            'distance_km' => ['nullable', 'numeric', 'min:0.3', 'max:3000'],
        ])->validate();

        $quote = RideQuote::between((int) $data['origin_city_id'], (int) $data['destination_city_id'], isset($data['distance_km']) ? (float) $data['distance_km'] : null);

        return response()->json(['success' => true, 'data' => [
            'known' => $quote !== null,
            // the customer has to say how far when the two ends are one city (nothing to measure between)
            'needs_distance' => $quote === null,
            'distance_km' => $quote['distance_km'] ?? null,
            'source' => $quote['source'] ?? null,
            'suggested_price' => $quote['suggested_price'] ?? null,
            'currency' => 'EGP',
        ]]);
    }

    /** POST service-requests — {kind: ride, origin, destination, passengers?, starts_at?, price?, distance_km?, details?} */
    public function store(Request $request)
    {
        $kind = (string) $request->input('kind', ServiceRequest::KIND_RIDE);
        $created = $this->service->create($request->user(), $kind, $request->all());

        return response()->json([
            'success' => true,
            'message' => __('نُشر طلبك. سيصلك ما يعرضه السائقون.'),
            'data' => ['request' => $this->serialize($created, $request->user())],
        ], 201);
    }

    /** GET service-requests — my requests, newest first. */
    public function index(Request $request)
    {
        $query = ServiceRequest::query()->where('requester_id', (int) $request->user()->id)->latest('id');

        if ($request->filled('status')) {
            $status = (string) $request->get('status');
            $status === 'expired'
                ? $query->where('status', ServiceRequest::STATUS_OPEN)->where('expires_at', '<=', now())
                : $query->where('status', $status);
        }

        $rows = $query->paginate((int) $request->get('per_page', 20));
        $counts = $this->offerCounts($rows->getCollection()->pluck('id')->all());
        $rows->getCollection()->transform(fn (ServiceRequest $r) => $this->serialize($r, $request->user(), offersCount: $counts[(int) $r->id] ?? 0));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** GET service-requests/{id} — mine, with every offer on it. */
    public function show(Request $request, int $id)
    {
        $row = $this->mineOrFail($request, $id);

        return response()->json(['success' => true, 'data' => ['request' => $this->serialize($row, $request->user(), withOffers: true)]]);
    }

    /** POST service-requests/{id}/price {price} — the customer changes the price he asks for. */
    public function setPrice(Request $request, int $id)
    {
        $data = $request->validate(['price' => ['required', 'numeric', 'min:1']]);
        $row = $this->service->setBudget($this->mineOrFail($request, $id), (float) $data['price']);

        return response()->json(['success' => true, 'data' => ['request' => $this->serialize($row, $request->user(), withOffers: true)]]);
    }

    /** POST service-requests/{id}/offers/{offer}/accept — he picks one: it becomes the booking. */
    public function accept(Request $request, int $id, int $offer)
    {
        $row = $this->mineOrFail($request, $id);
        $chosen = ServiceRequestOffer::query()->where('service_request_id', $row->id)->findOrFail($offer);

        $done = $this->service->accept($row, $chosen);

        return response()->json([
            'success' => true,
            'message' => __('تم اختيار العرض. أُنشئ حجزك.'),
            'data' => [
                'request' => $this->serialize($done['request'], $request->user(), withOffers: true),
                'booking_id' => (int) $done['operation']->getKey(),
            ],
        ]);
    }

    /** POST service-requests/{id}/cancel */
    public function cancel(Request $request, int $id)
    {
        $row = $this->service->cancel($this->mineOrFail($request, $id));

        return response()->json(['success' => true, 'data' => ['request' => $this->serialize($row, $request->user(), withOffers: true)]]);
    }

    // ---- the business ----------------------------------------------------------------------------------------------

    /** GET business/service-requests/kinds — which kinds of request this business's trade answers (empty: none). */
    public function kinds(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->kindsFor($this->businessOf($request))]]);
    }

    /** GET business/service-requests — the open requests this business may answer, with its own offer on each. */
    public function incoming(Request $request)
    {
        $business = $this->businessOf($request);

        $kind = (string) $request->get('kind', ServiceRequest::KIND_RIDE);
        $trades = $this->service->kind($kind)->tradeIds();

        $query = ServiceRequest::query()->open()
            ->where('kind', $kind)
            ->whereIn('category_child_id', in_array((int) $business->category_child_id, $trades, true) ? $trades : [0])
            ->where('governorate_id', (int) $business->governorate_id)
            ->where('requester_id', '!=', (int) $business->id)
            ->with('requester:id,name')
            ->latest('id');

        $rows = $query->paginate((int) $request->get('per_page', 20));
        $mine = ServiceRequestOffer::query()->where('business_id', $business->id)
            ->whereIn('service_request_id', $rows->getCollection()->pluck('id')->all())->get()->keyBy('service_request_id');
        $counts = $this->offerCounts($rows->getCollection()->pluck('id')->all());

        $rows->getCollection()->transform(fn (ServiceRequest $r) => $this->serialize($r, $business, offersCount: $counts[(int) $r->id] ?? 0, myOffer: $mine->get($r->id), forBusiness: true));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST business/service-requests/{id}/offers {price?, accepts_budget?, message?, eta_minutes?} */
    public function offer(Request $request, int $id)
    {
        $data = $request->validate([
            'price' => ['nullable', 'numeric', 'min:1'],
            'accepts_budget' => ['nullable', 'boolean'],
            'message' => ['nullable', 'string', 'max:300'],
            'eta_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'menu_item_id' => ['nullable', 'integer'],
        ]);

        if (empty($data['accepts_budget']) && ! isset($data['price'])) {
            throw ValidationException::withMessages(['price' => __('اكتب سعرًا أو اقبل سعر العميل.')]);
        }

        $business = $this->businessOf($request);
        $row = ServiceRequest::query()->findOrFail($id);
        $offer = $this->service->offer($row, $business, $data);

        return response()->json([
            'success' => true,
            'message' => __('أُرسل عرضك للعميل.'),
            'data' => ['request' => $this->serialize($row->refresh(), $business, myOffer: $offer, forBusiness: true)],
        ], 201);
    }

    /** DELETE business/service-requests/{id}/offers — take the offer back. */
    public function withdraw(Request $request, int $id)
    {
        $this->service->withdraw(ServiceRequest::query()->findOrFail($id), $this->businessOf($request));

        return response()->json(['success' => true, 'message' => __('سُحب عرضك.')]);
    }

    // ---- shape -----------------------------------------------------------------------------------------------------

    private function businessOf(Request $request): User
    {
        return User::query()->findOrFail(BusinessContext::id($request));
    }

    private function mineOrFail(Request $request, int $id): ServiceRequest
    {
        return ServiceRequest::query()->where('requester_id', (int) $request->user()->id)->findOrFail($id);
    }

    /** @param list<int|string> $ids @return array<int,int> request id → live offers on it */
    private function offerCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return ServiceRequestOffer::query()->whereIn('service_request_id', $ids)
            ->where('status', ServiceRequestOffer::STATUS_PENDING)
            ->select('service_request_id', DB::raw('count(*) as n'))->groupBy('service_request_id')
            ->pluck('n', 'service_request_id')->map(fn ($n) => (int) $n)->all();
    }

    /**
     * The customer sees every offer with who made it; a business sees the request, its own offer and how many others
     * there are — never the other businesses' prices — and only the customer's first name.
     */
    private function serialize(ServiceRequest $r, User $viewer, bool $withOffers = false, ?int $offersCount = null, ?ServiceRequestOffer $myOffer = null, bool $forBusiness = false): array
    {
        $out = [
            'id' => (int) $r->id,
            'kind' => (string) $r->kind,
            'title' => $r->title,
            'details' => $r->details,
            'status' => $r->effectiveStatus(),
            'params' => $r->params,
            'starts_at' => optional($r->starts_at)->toIso8601String(),
            'expires_at' => optional($r->expires_at)->toIso8601String(),
            'budget' => $r->budget !== null ? (float) $r->budget : null,
            'suggested_price' => $r->suggested_price !== null ? (float) $r->suggested_price : null,
            'currency' => (string) $r->currency,
            'offers_count' => $offersCount,
            'created_at' => optional($r->created_at)->toIso8601String(),
        ];

        if ($forBusiness) {
            $name = trim((string) ($r->relationLoaded('requester') ? $r->requester?->name : User::query()->whereKey($r->requester_id)->value('name')));
            $out['requester'] = ['first_name' => $name !== '' ? explode(' ', $name)[0] : null];
            $out['my_offer'] = $myOffer ? $this->serializeOffer($myOffer, false) : null;
        }

        if ((int) $r->requester_id === (int) $viewer->id) {
            $out['booking_id'] = $r->operation_id ? (int) $r->operation_id : null;
        }

        if ($withOffers) {
            $offers = $r->offers()->with('business:id,name,logo')->orderBy('price')->get();
            $items = DB::table('menu_items')->whereIn('id', $offers->pluck('menu_item_id')->filter()->all())->get(['id', 'name_ar', 'name_en', 'base_price'])->keyBy('id');
            $out['offers'] = $offers->map(fn (ServiceRequestOffer $o) => $this->serializeOffer($o, true, $items->get($o->menu_item_id)))->values()->all();
            $out['offers_count'] = $offers->where('status', ServiceRequestOffer::STATUS_PENDING)->count();
        }

        return $out;
    }

    private function serializeOffer(ServiceRequestOffer $o, bool $withBusiness, ?object $item = null): array
    {
        $out = [
            'id' => (int) $o->id,
            'price' => (float) $o->price,
            'accepts_budget' => (bool) $o->accepts_budget,
            'message' => $o->message,
            'eta_minutes' => $o->eta_minutes,
            'status' => (string) $o->status,
            // the listing the offer is about, if the business pointed at one
            'item' => $item ? ['id' => (int) $item->id, 'name' => app()->getLocale() === 'en' ? ($item->name_en ?: $item->name_ar) : ($item->name_ar ?: $item->name_en), 'price' => (float) $item->base_price] : null,
        ];

        if ($withBusiness) {
            $out['business'] = ['id' => (int) $o->business_id, 'name' => $o->business?->name, 'logo' => $o->business?->logo];
        }

        return $out;
    }
}
