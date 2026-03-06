<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\ServiceFee;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class ServiceFeeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $code = trim((string) $request->get('code', ''));
        $serviceId = (int) $request->get('service_id', 0);
        $isActive = $request->get('is_active', '');

        // مهم: نرسل الخدمات للفلتر في صفحة index
        $services = Service::query()
            ->select(['id', 'name_ar', 'name_en'])
            ->orderBy('name_ar')
            ->get();

        $rows = ServiceFee::query()
            ->with([
                'service:id,name_ar,name_en',
                'business:id,name,code',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->whereHas('business', function ($b) use ($q) {
                        $b->where('name', 'like', "%{$q}%")
                          ->orWhere('code', 'like', "%{$q}%");
                    })->orWhereHas('service', function ($s) use ($q) {
                        $s->where('name_ar', 'like', "%{$q}%")
                          ->orWhere('name_en', 'like', "%{$q}%");
                    });
                });
            })
            ->when($code !== '', function ($query) use ($code) {
                $query->whereHas('business', function ($b) use ($code) {
                    $b->where('code', 'like', "%{$code}%");
                });
            })
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
            'q',
            'code',
            'serviceId',
            'isActive',
            'services'
        ));
    }

    public function create()
    {
        $services = Service::query()
            ->select(['id', 'name_ar', 'name_en'])
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name', 'code'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $row = new ServiceFee([
            'is_active' => 1,
            'price' => 0,
        ]);

        return view('admin-v2.service-fees.create', compact('row', 'services', 'businesses'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = ServiceFee::updateOrCreate(
            [
                'business_id' => $data['business_id'],
                'service_id'  => $data['service_id'],
            ],
            $data
        );

        return redirect()
            ->route('admin.service-fees.edit', $row)
            ->with('success', 'تم حفظ السعر بنجاح.');
    }

    public function edit(ServiceFee $serviceFee)
    {
        $services = Service::query()
            ->select(['id', 'name_ar', 'name_en'])
            ->orderBy('name_ar')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name', 'code'])
            ->where('type', 'business')
            ->orderBy('name')
            ->get();

        $row = $serviceFee;

        return view('admin-v2.service-fees.edit', compact('row', 'services', 'businesses'));
    }

    public function update(Request $request, ServiceFee $serviceFee)
    {
        $data = $this->validateData($request);

        $serviceFee->update($data);

        return back()->with('success', 'تم تحديث السعر بنجاح.');
    }

    public function destroy(ServiceFee $serviceFee)
    {
        $serviceFee->delete();

        return redirect()
            ->route('admin.service-fees.index')
            ->with('success', 'تم الحذف بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:users,id'],
            'service_id'  => ['required', 'integer', 'exists:services,id'],
            'price'       => ['required', 'numeric', 'min:0'],
            'is_active'   => ['nullable'],
            'fee_type'    => ['nullable', 'in:fixed,percent'],
            'fee_value'   => ['nullable', 'numeric', 'min:0'],
            'rules'       => ['nullable'],
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