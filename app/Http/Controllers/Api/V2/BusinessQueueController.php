<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ServiceChair;
use App\Services\AppointmentQueue;
use App\Services\BusinessHoursService;
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
    /** GET /api/v2/business/queue?date= — the live bookings of a day (today by default), by chair then by time. */
    public function index(Request $request)
    {
        $business = BusinessContext::id($request);
        $tz = app(BusinessHoursService::class)->timezoneFor($business);
        $day = $request->filled('date')
            ? Carbon::createFromFormat('Y-m-d', (string) $request->input('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $rows = Booking::query()
            ->with(['user:id,name,phone'])
            ->where('business_id', $business)
            ->whereDate('starts_at', $day->toDateString())
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_ACCEPTED, Booking::STATUS_IN_PROGRESS])
            ->orderBy('starts_at')->orderBy('id')
            ->get();

        $chairs = ServiceChair::query()->where('business_id', $business)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['success' => true, 'data' => [
            'date' => $day->toDateString(),
            'flow' => AppointmentQueue::flowOf($business),
            'chairs' => $chairs->map->payload()->values(),
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

        $booking = DB::transaction(function () use ($business, $data, $price, $day, $minutes, $tz) {
            \App\Models\User::query()->whereKey($business)->lockForUpdate()->first(['id']);

            $next = app(AppointmentQueue::class)->next($business, $day, $minutes, ! empty($data['chair_id']) ? (int) $data['chair_id'] : null, true);

            abort_if($next['full'], 422, $next['closed'] ? __('النشاط مغلق في هذا اليوم.') : __('اكتمل دور هذا اليوم.'));

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
        abort_unless((int) $booking->business_id === (int) BusinessContext::id($request), 403, 'Booking does not belong to this business account.');

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
        abort_unless((int) $booking->business_id === (int) BusinessContext::id($request), 403, 'Booking does not belong to this business account.');

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
        abort_unless((int) $booking->business_id === (int) BusinessContext::id($request), 403, 'Booking does not belong to this business account.');

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
