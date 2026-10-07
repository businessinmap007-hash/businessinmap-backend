<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\StayRequest;
use App\Services\StayRequestService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The guest's side of «بلّغ عن مشكلة» / «اطلب خدمة» during a running hotel stay.
 */
final class StayRequestController extends Controller
{
    public function __construct(private readonly StayRequestService $requests)
    {
    }

    private function mine(Request $request, Booking $booking): Booking
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403, 'Booking does not belong to this client account.');

        return $booking;
    }

    /** GET /api/v2/bookings/{booking}/stay-requests — the issue categories, the hotel's services, and this stay's requests */
    public function index(Request $request, Booking $booking)
    {
        $this->mine($request, $booking);

        return response()->json(['success' => true, 'data' => $this->requests->options($booking) + [
            'requests' => StayRequest::query()->where('booking_id', $booking->id)->latest('id')->limit(50)->get()
                ->map(fn (StayRequest $r) => $this->requests->payload($r))->all(),
        ]]);
    }

    /** POST /api/v2/bookings/{booking}/stay-requests — `{kind: issue|service, category?, title?, note?}` */
    public function store(Request $request, Booking $booking)
    {
        $this->mine($request, $booking);
        $data = $request->validate([
            'kind' => ['required', Rule::in([StayRequest::KIND_ISSUE, StayRequest::KIND_SERVICE])],
            'category' => ['nullable', 'string', 'max:30'],
            'title' => ['nullable', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $created = $this->requests->create($booking, (int) $request->user()->id, $data['kind'], $data['category'] ?? null, $data['title'] ?? null, $data['note'] ?? null);

        return response()->json(['success' => true, 'data' => ['request' => $this->requests->payload($created)]], 201);
    }

    /** POST /api/v2/bookings/{booking}/stay-requests/{stayRequest}/cancel */
    public function cancel(Request $request, Booking $booking, int $stayRequest)
    {
        $this->mine($request, $booking);
        $row = StayRequest::query()->where('booking_id', $booking->id)->findOrFail($stayRequest);

        return response()->json(['success' => true, 'data' => ['request' => $this->requests->payload($this->requests->cancelByGuest($row, (int) $request->user()->id))]]);
    }
}
