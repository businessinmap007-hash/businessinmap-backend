<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\DentalService;
use App\Models\User;
use App\Services\Clinics\DentalServicesService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The services of a dental clinic (see DentalServicesService).
 *
 *   patient  GET clinics/{clinic}/dental-services
 *   clinic   GET|PUT business/dental-services, POST|DELETE business/dental-services/custom
 */
class DentalServiceController extends Controller
{
    public function __construct(private readonly DentalServicesService $service)
    {
    }

    private function clinicOrFail(Request $request): User
    {
        $clinic = User::query()->findOrFail(BusinessContext::id($request));
        abort_unless($this->service->isDentalClinic($clinic), 403, __('قائمة خدمات الأسنان لعيادات الأسنان فقط.'));

        return $clinic;
    }

    /** GET /api/v2/clinics/{clinic}/dental-services */
    public function offered(int $clinic)
    {
        $user = User::query()->where('type', User::TYPE_BUSINESS)->findOrFail($clinic);
        abort_unless($this->service->isDentalClinic($user), 404);

        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->offeredBy($user)]]);
    }

    /** GET /api/v2/business/dental-services */
    public function catalog(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->catalogOf($this->clinicOrFail($request))]]);
    }

    /** PUT /api/v2/business/dental-services — {items: [{service_id, offered, price}]} */
    public function save(Request $request)
    {
        $clinic = $this->clinicOrFail($request);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.service_id' => ['required', 'integer'],
            'items.*.offered' => ['nullable', 'boolean'],
            'items.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $this->service->save($clinic, $data['items']);

        return response()->json(['success' => true, 'message' => __('تم الحفظ.'), 'data' => ['kinds' => $this->service->catalogOf($clinic)]]);
    }

    /** POST /api/v2/business/dental-services/custom — {kind, name} */
    public function addCustom(Request $request)
    {
        $clinic = $this->clinicOrFail($request);
        $data = $request->validate(['kind' => ['required', Rule::in(DentalService::KINDS)], 'name' => ['required', 'string', 'max:160']]);

        $this->service->addOwn($clinic, $data['kind'], $data['name']);

        return response()->json(['success' => true, 'message' => __('تمت إضافة الخدمة.'), 'data' => ['kinds' => $this->service->catalogOf($clinic)]], 201);
    }

    /** DELETE /api/v2/business/dental-services/custom/{service} */
    public function deleteCustom(Request $request, int $service)
    {
        $clinic = $this->clinicOrFail($request);
        $this->service->deleteOwn($clinic, DentalService::query()->findOrFail($service));

        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->catalogOf($clinic)]]);
    }
}
