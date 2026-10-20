<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BusinessServicePrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «بالدور» — an appointment taken by turn instead of by a chosen time. The customer names the service and the day and
 * is given the next number of that day and the time it should come to, worked out from the day's opening, the length
 * of each service, and what the business already holds. The estimate moves as the queue does: a turn ahead that is
 * cancelled or done pulls everyone behind it forward.
 *
 * One at a time: the business has not said how many it serves together.
 */
final class AppointmentQueue
{
    public const FLOW_SLOTS = 'slots';

    public const FLOW_QUEUE = 'queue';

    /** What the business says it does today; anything else is the pattern's own — by appointment. */
    public static function flowOf(int $businessId): string
    {
        $flow = DB::table('business_booking_settings')->where('business_id', $businessId)->value('booking_flow');

        return $flow === self::FLOW_QUEUE ? self::FLOW_QUEUE : self::FLOW_SLOTS;
    }

    /** How long a service takes: its own row, else the business's slot setting, else half an hour. */
    public static function serviceMinutes(int $businessId, ?int $offeringId): int
    {
        $minutes = 0;

        if ($offeringId) {
            $minutes = (int) BusinessServicePrice::query()
                ->where('business_id', $businessId)->whereKey($offeringId)->value('duration_minutes');
        }

        if ($minutes <= 0) {
            $minutes = (int) DB::table('business_booking_settings')->where('business_id', $businessId)->value('slot_minutes');
        }

        return $minutes > 0 ? min(max($minutes, 5), 480) : 30;
    }

    /**
     * The business's opening window on a day, in its own time zone.
     *
     * @return array{known:bool,closed:bool,open:string,close:string}
     */
    public static function window(int $businessId, Carbon $day): array
    {
        $hours = app(BusinessHoursService::class);
        $all = $hours->hoursFor($businessId);
        $row = $all->get($day->dayOfWeek);
        $known = $all->isNotEmpty() && $row !== null;
        $closed = $known && ($row->is_closed || ! $row->open_time || ! $row->close_time);

        return [
            'known' => $known && ! $closed,
            'closed' => $closed,
            'open' => $known && ! $closed ? (string) $row->open_time : '09:00:00',
            'close' => $known && ! $closed ? (string) $row->close_time : '21:00:00',
        ];
    }

    /** The chairs a business takes bookings on (active, in the order it set); empty = one line, as it always was. */
    public static function chairsOf(int $businessId)
    {
        return \App\Models\ServiceChair::query()->where('business_id', $businessId)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * The next turn of a day: its number, when it should come, and how many are ahead. With chairs, each chair is a
     * line of its own: the customer's chair, or — when he has no preference — the one that can take him soonest.
     *
     * @return array{full:bool,closed:bool,turn:int,starts_at:?Carbon,ends_at:?Carbon,ahead:int,duration:int,chair_id:?int}
     */
    public function next(int $businessId, Carbon $day, int $minutes, ?int $chairId = null, bool $ignoreLead = false): array
    {
        $chairs = self::chairsOf($businessId);

        if ($chairs->isEmpty()) {
            return $this->nextOnLine($businessId, $day, $minutes, null, $ignoreLead) + ['chair_id' => null];
        }

        $candidates = $chairId ? $chairs->where('id', $chairId) : $chairs;
        $best = null;

        foreach ($candidates as $chair) {
            $one = $this->nextOnLine($businessId, $day, $minutes, (int) $chair->id, $ignoreLead) + ['chair_id' => (int) $chair->id];

            if ($one['full']) {
                $best ??= $one;

                continue;
            }

            if ($best === null || $best['full'] || $one['starts_at']->lt($best['starts_at'])) {
                $best = $one;
            }
        }

        return $best ?? ['full' => true, 'closed' => false, 'turn' => 1, 'starts_at' => null, 'ends_at' => null, 'ahead' => 0, 'duration' => $minutes, 'chair_id' => null];
    }

    /** @return array{full:bool,closed:bool,turn:int,starts_at:?Carbon,ends_at:?Carbon,ahead:int,duration:int} */
    private function nextOnLine(int $businessId, Carbon $day, int $minutes, ?int $chairId, bool $ignoreLead = false): array
    {
        $tz = app(BusinessHoursService::class)->timezoneFor($businessId);
        $day = $day->copy()->setTimezone($tz)->startOfDay();
        $now = Carbon::now($tz);
        $window = self::window($businessId, $day);

        $out = ['full' => false, 'closed' => $window['closed'], 'turn' => 1, 'starts_at' => null, 'ends_at' => null, 'ahead' => 0, 'duration' => $minutes];

        $turnOf = (int) Booking::query()->where('business_id', $businessId)
            ->whereDate('starts_at', $day->toDateString())->max('queue_turn');
        $out['turn'] = $turnOf + 1;

        if ($window['closed']) {
            $out['full'] = true;

            return $out;
        }

        $from = $day->copy()->setTimeFromTimeString($window['open']);
        $until = $day->copy()->setTimeFromTimeString($window['close']);
        if ($until->lte($from)) {
            $until->addDay();
        }

        // a customer already at the door needs no notice period
        $lead = $ignoreLead ? 0 : max((int) DB::table('business_booking_settings')->where('business_id', $businessId)->value('lead_time_minutes'), 0);
        $cursor = $from->copy();
        if ($day->isSameDay($now) && $now->copy()->addMinutes($lead)->gt($cursor)) {
            $cursor = $now->copy()->addMinutes($lead);
            // up to the next five minutes — «٦:٣٧» is not a time anyone is told
            $cursor->setSecond(0)->addMinutes((5 - ($cursor->minute % 5)) % 5);
        }

        $held = $this->heldOn($businessId, $day, $until, $chairId);

        // the earliest gap the service fits in, after what is already held
        foreach ($held as [$start, $end]) {
            if ($cursor->copy()->addMinutes($minutes)->lte($start)) {
                break;
            }
            if ($end->gt($cursor)) {
                $cursor = $end->copy();
            }
        }

        $end = $cursor->copy()->addMinutes($minutes);

        if ($end->gt($until)) {
            $out['full'] = true;

            return $out;
        }

        $out['starts_at'] = $cursor;
        $out['ends_at'] = $end;
        $out['ahead'] = $held->filter(fn ($h) => $h[0]->lt($cursor))->count();

        return $out;
    }

    /**
     * Where a booking stands in its day's queue right now — null when it has no turn or is no longer waiting.
     *
     * @return array{turn:int,ahead:int,expected_at:string}|null
     */
    public function estimate(Booking $booking): ?array
    {
        if ($booking->queue_turn === null || ! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED], true) || ! $booking->starts_at) {
            return null;
        }

        $businessId = (int) $booking->business_id;
        $tz = app(BusinessHoursService::class)->timezoneFor($businessId);
        $day = Carbon::parse($booking->starts_at, $tz)->startOfDay();
        $now = Carbon::now($tz);

        if ($day->lt($now->copy()->startOfDay())) {
            return null;
        }

        $window = self::window($businessId, $day);
        $cursor = $day->copy()->setTimeFromTimeString($window['open']);
        if ($day->isSameDay($now) && $now->gt($cursor)) {
            $cursor = $now->copy();
        }

        // the turns ahead that still wait hold the time before this one
        $ahead = Booking::query()
            ->where('business_id', $businessId)
            ->whereDate('starts_at', $day->toDateString())
            ->whereNotNull('queue_turn')->where('queue_turn', '<', $booking->queue_turn)
            ->when($booking->chair_id !== null, fn ($q) => $q->where('chair_id', $booking->chair_id))
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])
            ->get(['starts_at', 'ends_at']);

        $minutes = 0;
        foreach ($ahead as $a) {
            $minutes += $a->ends_at && $a->starts_at ? max((int) $a->starts_at->diffInMinutes($a->ends_at), 5) : 30;
        }

        return [
            'turn' => (int) $booking->queue_turn,
            'ahead' => $ahead->count(),
            'expected_at' => $cursor->copy()->addMinutes($minutes)->format('Y-m-d H:i:s'),
        ];
    }

    /** @return \Illuminate\Support\Collection<int,array{0:Carbon,1:Carbon}> sorted by start */
    public function heldOn(int $businessId, Carbon $day, Carbon $until, ?int $chairId = null, ?int $providerId = null)
    {
        return Booking::query()
            ->where('business_id', $businessId)
            ->when($chairId !== null, fn ($q) => $q->where('chair_id', $chairId))
            // «اختر مقدّم الخدمة»: only what that person already has
            ->when($providerId !== null, fn ($q) => $q->assignedTo($providerId))
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED, Booking::STATUS_COMPLETED])
            ->whereNotNull('starts_at')
            ->where('starts_at', '<', $until->format('Y-m-d H:i:s'))
            ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 30 MINUTE)) > ?', [$day->format('Y-m-d H:i:s')])
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at'])
            // a booking's times are the shop's wall clock: read them back in the SAME zone as the day being judged,
            // or a business that is not on UTC would see its own bookings three hours away from the slots
            ->map(function ($b) use ($day) {
                $zone = $day->getTimezone();
                $start = Carbon::createFromFormat('Y-m-d H:i:s', (string) $b->getRawOriginal('starts_at'), $zone);
                $rawEnd = $b->getRawOriginal('ends_at');

                return [
                    $start,
                    $rawEnd ? Carbon::createFromFormat('Y-m-d H:i:s', (string) $rawEnd, $zone) : $start->copy()->addMinutes(30),
                ];
            });
    }
}
