<?php

namespace App\Services\Hotels;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * «شاشة الغرف» of a hotel: one month, day by day, every room type (grouped by its kind — a room, a suite, a chalet, an apartment) with
 * how many of it are taken each night and how many are still free.
 *
 * A stay counts wherever it was sold: the hotel's own guest, or a guest of a travel agency that holds an allocation. The board says
 * which, so the hotel knows who is arriving and through whom. Rooms promised to a partner and not yet sold are shown as held — they are
 * not for sale to anyone else.
 */
final class OccupancyBoard
{
    /** @return array<string,mixed> */
    public function month(int $hotelId, string $month): array
    {
        $first = CarbonImmutable::createFromFormat('!Y-m', $month) ?: CarbonImmutable::now()->startOfMonth();
        $days = $first->daysInMonth;
        $end = $first->addMonth();

        $units = BookableItem::query()
            ->with(['lineOption:id,name_ar,name_en', 'rooms:id,bookable_item_id,number,status'])
            ->where('business_id', $hotelId)->where('item_type', 'booking_stay')->where('is_active', 1)
            ->orderBy('line_option_id')->orderBy('code')->get();

        $bookings = Booking::query()
            ->with(['user:id,name', 'business:id,name', 'room:id,number'])
            ->whereIn('bookable_id', $units->pluck('id'))
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])
            ->whereNotNull('starts_at')->whereNotNull('ends_at')
            ->where('starts_at', '<', $end)->where('ends_at', '>', $first)
            ->orderBy('starts_at')->get()->groupBy('bookable_id');

        $dealing = \App\Models\BookingHold::query()->live()->whereIn('bookable_item_id', $units->pluck('id'))
            ->where('starts_at', '<', $end)->where('ends_at', '>', $first)->with(['seller:id,name', 'user:id,name'])->orderBy('starts_at')->get()->groupBy('bookable_item_id');

        $held = BookableAllocation::query()->active()->where('allocation_type', BookableAllocation::TYPE_GUARANTEED)
            ->whereIn('bookable_item_id', $units->pluck('id'))->with('partnerBusiness:id,name')->get()->groupBy('bookable_item_id');

        $kinds = $units->groupBy(fn (BookableItem $u) => (int) ($u->line_option_id ?? 0))->map(function (Collection $inKind, $kindId) use ($first, $days, $bookings, $held, $dealing) {
            $head = $inKind->first();

            return [
                'line_option_id' => $kindId ?: null,
                'name' => (string) (optional($head->lineOption)->name_ar ?: optional($head->lineOption)->name_en ?: $head->displayLabel()),
                'units' => $inKind->map(fn (BookableItem $u) => $this->unit($u, $bookings->get($u->id, collect()), $held->get($u->id, collect()), $dealing->get($u->id, collect()), $first, $days))->values()->all(),
            ];
        })->values()->all();

        return ['month' => $first->format('Y-m'), 'days' => $days, 'first_weekday' => (int) $first->dayOfWeek, 'kinds' => $kinds];
    }

    /** @return array<string,mixed> */
    private function unit(BookableItem $u, Collection $bookings, Collection $held, Collection $dealing, CarbonImmutable $first, int $days): array
    {
        $stock = $u->stockCount();
        $booked = array_fill(0, $days, 0);
        $viaPartner = array_fill(0, $days, 0);

        foreach ($bookings as $b) {
            $quantity = max((int) $b->quantity, 1);
            $partner = $this->isPartnerSale($b, $u);
            $from = max(0, (int) $first->diffInDays(CarbonImmutable::parse($b->starts_at)->startOfDay(), false));
            $to = (int) $first->diffInDays(CarbonImmutable::parse($b->ends_at)->startOfDay(), false);

            // a night is a day from check-in up to, not including, check-out; a stay inside one day holds that day
            if ($to <= $from) {
                $to = $from + 1;
            }

            for ($d = $from; $d < min($to, $days); $d++) {
                $booked[$d] += $quantity;

                if ($partner) {
                    $viaPartner[$d] += $quantity;
                }
            }
        }

        // «تحت التعامل»: rooms someone is filling in a booking for right now
        $dealingPerDay = array_fill(0, $days, 0);

        foreach ($dealing as $h) {
            $from = max(0, (int) $first->diffInDays(CarbonImmutable::parse($h->starts_at)->startOfDay(), false));
            $to = min($days, max((int) $first->diffInDays(CarbonImmutable::parse($h->ends_at)->startOfDay(), false), $from + 1));

            for ($d = $from; $d < $to; $d++) {
                $dealingPerDay[$d] += (int) $h->quantity;
            }
        }

        $heldNow = (int) $held->sum(fn ($a) => $a->availableQuantity());

        return [
            'id' => (int) $u->id,
            'label' => (string) $u->displayLabel(),
            'stock' => $stock,
            'guest_scope' => (string) ($u->guest_scope ?: 'all'),
            'held_for_partners' => $heldNow,
            'rooms' => $u->rooms->map(fn ($r) => ['id' => (int) $r->id, 'number' => (string) $r->number, 'status' => (string) $r->status])->values()->all(),
            'booked' => $booked,
            'via_partner' => $viaPartner,
            'dealing' => $dealingPerDay,
            'dealing_now' => $dealing->map(fn ($h) => [
                'starts_at' => $h->starts_at->format('Y-m-d H:i:s'),
                'ends_at' => $h->ends_at->format('Y-m-d H:i:s'),
                'quantity' => (int) $h->quantity,
                'minutes_left' => max((int) now()->diffInMinutes($h->expires_at, false), 0),
                // through whom: the hotel's own booking screen (null) or a company that was given the room
                'seller' => $h->seller_business_id && (int) $h->seller_business_id !== (int) $u->business_id ? optional($h->seller)->name : null,
            ])->values()->all(),
            'bookings' => $bookings->map(fn (Booking $b) => [
                'id' => (int) $b->id,
                'status' => (string) $b->status,
                'starts_at' => optional($b->starts_at)->format('Y-m-d H:i:s'),
                'ends_at' => optional($b->ends_at)->format('Y-m-d H:i:s'),
                'quantity' => max((int) $b->quantity, 1),
                'guest' => optional($b->user)->name,
                'source' => $this->isPartnerSale($b, $u) ? 'partner' : 'direct',
                'partner' => $this->isPartnerSale($b, $u) ? optional($b->business)->name : null,
                'room_number' => optional($b->room)->number,
                // what the guest declared for a scoped room: the hotel checks the papers on arrival
                'guest_origin' => is_array($b->meta) ? ($b->meta['guest_origin'] ?? null) : null,
            ])->values()->all(),
        ];
    }

    /** A stay sold by another business through an allocation is booked at that business, on this hotel's room. */
    private function isPartnerSale(Booking $b, BookableItem $u): bool
    {
        return (int) $b->business_id !== (int) $u->business_id;
    }
}
