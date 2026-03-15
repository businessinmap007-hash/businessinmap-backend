<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BookableAvailabilityService
{
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
    public function check(BookableItem $item, CarbonInterface|string $startsAt, CarbonInterface|string $endsAt): array
    {
        $start = $this->normalizeDateTime($startsAt);
        $end   = $this->normalizeDateTime($endsAt);

        $this->validateRange($start, $end);

        if (! $item->is_active) {
            return $this->result(
                ok: true,
                available: false,
                reason: 'العنصر غير نشط',
                code: 'item_inactive',
                item: $item,
                start: $start,
                end: $end,
                conflicts: collect()
            );
        }

        $conflicts = $this->findBlockingSlots($item, $start, $end);

        if ($conflicts->isNotEmpty()) {
            return $this->result(
                ok: true,
                available: false,
                reason: 'العنصر غير متاح في الفترة المحددة',
                code: 'blocked_slot_conflict',
                item: $item,
                start: $start,
                end: $end,
                conflicts: $conflicts
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
