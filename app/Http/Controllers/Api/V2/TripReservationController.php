<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Services\Schedules\TripReservationService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * Reservations on trip legs. The customer side (reserve / list-own / cancel) is
 * open to any authenticated user; the carrier side (incoming / confirm /
 * complete / reject) is business-gated at the route level.
 */
final class TripReservationController extends Controller
{
    // ---- Customer side --------------------------------------------------

    public function reserve(Request $request, int $schedule, TripReservationService $service)
    {
        $data = $request->validate([
            'units' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // short of the deposit and a friend will stand for him: the reservation goes through, the friend is invited next
            'friend_guarantee' => ['nullable', 'boolean'],
        ]);

        $leg = TripSchedule::query()->findOrFail($schedule);

        $reservation = $service->reserve(
            client: $request->user(),
            schedule: $leg,
            units: (int) ($data['units'] ?? 1),
            notes: $data['notes'] ?? null,
            friendGuarantee: (bool) ($data['friend_guarantee'] ?? false)
        );

        return response()->json([
            'success' => true,
            'message' => __('تم إنشاء الحجز، بانتظار تأكيد الناقل.'),
            'data' => ['reservation' => $this->serialize($reservation)],
        ], 201);
    }

    public function myReservations(Request $request)
    {
        $reservations = TripReservation::query()
            ->where('client_id', (int) $request->user()->id)
            ->with(array_merge(self::LEG_RELATIONS, ['business:id,name,logo']))
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $reservations->getCollection()->transform(fn (TripReservation $r) => $this->serialize($r));

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    /** POST schedules/reservations/{reservation}/attendance — the customer says he is there: always first. */
    public function confirmAttendance(Request $request, int $reservation, TripReservationService $service)
    {
        $row = TripReservation::query()
            ->where('id', $reservation)
            ->where('client_id', (int) $request->user()->id)
            ->firstOrFail();

        $service->confirmAttendanceByClient($row);

        return response()->json([
            'success' => true,
            'message' => __('تم تأكيد حضورك.'),
            'data' => ['reservation' => $this->serialize($row->refresh())],
        ]);
    }

    public function cancel(Request $request, int $reservation, TripReservationService $service)
    {
        $row = TripReservation::query()
            ->where('id', $reservation)
            ->where('client_id', (int) $request->user()->id)
            ->firstOrFail();

        $service->cancel($row, (int) $request->user()->id);

        return response()->json(['success' => true, 'message' => __('تم إلغاء الحجز.')]);
    }

    // ---- Carrier (business) side ---------------------------------------

    /**
     * Carrier blocks capacity for an off-app deal (seats sold directly to a
     * customer), so remaining_capacity stays accurate. Release via /reject.
     */
    public function block(Request $request, int $schedule, TripReservationService $service)
    {
        $data = $request->validate([
            'units' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // the off-app passenger is a person with a name — never a nameless hold
            'passenger_name' => ['required', 'string', 'max:150'],
            'passenger_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $leg = TripSchedule::query()
            ->where('id', $schedule)
            ->where('business_id', BusinessContext::id($request))
            ->firstOrFail();

        $hold = $service->blockOffline(
            $leg,
            (int) $data['units'],
            $data['notes'] ?? null,
            $data['passenger_name'],
            $data['passenger_phone'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => __('تم حجز المقاعد يدويًا (تعامل خارج التطبيق).'),
            'data' => ['reservation' => $this->serialize($hold, true)],
        ], 201);
    }

    public function incoming(Request $request)
    {
        $status = trim((string) $request->get('status', ''));

        $query = TripReservation::query()
            ->where('business_id', BusinessContext::id($request))
            ->with(array_merge(self::LEG_RELATIONS, ['client:id,name,logo']))
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $reservations = $query->paginate((int) $request->get('per_page', 20));
        $reservations->getCollection()->transform(fn (TripReservation $r) => $this->serialize($r, true));

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    public function confirm(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);
        $service->confirm($row);

        return response()->json([
            'success' => true,
            'message' => __('تم تأكيد الحجز.'),
            'data' => ['reservation' => $this->serialize($row->refresh(), true)],
        ]);
    }

    public function complete(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);

        if ($row->source === TripReservation::SOURCE_OFFLINE) {
            $service->completeOffline($row);
        } else {
            $service->complete($row);
        }

        return response()->json([
            'success' => true,
            'message' => __('تم إكمال الرحلة وتسجيل التقييم للطرفين.'),
            'data' => ['reservation' => $this->serialize($row->refresh(), true)],
        ]);
    }

    /** POST business/schedules/reservations/{reservation}/attendance — the carrier confirms, after the customer did. */
    public function carrierAttendance(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);
        $service->confirmAttendanceByCarrier($row);

        return response()->json([
            'success' => true,
            'message' => __('تم تأكيد حضور الراكب.'),
            'data' => ['reservation' => $this->serialize($row->refresh(), true)],
        ]);
    }

    /**
     * POST business/schedules/reservations/{reservation}/rating-visibility — an off-app passenger appears in the
     * ratings and the completed/pending counts once the (same) fee is paid, taken from the driver's wallet.
     */
    public function ratingVisibility(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);
        $service->makeRatingVisible($row);

        return response()->json([
            'success' => true,
            'message' => __('ظهر الراكب في التقييم وخُصمت الرسوم من محفظتك.'),
            'data' => ['reservation' => $this->serialize($row->refresh(), true)],
        ]);
    }

    /** POST business/schedules/reservations/{reservation}/no-show — «لم يحضر»: the deposit stays held. */
    public function noShow(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);
        $service->noShow($row);

        return response()->json([
            'success' => true,
            'message' => __('سُجّل عدم الحضور، ويبقى العربون مجمّدًا حتى تعيده أو يُفتح نزاع.'),
            'data' => ['reservation' => $this->serialize($row->refresh(), true)],
        ]);
    }

    public function reject(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);
        $service->cancel($row, BusinessContext::id($request));

        return response()->json(['success' => true, 'message' => __('تم رفض/إلغاء الحجز.')]);
    }

    private function carrierReservationOrFail(Request $request, int $reservationId): TripReservation
    {
        return TripReservation::query()
            ->where('id', $reservationId)
            ->where('business_id', BusinessContext::id($request))
            ->firstOrFail();
    }

    /** $carrierView: only the carrier is told WHO the passenger is — other passengers and people searching never see names. */
    /** What a reservation needs of its leg to say where, when and with whom. */
    private const LEG_RELATIONS = [
        'schedule:id,mode,is_return_leg,origin_governorate_id,origin_city_id,destination_governorate_id,destination_city_id,schedule_pattern,day_of_week,trip_date,departure_time,deposit_per_unit,vehicle_label',
        'schedule.originGovernorate:id,name_ar,name_en',
        'schedule.destinationGovernorate:id,name_ar,name_en',
        'schedule.originCity:id,name_ar,name_en',
        'schedule.destinationCity:id,name_ar,name_en',
    ];

    /** @return array<string,mixed>|null the leg the reservation is on, named for the screen */
    private function leg(TripReservation $r): ?array
    {
        $s = $r->relationLoaded('schedule') ? $r->schedule : null;

        if (! $s) {
            return null;
        }

        return [
            'id' => (int) $s->id,
            'mode' => (string) $s->mode,
            'is_return_leg' => (bool) $s->is_return_leg,
            'origin' => ['governorate' => $s->originGovernorate?->loc('name'), 'city' => $s->originCity?->loc('name')],
            'destination' => ['governorate' => $s->destinationGovernorate?->loc('name'), 'city' => $s->destinationCity?->loc('name')],
            'pattern' => (string) $s->schedule_pattern,
            'day_of_week' => $s->day_of_week !== null ? (int) $s->day_of_week : null,
            'trip_date' => optional($s->trip_date)->toDateString(),
            // wall-clock digits (Cairo), like every time of day the API returns
            'departure_time' => $s->departure_time,
            'vehicle_label' => $s->vehicle_label,
        ];
    }

    private function serialize(TripReservation $r, bool $carrierView = false): array
    {
        return [
            'leg' => $this->leg($r),
            'business' => $r->relationLoaded('business') && $r->business ? ['id' => (int) $r->business->id, 'name' => $r->business->name, 'logo' => $r->business->logo] : null,
            'id' => (int) $r->id,
            'trip_schedule_id' => (int) $r->trip_schedule_id,
            'business_id' => (int) $r->business_id,
            'client_id' => $r->client_id ? (int) $r->client_id : null,
            'client_name' => $carrierView && $r->client_id ? $r->loadMissing('client:id,name')->client?->name : null,
            'passenger_name' => $r->passenger_name,
            'passenger_phone' => $r->passenger_phone,
            'source' => (string) $r->source,
            'rating_visible' => (bool) $r->rating_visible,
            'rating_fee' => $r->rating_fee !== null ? (float) $r->rating_fee : null,
            'client_confirmed_at' => optional($r->client_confirmed_at)->toIso8601String(),
            'carrier_confirmed_at' => optional($r->carrier_confirmed_at)->toIso8601String(),
            'units' => (int) $r->units,
            'unit_price' => $r->unit_price !== null ? (float) $r->unit_price : null,
            'total_price' => $r->total_price !== null ? (float) $r->total_price : null,
            'deposit_held' => (float) $r->deposit_held,
            // «عربون الجدية»: quoted until the carrier confirms, then held / returned / disputed
            'deposit' => app(\App\Services\VisitDepositService::class)->stateFor($r),
            'currency' => (string) $r->currency,
            'status' => (string) $r->status,
            'notes' => $r->notes,
            'created_at' => optional($r->created_at)->toIso8601String(),
        ];
    }
}
