<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Http\Request;

class BusinessServicePriceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = BusinessServicePrice::query()
            ->with(['business:id,name,code', 'service:id,key,name_ar,name_en'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('business', fn($b) => $b->where('name','like',"%{$q}%")->orWhere('code','like',"%{$q}%"))
                   ->orWhereHas('service', fn($s) => $s->where('key','like',"%{$q}%")->orWhere('name_ar','like',"%{$q}%")->orWhere('name_en','like',"%{$q}%"));
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.business-service-prices.index', compact('rows','q'));
    }

    public function create(Request $request)
    {
        $services = PlatformService::query()->where('is_active',1)->orderBy('name_ar')->get();
        $businesses = User::query()
            ->select(['id','name','code'])
            ->where('type','business') // عدّل حسب مشروعك
            ->orderBy('name')
            ->get();

        $row = new BusinessServicePrice([
            'is_active' => 1,
            'price' => 0,
            'business_id' => (int) $request->get('business_id', 0),
            'platform-service_id' => (int) $request->get('platform-service_id', 0),
        ]);

        return view('admin-v2.business-service-prices.create', compact('row','services','businesses'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        // منع تكرار (business + service)
        $row = BusinessServicePrice::query()->updateOrCreate(
            ['business_id'=>$data['business_id'], 'platform-service_id'=>$data['platform-service_id']],
            $data
        );

        return redirect()->route('admin.business-service-prices.edit', $row)->with('success','تم الحفظ بنجاح.');
    }

    public function edit(BusinessServicePrice $row)
    {
        $services = PlatformService::query()->where('is_active',1)->orderBy('name_ar')->get();
        $businesses = User::query()
            ->select(['id','name','code'])
            ->where('type','business')
            ->orderBy('name')
            ->get();

        return view('admin-v2.business-service-prices.edit', compact('row','services','businesses'));
    }

    public function update(Request $request, BusinessServicePrice $row)
    {
        $data = $this->validateData($request);
        $row->update($data);

        return back()->with('success','تم التحديث بنجاح.');
    }

    public function destroy(BusinessServicePrice $row)
    {
        $row->delete();
        return redirect()->route('admin.business-service-prices.index')->with('success','تم الحذف بنجاح.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'business_id' => ['required','integer','min:1'],
            'platform-service_id' => ['required','integer','min:1'],

            'price' => ['required','numeric','min:0'],
            'is_active' => ['nullable'],

            'fee_type' => ['nullable','in:fixed,percent'],
            'fee_value' => ['nullable','numeric','min:0'],
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');
        return $data;
    }
}