<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\SpecialtyService;
use App\Models\User;
use App\Services\Clinics\SpecialtyServicesService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The services of a specialty clinic — dental, dermatology and cosmetic (see SpecialtyServicesService).
 *
 *   patient  GET clinics/{clinic}/specialty-services
 *   clinic   GET|PUT business/specialty-services, POST|DELETE business/specialty-services/custom
 */
class SpecialtyServiceController extends Controller
{
    public function __construct(private readonly SpecialtyServicesService $service)
    {
    }

    private function clinicOrFail(Request $request): User
    {
        $clinic = User::query()->findOrFail(BusinessContext::id($request));
        abort_unless($this->service->hasLists($clinic), 403, __('قائمة الخدمات لعيادات الأسنان والجلدية والتجميل فقط.'));

        return $clinic;
    }

    /** GET /api/v2/clinics/{clinic}/specialty-services */
    public function offered(int $clinic)
    {
        $user = User::query()->where('type', User::TYPE_BUSINESS)->findOrFail($clinic);
        abort_unless($this->service->hasLists($user), 404);

        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->offeredBy($user)]]);
    }

    /** GET /api/v2/business/specialty-services */
    public function catalog(Request $request)
    {
        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->catalogOf($this->clinicOrFail($request))]]);
    }

    /** PUT /api/v2/business/specialty-services — {items: [{service_id, offered, price}]} */
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

    /** POST /api/v2/business/specialty-services/custom — {kind, name} */
    public function addCustom(Request $request)
    {
        $clinic = $this->clinicOrFail($request);
        $data = $request->validate(['kind' => ['required', Rule::in(SpecialtyService::allKinds())], 'name' => ['required', 'string', 'max:160']]);

        $this->service->addOwn($clinic, $data['kind'], $data['name']);

        return response()->json(['success' => true, 'message' => __('تمت إضافة الخدمة.'), 'data' => ['kinds' => $this->service->catalogOf($clinic)]], 201);
    }

    /** DELETE /api/v2/business/specialty-services/custom/{service} */
    public function deleteCustom(Request $request, int $service)
    {
        $clinic = $this->clinicOrFail($request);
        $this->service->deleteOwn($clinic, SpecialtyService::query()->findOrFail($service));

        return response()->json(['success' => true, 'data' => ['kinds' => $this->service->catalogOf($clinic)]]);
    }
}
