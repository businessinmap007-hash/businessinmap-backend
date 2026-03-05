<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessServicePriceController extends Controller
{
    public function index(Request $request)
    {
        $q = BusinessServicePrice::query()->with([
            'business:id,name,type',
            'service:id,name_ar,name_en',
        ]);

        if ($request->filled('business_id')) {
            $q->where('business_id', (int)$request->business_id);
        }
        if ($request->filled('service_id')) {
            $q->where('service_id', (int)$request->service_id);
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', (int)$request->is_active);
        }

        $rows = $q->orderByDesc('id')->paginate(50);

        $businesses = User::query()
            ->where('type', 'business')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id','name']);

        $services = Service::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id','name_ar','name_en']);

        return view('admin-v2.business-service-prices.index', compact('rows','businesses','services'));
    }

    public function create()
    {
        $businesses = User::query()->where('type', 'business')->orderByDesc('id')->limit(200)->get(['id','name']);
        $services = Service::query()->orderByDesc('id')->limit(200)->get(['id','name_ar','name_en']);

        return view('admin-v2.business-service-prices.create', compact('businesses','services'));
    }

    public function store(Request $request)
    {
        $data = $this->validateRow($request);

        // upsert: (business_id, service_id) unique
        $row = BusinessServicePrice::query()->updateOrCreate(
            ['business_id' => (int)$data['business_id'], 'service_id' => (int)$data['service_id']],
            ['price' => (float)$data['price'], 'is_active' => (int)$data['is_active']]
        );

        return redirect()->route('admin.business_service_prices.index')->with('success', 'تم حفظ تسعير الخدمة للبزنس.');
    }

    public function edit(BusinessServicePrice $row)
    {
        $row->load(['business:id,name', 'service:id,name_ar,name_en']);

        $businesses = User::query()->where('type', 'business')->orderByDesc('id')->limit(200)->get(['id','name']);
        $services = Service::query()->orderByDesc('id')->limit(200)->get(['id','name_ar','name_en']);

        return view('admin-v2.business-service-prices.edit', compact('row','businesses','services'));
    }

    public function update(Request $request, BusinessServicePrice $row)
    {
        $data = $this->validateRow($request, true);

        $row->update([
            'business_id' => (int)$data['business_id'],
            'service_id'  => (int)$data['service_id'],
            'price'       => (float)$data['price'],
            'is_active'   => (int)$data['is_active'],
        ]);

        return redirect()->route('admin.business_service_prices.index')->with('success', 'تم التحديث.');
    }

    public function destroy(BusinessServicePrice $row)
    {
        $row->delete();
        return redirect()->route('admin.business_service_prices.index')->with('success', 'تم الحذف.');
    }

    private function validateRow(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'business_id' => ['required','integer'],
            'service_id'  => ['required','integer'],
            'price'       => ['required','numeric','min:0'],
            'is_active'   => ['required', Rule::in([0,1])],
        ]);
    }
}