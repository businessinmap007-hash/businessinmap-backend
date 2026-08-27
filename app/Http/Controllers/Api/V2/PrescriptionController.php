<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicAppointment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Services\Agenda\MedicationScheduleService;
use App\Services\Prescriptions\PrescriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Prescriptions from the doctor's and the patient's side. A doctor (a clinic
 * business) issues one for a patient; the patient reads their prescriptions and
 * sends one to a pharmacy to be dispensed. Pharmacy-side actions live in
 * PharmacyPrescriptionController.
 */
class PrescriptionController extends Controller
{
    public function __construct(private readonly PrescriptionService $service)
    {
    }

    /** POST /api/v2/prescriptions — a doctor issues one for a patient. */
    public function store(Request $request)
    {
        $doctor = $this->businessOrFail($request); // a clinic is a business account

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:users,id', 'different:' . $doctor->id],
            'appointment_id' => ['nullable', 'integer', 'exists:clinic_appointments,id'],
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'patient_condition' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            // A prescription line names a real, dictionary-verified drug — never
            // free text, so a typo can never become what the patient buys.
            // Missing the drug in the dictionary? MedicineController::store adds
            // it as its own explicit step first, never silently mid-prescription.
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.dosage' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'string', 'max:120'],
            'items.*.instructions' => ['nullable', 'string', 'max:255'],
            // Structured dosage → feeds the patient's medication reminders.
            'items.*.frequency_per_day' => ['nullable', 'integer', 'min:1', 'max:12'],
            'items.*.food_timing' => ['nullable', Rule::in(PrescriptionItem::FOOD_TIMINGS)],
            'items.*.time_slots' => ['nullable', 'array'],
            'items.*.time_slots.*' => [Rule::in(PrescriptionItem::SLOTS)],
            // "كام يوم او اسبوع او شهر" — the doctor states a number + unit;
            // duration_days (always in days, what the reminder scheduler
            // reads) is derived from it, never taken directly.
            'items.*.duration_value' => ['nullable', 'integer', 'min:1', 'max:60', 'required_with:items.*.duration_unit'],
            'items.*.duration_unit' => ['nullable', Rule::in(array_keys(PrescriptionItem::DURATION_UNIT_DAYS)), 'required_with:items.*.duration_value'],
        ]);

        $patient = User::query()->findOrFail((int) $data['patient_id']);

        // If linked to a visit, it must be this clinic's appointment for this patient.
        if (! empty($data['appointment_id'])) {
            $appointment = ClinicAppointment::query()->findOrFail((int) $data['appointment_id']);
            abort_unless(
                (int) $appointment->clinic_id === (int) $doctor->id
                    && (int) $appointment->patient_id === (int) $patient->id,
                422,
                __('الموعد لا يخص هذه العيادة أو هذا المريض.'),
            );
        }

        $prescription = $this->service->issue($doctor, $patient, [
            'appointment_id' => $data['appointment_id'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'patient_condition' => $data['patient_condition'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $data['items']);

        return response()->json([
            'success' => true,
            'message' => __('تم إصدار الوصفة الطبية.'),
            'data' => ['prescription' => $this->serialize($prescription)],
        ], 201);
    }

    /** GET /api/v2/prescriptions/issued — a doctor's issued prescriptions. */
    public function issued(Request $request)
    {
        $doctor = $this->businessOrFail($request);

        $rows = Prescription::query()
            ->where('doctor_id', (int) $doctor->id)
            ->with(['items', 'patient:id,name', 'pharmacy:id,name'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (Prescription $p) => $this->serialize($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** GET /api/v2/prescriptions — the caller's prescriptions as a patient. */
    public function index(Request $request)
    {
        $rows = Prescription::query()
            ->where('patient_id', (int) $request->user()->id)
            ->with(['items', 'doctor:id,name', 'pharmacy:id,name'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (Prescription $p) => $this->serialize($p));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** GET /api/v2/prescriptions/{prescription} — any of the three parties. */
    public function show(Request $request, int $prescription)
    {
        $row = $this->partyOrFail($request, $prescription);

        return response()->json(['success' => true, 'data' => ['prescription' => $this->serialize($row)]]);
    }

    /** POST /api/v2/prescriptions/{prescription}/send — patient → pharmacy. */
    public function send(Request $request, int $prescription)
    {
        $row = Prescription::query()->findOrFail($prescription);

        // Only the patient may send their own prescription.
        abort_if((int) $row->patient_id !== (int) $request->user()->id, 404);

        $data = $request->validate([
            'pharmacy_id' => ['required', 'integer', 'exists:users,id'],
            'fulfillment_type' => ['required', Rule::in(Prescription::FULFILLMENTS)],
            'delivery_address' => ['nullable', 'string', 'max:255'],
        ]);

        $pharmacy = User::query()->findOrFail((int) $data['pharmacy_id']);
        abort_unless($pharmacy->isBusiness(), 422, __('يجب اختيار صيدلية صحيحة.'));

        $row = $this->service->sendToPharmacy(
            $row,
            $pharmacy,
            $data['fulfillment_type'],
            $data['delivery_address'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال الوصفة إلى الصيدلية.'),
            'data' => ['prescription' => $this->serialize($row->fresh(['items', 'doctor:id,name', 'pharmacy:id,name']))],
        ]);
    }

    /**
     * POST /api/v2/prescriptions/{prescription}/schedule-reminders — the patient
     * turns their prescription's doses into agenda reminders, timed off their
     * meal times. Re-running rebuilds the schedule.
     */
    public function scheduleReminders(Request $request, int $prescription, MedicationScheduleService $medications)
    {
        $row = Prescription::query()->with('items')->findOrFail($prescription);
        abort_if((int) $row->patient_id !== (int) $request->user()->id, 404);

        $placed = $medications->schedule($row);

        return response()->json([
            'success' => true,
            'message' => __('تمت جدولة تذكيرات الدواء.'),
            'data' => ['reminders' => $placed],
        ]);
    }

    /** POST /api/v2/prescriptions/{prescription}/cancel — doctor or patient. */
    public function cancel(Request $request, int $prescription)
    {
        $row = Prescription::query()->findOrFail($prescription);

        $uid = (int) $request->user()->id;
        abort_if($uid !== (int) $row->doctor_id && $uid !== (int) $row->patient_id, 404);

        $row = $this->service->cancel($row);

        return response()->json([
            'success' => true,
            'message' => __('تم إلغاء الوصفة.'),
            'data' => ['prescription' => $this->serialize($row)],
        ]);
    }

    /**
     * POST /api/v2/prescriptions/{prescription}/share — grant a second doctor
     * read-only access. Either the patient or the ORIGINAL doctor may do
     * this («الاثنين معا») — not a pharmacy, and not an already-shared-in
     * doctor (they read, they don't re-share).
     */
    public function share(Request $request, int $prescription)
    {
        $row = Prescription::query()->findOrFail($prescription);
        $uid = (int) $request->user()->id;

        abort_if($uid !== (int) $row->doctor_id && $uid !== (int) $row->patient_id, 404);

        $data = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:users,id', 'different:' . $row->doctor_id],
        ], [], ['doctor_id' => 'الطبيب']);

        $doctor = User::query()->findOrFail((int) $data['doctor_id']);
        abort_unless(Prescription::isDoctorBusiness($doctor), 422, __('يجب اختيار حساب طبيب/عيادة صحيح.'));

        $this->service->share($row, $doctor, $request->user());

        return response()->json([
            'success' => true,
            'message' => __('تمت مشاركة الوصفة مع الطبيب.'),
            'data' => ['prescription' => $this->serialize($row->fresh(['items', 'doctor:id,name', 'patient:id,name', 'pharmacy:id,name', 'shares.doctor:id,name']))],
        ]);
    }

    /**
     * POST /api/v2/prescriptions/{prescription}/revise — the ORIGINAL doctor
     * amends it. Never in place: creates a new prescription (linked via
     * revises_prescription_id) and cancels this one.
     */
    public function revise(Request $request, int $prescription)
    {
        $row = Prescription::query()->findOrFail($prescription);
        $doctor = $this->businessOrFail($request);

        abort_if((int) $row->doctor_id !== (int) $doctor->id, 404);

        $data = $request->validate([
            'diagnosis' => ['nullable', 'string', 'max:255'],
            'patient_condition' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:medicines,id'],
            'items.*.dosage' => ['nullable', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'string', 'max:120'],
            'items.*.instructions' => ['nullable', 'string', 'max:255'],
            'items.*.frequency_per_day' => ['nullable', 'integer', 'min:1', 'max:12'],
            'items.*.food_timing' => ['nullable', Rule::in(PrescriptionItem::FOOD_TIMINGS)],
            'items.*.time_slots' => ['nullable', 'array'],
            'items.*.time_slots.*' => [Rule::in(PrescriptionItem::SLOTS)],
            'items.*.duration_value' => ['nullable', 'integer', 'min:1', 'max:60', 'required_with:items.*.duration_unit'],
            'items.*.duration_unit' => ['nullable', Rule::in(array_keys(PrescriptionItem::DURATION_UNIT_DAYS)), 'required_with:items.*.duration_value'],
        ]);

        $revision = $this->service->revise($row, $data, $data['items']);

        return response()->json([
            'success' => true,
            'message' => __('تم تعديل الوصفة — أصبحت النسخة الجديدة سارية.'),
            'data' => ['prescription' => $this->serialize($revision)],
        ], 201);
    }

    /**
     * «يجب تقييد الطبيب بتخصص طبي» — المالك. Any business could issue a
     * prescription; now only a physician's own practice can (Prescription::
     * DOCTOR_CHILD_IDS — مستشفى/عيادة/مركز طبي, not a lab/pharmacy/imaging
     * center/cupping center).
     */
    private function businessOrFail(Request $request): User
    {
        $user = $request->user();

        if (! Prescription::isDoctorBusiness($user)) {
            abort(response()->json([
                'success' => false,
                'message' => __('إصدار الوصفات متاح لحسابات العيادات والمستشفيات والمراكز الطبية فقط.'),
            ], 403));
        }

        return $user;
    }

    private function partyOrFail(Request $request, int $id): Prescription
    {
        $row = Prescription::query()
            ->with(['items', 'doctor:id,name', 'patient:id,name', 'pharmacy:id,name', 'shares.doctor:id,name'])
            ->findOrFail($id);

        abort_unless($row->isParty((int) $request->user()->id), 404);

        return $row;
    }

    private function serialize(Prescription $p): array
    {
        return [
            'id' => (int) $p->id,
            'status' => (string) $p->status,
            'appointment_id' => $p->appointment_id ? (int) $p->appointment_id : null,
            'revises_prescription_id' => $p->revises_prescription_id ? (int) $p->revises_prescription_id : null,
            'superseded' => (bool) ($p->status === Prescription::STATUS_CANCELLED && $p->revisedBy()->exists()),
            'fulfillment_type' => $p->fulfillment_type,
            'diagnosis' => $p->diagnosis,
            'patient_condition' => $p->patient_condition,
            'notes' => $p->notes,
            'delivery_address' => $p->delivery_address,
            'medicine_total' => $p->medicine_total !== null ? (float) $p->medicine_total : null,
            'priced_at' => optional($p->priced_at)->toIso8601String(),
            'doctor' => $this->party($p->doctor, $p->doctor_id),
            'patient' => $this->party($p->patient, $p->patient_id),
            'pharmacy' => $p->pharmacy_id ? $this->party($p->pharmacy, $p->pharmacy_id) : null,
            'shared_with' => $p->relationLoaded('shares')
                ? $p->shares->map(fn ($s) => $this->party($s->doctor, $s->doctor_id))->values()->all()
                : [],
            'items' => $p->relationLoaded('items')
                ? $p->items->map(fn ($i) => [
                    'id' => (int) $i->id,
                    'medicine_id' => $i->medicine_id ? (int) $i->medicine_id : null,
                    'name' => $i->name,
                    'dosage' => $i->dosage,
                    'quantity' => $i->quantity,
                    'instructions' => $i->instructions,
                    'frequency_per_day' => $i->frequency_per_day,
                    'food_timing' => $i->food_timing,
                    'time_slots' => $i->time_slots,
                    'duration_days' => $i->duration_days,
                    'duration_value' => $i->duration_value,
                    'duration_unit' => $i->duration_unit,
                    'unit_price' => $i->unit_price !== null ? (float) $i->unit_price : null,
                    'billed_quantity' => $i->billed_quantity,
                    'line_total' => $i->line_total !== null ? (float) $i->line_total : null,
                ])->all()
                : [],
            'issued_at' => optional($p->issued_at)->toIso8601String(),
            'dispensed_at' => optional($p->dispensed_at)->toIso8601String(),
        ];
    }

    private function party(?User $user, $id): array
    {
        return $user ? ['id' => (int) $user->id, 'name' => $user->name] : ['id' => (int) $id];
    }
}
