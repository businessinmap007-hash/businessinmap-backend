<?php

namespace App\Services\Hospitals;

use App\Models\HospitalDoctor;
use App\Models\HospitalProcedure;
use App\Models\MedicalProcedure;
use App\Models\ProcedureRequest;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «إجراء طبي في المستشفى» — a hospital offers surgeries, endoscopies and treatment procedures picked from the platform's
 * list (or added by itself), with its OWN price (or none: «السعر بعد التقييم»). A patient asks for one; the hospital
 * accepts it with a date (quoting a price when it had none), declines it, and marks it done.
 */
class HospitalProcedureService
{
    public function __construct(
        private readonly NotificationDispatcherService $notifications,
        private readonly \App\Services\VisitDepositService $visitDeposits,
    )
    {
    }

    public function isHospital(?User $user): bool
    {
        return HospitalDoctor::isHospital($user);
    }

    // ───────────────────────── the hospital's own catalogue ─────────────────────────

    /**
     * Every procedure the hospital can offer — the platform's list and its own — with what it offers now.
     *
     * @return list<array{kind:string,procedures:list<array<string,mixed>>}>
     */
    public function catalogOf(User $hospital): array
    {
        $offered = HospitalProcedure::query()->where('hospital_id', (int) $hospital->id)->get()->keyBy('procedure_id');

        $rows = MedicalProcedure::query()->visibleTo((int) $hospital->id)->orderBy('kind')->orderBy('sort_order')->orderBy('id')->get();

        $out = [];
        foreach (MedicalProcedure::KINDS as $kind) {
            $out[] = [
                'kind' => $kind,
                'procedures' => $rows->where('kind', $kind)->map(function (MedicalProcedure $p) use ($offered) {
                    $o = $offered->get($p->id);

                    return [
                        'id' => (int) $p->id,
                        'name' => $p->label(),
                        'own' => $p->owner_id !== null,
                        'offered' => $o !== null,
                        'price' => $o?->price,
                    ];
                })->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Save what the hospital offers: `procedure_id`, `offered`, and `price` (null/0 = after assessment). Only entries
     * of the platform list or the hospital's own are accepted — never another hospital's.
     *
     * @param  list<array{procedure_id:int,offered?:bool,price?:float|int|string|null}>  $items
     */
    public function save(User $hospital, array $items): void
    {
        $allowed = MedicalProcedure::query()->visibleTo((int) $hospital->id)->pluck('id')->flip();

        DB::transaction(function () use ($hospital, $items, $allowed) {
            foreach ($items as $item) {
                $id = (int) ($item['procedure_id'] ?? 0);

                if (! $allowed->has($id)) {
                    throw ValidationException::withMessages(['items' => __('إجراء غير موجود في القائمة.')]);
                }

                if (! ($item['offered'] ?? true)) {
                    HospitalProcedure::query()->where('hospital_id', $hospital->id)->where('procedure_id', $id)->delete();

                    continue;
                }

                $price = isset($item['price']) && is_numeric($item['price']) && (float) $item['price'] > 0 ? round((float) $item['price'], 2) : null;

                HospitalProcedure::query()->updateOrCreate(
                    ['hospital_id' => (int) $hospital->id, 'procedure_id' => $id],
                    ['price' => $price],
                );
            }
        });
    }

    /** A procedure the platform list does not have: the hospital's own, offered at once. */
    public function addOwn(User $hospital, string $kind, string $name): MedicalProcedure
    {
        $name = trim($name);

        if (! in_array($kind, MedicalProcedure::KINDS, true) || $name === '') {
            throw ValidationException::withMessages(['name' => __('اكتب اسم الإجراء واختر نوعه.')]);
        }

        if (MedicalProcedure::query()->visibleTo((int) $hospital->id)->where('kind', $kind)->where('name_ar', $name)->exists()) {
            throw ValidationException::withMessages(['name' => __('هذا الإجراء موجود في القائمة.')]);
        }

        $row = MedicalProcedure::create(['kind' => $kind, 'name_ar' => $name, 'owner_id' => (int) $hospital->id, 'sort_order' => 999]);
        HospitalProcedure::create(['hospital_id' => (int) $hospital->id, 'procedure_id' => (int) $row->id]);

        return $row;
    }

    public function deleteOwn(User $hospital, MedicalProcedure $procedure): void
    {
        abort_unless((int) $procedure->owner_id === (int) $hospital->id, 404);

        $procedure->delete();
    }

    // ───────────────────────── what a patient sees ─────────────────────────

    /**
     * What the hospital offers, by kind. `id` is the offering a patient asks for.
     *
     * @return list<array{kind:string,procedures:list<array<string,mixed>>}>
     */
    public function offeredBy(User $hospital): array
    {
        $rows = HospitalProcedure::query()->where('hospital_id', (int) $hospital->id)->with('procedure')->get()
            ->filter(fn (HospitalProcedure $o) => $o->procedure !== null);

        $out = [];
        foreach (MedicalProcedure::KINDS as $kind) {
            $in = $rows->filter(fn (HospitalProcedure $o) => $o->procedure->kind === $kind)
                ->sortBy(fn (HospitalProcedure $o) => [$o->procedure->sort_order, $o->id]);

            if ($in->isEmpty()) {
                continue;
            }

            $out[] = [
                'kind' => $kind,
                'procedures' => $in->map(fn (HospitalProcedure $o) => [
                    'id' => (int) $o->id,
                    'name' => $o->procedure->label(),
                    'price' => $o->price,
                    'notes' => $o->notes,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    // ───────────────────────── requests ─────────────────────────

    public function request(User $patient, int $hospitalProcedureId, ?string $preferredDate, ?string $notes): ProcedureRequest
    {
        $offering = HospitalProcedure::query()->with(['procedure', 'hospital'])->find($hospitalProcedureId);

        if (! $offering || ! $offering->procedure || ! $this->isHospital($offering->hospital)) {
            throw ValidationException::withMessages(['hospital_procedure_id' => __('هذا الإجراء غير متاح.')]);
        }

        if ((int) $offering->hospital_id === (int) $patient->id) {
            throw ValidationException::withMessages(['hospital_procedure_id' => __('لا يمكنك طلب إجراء من حسابك.')]);
        }

        // «عربون الجدية»: a hospital that asks for one is only asked of a patient who can stand it (a price not yet
        // known asks nothing yet — it is asked when the hospital accepts with its price)
        $this->visitDeposits->assertCanAfford((int) $patient->id, (int) $offering->hospital_id, (float) $offering->price);

        $request = ProcedureRequest::create([
            'patient_id' => (int) $patient->id,
            'hospital_id' => (int) $offering->hospital_id,
            'procedure_id' => (int) $offering->procedure_id,
            'kind' => $offering->procedure->kind,
            'name' => $offering->procedure->name_ar,
            'price' => $offering->price,
            'preferred_date' => $preferredDate ?: null,
            'notes' => $notes ?: null,
            'status' => ProcedureRequest::STATUS_REQUESTED,
        ]);

        $this->notify('procedure_requested', (int) $offering->hospital_id, $request,
            'طلب إجراء طبي', 'Procedure request',
            'طلب مريض إجراء «' . $request->name . '».', 'A patient asked for “' . $request->name . '”.');

        return $request;
    }

    /**
     * «قرار إجراء جراحي»: the treating physician decides the patient needs one of the hospital's procedures. It reaches
     * the patient as a decision he answers — he confirms (it becomes a request the hospital schedules) or declines.
     * The hospital hears nothing until the patient confirms.
     */
    public function advise(User $hospital, ?int $physicianId, int $patientId, int $hospitalProcedureId, ?string $notes, ?int $appointmentId = null): ProcedureRequest
    {
        $offering = HospitalProcedure::query()->with('procedure')
            ->where('hospital_id', (int) $hospital->id)->find($hospitalProcedureId);

        if (! $offering || ! $offering->procedure) {
            throw ValidationException::withMessages(['hospital_procedure_id' => __('هذا الإجراء غير متاح لدى المستشفى.')]);
        }

        if ($patientId === (int) $hospital->id || $patientId === $physicianId) {
            throw ValidationException::withMessages(['patient_id' => __('اختر المريض.')]);
        }

        if ($appointmentId !== null) {
            $visit = \App\Models\ClinicAppointment::query()->find($appointmentId);

            if (! $visit || (int) $visit->clinic_id !== (int) $hospital->id || (int) $visit->patient_id !== $patientId) {
                throw ValidationException::withMessages(['appointment_id' => __('هذا الموعد لا يخص هذا المريض.')]);
            }
        }

        $request = ProcedureRequest::create([
            'patient_id' => $patientId,
            'hospital_id' => (int) $hospital->id,
            'procedure_id' => (int) $offering->procedure_id,
            'kind' => $offering->procedure->kind,
            'name' => $offering->procedure->name_ar,
            'price' => $offering->price,
            'notes' => $notes ?: null,
            'status' => ProcedureRequest::STATUS_ADVISED,
            'physician_id' => $physicianId,
            'appointment_id' => $appointmentId,
            'source' => 'physician',
        ]);

        $this->notify('procedure_advised', $patientId, $request,
            'قرار إجراء طبي', 'A procedure was advised',
            'قرر الطبيب لك إجراء «' . $request->name . '». افتح طلباتك لتؤكّد أو تعتذر.',
            'Your physician advised “' . $request->name . '”. Open your requests to confirm or decline.');

        return $request;
    }

    /** The patient accepts the physician's decision: it becomes a request the hospital schedules. */
    public function confirm(ProcedureRequest $request): ProcedureRequest
    {
        $this->assertStatus($request, [ProcedureRequest::STATUS_ADVISED]);
        $request->update(['status' => ProcedureRequest::STATUS_REQUESTED]);

        $this->notify('procedure_requested', (int) $request->hospital_id, $request,
            'طلب إجراء طبي', 'Procedure request',
            'أكّد مريض قرار الطبيب بإجراء «' . $request->name . '».', 'A patient confirmed the physician’s decision: “' . $request->name . '”.');

        if ($request->physician_id && (int) $request->physician_id !== (int) $request->hospital_id) {
            $this->notify('procedure_requested', (int) $request->physician_id, $request,
                'أكّد المريض قرارك', 'The patient confirmed your decision',
                'أكّد المريض إجراء «' . $request->name . '».', 'The patient confirmed “' . $request->name . '”.');
        }

        return $request->fresh();
    }

    public function accept(ProcedureRequest $request, Carbon $at, ?float $price, ?string $note): ProcedureRequest
    {
        $this->assertStatus($request, [ProcedureRequest::STATUS_REQUESTED]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $at, $price, $note) {
            $request->update([
                'status' => ProcedureRequest::STATUS_ACCEPTED,
                'scheduled_at' => $at,
                'hospital_note' => $note ?: null,
                // a quote only fills the gap when the hospital had no price — a listed price is never overwritten here
                'price' => $request->price ?? ($price !== null && $price > 0 ? round($price, 2) : null),
            ]);

            // the procedure stands: its deposit is frozen (or recorded as owed outside); a short wallet refuses
            // here and the request stays as it was
            $this->visitDeposits->secure($request->fresh());
        });

        $this->notify('procedure_accepted', (int) $request->patient_id, $request,
            'تم قبول طلب الإجراء', 'Procedure request accepted',
            'حدّد المستشفى موعد «' . $request->name . '» في ' . $at->format('Y-m-d H:i') . '.',
            'The hospital scheduled “' . $request->name . '” for ' . $at->format('Y-m-d H:i') . '.');

        return $request->fresh();
    }

    public function decline(ProcedureRequest $request, ?string $note): ProcedureRequest
    {
        $this->assertStatus($request, [ProcedureRequest::STATUS_REQUESTED]);
        $request->update(['status' => ProcedureRequest::STATUS_DECLINED, 'hospital_note' => $note ?: null]);
        $this->visitDeposits->safely(fn () => $this->visitDeposits->refund($request));

        $this->notify('procedure_declined', (int) $request->patient_id, $request,
            'اعتذر المستشفى عن الإجراء', 'Procedure request declined',
            'اعتذر المستشفى عن «' . $request->name . '».', 'The hospital declined “' . $request->name . '”.');

        return $request->fresh();
    }

    public function complete(ProcedureRequest $request): ProcedureRequest
    {
        $this->assertStatus($request, [ProcedureRequest::STATUS_ACCEPTED]);
        $request->update(['status' => ProcedureRequest::STATUS_COMPLETED]);
        $this->visitDeposits->safely(fn () => $this->visitDeposits->release($request));

        return $request->fresh();
    }

    public function cancel(ProcedureRequest $request): ProcedureRequest
    {
        $this->assertStatus($request, [ProcedureRequest::STATUS_ADVISED, ProcedureRequest::STATUS_REQUESTED, ProcedureRequest::STATUS_ACCEPTED]);
        $wasAdvised = $request->status === ProcedureRequest::STATUS_ADVISED;
        $request->update(['status' => ProcedureRequest::STATUS_CANCELLED]);
        $this->visitDeposits->safely(fn () => $this->visitDeposits->refund($request));

        // declining a physician's decision tells the physician; the hospital never heard of it
        if ($wasAdvised) {
            if ($request->physician_id) {
                $this->notify('procedure_cancelled', (int) $request->physician_id, $request,
                    'اعتذر المريض عن قرارك', 'The patient declined your decision',
                    'اعتذر المريض عن «' . $request->name . '».', 'The patient declined “' . $request->name . '”.');
            }

            return $request->fresh();
        }

        $this->notify('procedure_cancelled', (int) $request->hospital_id, $request,
            'ألغى المريض طلب الإجراء', 'Procedure request cancelled',
            'ألغى المريض طلب «' . $request->name . '».', 'The patient cancelled “' . $request->name . '”.');

        return $request->fresh();
    }

    private function assertStatus(ProcedureRequest $request, array $allowed): void
    {
        if (! in_array($request->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تنفيذ هذا الآن على هذا الطلب.')]);
        }
    }

    private function notify(string $eventKey, int $userId, ProcedureRequest $request, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, $userId, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => ProcedureRequest::class, 'notifiable_id' => (int) $request->id,
                'source_type' => ProcedureRequest::class, 'source_id' => (int) $request->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
