<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\StayRequest;
use App\Models\StayServiceOption;
use App\Services\StayRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The hotel's side: the guests' issues and orders arriving with the room number, and the list of services a guest may
 * order (the hotel's own words).
 */
final class BusinessStayRequestController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly StayRequestService $requests)
    {
    }

    /** GET /api/v2/business/stay-requests?status=open|done|all */
    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'open');
        $query = StayRequest::query()->where('business_id', $this->businessId())->with('booking.bookable', 'guest');

        $query = match ($status) {
            'all' => $query,
            'done' => $query->whereIn('status', [StayRequest::STATUS_DONE, StayRequest::STATUS_CANCELLED]),
            default => $query->whereIn('status', StayRequest::OPEN),
        };

        // oldest waiting first: the one that has waited longest is the one to do next
        $rows = $status === 'open' ? $query->orderBy('id')->limit(100)->get() : $query->latest('id')->limit(100)->get();

        return response()->json(['success' => true, 'data' => [
            'requests' => $rows->map(fn (StayRequest $r) => $this->requests->payload($r, true))->all(),
            'open_count' => StayRequest::query()->where('business_id', $this->businessId())->whereIn('status', StayRequest::OPEN)->count(),
        ]]);
    }

    /** PATCH /api/v2/business/stay-requests/{id} — `{status: in_progress|done|cancelled}` */
    public function update(Request $request, int $stayRequest)
    {
        $data = $request->validate(['status' => ['required', Rule::in([StayRequest::STATUS_IN_PROGRESS, StayRequest::STATUS_DONE, StayRequest::STATUS_CANCELLED])]]);
        $row = StayRequest::query()->where('business_id', $this->businessId())->findOrFail($stayRequest);

        return response()->json(['success' => true, 'data' => ['request' => $this->requests->payload($this->requests->setStatus($row, $this->businessId(), $data['status']), true)]]);
    }

    /** GET /api/v2/business/stay-services — the hotel's list, or the starting list it has not replaced yet */
    public function services()
    {
        return response()->json(['success' => true, 'data' => $this->servicesPayload()]);
    }

    /** POST /api/v2/business/stay-services — `{titles: ["قهوة", ...]}` (the first one written replaces the starting list) */
    public function storeService(Request $request)
    {
        $data = $request->validate([
            'titles' => ['required', 'array', 'min:1', 'max:40'],
            'titles.*' => ['required', 'string', 'max:120'],
        ]);

        $business = $this->businessId();
        $hadRows = StayServiceOption::query()->where('business_id', $business)->exists();

        // the starting list is the hotel's to edit: writing the first row keeps the others it was showing
        if (! $hadRows) {
            foreach (StayRequestService::DEFAULT_SERVICES as $i => $title) {
                StayServiceOption::create(['business_id' => $business, 'title' => $title, 'sort_order' => $i]);
            }
        }

        $existing = StayServiceOption::query()->where('business_id', $business)->pluck('title')->all();
        $next = (int) StayServiceOption::query()->where('business_id', $business)->max('sort_order') + 1;

        foreach (collect($data['titles'])->map(fn ($t) => trim((string) $t))->filter()->unique() as $title) {
            if (in_array($title, $existing, true)) {
                continue;
            }

            StayServiceOption::create(['business_id' => $business, 'title' => $title, 'sort_order' => $next++]);
        }

        return response()->json(['success' => true, 'data' => $this->servicesPayload()], 201);
    }

    /** PATCH /api/v2/business/stay-services/{id} — rename or switch off */
    public function updateService(Request $request, int $option)
    {
        $row = StayServiceOption::query()->where('business_id', $this->businessId())->findOrFail($option);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row->update($data);

        return response()->json(['success' => true, 'data' => $this->servicesPayload()]);
    }

    /** DELETE /api/v2/business/stay-services/{id} */
    public function destroyService(int $option)
    {
        StayServiceOption::query()->where('business_id', $this->businessId())->findOrFail($option)->delete();

        return response()->json(['success' => true, 'data' => $this->servicesPayload()]);
    }

    /**
     * `custom` says whether the hotel has written its own list; until then `services` is the platform's starting list
     * and carries no ids (nothing to edit yet — adding one makes the list the hotel's).
     */
    private function servicesPayload(): array
    {
        $rows = StayServiceOption::query()->where('business_id', $this->businessId())->orderBy('sort_order')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return [
                'custom' => false,
                'services' => collect(StayRequestService::DEFAULT_SERVICES)->map(fn ($t) => ['id' => null, 'title' => $t, 'is_active' => true])->all(),
            ];
        }

        return [
            'custom' => true,
            'services' => $rows->map(fn (StayServiceOption $r) => ['id' => (int) $r->id, 'title' => $r->title, 'is_active' => (bool) $r->is_active])->all(),
        ];
    }
}
