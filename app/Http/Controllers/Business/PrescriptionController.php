<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Api\V2\MedicineController;
use App\Http\Controllers\Api\V2\PrescriptionController as ApiPrescriptionController;
use App\Http\Controllers\Controller;
use App\Models\ClinicAppointment;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * «نسخة الطبيب على الهاتف والكمبيوتر أيضًا» — المالك، 2026-10-05: the doctor's prescriptions on the web panel.
 *
 * The page is a face on the SAME doors the app uses — the issue/list/ack actions of
 * {@see ApiPrescriptionController} and the dictionary search of {@see MedicineController} — so a prescription
 * written at the desk obeys every rule the phone's does (the dictionary only, the handwritten photo for a controlled
 * drug, the fingerprint). Only a physician's practice (Prescription::DOCTOR_CHILD_IDS) may use it.
 */
class PrescriptionController extends Controller
{
    public function __construct(private readonly ApiPrescriptionController $prescriptions)
    {
    }

    public function index(): View
    {
        abort_unless(Prescription::isDoctorBusiness(Auth::user()), 403, __('إصدار الوصفات متاح لحسابات العيادات والمستشفيات والمراكز الطبية فقط.'));

        return view('business.prescriptions.index');
    }

    /** The doctor's issued prescriptions (paginated, newest first) — each with its verifiable content and flags. */
    public function issued(Request $request)
    {
        return $this->prescriptions->issued($request);
    }

    /** The dictionary typeahead: by trade name, active ingredient or Arabic alias. */
    public function medicines(Request $request)
    {
        return app(MedicineController::class)->index($request);
    }

    /**
     * The visits this clinic knows about — a prescription is written for a patient from a visit on the clinic's own
     * list, never from a bare name or phone search (the same rule as the app).
     */
    public function appointments(Request $request): JsonResponse
    {
        abort_unless(Prescription::isDoctorBusiness(Auth::user()), 403);

        $rows = ClinicAppointment::query()
            ->where('clinic_id', (int) Auth::id())
            ->whereNotIn('status', [ClinicAppointment::STATUS_CANCELLED, ClinicAppointment::STATUS_NO_SHOW])
            ->where('scheduled_at', '>=', now()->subDays(60))
            ->with('patient:id,name,phone')
            ->orderByDesc('scheduled_at')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'data' => $rows->map(fn (ClinicAppointment $a) => [
            'id' => (int) $a->id,
            'patient_id' => (int) $a->patient_id,
            'patient_name' => (string) optional($a->patient)->name,
            'patient_phone' => (string) optional($a->patient)->phone,
            'scheduled_at' => optional($a->scheduled_at)->toIso8601String(),
            'status' => (string) $a->status,
        ])->values()]);
    }

    /** Issue — multipart (the handwritten photo) or JSON; every rule is the API's. */
    public function store(Request $request)
    {
        return $this->prescriptions->store($request);
    }

    /** The doctor's copy is held: `{id, content}`. */
    public function archived(Request $request)
    {
        return $this->prescriptions->archivedByDoctor($request);
    }
}
