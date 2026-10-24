<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableItemRoom;
use App\Models\BookableItemRoomFeature;
use App\Models\Booking;
use App\Services\BookingRoomService;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
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

    /** GET /api/v2/business/bookable-items/{item}/rooms/{room}/features — the vocabulary, with what THIS room carries ticked */
    public function features(int $item, int $room)
    {
        $model = $this->item($item);

        return response()->json(['success' => true, 'data' => $this->featurePayload($model->rooms()->findOrFail($room))]);
    }

    /** PUT /api/v2/business/bookable-items/{item}/rooms/{room}/features — `{option_ids: [853, 854]}` */
    public function updateFeatures(Request $request, int $item, int $room)
    {
        $model = $this->item($item);
        $row = $model->rooms()->findOrFail($room);
        $data = $request->validate(['option_ids' => ['nullable', 'array', 'max:100'], 'option_ids.*' => ['integer']]);

        $allowed = $this->rooms->featureVocabularyIds((int) $model->business_id);
        $chosen = collect($data['option_ids'] ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => in_array($id, $allowed, true))->unique()->values()->all();

        DB::transaction(function () use ($row, $chosen) {
            BookableItemRoomFeature::query()->where('room_id', $row->id)->delete();

            foreach ($chosen as $optionId) {
                BookableItemRoomFeature::query()->create(['room_id' => $row->id, 'option_id' => $optionId]);
            }
        });

        // a bulk delete fires no model event: what the guest is offered is read again
        RequestMemo::bump('offering-options');

        return response()->json(['success' => true, 'data' => $this->featurePayload($row->fresh())]);
    }

    /** @return array<string,mixed> */
    private function featurePayload(BookableItemRoom $room): array
    {
        $business = \App\Models\User::findOrFail((int) $room->business_id);
        $selected = BookableItemRoomFeature::query()->where('room_id', $room->id)->pluck('option_id')->map(fn ($id) => (int) $id)->all();
        $prices = $business->currentOfferingAdjustments();

        return ['room' => ['id' => (int) $room->id, 'number' => $room->number], 'features' => app(\App\Services\BookingAddOnsService::class)->featureVocabulary($business)->map(fn ($options, $group) => [
            'group' => $group,
            'options' => collect($options)->map(fn ($o) => [
                'id' => (int) $o->id,
                'name' => app()->getLocale() === 'en' ? ($o->name_en ?: $o->name_ar) : ($o->name_ar ?: $o->name_en),
                'selected' => in_array((int) $o->id, $selected, true),
                'price' => isset($prices[(int) $o->id]) ? (float) $prices[(int) $o->id]['value'] : null,
            ])->values()->all(),
        ])->values()->all()];
    }

    /** DELETE /api/v2/business/bookable-items/{item}/rooms/{room} */
    public function destroy(int $item, int $room)
    {
        $model = $this->item($item);
        $row = $model->rooms()->findOrFail($room);

        if (Booking::query()->where('room_id', $row->id)->whereIn('status', [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])->exists()) {
            throw ValidationException::withMessages(['room' => __('الغرفة مسندة لحجز قائم.')]);
        }

        BookableItemRoomFeature::query()->where('room_id', $row->id)->delete();
        $row->delete();
        $this->rooms->syncQuantity($model->fresh());

        return response()->json(['success' => true, 'data' => $this->payload($model->fresh())]);
    }

    /** GET /api/v2/business/bookings/{booking}/rooms — the rooms this stay could be put in, and the one it has. */
    public function forBooking(Request $request, Booking $booking)
    {
        $this->authorizeBooking($request, $booking);
        $booking->loadMissing('bookable');

        $wanted = $this->rooms->requestedFor($booking);
        $free = $this->rooms->bestFirst($this->rooms->freeRooms($booking), $wanted);
        $carried = $this->rooms->featuresOf($free);

        return response()->json(['success' => true, 'data' => [
            'uses_rooms' => $this->rooms->usesRooms($booking),
            'room' => $this->roomPayload($booking->room_id),
            // what the guest asked for («إطلالة بحر»), and which free rooms carry all of it
            'requested' => $wanted === [] ? [] : array_values(array_filter(array_map('trim', explode('،', $this->rooms->namesOf($wanted))))),
            'free' => $free->map(fn (BookableItemRoom $r) => [
                'id' => (int) $r->id,
                'number' => $r->number,
                'matches' => array_diff($wanted, $carried[(int) $r->id] ?? []) === [],
            ])->values(),
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

        $rooms = $item->rooms()->orderBy('number')->get();
        $carried = $this->rooms->featuresOf($rooms);
        $names = \App\Models\Option::query()->whereIn('id', collect($carried)->flatten()->unique()->all() ?: [0])->get(['id', 'name_ar', 'name_en'])->keyBy('id');

        return [
            'item_id' => (int) $item->id,
            'count' => $item->rooms()->count(),
            'open_count' => $item->rooms()->where('status', BookableItemRoom::STATUS_AVAILABLE)->count(),
            'rooms' => $rooms->map(fn (BookableItemRoom $r) => [
                'id' => (int) $r->id,
                'number' => $r->number,
                'status' => $r->status,
                'notes' => $r->notes,
                'occupied' => in_array($r->id, $inUse, true),
                'feature_ids' => $carried[(int) $r->id] ?? [],
                'features' => collect($carried[(int) $r->id] ?? [])->map(fn ($id) => isset($names[$id]) ? (app()->getLocale() === 'en' ? ($names[$id]->name_en ?: $names[$id]->name_ar) : ($names[$id]->name_ar ?: $names[$id]->name_en)) : null)->filter()->values(),
            ])->values(),
        ];
    }
}
