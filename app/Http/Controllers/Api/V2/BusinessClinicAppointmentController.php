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
use Illuminate\Validation\Rule;

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
            'service_price_id' => ['nullable', 'integer', 'exists:business_service_prices,id'],
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

    /**
     * POST .../clinic-appointments/{appointment}/complete — the doctor's
     * «تم» button: closes this visit AND hands back whoever queues next
     * (tagged with their own visit kind), in the same response, so the app
     * can move straight to them without a second round trip.
     */
    public function complete(Request $request, int $appointment)
    {
        $row = $this->ownedOrFail($request, $appointment);
        $result = $this->service->completeAndAdvance($row);

        return response()->json([
            'success' => true,
            'message' => __('تم إكمال الموعد.'),
            'data' => [
                'appointment' => $this->serialize($result['completed']->fresh('patient:id,name,phone')),
                'next' => $result['next'] ? $this->serializeQueueEntry($result['next']) : null,
            ],
        ]);
    }

    /**
     * POST /api/v2/business/clinic-appointments/walk-in — a patient the
     * secretary adds straight into today's queue: between existing bookings,
     * or standing in for one who hasn't shown up (who keeps their own place
     * untouched). No time slot to reserve — it joins the queue already
     * checked in.
     */
    public function storeWalkIn(Request $request)
    {
        $clinicId = BusinessContext::id($request);

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:users,id', 'different:' . $clinicId],
            'service_price_id' => ['nullable', 'integer', 'exists:business_service_prices,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $clinic = User::query()->findOrFail($clinicId);
        $patient = User::query()->findOrFail((int) $data['patient_id']);

        $appointment = $this->service->insertWalkIn($clinic, $patient, $data);

        return response()->json([
            'success' => true,
            'message' => __('انضم المريض إلى الدور.'),
            'data' => ['appointment' => $this->serializeQueueEntry($appointment->load('patient:id,name,phone'))],
        ], 201);
    }

    /**
     * GET /api/v2/business/clinic-appointments/queue — today's waiting
     * patients (checked in, not yet seen), with the automatic-next one
     * flagged so the app can highlight them.
     */
    public function queue(Request $request)
    {
        $clinic = User::query()->findOrFail(BusinessContext::id($request));

        $waiting = ClinicAppointment::query()
            ->where('clinic_id', $clinic->id)
            ->where('status', ClinicAppointment::STATUS_CONFIRMED)
            ->whereNotNull('checked_in_at')
            ->with(['patient:id,name,phone', 'servicePrice'])
            ->orderByRaw('queue_priority_override IS NULL, queue_priority_override, checked_in_at')
            ->get();

        $next = $this->service->nextInQueue($clinic);

        return response()->json([
            'success' => true,
            'data' => [
                'queue_pattern' => $clinic->clinic_queue_pattern,
                'next_id' => $next ? (int) $next->id : null,
                'waiting' => $waiting->map(fn (ClinicAppointment $a) => $this->serializeQueueEntry($a))->values()->all(),
            ],
        ]);
    }

    /** POST .../clinic-appointments/{appointment}/checkin — clinic scans the patient's QR. */
    public function checkin(Request $request, string $token)
    {
        $appointment = $this->service->confirmCheckin($token, BusinessContext::id($request));

        return response()->json([
            'success' => true,
            'message' => __('تم تسجيل حضور المريض.'),
            'data' => ['appointment' => $this->serializeQueueEntry($appointment->load('patient:id,name,phone'))],
        ]);
    }

    /**
     * POST .../clinic-appointments/{appointment}/call-now — secretary calls a
     * specific waiting patient ahead of the automatic pattern (the one whose
     * turn it is hasn't shown, so someone else goes instead).
     */
    public function callNow(Request $request, int $appointment)
    {
        $row = $this->ownedOrFail($request, $appointment);
        $row = $this->service->callNow($row);

        return response()->json([
            'success' => true,
            'message' => __('تم استدعاء المريض.'),
            'data' => ['appointment' => $this->serializeQueueEntry($row->load('patient:id,name,phone'))],
        ]);
    }

    /**
     * PATCH /api/v2/business/clinic-queue-pattern — «1 كشف ثم 2 استشارة»: the
     * repeating sequence of visit kinds (bookable_item_type keys) the queue
     * cycles through when deciding who's next. Send an empty array to go
     * back to plain first-checked-in-first-called.
     */
    public function updateQueuePattern(Request $request)
    {
        $data = $request->validate([
            'pattern' => ['present', 'array', 'max:20'],
            'pattern.*' => ['string', Rule::exists('platform_service_item_types', 'key')],
        ]);

        $clinic = User::query()->findOrFail(BusinessContext::id($request));
        $clinic->clinic_queue_pattern = $data['pattern'] ?: null;
        $clinic->save();

        return response()->json(['success' => true, 'data' => ['queue_pattern' => $clinic->clinic_queue_pattern]]);
    }

    public function noShow(Request $request, int $appointment)
    {
        return $this->act($request, $appointment, fn ($a) => $this->service->noShow($a), __('تم تسجيل عدم الحضور.'));
    }

    /** POST business/clinic-appointments/{appointment}/reschedule — clinic moves it. */
    public function reschedule(Request $request, int $appointment)
    {
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        return $this->act(
            $request,
            $appointment,
            fn ($a) => $this->service->rescheduleByClinic(
                $a,
                Carbon::parse($data['scheduled_at']),
                isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            ),
            __('تم تغيير الموعد.'),
        );
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
            'service_price_id' => $s->service_price_id ? (int) $s->service_price_id : null,
            'visit_kind' => $s->servicePrice?->offeringLabel($s->servicePrice->bookable_item_type),
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
            'service_price_id' => ['nullable', 'integer', 'exists:business_service_prices,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $clinic = User::query()->findOrFail(BusinessContext::id($request));

        // «الكشف ٣٠ والاستشارة ٢٠»: naming the visit is enough — its length comes
        // from what the clinic priced, and only falls back to the posted number.
        [$duration, $priceId] = $this->service->resolveDuration((int) $clinic->id, $data);
        $starts = $data['slots'] ?? [$data['starts_at']];

        $created = 0;
        $closed = 0;
        foreach ($starts as $at) {
            $start = Carbon::parse($at);

            if (! app(\App\Services\BusinessHoursService::class)
                ->isOpenThroughout((int) $clinic->id, $start, $start->copy()->addMinutes($duration))) {
                $closed++;

                continue;
            }

            if ($this->service->publishSlot($clinic, $start, $duration, $priceId)) {
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('تم نشر الفتحات.'),
            'data' => ['created' => $created, 'skipped' => count($starts) - $created],
        ], 201);
    }

    /**
     * POST /api/v2/business/clinic-slots/generate — publish a recurring weekly
     * grid at once. Give `weekdays` (0=Sun..6=Sat) plus either explicit `times`
     * (H:i) or a `start_time`/`end_time`/`interval_minutes` range, over `weeks`.
     */
    public function slotsGenerate(Request $request)
    {
        $data = $request->validate([
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'times' => ['required_without_all:start_time,end_time', 'array'],
            'times.*' => ['date_format:H:i'],
            'start_time' => ['required_with:end_time', 'date_format:H:i'],
            'end_time' => ['required_with:start_time', 'date_format:H:i', 'after:start_time'],
            'interval_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'service_price_id' => ['nullable', 'integer', 'exists:business_service_prices,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $times = $this->resolveTimes($data);
        abort_if($times === [], 422, __('حدّد الأوقات.'));

        $clinic = User::query()->findOrFail(BusinessContext::id($request));
        [$duration, $priceId] = $this->service->resolveDuration((int) $clinic->id, $data);

        $result = $this->service->generateSlots(
            $clinic,
            array_values(array_unique(array_map('intval', $data['weekdays']))),
            $times,
            (int) ($data['weeks'] ?? 4) * 7,
            $duration,
            $priceId,
        );

        return response()->json([
            'success' => true,
            'message' => __('تم نشر الفتحات.'),
            'data' => $result,
        ], 201);
    }

    /**
     * POST /api/v2/business/clinic-slots/generate-from-hours — publish slots
     * straight from the clinic's own configured working hours instead of
     * re-typing days/start/end: each open day gets its own slots sliced from
     * ITS OWN open/close window (see business/hours). A day left unset or
     * marked closed is simply skipped.
     */
    public function slotsGenerateFromHours(Request $request)
    {
        $data = $request->validate([
            'weeks' => ['nullable', 'integer', 'min:1', 'max:12'],
            'interval_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'service_price_id' => ['nullable', 'integer', 'exists:business_service_prices,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
        ]);

        $clinic = User::query()->findOrFail(BusinessContext::id($request));

        $result = $this->service->generateSlotsFromHours(
            $clinic,
            (int) ($data['weeks'] ?? 4) * 7,
            isset($data['interval_minutes']) ? (int) $data['interval_minutes'] : null,
            $data,
        );

        return response()->json([
            'success' => true,
            'message' => __('تم نشر الفتحات من مواعيد العمل.'),
            'data' => $result,
        ], 201);
    }

    /** Build the time list from explicit times or a start/end/interval range. */
    private function resolveTimes(array $data): array
    {
        if (! empty($data['times'])) {
            return array_values(array_unique($data['times']));
        }

        if (empty($data['start_time']) || empty($data['end_time'])) {
            return [];
        }

        $step = max((int) ($data['interval_minutes'] ?? $data['duration_minutes'] ?? 30), 5);
        $cursor = Carbon::createFromFormat('H:i', $data['start_time']);
        $end = Carbon::createFromFormat('H:i', $data['end_time']);

        $times = [];
        while ($cursor->lt($end)) {
            $times[] = $cursor->format('H:i');
            $cursor->addMinutes($step);
        }

        return $times;
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

    /**
     * GET .../clinic-appointments/{appointment}/patient-record — what the
     * doctor opens when the patient walks in: no prior visit means a blank
     * new prescription + report; a returning patient means their latest
     * prescription/report, to view or amend (via POST prescriptions/{id}
     * /revise) rather than starting from nothing.
     */
    public function patientRecord(Request $request, int $appointment)
    {
        $row = $this->ownedOrFail($request, $appointment);
        $clinicId = BusinessContext::id($request);
        $currentPrescriptionId = optional($row->prescription)->id;

        $previous = \App\Models\Prescription::query()
            ->where('doctor_id', $clinicId)
            ->where('patient_id', (int) $row->patient_id)
            ->when($currentPrescriptionId, fn ($q) => $q->where('id', '!=', $currentPrescriptionId))
            ->with('items')
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'is_first_visit' => $previous === null,
                'previous_prescription' => $previous ? $this->serializePrescriptionSummary($previous) : null,
            ],
        ]);
    }

    private function serializePrescriptionSummary(\App\Models\Prescription $p): array
    {
        return [
            'id' => (int) $p->id,
            'status' => (string) $p->status,
            'diagnosis' => $p->diagnosis,
            'patient_condition' => $p->patient_condition,
            'notes' => $p->notes,
            'issued_at' => optional($p->issued_at)->toIso8601String(),
            'items' => $p->items->map(fn ($i) => [
                'id' => (int) $i->id,
                'medicine_id' => $i->medicine_id ? (int) $i->medicine_id : null,
                'name' => $i->name,
                'dosage' => $i->dosage,
                'quantity' => $i->quantity,
                'instructions' => $i->instructions,
                'frequency_per_day' => $i->frequency_per_day,
                'food_timing' => $i->food_timing,
                'time_slots' => $i->time_slots,
                'duration_value' => $i->duration_value,
                'duration_unit' => $i->duration_unit,
            ])->all(),
        ];
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

    /** The base serialize() plus the queue-specific fields — check-in, kind tag, walk-in flag. */
    private function serializeQueueEntry(ClinicAppointment $a): array
    {
        $kind = $a->visitKind();

        return $this->serialize($a) + [
            'checked_in_at' => optional($a->checked_in_at)->toIso8601String(),
            'is_walk_in' => (bool) $a->is_walk_in,
            'called_now' => $a->queue_priority_override !== null,
            'visit_kind' => $kind,
            'visit_kind_label' => \App\Models\PlatformServiceItemType::query()->where('key', $kind)->value('name_ar'),
        ];
    }
}
