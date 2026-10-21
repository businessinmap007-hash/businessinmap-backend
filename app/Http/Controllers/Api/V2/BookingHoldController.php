<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Services\AllocationConsumptionService;
use App\Services\BookingHolds;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * «تحت التعامل»: the booking screen holds the room for the guest while they fill it in, so nobody else — the hotel's own guest, a
 * travel company's guest — is offered it at the same moment.
 */
class BookingHoldController extends Controller
{
    public function __construct(private readonly BookingHolds $holds, private readonly AllocationConsumptionService $allocations)
    {
    }

    /** POST bookings/holds {business_id, bookable_id, starts_at, ends_at, quantity?} — hold (or move the hold on) the room. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'min:1'],
            'bookable_id' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $unit = BookableItem::query()->where('is_active', 1)->find((int) $data['bookable_id']);
        $businessId = (int) $data['business_id'];

        // the room belongs to this business, or this business was given it to sell
        $allocation = $unit && (int) $unit->business_id !== $businessId ? $this->allocations->findActiveAllocation($businessId, (int) $unit->id) : null;
        abort_unless($unit && ((int) $unit->business_id === $businessId || $allocation), 404);

        $hold = $this->holds->place(
            (int) $request->user()->id,
            $unit,
            Carbon::parse($data['starts_at']),
            Carbon::parse($data['ends_at']),
            max((int) ($data['quantity'] ?? 1), 1),
            $businessId,
            $allocation ? (int) $allocation->partner_business_id : null
        );

        return response()->json(['success' => true, 'data' => ['hold' => [
            'id' => (int) $hold->id,
            'expires_at' => $hold->expires_at->toIso8601String(),
            'seconds_left' => max((int) now()->diffInSeconds($hold->expires_at, false), 0),
        ]]], 201);
    }

    /** DELETE bookings/holds/{hold} — the guest left the screen. */
    public function destroy(Request $request, int $hold)
    {
        $this->holds->release((int) $request->user()->id, $hold);

        return response()->json(['success' => true]);
    }
}
