<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BookableAllocation extends Model
{
    public const TYPE_GUARANTEED = 'guaranteed';
    public const TYPE_NON_GUARANTEED = 'non_guaranteed';
    public const TYPE_REQUEST_ONLY = 'request_only';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'bookable_allocations';

    protected $fillable = [
        'partnership_id',
        'owner_business_id',
        'partner_business_id',
        'bookable_item_id',
        'platform_service_id',
        'allocation_type',
        'starts_at',
        'ends_at',
        'quantity_total',
        'quantity_sold',
        'quantity_reserved',
        'quantity_released',
        'release_days_before',
        'min_nights',
        'max_nights',
        'contract_price',
        'currency',
        'markup_type',
        'markup_value',
        'status',
        'meta',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'quantity_total' => 'integer',
        'quantity_sold' => 'integer',
        'quantity_reserved' => 'integer',
        'quantity_released' => 'integer',
        'release_days_before' => 'integer',
        'min_nights' => 'integer',
        'max_nights' => 'integer',
        'contract_price' => 'decimal:2',
        'markup_value' => 'decimal:2',
        'meta' => 'array',
    ];

    public static function allocationTypes(): array
    {
        return [
            self::TYPE_GUARANTEED => 'Guaranteed',
            self::TYPE_NON_GUARANTEED => 'Non Guaranteed',
            self::TYPE_REQUEST_ONLY => 'Request Only',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_STOPPED => 'Stopped',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function partnership()
    {
        return $this->belongsTo(BusinessPartnership::class, 'partnership_id');
    }

    public function ownerBusiness()
    {
        return $this->belongsTo(User::class, 'owner_business_id');
    }

    public function partnerBusiness()
    {
        return $this->belongsTo(User::class, 'partner_business_id');
    }

    public function bookableItem()
    {
        return $this->belongsTo(BookableItem::class, 'bookable_item_id');
    }

    public function platformService()
    {
        return $this->belongsTo(PlatformService::class, 'platform_service_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        // The dates of an allotment are the dates of the STAY («٥ غرف كل ليلة من ١ ديسمبر إلى ٣١»), not of the selling: it is on sale as
        // soon as it is agreed, for stays that start in its window, until the release cut-off of each night. It stops being on sale when
        // it has no nights left ahead.
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()->startOfDay());
            });
    }

    /**
     * An allotment is ROOMS PER NIGHT, the way the trade writes it: «٥ غرف كل ليلة من ١ ديسمبر إلى ٣١». `quantity_total` is how many of the
     * room the partner may have on any one night; a room it sold for the 10th and 11th is free again for the 12th.
     *
     * What is left for a stay is what the BUSIEST night of it leaves: the total, less what the partner has already sold on that night,
     * less the rooms it gave back for it. (The `quantity_sold` / `quantity_reserved` counters are only a running tally of bookings; they no
     * longer decide anything, which is why a long-ago stay does not eat a room of next month.)
     */
    public function availableFor(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): int
    {
        $peak = 0;

        foreach ($this->usedPerNight($start, $end) as $used) {
            $peak = max($peak, $used);
        }

        return max((int) $this->quantity_total - (int) $this->quantity_released - $peak, 0);
    }

    /**
     * What is left across the rest of the allocation's life — the busiest night still ahead — for screens that have no stay in hand.
     */
    public function availableQuantity(): int
    {
        [$from, $to] = $this->lifeWindow();

        return $this->availableFor($from, $to);
    }

    /**
     * What is left ahead on MANY allocations at once (two queries, not two each). Each is judged over its own remaining life.
     *
     * @param  iterable<BookableAllocation>  $allocations
     * @return array<int,int> allocation id => rooms left
     */
    public static function availableQuantityMany(iterable $allocations): array
    {
        $list = collect($allocations)->values();

        if ($list->isEmpty()) {
            return [];
        }

        $windows = $list->mapWithKeys(fn ($a) => [(int) $a->id => $a->lifeWindow()]);
        $from = $windows->min(fn ($w) => $w[0]->timestamp);
        $to = $windows->max(fn ($w) => $w[1]->timestamp);
        $sources = self::sourcesOf($list->pluck('id')->map(fn ($id) => (int) $id)->all(), \Illuminate\Support\Carbon::createFromTimestamp($from), \Illuminate\Support\Carbon::createFromTimestamp($to));
        $left = [];

        foreach ($list as $a) {
            [$f, $t] = $windows[(int) $a->id];
            [$f, $t] = self::windowOf($f, $t);
            $peak = max([0, ...array_values(self::spread($sources[(int) $a->id] ?? [], $f, $t))]);
            $left[(int) $a->id] = max((int) $a->quantity_total - (int) $a->quantity_released - $peak, 0);
        }

        return $left;
    }

    /** The nights still ahead of this allocation, as the look of `availableQuantity`. @return array{0:\Illuminate\Support\Carbon,1:\Illuminate\Support\Carbon} */
    protected function lifeWindow(): array
    {
        $from = \Illuminate\Support\Carbon::today();

        if ($this->starts_at && $this->starts_at->isAfter($from)) {
            $from = $this->starts_at->copy()->startOfDay();
        }

        $to = $this->ends_at ? $this->ends_at->copy()->startOfDay()->addDay() : $from->copy()->addDays(365);

        if ($to->isBefore($from) || $to->equalTo($from)) {
            $to = $from->copy()->addDay();
        }

        // a year is the longest look: the loop below is by night
        if ($from->diffInDays($to) > 366) {
            $to = $from->copy()->addDays(366);
        }

        return [$from, $to];
    }

    /**
     * Rooms of this allocation in use on each night of the window: the bookings sold from it (cancelled and rejected ones do not count)
     * and the rooms a partner agreed to give back for those nights.
     *
     * @return array<string,int> night (Y-m-d) => rooms
     */
    public function usedPerNight(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): array
    {
        [$from, $to] = self::windowOf($start, $end);

        return self::spread(self::sourcesOf([(int) $this->id], $from, $to)[(int) $this->id] ?? [], $from, $to);
    }

    /**
     * What is left for a stay on MANY allocations at once, in two queries however many there are (a list of rooms used to ask two per row).
     *
     * @param  iterable<BookableAllocation>  $allocations
     * @return array<int,int> allocation id => rooms left
     */
    public static function availableForMany(iterable $allocations, \Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): array
    {
        $list = collect($allocations)->values();

        if ($list->isEmpty()) {
            return [];
        }

        [$from, $to] = self::windowOf($start, $end);
        $sources = self::sourcesOf($list->pluck('id')->map(fn ($id) => (int) $id)->all(), $from, $to);
        $left = [];

        foreach ($list as $a) {
            $peak = max([0, ...array_values(self::spread($sources[(int) $a->id] ?? [], $from, $to))]);
            $left[(int) $a->id] = max((int) $a->quantity_total - (int) $a->quantity_released - $peak, 0);
        }

        return $left;
    }

    /** @return array{0:\Illuminate\Support\Carbon,1:\Illuminate\Support\Carbon} */
    private static function windowOf(\Carbon\CarbonInterface $start, \Carbon\CarbonInterface $end): array
    {
        $from = \Illuminate\Support\Carbon::parse($start)->startOfDay();
        $to = \Illuminate\Support\Carbon::parse($end)->startOfDay();

        if (! $to->isAfter($from)) {
            $to = $from->copy()->addDay(); // a stay inside one day holds that day
        }

        return [$from, $to];
    }

    /**
     * The stays and the approved give-backs of these allocations that touch the window, grouped by allocation.
     *
     * @param  array<int,int>  $ids
     * @return array<int,array<int,array{0:mixed,1:mixed,2:int}>>
     */
    private static function sourcesOf(array $ids, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $sources = [];

        foreach (\App\Models\Booking::query()->whereIn('allocation_id', $ids)
            ->whereNotIn('status', [\App\Models\Booking::STATUS_CANCELLED, \App\Models\Booking::STATUS_REJECTED])
            ->whereNotNull('starts_at')->whereNotNull('ends_at')->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->get(['allocation_id', 'starts_at', 'ends_at', 'quantity']) as $b) {
            $sources[(int) $b->allocation_id][] = [$b->starts_at, $b->ends_at, max((int) $b->quantity, 1)];
        }

        foreach (\App\Models\AllocationReleaseRequest::query()->whereIn('allocation_id', $ids)->where('status', \App\Models\AllocationReleaseRequest::APPROVED)
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get(['allocation_id', 'starts_at', 'ends_at', 'quantity']) as $r) {
            $sources[(int) $r->allocation_id][] = [$r->starts_at, $r->ends_at, (int) $r->quantity];
        }

        return $sources;
    }

    /**
     * @param  array<int,array{0:mixed,1:mixed,2:int}>  $sources
     * @return array<string,int> night => rooms in use
     */
    private static function spread(array $sources, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $nights = [];

        for ($d = \Illuminate\Support\Carbon::parse($from); $d->isBefore($to) && count($nights) < 400; $d->addDay()) {
            $nights[$d->toDateString()] = 0;
        }

        foreach ($sources as [$sFrom, $sTo, $qty]) {
            $first = \Illuminate\Support\Carbon::parse($sFrom)->startOfDay();
            $last = \Illuminate\Support\Carbon::parse($sTo)->startOfDay();

            if (! $last->isAfter($first)) {
                $last = $first->copy()->addDay();
            }

            for ($d = $first->copy(); $d->isBefore($last); $d->addDay()) {
                $key = $d->toDateString();

                if (isset($nights[$key])) {
                    $nights[$key] += $qty;
                }
            }
        }

        return $nights;
    }

    /** Does a stay (check-in, check-out) sit inside the dates this allocation covers — every night of it? */
    public function coversStay(\Carbon\CarbonInterface $checkIn, \Carbon\CarbonInterface $checkOut): bool
    {
        $first = \Illuminate\Support\Carbon::parse($checkIn)->startOfDay();
        $last = \Illuminate\Support\Carbon::parse($checkOut)->startOfDay();
        $lastNight = $last->isAfter($first) ? $last->copy()->subDay() : $first;

        return (! $this->starts_at || ! $first->lt($this->starts_at->copy()->startOfDay()))
            && (! $this->ends_at || ! $lastNight->gt($this->ends_at->copy()->startOfDay()));
    }

    public function isReleasedForDate($date): bool
    {
        if ((int) $this->release_days_before <= 0) {
            return false;
        }

        try {
            $releaseAt = \Illuminate\Support\Carbon::parse($date)->subDays((int) $this->release_days_before)->endOfDay();
        } catch (\Throwable $e) {
            return false;
        }

        return now()->greaterThan($releaseAt);
    }

    public function finalPrice(): float
    {
        $price = round((float) $this->contract_price, 2);
        $markupType = (string) ($this->markup_type ?? 'none');
        $markupValue = round((float) ($this->markup_value ?? 0), 2);

        if ($markupType === 'fixed') {
            return max(round($price + $markupValue, 2), 0);
        }

        if ($markupType === 'percent') {
            return max(round($price + ($price * ($markupValue / 100)), 2), 0);
        }

        return max($price, 0);
    }
}
