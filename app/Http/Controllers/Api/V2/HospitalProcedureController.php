<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\MedicalProcedure;
use App\Models\ProcedureRequest;
use App\Models\User;
use App\Services\Hospitals\HospitalProcedureService;
use App\Support\BusinessContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Medical procedures of a hospital (see HospitalProcedureService).
 *
 *   patient   GET hospitals/{hospital}/procedures, POST procedure-requests, GET procedure-requests, POST …/{id}/cancel
 *   hospital  GET|PUT business/hospital-procedures, POST|DELETE business/hospital-procedures/custom,
 *             GET business/procedure-requests, POST …/{id}/accept|decline|complete
 */
class HospitalProcedureController extends Controller
{
    public function __construct(private readonly HospitalProcedureService $service)
    {
    }

    private function hospitalOrFail(Request $request): User
    {
        $hospital = User::query()->findOrFail(BusinessContext::id($request));
        abort_unless($this->service->isHospital($hospital), 403, __('الإجراءات الطبية للمستشفيات والمراكز الطبية فقط.'));

        return $hospital;
    }

    // ───────────────────────── patient ─────────────────────────

    /** GET /api/v2/hospitals/{hospital}/procedures */
    public function offered(int $hospital)
    {
        $user = User::query()->where('type', User::TYPE_BUSINESS)->findOrFail($hospital);
        abort_unless($this->service->isHospital($user), 404);

        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->offeredBy($user)]]);
    }

    /** POST /api/v2/procedure-requests — {hospital_procedure_id, preferred_date?, notes?} */
    public function store(Request $request)
    {
        $data = $request->validate([
            'hospital_procedure_id' => ['required', 'integer'],
            'preferred_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = $this->service->request($request->user(), (int) $data['hospital_procedure_id'], $data['preferred_date'] ?? null, $data['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال طلبك إلى المستشفى.'),
            'data' => ['request' => $this->serialize($row->load('hospital:id,name,name_en', 'patient:id,name,phone'))],
        ], 201);
    }

    /** GET /api/v2/procedure-requests — the caller's own, as a patient. */
    public function index(Request $request)
    {
        $rows = ProcedureRequest::query()->where('patient_id', (int) $request->user()->id)
            ->with('hospital:id,name,name_en')->latest('id')->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (ProcedureRequest $r) => $this->serialize($r));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /api/v2/procedure-requests/{id}/cancel */
    public function cancel(Request $request, int $row)
    {
        $r = ProcedureRequest::query()->where('patient_id', (int) $request->user()->id)->findOrFail($row);

        return response()->json(['success' => true, 'data' => ['request' => $this->serialize($this->service->cancel($r)->load('hospital:id,name,name_en'))]]);
    }

    // ───────────────────────── hospital ─────────────────────────

    /** GET /api/v2/business/hospital-procedures */
    public function catalog(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->catalogOf($this->hospitalOrFail($request))]]);
    }

    /** PUT /api/v2/business/hospital-procedures — {items: [{procedure_id, offered, price}]} */
    public function save(Request $request)
    {
        $hospital = $this->hospitalOrFail($request);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.procedure_id' => ['required', 'integer'],
            'items.*.offered' => ['nullable', 'boolean'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $this->service->save($hospital, $data['items']);

        return response()->json(['success' => true, 'message' => __('تم الحفظ.'), 'data' => ['kinds' => $this->service->catalogOf($hospital)]]);
    }

    /** POST /api/v2/business/hospital-procedures/custom — {kind, name} */
    public function addCustom(Request $request)
    {
        $hospital = $this->hospitalOrFail($request);
        $data = $request->validate(['kind' => ['required', Rule::in(MedicalProcedure::KINDS)], 'name' => ['required', 'string', 'max:160']]);

        $this->service->addOwn($hospital, $data['kind'], $data['name']);

        return response()->json(['success' => true, 'message' => __('تمت إضافة الإجراء.'), 'data' => ['kinds' => $this->service->catalogOf($hospital)]], 201);
    }

    /** DELETE /api/v2/business/hospital-procedures/custom/{procedure} */
    public function deleteCustom(Request $request, int $procedure)
    {
        $hospital = $this->hospitalOrFail($request);
        $this->service->deleteOwn($hospital, MedicalProcedure::query()->findOrFail($procedure));

        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->catalogOf($hospital)]]);
    }

    /** GET /api/v2/business/procedure-requests?tab=incoming|upcoming|done */
    public function requests(Request $request)
    {
        $hospital = $this->hospitalOrFail($request);
        $tab = (string) $request->query('tab', 'incoming');

        $statuses = match ($tab) {
            'upcoming' => [ProcedureRequest::STATUS_ACCEPTED],
            'done' => [ProcedureRequest::STATUS_COMPLETED, ProcedureRequest::STATUS_DECLINED, ProcedureRequest::STATUS_CANCELLED],
            default => [ProcedureRequest::STATUS_REQUESTED],
        };

        $rows = ProcedureRequest::query()->where('hospital_id', (int) $hospital->id)->whereIn('status', $statuses)
            ->with('patient:id,name,phone')->latest('id')->paginate((int) $request->get('per_page', 30));

        $rows->getCollection()->transform(fn (ProcedureRequest $r) => $this->serialize($r));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    private function ownRequest(Request $request, int $row): ProcedureRequest
    {
        $hospital = $this->hospitalOrFail($request);

        return ProcedureRequest::query()->where('hospital_id', (int) $hospital->id)->with('patient:id,name,phone')->findOrFail($row);
    }

    /** POST /api/v2/business/procedure-requests/{id}/accept — {scheduled_at, price?, note?} */
    public function accept(Request $request, int $row)
    {
        $r = $this->ownRequest($request, $row);
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $r = $this->service->accept($r, Carbon::parse($data['scheduled_at']), isset($data['price']) ? (float) $data['price'] : null, $data['note'] ?? null);

        return response()->json(['success' => true, 'data' => ['request' => $this->serialize($r->load('patient:id,name,phone'))]]);
    }

    /** POST /api/v2/business/procedure-requests/{id}/decline — {note?} */
    public function decline(Request $request, int $row)
    {
        $r = $this->ownRequest($request, $row);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        return response()->json(['success' => true, 'data' => ['request' => $this->serialize($this->service->decline($r, $data['note'] ?? null)->load('patient:id,name,phone'))]]);
    }

    /** POST /api/v2/business/procedure-requests/{id}/complete */
    public function complete(Request $request, int $row)
    {
        $r = $this->ownRequest($request, $row);

        return response()->json(['success' => true, 'data' => ['request' => $this->serialize($this->service->complete($r)->load('patient:id,name,phone'))]]);
    }

    private function serialize(ProcedureRequest $r): array
    {
        return [
            'id' => (int) $r->id,
            'status' => (string) $r->status,
            'kind' => (string) $r->kind,
            'name' => (string) $r->name,
            'price' => $r->price,
            'preferred_date' => optional($r->preferred_date)->toDateString(),
            'notes' => $r->notes,
            'scheduled_at' => optional($r->scheduled_at)->toIso8601String(),
            'hospital_note' => $r->hospital_note,
            'created_at' => optional($r->created_at)->toIso8601String(),
            'hospital' => $r->relationLoaded('hospital') && $r->hospital
                ? ['id' => (int) $r->hospital->id, 'name' => $r->hospital->displayName()]
                : ['id' => (int) $r->hospital_id],
            'patient' => $r->relationLoaded('patient') && $r->patient
                ? ['id' => (int) $r->patient->id, 'name' => $r->patient->displayName(), 'phone' => $r->patient->phone]
                : ['id' => (int) $r->patient_id],
        ];
    }
}
