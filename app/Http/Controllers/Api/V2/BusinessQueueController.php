<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ServiceChair;
use App\Services\AppointmentQueue;
use App\Services\BusinessHoursService;
use App\Support\BusinessCapability;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «نداء الدور التالي»: the day's queue as the business runs it — who is being served, who waits, and the call that
 * tells the next customer it is their turn.
 */
final class BusinessQueueController extends Controller
{
    /**
     * Who may run this queue, and from which chairs. A staff member who sits at a chair runs that chair's line and no
     * other — and the chair itself is the grant (no `bookings` capability is needed for it); any other staff member
     * needs the capability; the owner runs it all. Null = the whole day, a list = only these chairs.
     *
     * @return list<int>|null
     */
    private function lineOf(Request $request): ?array
    {
        $own = ServiceChair::restrictedTo($request);

        if ($own === null && ! BusinessContext::isOwner($request)) {
            abort_unless(in_array(BusinessCapability::BOOKINGS, BusinessContext::capabilities($request), true), 403, __('لا تملك صلاحية تنفيذ هذا الإجراء.'));
        }

        return $own;
    }

    /** A turn belongs to this business, and — for a barber — to a chair of his own. */
    private function mayServe(Request $request, Booking $booking): void
    {
        abort_unless((int) $booking->business_id === (int) BusinessContext::id($request), 403, 'Booking does not belong to this business account.');

        $own = $this->lineOf($request);

        abort_if($own !== null && ! in_array((int) $booking->chair_id, $own, true), 403, __('هذا الدور على كرسيّ آخر.'));
    }

    /**
     * GET /api/v2/business/queue?date=&mine= — the live bookings of a day (today by default), by chair then by time.
     * `mine=1` is «كرسيي»: only the chairs this account sits at (a staff member who sits at one gets that always).
     */
    public function index(Request $request)
    {
        $business = BusinessContext::id($request);
        $scope = $this->lineOf($request);

        if ($scope === null && $request->boolean('mine')) {
            $scope = ServiceChair::idsOf($business, (int) $request->user()->id);
        }
        $tz = app(BusinessHoursService::class)->timezoneFor($business);
        $day = $request->filled('date')
            ? Carbon::createFromFormat('Y-m-d', (string) $request->input('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $rows = Booking::query()
            ->with(['user:id,name,phone'])
            ->where('business_id', $business)
            ->whereDate('starts_at', $day->toDateString())
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])
            ->when($scope !== null, fn ($q) => $q->whereIn('chair_id', $scope))
            ->orderBy('starts_at')->orderBy('id')
            ->get();

        $chairs = ServiceChair::query()->with('staff:id,name,logo')->where('business_id', $business)->where('is_active', true)
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))
            ->orderBy('sort_order')->orderBy('id')->get();

        $served = Booking::query()->where('business_id', $business)->whereDate('starts_at', $day->toDateString())
            ->where('status', Booking::STATUS_COMPLETED)
            ->when($scope !== null, fn ($q) => $q->whereIn('chair_id', $scope))
            ->count();

        return response()->json(['success' => true, 'data' => [
            'date' => $day->toDateString(),
            'flow' => AppointmentQueue::flowOf($business),
            'chairs' => $chairs->map->payload()->values(),
            'served' => $served,
            'items' => $rows->map(fn (Booking $b) => [
                'id' => (int) $b->id,
                'turn' => $b->queue_turn !== null ? (int) $b->queue_turn : null,
                'status' => (string) $b->status,
                'called_at' => $b->called_at?->toIso8601String(),
                'starts_at' => $b->starts_at?->format('Y-m-d H:i:s'),
                'ends_at' => $b->ends_at?->format('Y-m-d H:i:s'),
                'chair_id' => $b->chair_id ? (int) $b->chair_id : null,
                'walk_in' => (bool) data_get($b->meta, 'walk_in'),
                // a customer with no booking: the name the staff wrote, not the account of the business itself
                'customer' => data_get($b->meta, 'walk_in')
                    ? ['id' => null, 'name' => data_get($b->meta, 'walk_in.name'), 'phone' => data_get($b->meta, 'walk_in.phone')]
                    : ($b->user ? ['id' => (int) $b->user->id, 'name' => $b->user->name, 'phone' => $b->user->phone] : null),
                'title' => $b->title(),
                'notes' => $b->notes,
            ])->values(),
        ]]);
    }

    /**
     * POST /api/v2/business/queue/walk-in — «جاء عميل بدون حجز»: the staff add a turn for him. No account is needed: the
     * name (and a phone) is written on the turn. He joins the end of the line — the chosen chair's, or the one that can
     * take him soonest — with no notice period, since he is already there.
     */
    public function walkIn(Request $request)
    {
        $business = BusinessContext::id($request);
        $own = $this->lineOf($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'offering_id' => ['required', 'integer'],
            'chair_id' => ['nullable', 'integer'],
        ]);

        $price = \App\Models\BusinessServicePrice::query()->where('business_id', $business)->where('is_active', 1)->find((int) $data['offering_id']);
        abort_unless($price, 422, __('اختر خدمة من خدمات النشاط.'));

        $tz = app(BusinessHoursService::class)->timezoneFor($business);
        $day = Carbon::now($tz)->startOfDay();
        $minutes = AppointmentQueue::serviceMinutes($business, (int) $price->id);

        $wanted = ! empty($data['chair_id']) ? (int) $data['chair_id'] : null;

        // a barber's walk-in sits at one of his own chairs: the one he named, else whichever of his can take it soonest
        abort_if($own !== null && $wanted !== null && ! in_array($wanted, $own, true), 403, __('هذا الدور على كرسيّ آخر.'));
        $candidates = $own === null ? [$wanted] : ($wanted !== null ? [$wanted] : $own);

        $booking = DB::transaction(function () use ($business, $price, $day, $minutes, $tz, $candidates, $data) {
            \App\Models\User::query()->whereKey($business)->lockForUpdate()->first(['id']);

            $next = null;
            $refused = null;

            foreach ($candidates as $candidate) {
                $try = app(AppointmentQueue::class)->next($business, $day, $minutes, $candidate, true);

                if ($try['full']) {
                    $refused = $try;

                    continue;
                }

                if ($next === null || $try['starts_at']->lt($next['starts_at'])) {
                    $next = $try;
                }
            }

            abort_if($next === null, 422, ($refused['closed'] ?? false) ? __('النشاط مغلق في هذا اليوم.') : __('اكتمل دور هذا اليوم.'));

            return Booking::query()->create([
                // the account of the business itself stands in for a customer with none; the name is on the turn
                'user_id' => $business,
                'business_id' => $business,
                'service_id' => (int) $price->service_id,
                'offering_type' => $price->getMorphClass(),
                'offering_id' => (int) $price->id,
                'date' => $next['starts_at']->toDateString(),
                'time' => $next['starts_at']->format('H:i:s'),
                'starts_at' => $next['starts_at']->format('Y-m-d H:i:s'),
                'ends_at' => $next['ends_at']->format('Y-m-d H:i:s'),
                'all_day' => false,
                'timezone' => $tz,
                'quantity' => 1,
                'status' => Booking::STATUS_ACCEPTED,
                'price' => (float) $price->price,
                'queue_turn' => (int) $next['turn'],
                'chair_id' => $next['chair_id'],
                'meta' => ['source' => 'walk_in', 'walk_in' => ['name' => trim($data['name']), 'phone' => $data['phone'] ?? null]],
            ]);
        });

        return response()->json(['success' => true, 'data' => [
            'id' => (int) $booking->id,
            'turn' => (int) $booking->queue_turn,
            'chair_id' => $booking->chair_id ? (int) $booking->chair_id : null,
            'starts_at' => $booking->starts_at?->format('Y-m-d H:i:s'),
        ]], 201);
    }

    /**
     * POST /api/v2/business/queue/{booking}/start — the customer sits down. A walk-in turn moves on by itself; an
     * online booking goes through the booking engine as it always did (deposit, fees).
     */
    public function start(Request $request, Booking $booking)
    {
        $this->mayServe($request, $booking);

        if (data_get($booking->meta, 'walk_in')) {
            if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED], true)) {
                return response()->json(['success' => false, 'message' => __('لا يمكن بدء هذا الدور الآن.')], 422);
            }

            $booking->forceFill(['status' => Booking::STATUS_IN_PROGRESS, 'called_at' => $booking->called_at ?? now()])->save();

            return response()->json(['success' => true, 'data' => ['id' => (int) $booking->id, 'status' => $booking->status]]);
        }

        return app(BookingController::class)->start($request, $booking);
    }

    /** POST /api/v2/business/queue/{booking}/finish — the service is done; the line moves up. */
    public function finish(Request $request, Booking $booking)
    {
        $this->mayServe($request, $booking);

        if (data_get($booking->meta, 'walk_in')) {
            if ($booking->status !== Booking::STATUS_IN_PROGRESS) {
                return response()->json(['success' => false, 'message' => __('ابدأ الدور أولًا.')], 422);
            }

            $booking->forceFill(['status' => Booking::STATUS_COMPLETED])->save();

            return response()->json(['success' => true, 'data' => ['id' => (int) $booking->id, 'status' => $booking->status]]);
        }

        return app(BookingController::class)->complete($request, $booking);
    }

    /**
     * POST /api/v2/business/queue/{booking}/call — «نادِ التالي»: the customer is told it is their turn. A booking still
     * waiting for approval is accepted by the call (a turn taken at the door needs no second tap).
     */
    public function call(Request $request, Booking $booking)
    {
        $this->mayServe($request, $booking);

        if (! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED], true)) {
            return response()->json(['success' => false, 'message' => __('لا يمكن نداء هذا الدور الآن.')], 422);
        }

        if ($booking->status === Booking::STATUS_PENDING) {
            app(BookingController::class)->accept($request, $booking);
            $booking->refresh();
        }

        $booking->forceFill(['called_at' => now()])->save();

        try {
            app(\App\Services\Notifications\NotificationDispatcherService::class)->dispatch('booking.turn_called', (int) $booking->user_id, [
                'title_ar' => 'حان دورك',
                'title_en' => "It's your turn",
                'body_ar' => 'تفضّل — ناداك النشاط الآن.',
                'body_en' => 'Please come in — you have been called.',
                'notifiable_type' => Booking::class,
                'notifiable_id' => (int) $booking->id,
                'source_id' => (int) $booking->id,
            ]);
        } catch (\Throwable $e) {
            report($e); // a missed notice must never undo the call
        }

        return response()->json(['success' => true, 'data' => ['id' => (int) $booking->id, 'called_at' => $booking->called_at?->toIso8601String()]]);
    }
}
