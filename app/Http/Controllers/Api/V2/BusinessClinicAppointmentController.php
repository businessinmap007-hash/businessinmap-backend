<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicAppointment;
use App\Models\User;
use App\Services\Clinics\ClinicAppointmentService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

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
            ->with('patient:id,name,phone')
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
            'patient' => $a->relationLoaded('patient') && $a->patient
                ? ['id' => (int) $a->patient->id, 'name' => $a->patient->name, 'phone' => $a->patient->phone]
                : ['id' => (int) $a->patient_id],
        ];
    }
}
