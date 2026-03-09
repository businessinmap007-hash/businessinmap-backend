<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\ServiceFee;
use App\Models\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceFeeController extends Controller
{
    public function index(Request $request)
    {
        $serviceId = (int) $request->get('service_id', 0);
        $isActive  = $request->get('is_active', '');

        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en'])
            ->orderBy('name_ar')
            ->get();

        $rows = ServiceFee::query()
            ->with(['service:id,key,name_ar,name_en'])
            ->when($serviceId > 0, function ($query) use ($serviceId) {
                $query->where('service_id', $serviceId);
            })
            ->when($isActive !== '' && $isActive !== null, function ($query) use ($isActive) {
                $query->where('is_active', (int) $isActive);
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.service-fees.index', compact(
            'rows',
            'services',
            'serviceId',
            'isActive'
        ));
    }

    public function create()
    {
        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $row = new ServiceFee([
            'is_active' => 1,
            'amount' => 0,
        ]);

        return view('admin-v2.service-fees.create', compact('row', 'services'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = ServiceFee::create($data);

        return redirect()
            ->route('admin.service-fees.edit', $row)
            ->with('success', 'تم إنشاء رسم الخدمة بنجاح.');
    }

    public function edit(ServiceFee $serviceFee)
    {
        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->get();

        $row = $serviceFee->load('service:id,key,name_ar,name_en');

        return view('admin-v2.service-fees.edit', compact('row', 'services'));
    }

    public function update(Request $request, ServiceFee $serviceFee)
    {
        $data = $this->validateData($request, $serviceFee->id);

        $serviceFee->update($data);

        return back()->with('success', 'تم تحديث رسم الخدمة بنجاح.');
    }

    public function destroy(ServiceFee $serviceFee)
    {
        $serviceFee->delete();

        return redirect()
            ->route('admin.service-fees.index')
            ->with('success', 'تم حذف السجل بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique('service_fees', 'code')->ignore($ignoreId),
            ],
            'service_id' => [
                'nullable',
                'integer',
                'exists:platform_services,id',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
            ],
            'rules' => ['nullable'],
            'is_active' => ['nullable'],
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');

        if (isset($data['rules']) && is_string($data['rules']) && trim($data['rules']) !== '') {
            $decoded = json_decode($data['rules'], true);
            $data['rules'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } else {
            $data['rules'] = null;
        }

        return $data;
    }
}