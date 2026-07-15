<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Services\Schedules\TripReservationService;
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
        ]);

        $leg = TripSchedule::query()->findOrFail($schedule);

        $reservation = $service->reserve(
            client: $request->user(),
            schedule: $leg,
            units: (int) ($data['units'] ?? 1),
            notes: $data['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الحجز، بانتظار تأكيد الناقل.',
            'data' => ['reservation' => $this->serialize($reservation)],
        ], 201);
    }

    public function myReservations(Request $request)
    {
        $reservations = TripReservation::query()
            ->where('client_id', (int) $request->user()->id)
            ->with(['schedule:id,mode,origin_governorate_id,destination_governorate_id,day_of_week,departure_time', 'business:id,name,logo'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $reservations->getCollection()->transform(fn (TripReservation $r) => $this->serialize($r));

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    public function cancel(Request $request, int $reservation, TripReservationService $service)
    {
        $row = TripReservation::query()
            ->where('id', $reservation)
            ->where('client_id', (int) $request->user()->id)
            ->firstOrFail();

        $service->cancel($row);

        return response()->json(['success' => true, 'message' => 'تم إلغاء الحجز.']);
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
        ]);

        $leg = TripSchedule::query()
            ->where('id', $schedule)
            ->where('business_id', (int) $request->user()->id)
            ->firstOrFail();

        $hold = $service->blockOffline($leg, (int) $data['units'], $data['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'تم حجز المقاعد يدويًا (تعامل خارج التطبيق).',
            'data' => ['reservation' => $this->serialize($hold)],
        ], 201);
    }

    public function incoming(Request $request)
    {
        $status = trim((string) $request->get('status', ''));

        $query = TripReservation::query()
            ->where('business_id', (int) $request->user()->id)
            ->with(['schedule:id,mode,origin_governorate_id,destination_governorate_id,day_of_week,departure_time', 'client:id,name,logo'])
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $reservations = $query->paginate((int) $request->get('per_page', 20));
        $reservations->getCollection()->transform(fn (TripReservation $r) => $this->serialize($r));

        return response()->json(['success' => true, 'data' => $reservations]);
    }

    public function confirm(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);
        $service->confirm($row);

        return response()->json([
            'success' => true,
            'message' => 'تم تأكيد الحجز.',
            'data' => ['reservation' => $this->serialize($row->refresh())],
        ]);
    }

    public function complete(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);
        $service->complete($row);

        return response()->json([
            'success' => true,
            'message' => 'تم إكمال الرحلة وتسجيل التقييم للطرفين.',
            'data' => ['reservation' => $this->serialize($row->refresh())],
        ]);
    }

    public function reject(Request $request, int $reservation, TripReservationService $service)
    {
        $row = $this->carrierReservationOrFail($request, $reservation);
        $service->cancel($row);

        return response()->json(['success' => true, 'message' => 'تم رفض/إلغاء الحجز.']);
    }

    private function carrierReservationOrFail(Request $request, int $reservationId): TripReservation
    {
        return TripReservation::query()
            ->where('id', $reservationId)
            ->where('business_id', (int) $request->user()->id)
            ->firstOrFail();
    }

    private function serialize(TripReservation $r): array
    {
        return [
            'id' => (int) $r->id,
            'trip_schedule_id' => (int) $r->trip_schedule_id,
            'business_id' => (int) $r->business_id,
            'client_id' => $r->client_id ? (int) $r->client_id : null,
            'source' => (string) $r->source,
            'units' => (int) $r->units,
            'unit_price' => $r->unit_price !== null ? (float) $r->unit_price : null,
            'total_price' => $r->total_price !== null ? (float) $r->total_price : null,
            'currency' => (string) $r->currency,
            'status' => (string) $r->status,
            'notes' => $r->notes,
            'created_at' => optional($r->created_at)->toIso8601String(),
        ];
    }
}
