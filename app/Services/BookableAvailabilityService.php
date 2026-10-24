<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BookableAvailabilityService
{
    /**
     * What was read for a whole list of rooms in a handful of queries (see preload), so judging each room in turn asks the database nothing.
     *
     * @var array<string,mixed>|null
     */
    protected ?array $frame = null;

    /**
     * Read, for MANY units and ONE window, everything `check` would otherwise ask the database per unit: the hours of each business, the
     * blocked slots, the rooms taken, the rooms promised to partners and the rooms other people are dealing with. A page of forty room
     * types used to cost about two hundred queries; it costs six, and the answers are the very same ones.
     *
     * @param  iterable<BookableItem>  $items
     */
    public function preload(iterable $items, CarbonInterface|string $startsAt, CarbonInterface|string $endsAt, ?int $viewerId = null): void
    {
        $start = $this->normalizeDateTime($startsAt);
        $end = $this->normalizeDateTime($endsAt);
        $items = collect($items)->filter(fn ($i) => $i instanceof BookableItem)->values();
        $viewer = $viewerId ?? (int) optional(request()->user('sanctum'))->id;

        $this->frame = null;

        if ($items->isEmpty() || $end->lessThanOrEqualTo($start)) {
            return;
        }

        $ids = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        $live = [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED];

        $taken = Booking::query()->whereIn('bookable_id', $ids)->whereNotIn('status', $live)
            ->whereNotNull('starts_at')->whereNotNull('ends_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->selectRaw('bookable_id, COUNT(*) as bookings, COALESCE(SUM(GREATEST(COALESCE(quantity, 0), 1)), 0) as rooms')
            ->groupBy('bookable_id')->get()->keyBy('bookable_id');

        $guaranteed = \App\Models\BookableAllocation::query()->active()->whereIn('bookable_item_id', $ids)
            ->where('allocation_type', \App\Models\BookableAllocation::TYPE_GUARANTEED)->get();
        $left = \App\Models\BookableAllocation::availableForMany($guaranteed, $start, $end);

        $this->frame = [
            'key' => $this->frameKey($start, $end, $viewer),
            'ids' => array_flip($ids),
            'open' => [],
            'blocked' => BookableItemBlockedSlot::query()->whereIn('bookable_item_id', $ids)->active()->overlapping($start, $end)->ordered()->get()->groupBy('bookable_item_id'),
            'taken' => $taken,
            'guaranteed' => $guaranteed->map(fn ($a) => ['item' => (int) $a->bookable_item_id, 'partner' => (int) $a->partner_business_id, 'left' => $left[(int) $a->id] ?? 0])->groupBy('item'),
            'soft' => app(BookingHolds::class)->heldRoomsMany($ids, $start, $end, $viewer ?: null),
        ];
    }

    /** Forget a preload: what follows asks the database again. */
    public function forget(): void
    {
        $this->frame = null;
    }

    protected function frameKey(CarbonInterface $start, CarbonInterface $end, int $viewer): string
    {
        return $start->toDateTimeString() . '|' . $end->toDateTimeString() . '|' . $viewer;
    }

    /** The preload that answers for this unit and window, or null (ask the database). */
    protected function frameFor(BookableItem $item, CarbonInterface $start, CarbonInterface $end, int $viewer): ?array
    {
        return $this->frame && isset($this->frame['ids'][(int) $item->id]) && $this->frame['key'] === $this->frameKey($start, $end, $viewer)
            ? $this->frame
            : null;
    }

    /**
     * Check availability for a specific bookable item within a date/time range.
     *
     * @param  BookableItem  $item
     * @param  CarbonInterface|string  $startsAt
     * @param  CarbonInterface|string  $endsAt
     * @return array{
     *     ok: bool,
     *     available: bool,
     *     reason: ?string,
     *     code: string,
     *     item_id: int,
     *     starts_at: string,
     *     ends_at: string,
     *     conflicts: \Illuminate\Support\Collection<int, BookableItemBlockedSlot>
     * }
     */
    public function check(BookableItem $item, CarbonInterface|string $startsAt, CarbonInterface|string $endsAt, int $requested = 1, ?int $viaPartnerId = null, bool $honourHolds = false, ?int $viewerId = null): array
    {
        $start = $this->normalizeDateTime($startsAt);
        $end   = $this->normalizeDateTime($endsAt);

        $this->validateRange($start, $end);

        if (! $item->is_active) {
            return $this->result(
                ok: true,
                available: false,
                reason: __('العنصر غير نشط'),
                code: 'item_inactive',
                item: $item,
                start: $start,
                end: $end,
                conflicts: collect()
            );
        }

        // The business's own week, which nothing here used to read: a clinic
        // that closes at 17:00 was handed a 16:50 appointment thirty minutes
        // long, and a shop shut on Friday took Friday bookings all day. Only
        // same-day windows are judged — a four-night stay is not a question
        // about opening hours. See BusinessHoursService::isOpenThroughout.
        $viewer = $viewerId ?? (int) optional(request()->user('sanctum'))->id;
        $frame = $this->frameFor($item, $start, $end, $viewer);

        if ($item->business_id && ! ($frame
            ? ($this->frame['open'][(int) $item->business_id] ??= app(BusinessHoursService::class)->isOpenThroughout((int) $item->business_id, $start, $end))
            : app(BusinessHoursService::class)->isOpenThroughout((int) $item->business_id, $start, $end))) {
            return $this->result(
                ok: true,
                available: false,
                reason: __('النشاط مغلق في هذا الوقت'),
                code: 'outside_working_hours',
                item: $item,
                start: $start,
                end: $end,
                conflicts: collect()
            );
        }

        $conflicts = $frame ? ($frame['blocked']->get((int) $item->id) ?? collect()) : $this->findBlockingSlots($item, $start, $end);

        if ($conflicts->isNotEmpty()) {
            return $this->result(
                ok: true,
                available: false,
                reason: __('العنصر غير متاح في الفترة المحددة'),
                code: 'blocked_slot_conflict',
                item: $item,
                start: $start,
                end: $end,
                conflicts: $conflicts
            );
        }

        // Blocked slots are what the BUSINESS closed by hand. They said nothing
        // about what CUSTOMERS have already taken, so a room sold for these
        // nights still read as available and could be sold again.
        $stay = $item->item_type === 'booking_stay';
        if ($frame) {
            $row = $frame['taken']->get((int) $item->id);
            $taken = (int) ($stay ? ($row->rooms ?? 0) : ($row->bookings ?? 0));
        } else {
            $taken = $stay ? $this->takenRooms($item, $start, $end) : $this->countLiveBookings($item, $start, $end);
        }

        $capacity = $stay ? $item->stockCount() : max((int) ($item->quantity ?? 1), 1);
        // rooms a hotel promised to a partner (a guaranteed allocation) are not for sale to anyone else; the partner's own
        // booking does not count its own promise against itself
        $held = 0;

        if ($stay && $honourHolds) {
            $held = $frame
                ? (int) collect($frame['guaranteed']->get((int) $item->id, []))->filter(fn ($g) => ! $viaPartnerId || $g['partner'] !== (int) $viaPartnerId)->sum('left')
                : $this->heldForPartners($item, $viaPartnerId, $start, $end);
        }

        // «تحت التعامل»: someone else is filling in a booking for this room right now. Their hold counts against everyone but them, so
        // the hotel's guest and a travel company's guest are never both offered the last room.
        $soft = $frame ? (int) ($frame['soft'][(int) $item->id] ?? 0) : app(BookingHolds::class)->heldRooms($item, $start, $end, $viewer ?: null);

        // the hotel keeps a few rooms of the type back for its own guests in peak: a partner (selling through an allocation) can
        // take the type only up to its stock less that reserve, while the hotel's own guests can take every room
        $reserve = $stay && $viaPartnerId ? min(max((int) ($item->reserve_rooms ?? 0), 0), $capacity) : 0;

        if ($reserve > 0 && $taken + $held + $soft + max($requested, 1) > $capacity - $reserve && $taken + $held + $soft + max($requested, 1) <= $capacity) {
            return $this->result(
                ok: true,
                available: false,
                reason: __('هذه الغرف محجوزة احتياطيًا لنزلاء الفندق.'),
                code: 'reserved_for_hotel',
                item: $item,
                start: $start,
                end: $end,
                conflicts: collect()
            );
        }

        if ($taken + $held + $soft + max($requested, 1) > $capacity) {
            // what stands in the way: only a partner's promise (it can be asked to give the room back), only someone's open booking
            // screen (it frees itself), or the room is simply taken
            $byPartner = $held > 0 && $taken + $soft + max($requested, 1) <= $capacity;
            $byOthers = ! $byPartner && $soft > 0 && $taken + $held + max($requested, 1) <= $capacity;

            return $this->result(
                ok: true,
                available: false,
                reason: $byPartner
                    ? __('هذه الغرفة محجوزة لشريك للفندق. يمكنك طلب تحريرها منه.')
                    : ($byOthers ? __('تحت التعامل من طرف آخر الآن، جرّب بعد دقائق.') : __('هذه الوحدة محجوزة بالفعل في هذه الفترة')),
                code: $byPartner ? 'held_for_partner' : ($byOthers ? 'held_by_other' : 'booking_conflict'),
                item: $item,
                start: $start,
                end: $end,
                conflicts: collect()
            );
        }

        return $this->result(
            ok: true,
            available: true,
            reason: null,
            code: 'available',
            item: $item,
            start: $start,
            end: $end,
            conflicts: collect()
        );
    }

    /**
     * Check availability by item id.
     *
     * @param  int  $bookableItemId
     * @param  CarbonInterface|string  $startsAt
     * @param  CarbonInterface|string  $endsAt
     * @return array
     */
    public function checkById(int $bookableItemId, CarbonInterface|string $startsAt, CarbonInterface|string $endsAt): array
    {
        $item = BookableItem::query()->findOrFail($bookableItemId);

        return $this->check($item, $startsAt, $endsAt);
    }

    /**
     * Return only conflicts.
     *
     * @param  BookableItem  $item
     * @param  CarbonInterface|string  $startsAt
     * @param  CarbonInterface|string  $endsAt
     * @return \Illuminate\Support\Collection<int, BookableItemBlockedSlot>
     */
    public function getConflicts(BookableItem $item, CarbonInterface|string $startsAt, CarbonInterface|string $endsAt): Collection
    {
        $start = $this->normalizeDateTime($startsAt);
        $end   = $this->normalizeDateTime($endsAt);

        $this->validateRange($start, $end);

        return $this->findBlockingSlots($item, $start, $end);
    }

    /**
     * Check if item is available as a boolean only.
     *
     * @param  BookableItem  $item
     * @param  CarbonInterface|string  $startsAt
     * @param  CarbonInterface|string  $endsAt
     * @return bool
     */
    public function isAvailable(BookableItem $item, CarbonInterface|string $startsAt, CarbonInterface|string $endsAt): bool
    {
        $result = $this->check($item, $startsAt, $endsAt);

        return (bool) ($result['available'] ?? false);
    }

    /**
     * Find blocking slots that overlap with the requested range.
     *
     * Overlap logic:
     * existing.starts_at < requested_end
     * AND
     * existing.ends_at > requested_start
     *
     * @param  BookableItem  $item
     * @param  CarbonInterface  $start
     * @param  CarbonInterface  $end
     * @return \Illuminate\Support\Collection<int, BookableItemBlockedSlot>
     */
    protected function findBlockingSlots(BookableItem $item, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return BookableItemBlockedSlot::query()
            ->forBookableItem($item->id)
            ->active()
            ->overlapping($start, $end)
            ->ordered()
            ->get();
    }

    /**
     * How many live bookings already overlap this window.
     *
     * A cancelled or rejected booking releases the unit; a completed one is in
     * the past and cannot overlap a future window anyway, but is excluded for
     * the same reason — it no longer holds anything. Everything else does.
     *
     * `quantity` on the item is the number of identical units behind one row
     * («٣ غرف مزدوجة»), so the comparison is a count, not a boolean.
     */
    /** How many rooms of the type live bookings take in the window: a stay of three rooms takes three, whoever sold it. */
    public function takenRooms(BookableItem $item, CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) Booking::query()
            ->where('bookable_id', $item->id)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->sum(\DB::raw('GREATEST(COALESCE(quantity, 0), 1)'));
    }

    /** What the hotel still owes its partners on this type: the unsold part of every active guaranteed allocation but the asker's own. */
    public function heldForPartners(BookableItem $item, ?int $exceptPartnerId = null, ?CarbonInterface $start = null, ?CarbonInterface $end = null): int
    {
        return (int) \App\Models\BookableAllocation::query()->active()
            ->where('bookable_item_id', $item->id)
            ->where('allocation_type', \App\Models\BookableAllocation::TYPE_GUARANTEED)
            ->when($exceptPartnerId, fn ($q) => $q->where('partner_business_id', '!=', $exceptPartnerId))
            ->get()
            ->pipe(function ($allocations) use ($start, $end) {
                if ($start && $end) {
                    return array_sum(\App\Models\BookableAllocation::availableForMany($allocations, $start, $end));
                }

                return $allocations->sum(fn ($a) => $a->availableQuantity());
            });
    }

    protected function countLiveBookings(BookableItem $item, CarbonInterface $start, CarbonInterface $end): int
    {
        return Booking::query()
            ->where('bookable_id', $item->id)
            ->whereNotIn('status', [
                Booking::STATUS_CANCELLED,
                Booking::STATUS_REJECTED,
                Booking::STATUS_COMPLETED,
            ])
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->count();
    }

    /**
     * Normalize date/time input to Carbon instance.
     *
     * @param  CarbonInterface|string  $value
     * @return Carbon
     */
    protected function normalizeDateTime(CarbonInterface|string $value): Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        return Carbon::parse($value);
    }

    /**
     * Validate date range.
     *
     * @param  CarbonInterface  $start
     * @param  CarbonInterface  $end
     * @return void
     */
    protected function validateRange(CarbonInterface $start, CarbonInterface $end): void
    {
        if ($end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('End datetime must be greater than start datetime.');
        }
    }

    /**
     * Standard result payload.
     *
     * @param  bool  $ok
     * @param  bool  $available
     * @param  string|null  $reason
     * @param  string  $code
     * @param  BookableItem  $item
     * @param  CarbonInterface  $start
     * @param  CarbonInterface  $end
     * @param  \Illuminate\Support\Collection<int, BookableItemBlockedSlot>  $conflicts
     * @return array
     */
    protected function result(
        bool $ok,
        bool $available,
        ?string $reason,
        string $code,
        BookableItem $item,
        CarbonInterface $start,
        CarbonInterface $end,
        Collection $conflicts
    ): array {
        return [
            'ok' => $ok,
            'available' => $available,
            'reason' => $reason,
            'code' => $code,
            'item_id' => (int) $item->id,
            'starts_at' => $start->toDateTimeString(),
            'ends_at' => $end->toDateTimeString(),
            'conflicts' => $conflicts,
        ];
    }
}
