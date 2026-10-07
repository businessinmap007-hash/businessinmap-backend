<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\InvestigationOrder;
use App\Models\User;
use App\Services\Investigations\InvestigationOrderService;
use App\Services\Media\ImageUploadService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Investigation orders — a doctor's lab tests and radiology exams for a patient (see InvestigationOrderService).
 *
 *   doctor   POST investigation-orders, GET investigation-orders/issued
 *   patient  GET investigation-orders, GET …/{id}, GET …/{id}/centers, POST …/{id}/send, POST investigation-orders/request
 *   centre   GET business/investigation-orders, POST business/investigation-orders/{id}/accept|decline|results
 *
 * Only the doctor, the patient and the centre the order was sent to may read it; everyone else gets a 404.
 */
class InvestigationOrderController extends Controller
{
    public function __construct(private readonly InvestigationOrderService $service)
    {
    }

    /** GET /api/v2/investigations/catalog — the tests and exams a doctor picks from, never typed. */
    public function catalog()
    {
        return response()->json(['success' => true, 'data' => $this->service->catalog()]);
    }

    /** GET /api/v2/investigation-centers/{center}/tests — what this lab or radiology centre does and charges, for its page. */
    public function centerTests(int $center)
    {
        $business = User::query()->where('type', User::TYPE_BUSINESS)->findOrFail($center);
        abort_unless(InvestigationOrder::isCenter($business), 404);

        return response()->json(['success' => true, 'data' => ['tests' => $this->service->testsOf($business)]]);
    }

    // ───────────────────────── the doctor ─────────────────────────

    /** POST /api/v2/investigation-orders — a doctor orders tests for a patient. */
    public function store(Request $request)
    {
        $doctor = $this->issuerOrFail($request);

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([(int) $doctor->id])],
            'option_ids' => ['required', 'array', 'min:1', 'max:60'],
            'option_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = $this->service->issue($doctor, User::query()->findOrFail((int) $data['patient_id']), $data['option_ids'], $data['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال طلب الفحوصات إلى المريض.'),
            'data' => ['order' => $this->serialize($order->fresh(['items', 'patient:id,name', 'center:id,name']), $request->user())],
        ], 201);
    }

    /** GET /api/v2/investigation-orders/issued — a doctor's own orders. */
    public function issued(Request $request)
    {
        $doctor = $this->issuerOrFail($request);

        $rows = InvestigationOrder::query()->where('doctor_id', (int) $doctor->id)
            ->with(['items', 'patient:id,name', 'center:id,name', 'images'])->latest('id')->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (InvestigationOrder $o) => $this->serialize($o, $request->user()));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // ───────────────────────── the patient ─────────────────────────

    /** GET /api/v2/investigation-orders — the caller's orders as a patient. */
    public function index(Request $request)
    {
        $rows = InvestigationOrder::query()->where('patient_id', (int) $request->user()->id)
            ->with(['items', 'doctor:id,name,name_en,medical_title', 'center:id,name', 'images'])->latest('id')->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (InvestigationOrder $o) => $this->serialize($o, $request->user()));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** GET /api/v2/investigation-orders/{id} — any of the three parties. */
    public function show(Request $request, int $order)
    {
        return response()->json(['success' => true, 'data' => ['order' => $this->serialize($this->partyOrFail($request, $order), $request->user())]]);
    }

    /** GET /api/v2/investigation-orders/{id}/centers — the registered centres and what each charges for the whole order. */
    public function centers(Request $request, int $order)
    {
        $row = $this->patientOrderOrFail($request, $order);

        return response()->json(['success' => true, 'data' => ['centers' => $this->service->centersFor($row->load('items'))]]);
    }

    /** POST /api/v2/investigation-orders/{id}/send — the patient shares the order with a centre. */
    public function send(Request $request, int $order)
    {
        $row = $this->patientOrderOrFail($request, $order);
        $data = $request->validate(['center_id' => ['required', 'integer', 'exists:users,id']]);

        $row = $this->service->send($row->load('items'), User::query()->findOrFail((int) $data['center_id']));

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال الطلب إلى الجهة.'),
            'data' => ['order' => $this->serialize($row->fresh(['items', 'doctor:id,name', 'center:id,name', 'images']), $request->user())],
        ]);
    }

    /** POST /api/v2/investigation-orders/request — the patient asks a centre directly (no doctor), optionally with a photo of a paper request. */
    public function requestFromCenter(Request $request)
    {
        $data = $request->validate([
            'center_id' => ['required', 'integer', 'exists:users,id'],
            'option_ids' => ['required', 'array', 'min:1', 'max:60'],
            'option_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => array_merge(['nullable'], ImageUploadService::validationRules()),
        ]);

        $order = $this->service->requestFromCenter(
            $request->user(), User::query()->findOrFail((int) $data['center_id']), $data['option_ids'], $data['notes'] ?? null, $request->file('photo')
        );

        return response()->json([
            'success' => true,
            'message' => __('تم إرسال طلبك إلى الجهة.'),
            'data' => ['order' => $this->serialize($order->fresh(['items', 'center:id,name', 'images']), $request->user())],
        ], 201);
    }

    /** POST /api/v2/investigation-orders/{id}/cancel — the doctor or the patient, until the centre accepts. */
    public function cancel(Request $request, int $order)
    {
        $row = $this->partyOrFail($request, $order);
        abort_unless(in_array((int) $request->user()->id, [(int) $row->doctor_id, (int) $row->patient_id], true) || (int) BusinessContext::id($request) === (int) $row->doctor_id, 404);

        return response()->json(['success' => true, 'data' => ['order' => $this->serialize($this->service->cancel($row), $request->user())]]);
    }

    // ───────────────────────── the centre ─────────────────────────

    /** GET /api/v2/business/investigation-prices — the centre's own price list of the platform's tests and exams. */
    public function priceList(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['tests' => $this->service->priceListOf($this->centerOrFail($request))]]);
    }

    /** PUT /api/v2/business/investigation-prices — `{prices: {optionId: price|null}}` (null or 0 = the centre does not do it). */
    public function savePrices(Request $request)
    {
        $center = $this->centerOrFail($request);
        $data = $request->validate(['prices' => ['required', 'array', 'max:400'], 'prices.*' => ['nullable', 'numeric', 'min:0', 'max:1000000']]);

        return response()->json(['success' => true, 'data' => ['tests' => $this->service->savePrices($center, $data['prices'])]]);
    }

    /** GET /api/v2/business/investigation-orders?tab=incoming|accepted|done */
    public function centerIndex(Request $request)
    {
        $center = $this->centerOrFail($request);
        $tab = (string) $request->get('tab', 'incoming');
        $statuses = match ($tab) {
            'accepted' => [InvestigationOrder::STATUS_ACCEPTED],
            'done' => [InvestigationOrder::STATUS_READY, InvestigationOrder::STATUS_DECLINED],
            default => [InvestigationOrder::STATUS_SENT],
        };

        $rows = InvestigationOrder::query()->where('center_id', (int) $center->id)->whereIn('status', $statuses)
            ->with(['items', 'doctor:id,name,name_en,medical_title', 'patient:id,name', 'images'])->latest('id')->paginate((int) $request->get('per_page', 20));

        $rows->getCollection()->transform(fn (InvestigationOrder $o) => $this->serialize($o, $request->user()));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /** POST /api/v2/business/investigation-orders/{id}/accept — `{appointment_at?, note?}` */
    public function accept(Request $request, int $order)
    {
        $row = $this->centerOrderOrFail($request, $order);
        $data = $request->validate(['appointment_at' => ['nullable', 'date', 'after:now'], 'note' => ['nullable', 'string', 'max:1000']]);

        $row = $this->service->accept($row, ! empty($data['appointment_at']) ? new \DateTimeImmutable($data['appointment_at']) : null, $data['note'] ?? null);

        return response()->json(['success' => true, 'data' => ['order' => $this->serialize($row->load(['items', 'patient:id,name', 'doctor:id,name', 'images']), $request->user())]]);
    }

    /** POST /api/v2/business/investigation-orders/{id}/decline — `{note?}` */
    public function decline(Request $request, int $order)
    {
        $row = $this->centerOrderOrFail($request, $order);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $row = $this->service->decline($row, $data['note'] ?? null);

        return response()->json(['success' => true, 'data' => ['order' => $this->serialize($row->load(['patient:id,name', 'doctor:id,name', 'images']), $request->user())]]);
    }

    /** POST /api/v2/business/investigation-orders/{id}/results — photos of the results (multipart `images[]`) and a note. */
    public function results(Request $request, int $order)
    {
        $row = $this->centerOrderOrFail($request, $order);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ImageUploadService::validationRules(),
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $row = $this->service->attachResults($row, $request->file('images', []), $data['note'] ?? null);

        return response()->json(['success' => true, 'data' => ['order' => $this->serialize($row->load(['patient:id,name', 'doctor:id,name']), $request->user())]]);
    }

    // ───────────────────────── a file, from private storage ─────────────────────────

    /** GET /api/v2/investigation-files/{image} — `signed` middleware, like the training-plan photos. */
    public function file(int $image)
    {
        $row = Image::query()->whereKey($image)->where('imageable_type', InvestigationOrder::class)->first();

        abort_unless($row && ImageUploadService::isPrivate($row->image), 404);

        $full = ImageUploadService::privatePath($row->image);
        abort_unless(is_file($full), 404);

        return response()->file($full, ['Cache-Control' => 'private, max-age=21600', 'X-Content-Type-Options' => 'nosniff']);
    }

    // ───────────────────────── helpers ─────────────────────────

    private function issuerOrFail(Request $request): User
    {
        $business = BusinessContext::business($request);

        abort_unless($this->service->canIssue($business), 403, __('هذه الخدمة للأطباء والجهات الطبية.'));

        return $business;
    }

    private function centerOrFail(Request $request): User
    {
        $business = BusinessContext::business($request);

        abort_unless($this->service->canReceive($business), 403, __('هذه الخدمة للمعامل ومراكز الأشعة.'));

        return $business;
    }

    private function patientOrderOrFail(Request $request, int $id): InvestigationOrder
    {
        return InvestigationOrder::query()->where('patient_id', (int) $request->user()->id)->findOrFail($id);
    }

    private function centerOrderOrFail(Request $request, int $id): InvestigationOrder
    {
        $center = $this->centerOrFail($request);

        return InvestigationOrder::query()->where('center_id', (int) $center->id)->with('items')->findOrFail($id);
    }

    /** The doctor, the patient, the centre — a business may be acting through a delegate. */
    private function partyOrFail(Request $request, int $id): InvestigationOrder
    {
        $row = InvestigationOrder::query()->with(['items', 'doctor:id,name,name_en,medical_title', 'patient:id,name', 'center:id,name', 'images'])->findOrFail($id);

        abort_unless($row->isParty((int) $request->user()->id) || $row->isParty((int) BusinessContext::id($request)), 404);

        return $row;
    }

    /** @return array<string,mixed> */
    private function serialize(InvestigationOrder $o, ?User $viewer): array
    {
        $o->loadMissing('images');

        return [
            'id' => (int) $o->id,
            'status' => (string) $o->status,
            'doctor' => $o->doctor ? ['id' => (int) $o->doctor->id, 'name' => $o->doctor->name] : null,
            'patient' => $o->patient ? ['id' => (int) $o->patient->id, 'name' => $o->patient->name] : null,
            'center' => $o->center ? ['id' => (int) $o->center->id, 'name' => $o->center->name] : null,
            'items' => $o->items->map(fn ($i) => ['id' => (int) $i->id, 'kind' => $i->kind, 'name' => $i->name, 'price' => $i->price])->values(),
            'total' => $o->total,
            'notes' => $o->notes,
            'center_note' => $o->center_note,
            'appointment_at' => $o->appointment_at?->toIso8601String(),
            'issued_at' => $o->issued_at?->toIso8601String(),
            'sent_at' => $o->sent_at?->toIso8601String(),
            'ready_at' => $o->ready_at?->toIso8601String(),
            'request_files' => $o->filesOf(InvestigationOrder::PURPOSE_REQUEST),
            'result_files' => $o->filesOf(InvestigationOrder::PURPOSE_RESULT),
        ];
    }
}
