<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\ServiceFee;   // عدّل لو اسم الموديل مختلف
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;

class ServiceFeeController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = ServiceFee::query()
            ->with([
                'service:id,name_ar,name_en',
                'business:id,name,code',
            ])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('business', fn($b) => $b->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                   ->orWhereHas('service', fn($s) => $s->where('name_ar', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%"));
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin_v2.service-fees.index', compact('rows', 'q'));
    }

    public function create()
    {
        // ✅ لو dropdown service فاضي: السبب غالبًا هنا
        $services = Service::query()
            ->select(['id','name_ar','name_en'])
            ->orderBy('id','desc')
            ->get();

        $businesses = User::query()
            ->select(['id','name','code'])
            ->where('type', 'business') // عدّل حسب مشروعك
            ->orderByDesc('id')
            ->get();

        return view('admin_v2.service-fees.create', compact('services','businesses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_id'  => ['required','integer','min:1'],
            'business_id' => ['required','integer','min:1'],
            'price'       => ['required','numeric','min:0'],
            'is_active'   => ['nullable'],
        ]);

        $data['is_active'] = (int) ($request->boolean('is_active') ? 1 : 0);

        // ✅ منع التكرار (business + service)
        $row = ServiceFee::query()->updateOrCreate(
            [
                'service_id'  => (int) $data['service_id'],
                'business_id' => (int) $data['business_id'],
            ],
            [
                'price'     => $data['price'],
                'is_active' => $data['is_active'],
            ]
        );

        return redirect()
            ->route('admin.service-fees.edit', $row)
            ->with('success', 'تم إنشاء/تحديث سعر الخدمة بنجاح.');
    }

    public function edit(ServiceFee $serviceFee)
    {
        $services = Service::query()
            ->select(['id','name_ar','name_en'])
            ->orderBy('id','desc')
            ->get();

        $businesses = User::query()
            ->select(['id','name','code'])
            ->where('type', 'business')
            ->orderByDesc('id')
            ->get();

        return view('admin_v2.service-fees.edit', compact('serviceFee','services','businesses'));
    }

    public function update(Request $request, ServiceFee $serviceFee)
    {
        $data = $request->validate([
            'service_id'  => ['required','integer','min:1'],
            'business_id' => ['required','integer','min:1'],
            'price'       => ['required','numeric','min:0'],
            'is_active'   => ['nullable'],
        ]);

        $serviceFee->service_id  = (int) $data['service_id'];
        $serviceFee->business_id = (int) $data['business_id'];
        $serviceFee->price       = $data['price'];
        $serviceFee->is_active   = (int) ($request->boolean('is_active') ? 1 : 0);
        $serviceFee->save();

        return back()->with('success', 'تم تحديث سعر الخدمة بنجاح.');
    }

    public function destroy(ServiceFee $serviceFee)
    {
        $serviceFee->delete();
        return redirect()->route('admin.service-fees.index')->with('success', 'تم الحذف بنجاح.');
    }
}