<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessServicePriceController extends Controller
{
    public function index(Request $request)
    {
        $serviceId  = (int) $request->get('platform_service_id', 0);
        $businessId = (int) $request->get('business_id', 0);
        $isActive   = $request->get('is_active', '');

        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $rows = BusinessServicePrice::query()
            ->with([
                'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent',
                'business:id,name',
            ])
            ->when($serviceId > 0, function ($query) use ($serviceId) {
                $query->where('platform_service_id', $serviceId);
            })
            ->when($businessId > 0, function ($query) use ($businessId) {
                $query->where('business_id', $businessId);
            })
            ->when($isActive !== '' && $isActive !== null, function ($query) use ($isActive) {
                $query->where('is_active', (int) $isActive);
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.business-service-prices.index', compact(
            'rows',
            'services',
            'businesses',
            'serviceId',
            'businessId',
            'isActive'
        ));
    }

    public function create()
    {
        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $row = new BusinessServicePrice([
            'is_active' => 1,
            'price' => 0,
            'deposit_enabled' => 0,
            'deposit_percent' => 0,
        ]);

        return view('admin-v2.business-service-prices.create', compact('row', 'services', 'businesses'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = BusinessServicePrice::updateOrCreate(
            [
                'business_id' => $data['business_id'],
                'platform_service_id' => $data['platform_service_id'],
            ],
            $data
        );

        return redirect()
            ->route('admin.business_service_prices.edit', $row)
            ->with('success', 'تم حفظ سعر الخدمة وإعدادات الديبوزت بنجاح.');
    }

    public function edit(BusinessServicePrice $row)
    {
        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $row->load([
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent',
            'business:id,name',
        ]);

        return view('admin-v2.business-service-prices.edit', compact('row', 'services', 'businesses'));
    }

    public function update(Request $request, BusinessServicePrice $row)
    {
        $data = $this->validateData($request, $row->id);

        $row->update($data);

        return back()->with('success', 'تم تحديث السجل بنجاح.');
    }

    public function destroy(BusinessServicePrice $row)
    {
        $row->delete();

        return redirect()
            ->route('admin.business_service_prices.index')
            ->with('success', 'تم حذف السجل بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'business_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'platform_service_id' => [
                'required',
                'integer',
                'exists:platform_services,id',
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
            ],
            'is_active' => ['nullable'],
            'deposit_enabled' => ['nullable'],
            'deposit_percent' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');
        $data['deposit_enabled'] = (int) $request->boolean('deposit_enabled');
        $data['deposit_percent'] = (int) ($data['deposit_percent'] ?? 0);

        $service = PlatformService::query()->find($data['platform_service_id']);

        if (!$service) {
            abort(422, 'الخدمة غير موجودة.');
        }

        if (!(bool) $service->supports_deposit) {
            $data['deposit_enabled'] = 0;
            $data['deposit_percent'] = 0;
            return $data;
        }

        if (!$data['deposit_enabled']) {
            $data['deposit_percent'] = 0;
            return $data;
        }

        $maxAllowed = (int) ($service->max_deposit_percent ?? 0);

        if ($data['deposit_percent'] > $maxAllowed) {
            abort(422, "نسبة الديبوزت المطلوبة تتجاوز الحد المسموح لهذه الخدمة ({$maxAllowed}%).");
        }

        return $data;
    }
}