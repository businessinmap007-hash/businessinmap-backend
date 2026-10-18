<?php

namespace App\Services\Schedules;

use App\Models\Booking;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Models\User;
use App\Services\Requests\RideQuote;
use App\Services\Requests\RideRequestKind;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «راجع فاضي» (owner, 2026-10-18): a limousine driver who took a customer from Alexandria to Cairo and is going back
 * empty offers the way home at a lower price he sets himself — an extra earning for him, a cheap ride for the customer.
 *
 * It is NOT a new subsystem: an offer is one trip leg of the scheduling service (mode limousine, one date, ONE «ride» of
 * capacity — the whole car — flagged `is_return_leg` and `meta.empty_return`), so the reservation, the deposit, the
 * attendance confirmation, the rating and the disputes are the ones every trip already has. What this service adds is
 * what an empty return needs and an ordinary leg does not: a price that may never exceed the reference fare of that
 * distance (so it cannot be a fake «offer»), a date that must still be ahead, and a listing that ends at the departure.
 */
final class EmptyReturnService
{
    public function __construct(
        private readonly TripScheduleService $schedules,
        private readonly TripReservationService $reservations
    ) {}

    /**
     * The reference fare of the distance and the price an empty return starts from (his own to change).
     *
     * @return array{distance_km:float, source:string, reference_price:float, suggested_price:float}|null
     */
    public function quote(int $originCityId, int $destinationCityId, ?float $manualKm = null): ?array
    {
        $quote = RideQuote::between($originCityId, $destinationCityId, $manualKm);

        if ($quote === null) {
            return null;
        }

        return [
            'distance_km' => $quote['distance_km'],
            'source' => $quote['source'],
            'reference_price' => $quote['suggested_price'],
            'suggested_price' => max(1.0, round($quote['suggested_price'] * (float) config('bim.rides.empty_return.suggested_share', 0.5))),
        ];
    }

    /**
     * @param  array{origin_city_id:int, destination_city_id:int, departs_at:string|\DateTimeInterface, price:float|int|string, seats?:?int, distance_km?:?float, notes?:?string, vehicle_label?:?string, from_booking_id?:?int}  $data
     */
    public function publish(User $driver, array $data): TripSchedule
    {
        if (! $driver->isBusiness() || (int) $driver->category_child_id !== RideRequestKind::tradeId()) {
            throw ValidationException::withMessages(['driver' => __('«راجع فاضي» لسائقي الليموزين فقط.')]);
        }

        if (! $driver->acceptsRequests()) {
            throw ValidationException::withMessages(['driver' => __('أنت في وضع الاستراحة: فعّل استقبال الطلبات لتنشر.')]);
        }

        $cities = DB::table('cities')->whereIn('id', [(int) $data['origin_city_id'], (int) $data['destination_city_id']])->get(['id', 'governorate_id'])->keyBy('id');
        $from = $cities->get((int) $data['origin_city_id']);
        $to = $cities->get((int) $data['destination_city_id']);

        if (! $from || ! $to || (int) $from->id === (int) $to->id) {
            throw ValidationException::withMessages(['destination_city_id' => __('اختر مدينتين مختلفتين.')]);
        }

        $departs = ($data['departs_at'] instanceof \DateTimeInterface ? Carbon::instance($data['departs_at']) : Carbon::parse((string) $data['departs_at']))
            ->setTimezone(config('app.timezone'));
        $windowHours = (int) config('bim.rides.empty_return.window_hours', 72);

        if ($departs->lt(now()) || $departs->gt(now()->addHours($windowHours))) {
            throw ValidationException::withMessages(['departs_at' => __('اختر موعدًا قادمًا خلال :h ساعة.', ['h' => $windowHours])]);
        }

        // «the way back from THIS ride»: it must be the driver's own ride, and a ride has one way back
        $fromBooking = null;

        if (! empty($data['from_booking_id'])) {
            $fromBooking = $this->rideBookings($driver)->whereKey((int) $data['from_booking_id'])->first();

            if (! $fromBooking) {
                throw ValidationException::withMessages(['from_booking_id' => __('هذا المشوار ليس من مشاويرك.')]);
            }

            if ($this->linkedBookingIds((int) $driver->id)->contains((int) $fromBooking->id)) {
                throw ValidationException::withMessages(['from_booking_id' => __('نشرت رجوعًا لهذا المشوار من قبل.')]);
            }
        }

        $quote = $this->quote((int) $from->id, (int) $to->id, isset($data['distance_km']) ? (float) $data['distance_km'] : null);

        if ($quote === null) {
            throw ValidationException::withMessages(['distance_km' => __('اكتب المسافة التقريبية بالكيلومتر.')]);
        }

        $price = round((float) $data['price'], 2);

        if ($price < 1) {
            throw ValidationException::withMessages(['price' => __('اكتب سعرًا صالحًا.')]);
        }

        // the point of the feature is a LOWER price: above the reference fare it is not a return offer at all
        if ($price > $quote['reference_price']) {
            throw ValidationException::withMessages(['price' => __('سعر «راجع فاضي» لا يزيد على سعر المشوار المعتاد (:p جنيه).', ['p' => (int) $quote['reference_price']])]);
        }

        $duplicate = TripSchedule::query()
            ->where('business_id', $driver->id)->where('is_return_leg', true)->where('status', TripSchedule::STATUS_ACTIVE)
            ->where('origin_city_id', $from->id)->where('destination_city_id', $to->id)
            ->whereDate('trip_date', $departs->toDateString())->where('departure_time', $departs->format('H:i:s'))->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['departs_at' => __('نشرت هذه الرحلة بنفس الموعد من قبل.')]);
        }

        return TripSchedule::create([
            'business_id' => (int) $driver->id,
            'mode' => TripSchedule::MODE_LIMOUSINE,
            'scope' => TripSchedule::SCOPE_DOMESTIC,
            'origin_governorate_id' => (int) $from->governorate_id,
            'origin_city_id' => (int) $from->id,
            'destination_governorate_id' => (int) $to->governorate_id,
            'destination_city_id' => (int) $to->id,
            'schedule_pattern' => TripSchedule::PATTERN_ONE_OFF,
            'trip_date' => $departs->toDateString(),
            'departure_time' => $departs->format('H:i:s'),
            // the whole car is one «ride»: the first customer who books it fills it
            'capacity' => 1,
            'capacity_unit' => 'ride',
            'price' => $price,
            'currency' => 'EGP',
            'is_return_leg' => true,
            'vehicle_label' => isset($data['vehicle_label']) ? (trim((string) $data['vehicle_label']) ?: null) : null,
            'notes' => isset($data['notes']) ? (trim((string) $data['notes']) ?: null) : null,
            'status' => TripSchedule::STATUS_ACTIVE,
            'meta' => [
                'empty_return' => true,
                'reference_price' => $quote['reference_price'],
                'distance_km' => $quote['distance_km'],
                'seats' => max(1, min(30, (int) ($data['seats'] ?? 4))),
                'from_booking_id' => $fromBooking ? (int) $fromBooking->id : null,
            ],
        ]);
    }

    /**
     * The driver's own rides that are about to take him somewhere (or just did): each with the way back already worked
     * out — from where the customer was dropped to where he was picked up, the usual fare of it, the price a return
     * starts from and a time to leave (the end of the ride and a short rest, never in the past). A ride that already has
     * a way back published is not offered again.
     *
     * @return Collection<int, array<string,mixed>>
     */
    public function suggestions(User $driver): Collection
    {
        if (! $driver->isBusiness() || (int) $driver->category_child_id !== RideRequestKind::tradeId()) {
            return collect();
        }

        $linked = $this->linkedBookingIds((int) $driver->id);
        $window = (int) config('bim.rides.empty_return.window_hours', 72);

        return $this->rideBookings($driver)
            ->whereIn('status', [Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS, Booking::STATUS_COMPLETED])
            // a ride that ended a long time ago is no longer a car on its way home
            ->where('starts_at', '>=', now()->subHours(6))->where('starts_at', '<=', now()->addHours($window))
            ->orderBy('starts_at')->limit(20)->get()
            ->reject(fn (Booking $b) => $linked->contains((int) $b->id))
            ->map(function (Booking $b) use ($window) {
                $ride = (array) data_get($b->meta, 'ride', []);
                $origin = (array) ($ride['origin'] ?? []);
                $destination = (array) ($ride['destination'] ?? []);

                if (empty($origin['city_id']) || empty($destination['city_id'])) {
                    return null;
                }

                $back = $this->quote((int) $destination['city_id'], (int) $origin['city_id'], isset($ride['distance_km']) ? (float) $ride['distance_km'] : null);
                $km = (float) ($ride['distance_km'] ?? 0);
                $free = $b->starts_at->copy()->addMinutes((int) max(30, round($km / 50 * 60)) + 15);
                $leaves = $free->lt(now()->addMinutes(30)) ? now()->addMinutes(30) : $free;

                if ($leaves->gt(now()->addHours($window))) {
                    return null;
                }

                $places = DB::table('cities as c')->join('governorates as g', 'g.id', '=', 'c.governorate_id')
                    ->whereIn('c.id', [(int) $destination['city_id'], (int) $origin['city_id']])
                    ->get(['c.id', 'c.name_ar', 'c.name_en', 'c.governorate_id', 'g.country_id', 'g.name_ar as g_ar', 'g.name_en as g_en'])->keyBy('id');
                $place = function (int $cityId) use ($places) {
                    $p = $places->get($cityId);
                    $en = app()->getLocale() === 'en';

                    return $p ? [
                        'country_id' => (int) $p->country_id,
                        'governorate_id' => (int) $p->governorate_id,
                        'governorate' => $en ? ($p->g_en ?: $p->g_ar) : ($p->g_ar ?: $p->g_en),
                        'city_id' => (int) $p->id,
                        'city' => $en ? ($p->name_en ?: $p->name_ar) : ($p->name_ar ?: $p->name_en),
                    ] : null;
                };

                return [
                    'booking_id' => (int) $b->id,
                    'status' => (string) $b->status,
                    'ride_starts_at' => $b->starts_at->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    // the way back: from where he drops the customer to where he picked him up
                    'from' => $place((int) $destination['city_id']),
                    'to' => $place((int) $origin['city_id']),
                    'distance_km' => $back['distance_km'] ?? null,
                    'reference_price' => $back['reference_price'] ?? null,
                    'suggested_price' => $back['suggested_price'] ?? null,
                    'suggested_departs_at' => $leaves->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'passengers' => (int) ($ride['passengers'] ?? 1),
                ];
            })
            ->filter(fn ($s) => $s !== null && $s['from'] !== null && $s['to'] !== null)
            ->values();
    }

    /** The bookings the driver got from a customer's ride request. */
    private function rideBookings(User $driver)
    {
        return Booking::query()->where('business_id', (int) $driver->id)->where('meta->source', 'service_request')
            ->whereNotNull('meta->ride');
    }

    /** @return Collection<int,int> the rides of this driver that already have an active way back */
    private function linkedBookingIds(int $driverId): Collection
    {
        return $this->base()->where('business_id', $driverId)->where('status', TripSchedule::STATUS_ACTIVE)->get(['meta'])
            ->map(fn (TripSchedule $s) => (int) data_get($s->meta, 'from_booking_id'))->filter()->values();
    }

    /** The driver's own offers, upcoming first; the ones whose time has passed come after. */
    public function mine(User $driver): Collection
    {
        return $this->base()
            ->where('business_id', (int) $driver->id)
            ->with(['originCity:id,name_ar,name_en', 'destinationCity:id,name_ar,name_en'])
            ->orderByDesc('trip_date')->orderByDesc('departure_time')->limit(100)->get();
    }

    /**
     * What a customer going from one city to another sees: the empty returns of that governorate pair that are still
     * ahead, the exact city pair first, then the cheaper, then the driver the platform trusts more.
     *
     * @return Collection<int, array{schedule:TripSchedule, exact:bool, remaining:int, trust:array<string,mixed>}>
     */
    public function search(int $originCityId, int $destinationCityId, ?string $date = null): Collection
    {
        $cities = DB::table('cities')->whereIn('id', [$originCityId, $destinationCityId])->get(['id', 'governorate_id'])->keyBy('id');
        $from = $cities->get($originCityId);
        $to = $cities->get($destinationCityId);

        if (! $from || ! $to) {
            return collect();
        }

        $rows = $this->base()
            ->where('status', TripSchedule::STATUS_ACTIVE)
            ->where('origin_governorate_id', (int) $from->governorate_id)
            ->where('destination_governorate_id', (int) $to->governorate_id)
            ->where('business_id', '!=', (int) (auth()->id() ?? 0))
            // a driver on a break is not offered, whatever he published before
            ->whereHas('business', fn ($q) => $q->whereNull('requests_paused_at'))
            ->when($date, fn ($q) => $q->whereDate('trip_date', $date))
            ->whereDate('trip_date', '>=', now()->toDateString())
            ->with(['business:id,name,logo,image', 'originCity:id,name_ar,name_en', 'destinationCity:id,name_ar,name_en'])
            ->limit(100)->get()
            ->filter(fn (TripSchedule $s) => $this->isAhead($s));

        if ($rows->isEmpty()) {
            return collect();
        }

        $remaining = $this->reservations->remainingCapacityFor($rows->pluck('id')->map(fn ($id) => (int) $id)->all());
        $trust = $this->schedules->trustForBusinesses($rows->pluck('business_id')->map(fn ($id) => (int) $id)->unique()->all());

        return $rows
            ->map(fn (TripSchedule $s) => [
                'schedule' => $s,
                'exact' => (int) $s->origin_city_id === $originCityId && (int) $s->destination_city_id === $destinationCityId,
                'remaining' => (int) ($remaining[(int) $s->id] ?? 0),
                'trust' => $trust[(int) $s->business_id] ?? [],
            ])
            // a car somebody already booked is not offered again
            ->filter(fn (array $r) => $r['remaining'] > 0)
            ->sort(fn (array $a, array $b) => [$b['exact'], (float) $a['schedule']->price, -($a['trust']['stars_average'] ?? 0)]
                <=> [$a['exact'], (float) $b['schedule']->price, -($b['trust']['stars_average'] ?? 0)])
            ->values();
    }

    /** The driver takes his offer down — only while nobody holds it. */
    public function cancel(User $driver, int $scheduleId): TripSchedule
    {
        $leg = $this->base()->where('business_id', (int) $driver->id)->whereKey($scheduleId)->firstOrFail();

        $held = TripReservation::query()->where('trip_schedule_id', $leg->id)
            ->whereIn('status', [TripReservation::STATUS_PENDING, TripReservation::STATUS_CONFIRMED])->exists();

        if ($held) {
            throw ValidationException::withMessages(['schedule' => __('عليها حجز: ارفضه أو أكمله أولًا.')]);
        }

        $leg->update(['status' => TripSchedule::STATUS_CANCELLED]);

        return $leg;
    }

    /** Is the departure still ahead (with a short grace, so a driver who is a little late is still bookable)? */
    public function isAhead(TripSchedule $schedule): bool
    {
        if (! $schedule->trip_date) {
            return false;
        }

        $departs = Carbon::parse($schedule->trip_date->toDateString() . ' ' . ($schedule->departure_time ?: '23:59:59'));

        return $departs->copy()->addMinutes((int) config('bim.rides.empty_return.grace_minutes', 30))->gte(now());
    }

    public function departsAt(TripSchedule $schedule): ?Carbon
    {
        if (! $schedule->trip_date) {
            return null;
        }

        return Carbon::parse($schedule->trip_date->toDateString() . ' ' . ($schedule->departure_time ?: '00:00:00'));
    }

    private function base()
    {
        return TripSchedule::query()->where('mode', TripSchedule::MODE_LIMOUSINE)->where('is_return_leg', true)
            ->where('meta->empty_return', true);
    }
}
