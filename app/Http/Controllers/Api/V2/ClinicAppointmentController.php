<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicAppointment;
use App\Models\ClinicAppointmentPayment;
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
            // prescription is loaded so serialize()'s prescription_id is ever
            // non-null on the list a patient actually browses — without it
            // this only worked on show()/reschedule(), which nothing calls.
            ->with(['clinic:id,name,name_en,medical_title,logo', 'prescription:id,appointment_id'])
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
            // Naming the visit sets its length from what the clinic priced —
            // the patient's own duration is only the fallback.
            'service_price_id' => ['nullable', 'integer', 'exists:business_service_prices,id'],
            // what he came for, from the clinic's own list: several may be named (resolveSpecialtyServices)
            'specialty_service_ids' => ['nullable', 'array', 'max:10'],
            'specialty_service_ids.*' => ['integer', 'distinct', 'exists:specialty_services,id'],
            'specialty_service_id' => ['nullable', 'integer', 'exists:specialty_services,id'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
            'for_name' => ['nullable', 'string', 'max:120'],
            'for_phone' => ['nullable', 'string', 'max:30'],
            // short of the deposit and a friend will stand for him: the request goes through, the friend is invited next
            'friend_guarantee' => ['nullable', 'boolean'],
        ]);

        $clinic = User::query()->findOrFail((int) $data['clinic_id']);
        abort_unless($clinic->isBusiness(), 422, __('يجب اختيار عيادة صحيحة.'));

        $appointment = $this->service->request($request->user(), $clinic, $data);

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال طلب الموعد.'),
            'data' => ['appointment' => $this->serialize($appointment->load('clinic:id,name,name_en,medical_title,logo'))],
        ], 201);
    }

    /** GET /api/v2/clinic-appointments/{appointment} — a party only. */
    public function show(Request $request, int $appointment)
    {
        $row = ClinicAppointment::query()
            ->with(['clinic:id,name,name_en,medical_title,logo', 'patient:id,name', 'prescription:id,appointment_id'])
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
            'data' => ['appointment' => $this->serialize($row->fresh(['clinic:id,name,name_en,medical_title,logo', 'prescription:id,appointment_id']))],
        ]);
    }

    /**
     * GET /api/v2/clinic-appointments/{appointment}/checkin-token — the
     * patient's own check-in code («كل مريض له QR خاص بحجزه»): the app
     * renders it as a QR for the clinic to scan on arrival. Issued once
     * (stable across repeat views) while the appointment is confirmed.
     */
    public function checkinToken(Request $request, int $appointment)
    {
        $row = ClinicAppointment::query()->findOrFail($appointment);
        abort_if((int) $row->patient_id !== (int) $request->user()->id, 404);

        $token = $this->service->issueCheckinToken($row);

        return response()->json(['success' => true, 'data' => ['checkin_token' => $token]]);
    }

    /** POST /api/v2/clinic-appointments/{appointment}/payments/{payment}/confirm — «نعم، دفعت هذا المبلغ». */
    public function confirmPayment(Request $request, int $appointment, int $payment)
    {
        $a = $this->mineOrFail($request, $appointment);
        $p = ClinicAppointmentPayment::query()->findOrFail($payment);

        app(\App\Services\Clinics\ClinicBillingService::class)->confirmPayment($a, $p);

        return response()->json(['success' => true, 'message' => __('تم تأكيد الدفع.'), 'data' => ['appointment' => $this->serialize($a->refresh())]]);
    }

    /** POST /api/v2/clinic-appointments/{appointment}/payments/{payment}/dispute — {note?}: «لم أدفع هذا المبلغ». */
    public function disputePayment(Request $request, int $appointment, int $payment)
    {
        $a = $this->mineOrFail($request, $appointment);
        $p = ClinicAppointmentPayment::query()->findOrFail($payment);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        app(\App\Services\Clinics\ClinicBillingService::class)->disputePayment($a, $p, $data['note'] ?? null);

        return response()->json(['success' => true, 'message' => __('سُجّل اعتراضك.'), 'data' => ['appointment' => $this->serialize($a->refresh())]]);
    }

    /**
     * POST clinic-appointments/arrive — {code}: the patient scanned the clinic's arrival QR; his turn in the queue is
     * activated. Idempotent.
     */
    public function arrive(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);

        $appointment = $this->service->arriveByClinicQr($request->user(), $data['code']);

        return response()->json([
            'success' => true,
            'message' => __('تم تسجيل وصولك. أنت الآن في الطابور.'),
            'data' => ['appointment_id' => (int) $appointment->id, 'checked_in_at' => optional($appointment->checked_in_at)->toIso8601String()],
        ]);
    }

    private function mineOrFail(Request $request, int $id): ClinicAppointment
    {
        return ClinicAppointment::query()->where('id', $id)->where('patient_id', (int) $request->user()->id)->firstOrFail();
    }

    /**
     * GET /api/v2/clinics/{clinic}/visit-kinds — every visit kind the clinic prices (كشف، استشارة، متابعة، إجراء طبي…) with
     * its fee, whether or not it has an open slot today, and what the caller still owes the clinic from earlier visits.
     */
    public function visitKinds(Request $request, int $clinic)
    {
        $clinicUser = User::query()->findOrFail($clinic);
        abort_unless($clinicUser->isBusiness(), 404);

        $order = ['booking_examination', 'booking_consultation', 'booking_follow_up', 'booking_procedure', 'booking_session', 'booking_online_consultation', 'booking_home_visit'];

        $rows = \App\Models\BusinessServicePrice::query()
            ->where('business_id', $clinic)->where('is_active', 1)->where('line_option_id', 0)
            ->whereIn('bookable_item_type', $order)
            ->get()
            ->sortBy(fn ($r) => array_search($r->bookable_item_type, $order, true))
            ->values();

        $kinds = $rows->map(fn ($r) => [
            'key' => (string) $r->bookable_item_type,
            'name' => $this->kindName((string) $r->bookable_item_type),
            'price' => (float) $r->price,
            'duration_minutes' => $r->duration_minutes !== null ? (int) $r->duration_minutes : null,
        ])->all();

        return response()->json(['success' => true, 'data' => [
            'kinds' => $kinds,
            'my_balance' => app(\App\Services\Clinics\ClinicBillingService::class)->owedBy((int) $request->user()->id, $clinic),
        ]]);
    }

    /**
     * GET /api/v2/clinics/{clinic}/physicians — the physicians who work for this hospital / centre and have times to
     * book (name + his title there). Empty for a clinic with no staff physicians: its board is one board.
     */
    public function physicians(Request $request, int $clinic)
    {
        abort_unless(User::query()->whereKey($clinic)->where('type', User::TYPE_BUSINESS)->exists(), 404);

        $withTimes = ClinicAppointmentSlot::query()
            ->where('clinic_id', $clinic)
            ->whereNotNull('physician_id')
            ->open()
            ->select('physician_id', \DB::raw('count(*) as n'), \DB::raw('min(starts_at) as next_at'))
            ->groupBy('physician_id')
            ->get()
            ->keyBy('physician_id');

        $rows = \App\Support\Physicians::of($clinic)->map(function (array $p) use ($withTimes) {
            $row = $withTimes->get($p['id']);

            return $p + [
                'open_slots' => $row ? (int) $row->n : 0,
                'next_at' => $row?->next_at ? \Carbon\Carbon::parse($row->next_at)->toIso8601String() : null,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => ['physicians' => $rows]]);
    }

    /** GET /api/v2/clinics/{clinic}/slots — a clinic's open, still-future slots. */
    public function slots(Request $request, int $clinic)
    {
        $clinicUser = User::query()->findOrFail($clinic);
        abort_unless($clinicUser->isBusiness(), 404);

        // The whole booking window at once — two weeks by default — not the first thirty rows: with eight slots a day
        // thirty rows were four days, and a patient could not see that the doctor sits on the other days too.
        $days = max(1, min(60, (int) $request->get('days', 14)));
        $from = now()->startOfDay();
        $to = $from->copy()->addDays($days)->startOfDay();

        // a hospital's physicians each publish their own times: the patient asks for ONE physician's board (a physician
        // chosen is the filter; none chosen = the times the hospital itself published without naming one)
        $physician = $request->filled('physician_id') ? (int) $request->get('physician_id') : null;

        $rows = ClinicAppointmentSlot::query()
            ->where('clinic_id', $clinic)
            ->when($physician, fn ($q) => $q->where('physician_id', $physician), fn ($q) => $q->whereNull('physician_id'))
            ->open()
            ->where('starts_at', '<', $to)
            ->orderBy('starts_at')
            ->paginate(max(1, min(1000, (int) $request->get('per_page', 600))));

        // «حجوزات العيادات خاضعة لمواعيد عمل العيادات» — المالك: a slot published before the clinic changed its hours
        // is not offered outside them (booking it would be refused anyway).
        $hours = app(\App\Services\BusinessHoursService::class);
        $rows->setCollection($rows->getCollection()->filter(
            fn (ClinicAppointmentSlot $s) => $hours->isOpenThroughout((int) $clinic, $s->starts_at, $s->starts_at->copy()->addMinutes((int) $s->duration_minutes))
                // a day the clinic closed («اكتمل العدد اليوم») offers no times: the strip shows it as full
                && ! \App\Support\DayClosure::isClosedOn($clinicUser, $s->starts_at)
        )->values());

        // the day strip: every day of the window with whether the clinic sits that day (its weekly hours say so; a week
        // it never wrote counts as open) and how many open times are left
        $week = $hours->hoursFor((int) $clinic);
        $perDay = $rows->getCollection()->countBy(fn (ClinicAppointmentSlot $s) => $s->starts_at->toDateString());
        // every time the clinic PUBLISHED that day — booked ones too: a day with none is a day the doctor is not there
        // (a leave, a conference) and the app does not show it at all; a day with all of them taken shows as full
        $publishedPerDay = ClinicAppointmentSlot::query()
            ->where('clinic_id', $clinic)
            ->when($physician, fn ($q) => $q->where('physician_id', $physician), fn ($q) => $q->whereNull('physician_id'))
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->get(['starts_at'])
            ->countBy(fn (ClinicAppointmentSlot $s) => $s->starts_at->toDateString());
        $strip = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $row = $week->get($day->dayOfWeek);
            $open = $row === null || (! $row->is_closed && $row->open_time && $row->close_time);

            $strip[] = [
                'date' => $day->toDateString(),
                'open' => (bool) $open,
                'hours' => $open && $row ? ['open' => substr((string) $row->open_time, 0, 5), 'close' => substr((string) $row->close_time, 0, 5)] : null,
                'slots' => (int) ($perDay[$day->toDateString()] ?? 0),
                'published' => (int) ($publishedPerDay[$day->toDateString()] ?? 0),
            ];
        }

        $rows->getCollection()->transform(fn (ClinicAppointmentSlot $s) => [
            'id' => (int) $s->id,
            'starts_at' => optional($s->starts_at)->toIso8601String(),
            'duration_minutes' => (int) $s->duration_minutes,
            'physician_id' => $s->physician_id ? (int) $s->physician_id : null,
            // What the patient is booking, not just how long it takes.
            'visit_kind' => $s->servicePrice?->offeringLabel($this->kindName((string) $s->servicePrice->bookable_item_type)),
            'visit_kind_key' => $s->servicePrice?->bookable_item_type,
            // a number, not the decimal's string: the app reads it as one
            'price' => $s->servicePrice !== null ? (float) $s->servicePrice->price : null,
        ]);

        return response()->json(['success' => true, 'data' => $rows->toArray() + ['days' => $strip]]);
    }

    /** POST /api/v2/clinic-slots/{slot}/book — book an open slot (confirmed at once). */
    public function bookSlot(Request $request, int $slot)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'specialty_service_ids' => ['nullable', 'array', 'max:10'],
            'specialty_service_ids.*' => ['integer', 'distinct', 'exists:specialty_services,id'],
            'specialty_service_id' => ['nullable', 'integer', 'exists:specialty_services,id'],
            'for_name' => ['nullable', 'string', 'max:120'],
            'for_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $row = ClinicAppointmentSlot::query()->findOrFail($slot);
        $appointment = $this->service->bookSlot($request->user(), $row, $data);

        return response()->json([
            'success' => true,
            'message' => __('تم حجز الموعد.'),
            'data' => ['appointment' => $this->serialize($appointment->load('clinic:id,name,name_en,medical_title,logo'))],
        ], 201);
    }

    /** The visit kind's own name in the caller's language («كشف»), not its key («booking_examination»). */
    private function kindName(string $key): string
    {
        $type = \App\Models\PlatformServiceItemType::query()->where('key', $key)->first();
        if (! $type) {
            return $key;
        }

        $name = app()->getLocale() === 'en' ? trim((string) $type->name_en) : trim((string) $type->name_ar);

        return $name !== '' ? $name : (trim((string) $type->name_ar) ?: $key);
    }

    private function serialize(ClinicAppointment $a): array
    {
        return [
            'id' => (int) $a->id,
            'status' => (string) $a->status,
            'scheduled_at' => optional($a->scheduled_at)->toIso8601String(),
            'duration_minutes' => (int) $a->duration_minutes,
            'service_price_id' => $a->service_price_id ? (int) $a->service_price_id : null,
            'reason' => $a->reason,
            'specialty_services' => $a->servicesSummary(),
            'billing' => app(\App\Services\Clinics\ClinicBillingService::class)->summary($a),
            'deposit' => app(\App\Services\VisitDepositService::class)->stateFor($a),
            'attendee' => $a->attendee_name ? ['name' => $a->attendee_name, 'phone' => $a->attendee_phone] : null,
            'physician' => \App\Support\Physicians::party($a->physician_id ? (int) $a->physician_id : null, (int) $a->clinic_id),
            // «يجب التواجد قبل الموعد بـ ١٥ دقيقة» — the clinic's own words
            'arrival_notice' => \App\Services\ArrivalNotice::forBusiness((int) $a->clinic_id),
            'prescription_id' => $a->relationLoaded('prescription') && $a->prescription
                ? (int) $a->prescription->id : null,
            'clinic' => $a->relationLoaded('clinic') && $a->clinic
                ? ['id' => (int) $a->clinic->id, 'name' => $a->clinic->displayName(), 'logo' => $a->clinic->logo]
                : ['id' => (int) $a->clinic_id],
            'patient' => $a->relationLoaded('patient') && $a->patient
                ? ['id' => (int) $a->patient->id, 'name' => $a->patient->name]
                : ['id' => (int) $a->patient_id],
        ];
    }
}
