<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BookingHold;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «تحت التعامل»: a room is held for whoever is filling in a booking for it, for a few minutes (config `bim.booking_holds.minutes`).
 *
 * Every availability answer counts the holds of OTHER people as taken and its own asker's as nothing, so the hotel's guest and a
 * travel company's guest cannot both be offered the last room: the first to open the booking holds it, the second is told it is being
 * dealt with by someone else. The hold becomes the booking when it is made (the booking itself is made under a lock on the room), and
 * falls away on its own when the minutes are up — nothing needs a job to clean it, expired holds simply do not count.
 */
final class BookingHolds
{
    public function minutes(): int
    {
        return max((int) config('bim.booking_holds.minutes', 10), 1);
    }

    /** How many rooms of this unit other people hold for these dates. */
    public function heldRooms(BookableItem $item, CarbonInterface $start, CarbonInterface $end, ?int $exceptUserId = null): int
    {
        return (int) BookingHold::query()->live()
            ->where('bookable_item_id', $item->id)
            ->when($exceptUserId, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->sum('quantity');
    }

    /**
     * Hold the room for this user, replacing the hold they already had on it (a new date range moves the hold, it does not add one).
     * The answer is judged under a lock on the room, so two people pressing at the same moment get one hold and one refusal.
     *
     * @throws ValidationException when the room is not free for the asker
     */
    public function place(int $userId, BookableItem $item, CarbonInterface $start, CarbonInterface $end, int $quantity = 1, ?int $sellerBusinessId = null, ?int $viaPartnerId = null): BookingHold
    {
        return DB::transaction(function () use ($userId, $item, $start, $end, $quantity, $sellerBusinessId, $viaPartnerId) {
            // the lock comes first: everything read after it is read after whoever held it before us has finished
            BookableItem::query()->whereKey($item->id)->lockForUpdate()->first(['id']);

            BookingHold::query()->where('bookable_item_id', $item->id)->where('user_id', $userId)->delete();
            BookingHold::query()->where('bookable_item_id', $item->id)->where('expires_at', '<=', now())->delete();

            $answer = app(BookableAvailabilityService::class)->check($item, $start, $end, $quantity, $viaPartnerId, true, $userId);

            if (! ($answer['available'] ?? false)) {
                throw ValidationException::withMessages(['bookable_id' => $answer['reason'] ?: __('هذه الوحدة غير متاحة الآن.')]);
            }

            return BookingHold::query()->create([
                'bookable_item_id' => (int) $item->id, 'user_id' => $userId, 'seller_business_id' => $sellerBusinessId,
                'starts_at' => $start, 'ends_at' => $end, 'quantity' => max($quantity, 1), 'expires_at' => now()->addMinutes($this->minutes()),
            ]);
        });
    }

    public function release(int $userId, int $holdId): void
    {
        BookingHold::query()->whereKey($holdId)->where('user_id', $userId)->delete();
    }

    /** The booking was made: the hold has done its job. */
    public function consume(int $userId, int $itemId): void
    {
        BookingHold::query()->where('bookable_item_id', $itemId)->where('user_id', $userId)->delete();
    }
}
