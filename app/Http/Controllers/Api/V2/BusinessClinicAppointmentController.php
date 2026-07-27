<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
use App\Models\User;
use App\Services\Clinics\ClinicAppointmentService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The clinic's side of appointments. The business.member:clinic middleware
 * resolves the acting clinic (owner or a delegate — e.g. the secretary — with
 * the `clinic` capability); every action is scoped to that clinic's calendar.
 */
class BusinessClinicAppointmentController extends Controller
{
    public function __construct(private readonly ClinicAppointmentService $service)
    {
    }

    /** GET /api/v2/business/clinic-appointments — the clinic's calendar/queue. */
    public function index(Request $request)
    {
        $rows = ClinicAppointment::query()
            ->where('clinic_id', BusinessContext::id($request))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('scheduled_at', $request->get('date')))
            ->with(['patient:id,name,phone', 'prescription:id,appointment_id'])
            ->orderBy('scheduled_at')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (ClinicAppointment $a) => $this->serialize($a));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /api/v2/business/clinic-appointments — clinic books directly (confirmed). */
    public function store(Request $request)
    {
        $clinicId = BusinessContext::id($request);

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:users,id', 'different:' . $clinicId],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $clinic = User::query()->findOrFail($clinicId);
        $patient = User::query()->findOrFail((int) $data['patient_id']);

        $appointment = $this->service->bookByClinic($clinic, $patient, $data);

        return response()->json([
            'success' => true,
            'message' => __('تم حجز الموعد.'),
            'data' => ['appointment' => $this->serialize($appointment->load('patient:id,name,phone'))],
        ], 201);
    }

    public function confirm(Request $request, int $appointment)
    {
        return $this->act($request, $appointment, fn ($a) => $this->service->confirm($a), __('تم تأكيد الموعد.'));
    }

    public function reject(Request $request, int $appointment)
    {
        return $this->act($request, $appointment, fn ($a) => $this->service->reject($a), __('تم رفض/إلغاء الموعد.'));
    }

    public function complete(Request $request, int $appointment)
    {
        return $this->act($request, $appointment, fn ($a) => $this->service->complete($a), __('تم إكمال الموعد.'));
    }

    public function noShow(Request $request, int $appointment)
    {
        return $this->act($request, $appointment, fn ($a) => $this->service->noShow($a), __('تم تسجيل عدم الحضور.'));
    }

    /** GET /api/v2/business/clinic-slots — the clinic's published slots (open by default). */
    public function slotsIndex(Request $request)
    {
        $rows = ClinicAppointmentSlot::query()
            ->where('clinic_id', BusinessContext::id($request))
            ->when(! $request->boolean('include_booked'), fn ($q) => $q->open())
            ->orderBy('starts_at')
            ->paginate((int) $request->get('per_page', 30));

        $rows->getCollection()->transform(fn (ClinicAppointmentSlot $s) => [
            'id' => (int) $s->id,
            'starts_at' => optional($s->starts_at)->toIso8601String(),
            'duration_minutes' => (int) $s->duration_minutes,
            'appointment_id' => $s->appointment_id ? (int) $s->appointment_id : null,
            'is_open' => $s->isOpen(),
        ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * POST /api/v2/business/clinic-slots — publish one or many open slots. Accepts
     * `starts_at` (single) or `slots` (array of Y-m-d H:i:s). Duplicates are skipped.
     */
    public function slotsStore(Request $request)
    {
        $data = $request->validate([
            'starts_at' => ['required_without:slots', 'date', 'after:now'],
            'slots' => ['required_without:starts_at', 'array', 'min:1', 'max:200'],
            'slots.*' => ['date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $clinic = User::query()->findOrFail(BusinessContext::id($request));
        $duration = (int) ($data['duration_minutes'] ?? 30);
        $starts = $data['slots'] ?? [$data['starts_at']];

        $created = 0;
        foreach ($starts as $at) {
            if ($this->service->publishSlot($clinic, Carbon::parse($at), $duration)) {
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('تم نشر الفتحات.'),
            'data' => ['created' => $created, 'skipped' => count($starts) - $created],
        ], 201);
    }

    /** DELETE /api/v2/business/clinic-slots/{slot} — remove an open slot. */
    public function slotsDestroy(Request $request, int $slot)
    {
        $row = ClinicAppointmentSlot::query()
            ->where('id', $slot)
            ->where('clinic_id', BusinessContext::id($request))
            ->firstOrFail();

        $this->service->deleteSlot($row);

        return response()->json(['success' => true, 'message' => __('تم حذف الفتحة.')]);
    }

    private function act(Request $request, int $appointmentId, \Closure $action, string $message)
    {
        $row = $this->ownedOrFail($request, $appointmentId);
        $row = $action($row);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['appointment' => $this->serialize($row->fresh('patient:id,name,phone'))],
        ]);
    }

    private function ownedOrFail(Request $request, int $id): ClinicAppointment
    {
        return ClinicAppointment::query()
            ->where('id', $id)
            ->where('clinic_id', BusinessContext::id($request))
            ->firstOrFail();
    }

    private function serialize(ClinicAppointment $a): array
    {
        return [
            'id' => (int) $a->id,
            'status' => (string) $a->status,
            'scheduled_at' => optional($a->scheduled_at)->toIso8601String(),
            'duration_minutes' => (int) $a->duration_minutes,
            'reason' => $a->reason,
            'notes' => $a->notes,
            'prescription_id' => $a->relationLoaded('prescription') && $a->prescription
                ? (int) $a->prescription->id : null,
            'patient' => $a->relationLoaded('patient') && $a->patient
                ? ['id' => (int) $a->patient->id, 'name' => $a->patient->name, 'phone' => $a->patient->phone]
                : ['id' => (int) $a->patient_id],
        ];
    }
}
