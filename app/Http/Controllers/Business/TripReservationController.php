<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use App\Services\Schedules\TripReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The carrier's reservation desk: who booked which leg, and the actions on
 * them (confirm → complete, or reject). Also the offline hold — seats sold in
 * a direct deal off the app, blocked here so the leg's remaining capacity
 * stays honest. All state changes go through TripReservationService, which
 * owns capacity, deposits, ratings and notifications.
 */
class TripReservationController extends Controller
{
    public function __construct(private readonly TripReservationService $service) {}

    private function businessId(): int
    {
        return (int) Auth::id();
    }

    private function scopedReservation(int $id): TripReservation
    {
        return TripReservation::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    private function scopedLeg(int $id): TripSchedule
    {
        return TripSchedule::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $status = trim((string) $request->get('status', ''));
        $scheduleId = (int) $request->get('trip_schedule_id', 0);

        $rows = TripReservation::query()
            ->where('business_id', $this->businessId())
            ->with([
                'client:id,name,phone',
                'schedule:id,mode,scope,origin_governorate_id,destination_governorate_id,day_of_week,departure_time,capacity_unit',
                'schedule.originGovernorate:id,name_ar',
                'schedule.destinationGovernorate:id,name_ar',
            ])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($scheduleId > 0, fn ($query) => $query->where('trip_schedule_id', $scheduleId))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('business.schedules.reservations', [
            'rows' => $rows,
            'status' => $status,
            'scheduleId' => $scheduleId,
            'legs' => TripSchedule::query()
                ->where('business_id', $this->businessId())
                ->with(['originGovernorate:id,name_ar', 'destinationGovernorate:id,name_ar'])
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function confirm(int $id): RedirectResponse
    {
        $this->service->confirm($this->scopedReservation($id));

        return back()->with('success', 'تم تأكيد الحجز.');
    }

    public function complete(int $id): RedirectResponse
    {
        $this->service->complete($this->scopedReservation($id));

        return back()->with('success', 'تم إكمال الرحلة وتسجيل التقييم للطرفين.');
    }

    public function reject(int $id): RedirectResponse
    {
        $this->service->cancel($this->scopedReservation($id), $this->businessId());

        return back()->with('success', 'تم رفض/إلغاء الحجز.');
    }

    /** Block capacity for seats sold off the app. Released via reject(). */
    public function block(Request $request, int $schedule): RedirectResponse
    {
        $data = $request->validate([
            'units' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'units' => 'عدد الوحدات',
        ]);

        $this->service->blockOffline(
            $this->scopedLeg($schedule),
            (int) $data['units'],
            $data['notes'] ?? null
        );

        return back()->with('success', 'تم حجز المقاعد يدويًا (تعامل خارج التطبيق).');
    }
}
