<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentSlot;
use App\Models\User;
use App\Services\Clinics\ClinicAppointmentService;
use Illuminate\Http\Request;

/**
 * The patient's side of clinic appointments: request one, read mine, cancel.
 * The clinic side lives in BusinessClinicAppointmentController.
 */
class ClinicAppointmentController extends Controller
{
    public function __construct(private readonly ClinicAppointmentService $service)
    {
    }

    /** GET /api/v2/clinic-appointments — my appointments. */
    public function index(Request $request)
    {
        $rows = ClinicAppointment::query()
            ->where('patient_id', (int) $request->user()->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->with('clinic:id,name,logo')
            ->orderByDesc('scheduled_at')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (ClinicAppointment $a) => $this->serialize($a));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /api/v2/clinic-appointments — request an appointment at a clinic. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'clinic_id' => ['required', 'integer', 'exists:users,id', 'different:' . (int) $request->user()->id],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $clinic = User::query()->findOrFail((int) $data['clinic_id']);
        abort_unless($clinic->isBusiness(), 422, __('يجب اختيار عيادة صحيحة.'));

        $appointment = $this->service->request($request->user(), $clinic, $data);

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال طلب الموعد.'),
            'data' => ['appointment' => $this->serialize($appointment->load('clinic:id,name,logo'))],
        ], 201);
    }

    /** GET /api/v2/clinic-appointments/{appointment} — a party only. */
    public function show(Request $request, int $appointment)
    {
        $row = ClinicAppointment::query()
            ->with(['clinic:id,name,logo', 'patient:id,name', 'prescription:id,appointment_id'])
            ->findOrFail($appointment);

        abort_unless($row->isParty((int) $request->user()->id), 404);

        return response()->json(['success' => true, 'data' => ['appointment' => $this->serialize($row)]]);
    }

    /** POST /api/v2/clinic-appointments/{appointment}/cancel — patient cancels. */
    public function cancel(Request $request, int $appointment)
    {
        $row = ClinicAppointment::query()->findOrFail($appointment);
        abort_if((int) $row->patient_id !== (int) $request->user()->id, 404);

        $row = $this->service->cancelByPatient($row);

        return response()->json([
            'success' => true,
            'message' => __('تم إلغاء الموعد.'),
            'data' => ['appointment' => $this->serialize($row)],
        ]);
    }

    /** POST /api/v2/clinic-appointments/{appointment}/reschedule — patient moves it. */
    public function reschedule(Request $request, int $appointment)
    {
        $row = ClinicAppointment::query()->findOrFail($appointment);
        abort_if((int) $row->patient_id !== (int) $request->user()->id, 404);

        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $row = $this->service->rescheduleByPatient(
            $row,
            \Illuminate\Support\Carbon::parse($data['scheduled_at']),
            isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
        );

        return response()->json([
            'success' => true,
            'message' => __('تم تغيير موعدك.'),
            'data' => ['appointment' => $this->serialize($row->fresh(['clinic:id,name,logo', 'prescription:id,appointment_id']))],
        ]);
    }

    /** GET /api/v2/clinics/{clinic}/slots — a clinic's open, still-future slots. */
    public function slots(Request $request, int $clinic)
    {
        $clinicUser = User::query()->findOrFail($clinic);
        abort_unless($clinicUser->isBusiness(), 404);

        $rows = ClinicAppointmentSlot::query()
            ->where('clinic_id', $clinic)
            ->open()
            ->orderBy('starts_at')
            ->paginate((int) $request->get('per_page', 30));

        $rows->getCollection()->transform(fn (ClinicAppointmentSlot $s) => [
            'id' => (int) $s->id,
            'starts_at' => optional($s->starts_at)->toIso8601String(),
            'duration_minutes' => (int) $s->duration_minutes,
        ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /api/v2/clinic-slots/{slot}/book — book an open slot (confirmed at once). */
    public function bookSlot(Request $request, int $slot)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $row = ClinicAppointmentSlot::query()->findOrFail($slot);
        $appointment = $this->service->bookSlot($request->user(), $row, $data);

        return response()->json([
            'success' => true,
            'message' => __('تم حجز الموعد.'),
            'data' => ['appointment' => $this->serialize($appointment->load('clinic:id,name,logo'))],
        ], 201);
    }

    private function serialize(ClinicAppointment $a): array
    {
        return [
            'id' => (int) $a->id,
            'status' => (string) $a->status,
            'scheduled_at' => optional($a->scheduled_at)->toIso8601String(),
            'duration_minutes' => (int) $a->duration_minutes,
            'reason' => $a->reason,
            'prescription_id' => $a->relationLoaded('prescription') && $a->prescription
                ? (int) $a->prescription->id : null,
            'clinic' => $a->relationLoaded('clinic') && $a->clinic
                ? ['id' => (int) $a->clinic->id, 'name' => $a->clinic->name, 'logo' => $a->clinic->logo]
                : ['id' => (int) $a->clinic_id],
            'patient' => $a->relationLoaded('patient') && $a->patient
                ? ['id' => (int) $a->patient->id, 'name' => $a->patient->name]
                : ['id' => (int) $a->patient_id],
        ];
    }
}
