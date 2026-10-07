<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableItemRoom;
use App\Models\Booking;
use App\Services\BookingRoomService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The rooms behind a room type — the hotel's own list, never shown to a customer — and the hotel putting a stay in a
 * room. Hotels only (`booking_stay` types).
 */
final class BusinessBookableRoomController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly BookingRoomService $rooms)
    {
    }

    private function item(int $id): BookableItem
    {
        $item = BookableItem::query()->where('business_id', $this->businessId())->findOrFail($id);

        if ($item->item_type !== 'booking_stay') {
            throw ValidationException::withMessages(['room' => __('الغرف للفنادق والإقامة فقط.')]);
        }

        return $item;
    }

    /** GET /api/v2/business/bookable-items/{item}/rooms */
    public function index(int $item)
    {
        $model = $this->item($item);

        return response()->json(['success' => true, 'data' => $this->payload($model)]);
    }

    /** POST /api/v2/business/bookable-items/{item}/rooms — `{numbers: ["101","102"]}` */
    public function store(Request $request, int $item)
    {
        $model = $this->item($item);
        $data = $request->validate([
            'numbers' => ['required', 'array', 'min:1', 'max:200'],
            'numbers.*' => ['required', 'string', 'max:40'],
        ]);

        $numbers = collect($data['numbers'])->map(fn ($n) => trim((string) $n))->filter()->unique()->values();
        $existing = $model->rooms()->pluck('number')->all();

        foreach ($numbers as $number) {
            if (in_array($number, $existing, true)) {
                continue; // a room already listed is not an error — adding the same list twice is harmless
            }

            $model->rooms()->create(['business_id' => $model->business_id, 'number' => $number, 'status' => BookableItemRoom::STATUS_AVAILABLE]);
        }

        $this->rooms->syncQuantity($model->fresh());

        return response()->json(['success' => true, 'data' => $this->payload($model->fresh())], 201);
    }

    /** PATCH /api/v2/business/bookable-items/{item}/rooms/{room} — close for maintenance, rename, note */
    public function update(Request $request, int $item, int $room)
    {
        $model = $this->item($item);
        $row = $model->rooms()->findOrFail($room);
        $data = $request->validate([
            'number' => ['sometimes', 'string', 'max:40', Rule::unique('bookable_item_rooms', 'number')->where('bookable_item_id', $model->id)->ignore($row->id)],
            'status' => ['sometimes', Rule::in([BookableItemRoom::STATUS_AVAILABLE, BookableItemRoom::STATUS_MAINTENANCE])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $row->update($data);
        $this->rooms->syncQuantity($model->fresh());

        return response()->json(['success' => true, 'data' => $this->payload($model->fresh())]);
    }

    /** DELETE /api/v2/business/bookable-items/{item}/rooms/{room} */
    public function destroy(int $item, int $room)
    {
        $model = $this->item($item);
        $row = $model->rooms()->findOrFail($room);

        if (Booking::query()->where('room_id', $row->id)->whereIn('status', [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])->exists()) {
            throw ValidationException::withMessages(['room' => __('الغرفة مسندة لحجز قائم.')]);
        }

        $row->delete();
        $this->rooms->syncQuantity($model->fresh());

        return response()->json(['success' => true, 'data' => $this->payload($model->fresh())]);
    }

    /** GET /api/v2/business/bookings/{booking}/rooms — the rooms this stay could be put in, and the one it has. */
    public function forBooking(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $booking->loadMissing('bookable');

        return response()->json(['success' => true, 'data' => [
            'uses_rooms' => $this->rooms->usesRooms($booking),
            'room' => $this->roomPayload($booking->room_id),
            'free' => $this->rooms->freeRooms($booking)->map(fn (BookableItemRoom $r) => ['id' => (int) $r->id, 'number' => $r->number])->values(),
        ]]);
    }

    /** POST /api/v2/business/bookings/{booking}/room — `{room_id}` */
    public function assign(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $data = $request->validate(['room_id' => ['required', 'integer']]);
        $booking->loadMissing('bookable');

        if (! $this->rooms->usesRooms($booking)) {
            throw ValidationException::withMessages(['room' => __('هذا الحجز ليس على نوع غرف مسجّلة.')]);
        }

        $room = $this->rooms->assign($booking, (int) $data['room_id']);

        return response()->json(['success' => true, 'data' => ['room' => $this->roomPayload($room->id)]]);
    }

    private function authorizeBooking(Request $request, Booking $booking): void
    {
        if ((int) $booking->business_id !== \App\Support\BusinessContext::id($request)) {
            abort(403, 'Booking does not belong to this business account.');
        }
    }

    /** @return array<string,mixed>|null */
    private function roomPayload(?int $roomId): ?array
    {
        $room = $roomId ? BookableItemRoom::query()->find($roomId) : null;

        return $room ? ['id' => (int) $room->id, 'number' => $room->number, 'status' => $room->status] : null;
    }

    /** @return array<string,mixed> */
    private function payload(BookableItem $item): array
    {
        $inUse = Booking::query()
            ->where('bookable_type', $item->getMorphClass())
            ->where('bookable_id', $item->id)
            ->where('status', Booking::STATUS_IN_PROGRESS)
            ->whereNotNull('room_id')
            ->pluck('room_id')
            ->all();

        return [
            'item_id' => (int) $item->id,
            'count' => $item->rooms()->count(),
            'open_count' => $item->rooms()->where('status', BookableItemRoom::STATUS_AVAILABLE)->count(),
            'rooms' => $item->rooms()->orderBy('number')->get()->map(fn (BookableItemRoom $r) => [
                'id' => (int) $r->id,
                'number' => $r->number,
                'status' => $r->status,
                'notes' => $r->notes,
                'occupied' => in_array($r->id, $inUse, true),
            ])->values(),
        ];
    }
}
