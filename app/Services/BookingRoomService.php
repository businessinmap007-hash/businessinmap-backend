<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BookableItemRoom;
use App\Models\Booking;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * «العدد والرقم لكل غرفة يكون لدى الفندق فقط ويظهر عند بداية التنفيذ» — المالك، 2026-10-07.
 *
 * The customer books a room TYPE (a `BookableItem`). The numbered rooms behind it belong to the hotel alone; one is
 * given to the stay when it STARTS (the same moment a deposit or a guarantee is frozen) and only then does the
 * customer see its number. A hotel that never lists rooms is untouched: the type is the whole story, as before.
 */
class BookingRoomService
{
    /** Does this booking sit on a room type whose rooms the hotel has listed? */
    public function usesRooms(Booking $booking): bool
    {
        $item = $booking->bookable;

        return $item instanceof BookableItem && $item->rooms()->exists();
    }

    /**
     * The rooms of this booking's type that are open for it: not closed for maintenance, and not held by another
     * live stay that overlaps this one.
     *
     * @return Collection<int,BookableItemRoom>
     */
    public function freeRooms(Booking $booking): Collection
    {
        $item = $booking->bookable;

        if (! $item instanceof BookableItem) {
            return collect();
        }

        $taken = Booking::query()
            ->where('bookable_type', $booking->bookable_type)
            ->where('bookable_id', $booking->bookable_id)
            ->whereKeyNot($booking->id)
            ->whereNotNull('room_id')
            ->whereIn('status', [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])
            ->when($booking->starts_at && $booking->ends_at, fn ($q) => $q
                ->where('starts_at', '<', $booking->ends_at)
                ->where('ends_at', '>', $booking->starts_at))
            ->pluck('room_id');

        return $item->rooms()
            ->where('status', BookableItemRoom::STATUS_AVAILABLE)
            ->whereNotIn('id', $taken)
            ->orderBy('number')
            ->get();
    }

    /**
     * Give the stay a room when it starts. A hotel with no listed rooms keeps the old behaviour (nothing to give);
     * one with rooms and none free cannot start the stay — there is no room to hand over.
     */
    public function assignOnStart(Booking $booking): ?BookableItemRoom
    {
        if (! $this->usesRooms($booking)) {
            return null;
        }

        if ($booking->room_id) {
            return BookableItemRoom::query()->find($booking->room_id);
        }

        $room = $this->freeRooms($booking)->first();

        if (! $room) {
            throw ValidationException::withMessages([
                'room' => __('لا توجد غرفة متاحة من هذا النوع الآن.'),
            ]);
        }

        $booking->room_id = $room->id;
        $booking->save();

        return $room;
    }

    /** The hotel puts a stay in a room of its choosing (before the start or to move a guest). */
    public function assign(Booking $booking, int $roomId): BookableItemRoom
    {
        $room = $this->freeRooms($booking)->firstWhere('id', $roomId)
            ?? ($booking->room_id === $roomId ? BookableItemRoom::query()->find($roomId) : null);

        if (! $room) {
            throw ValidationException::withMessages([
                'room' => __('هذه الغرفة غير متاحة لهذا الحجز.'),
            ]);
        }

        if (in_array((string) $booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED], true)) {
            throw ValidationException::withMessages([
                'room' => __('لا يمكن تغيير غرفة حجز منتهٍ.'),
            ]);
        }

        $booking->room_id = $room->id;
        $booking->save();

        return $room;
    }

    /** Keep the type's count in step with its rooms: what can be sold is the rooms that are open. */
    public function syncQuantity(BookableItem $item): void
    {
        if (! $item->rooms()->exists()) {
            return;
        }

        $item->forceFill([
            'quantity' => max(0, $item->rooms()->where('status', BookableItemRoom::STATUS_AVAILABLE)->count()),
        ])->save();
    }
}
