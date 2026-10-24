<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BookableItemRoom;
use App\Models\Booking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        $rooms = $item->rooms()
            ->where('status', BookableItemRoom::STATUS_AVAILABLE)
            ->whereNotIn('id', $taken)
            ->orderByRaw('LENGTH(number), number')
            ->get();

        // a number another guest is dealing with right now («تحت التعامل») goes last: it is offered only when nothing else is left,
        // and the hold simply yields (it is a few minutes of courtesy, a started stay is a fact)
        $dealing = $this->dealtRoomIds($item, $booking->starts_at, $booking->ends_at, (int) $booking->user_id);

        return $dealing === [] ? $rooms : $rooms->sortBy(fn (BookableItemRoom $r) => in_array((int) $r->id, $dealing, true) ? 1 : 0)->values();
    }

    /**
     * The lowest-numbered room of the type that nothing else stands on for these nights: not closed, not under a started or accepted
     * stay, not under another guest's open booking screen. This is the number «تحت التعامل» names for a hold. Null when the type has no
     * listed rooms, or every number is spoken for (the type still has a room by count — numbers are only given when a stay starts).
     */
    public function roomForHold(BookableItem $item, $start, $end, ?int $exceptUserId = null): ?int
    {
        if (! $item->hasListedRooms()) {
            return null;
        }

        $standing = Booking::query()
            ->where('bookable_type', $item->getMorphClass())->where('bookable_id', $item->id)
            ->whereNotNull('room_id')
            ->whereIn('status', [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])
            ->when($start && $end, fn ($q) => $q->where('starts_at', '<', $end)->where('ends_at', '>', $start))
            ->pluck('room_id')->map(fn ($id) => (int) $id)->all();

        $busy = array_merge($standing, $this->dealtRoomIds($item, $start, $end, $exceptUserId));

        $room = $item->rooms()->where('status', BookableItemRoom::STATUS_AVAILABLE)
            ->when($busy !== [], fn ($q) => $q->whereNotIn('id', $busy))
            ->orderByRaw('LENGTH(number), number')->first(['id']);

        return $room ? (int) $room->id : null;
    }

    /** @return list<int> the numbers other guests hold right now over these nights */
    private function dealtRoomIds(BookableItem $item, $start, $end, ?int $exceptUserId = null): array
    {
        return \App\Models\BookingHold::query()->live()
            ->where('bookable_item_id', $item->id)->whereNotNull('room_id')
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->when($start && $end, fn ($q) => $q->where('starts_at', '<', $end)->where('ends_at', '>', $start))
            ->pluck('room_id')->map(fn ($id) => (int) $id)->all();
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

        // two stays starting at the same moment must not be handed the same number: the room type is locked, the free numbers are read
        // AFTER the lock (so after whoever held it before has written their number), and only then is one given
        return DB::transaction(function () use ($booking) {
            $this->lockType($booking);

            $room = $this->freeRooms($booking)->first();

            if (! $room) {
                throw ValidationException::withMessages([
                    'room' => __('لا توجد غرفة متاحة من هذا النوع الآن.'),
                ]);
            }

            $booking->room_id = $room->id;
            $booking->save();

            return $room;
        });
    }

    /** The row every booking of this room type is made under, so room numbers and stock are decided one stay at a time. */
    private function lockType(Booking $booking): void
    {
        BookableItem::query()->whereKey((int) $booking->bookable_id)->lockForUpdate()->first(['id']);
    }

    /** The hotel puts a stay in a room of its choosing (before the start or to move a guest). */
    public function assign(Booking $booking, int $roomId): BookableItemRoom
    {
        return DB::transaction(function () use ($booking, $roomId) {
            $this->lockType($booking);

            $room = $this->freeRooms($booking)->firstWhere('id', $roomId)
                ?? ((int) $booking->room_id === $roomId ? BookableItemRoom::query()->find($roomId) : null);

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
        });
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
