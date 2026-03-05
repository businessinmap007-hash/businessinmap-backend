<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Models\ServiceFee;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceFeeController extends Controller
{
    public function index(Request $request)
    {
        $q = ServiceFee::query();

        if ($request->filled('code')) {
            $q->where('code', 'like', '%'.$request->get('code').'%');
        }
        if ($request->filled('service_id')) {
            $q->where('service_id', (int)$request->service_id);
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', (int)$request->is_active);
        }

        $rows = $q->orderByDesc('id')->paginate(50);

        $services = Service::query()->orderByDesc('id')->limit(200)->get(['id','name_ar','name_en']);

        return view('admin-v2.service-fees.index', compact('rows','services'));
    }

   public function create()
    {
        $services = Service::query()
            ->select('id','name')   // عدّل الاسم لو عندك name_ar مثلاً
            ->orderBy('name')
            ->get();

        $businesses = User::query()
            ->select('id','name')
            ->where('type','business')   // عدّل حسب مشروعك: role/type/is_business...
            ->orderBy('name')
            ->get();

        return view('admin_v2.service_fees.create', compact('services','businesses'));
    }

    public function store(Request $request)
    {
        $data = $this->validateFee($request);

        // rules JSON normalize
        $data['rules'] = $this->normalizeRules($data['rules'] ?? null);

        $row = ServiceFee::create($data);

        return redirect()->route('admin.service_fees.show', $row)->with('success', 'تم إنشاء Service Fee.');
    }

    public function show(ServiceFee $serviceFee)
    {
        $serviceFee->load('service:id,name_ar,name_en');
        return view('admin-v2.service-fees.show', compact('serviceFee'));
    }

    public function edit(ServiceFee $serviceFee)
    {
        $services = Service::query()->orderByDesc('id')->limit(200)->get(['id','name_ar','name_en']);
        return view('admin-v2.service-fees.edit', compact('serviceFee','services'));
    }

    public function update(Request $request, ServiceFee $serviceFee)
    {
        $data = $this->validateFee($request, $serviceFee->id);
        $data['rules'] = $this->normalizeRules($data['rules'] ?? null);

        $serviceFee->update($data);

        return redirect()->route('admin.service_fees.show', $serviceFee)->with('success', 'تم تحديث Service Fee.');
    }

    public function destroy(ServiceFee $serviceFee)
    {
        $serviceFee->delete();
        return redirect()->route('admin.service_fees.index')->with('success', 'تم الحذف.');
    }

    private function validateFee(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required','string','max:100'],
            'service_id' => ['nullable','integer'],
            'amount' => ['required','numeric','min:0'],
            'rules' => ['nullable'], // JSON string
            'is_active' => ['required', Rule::in([0,1])],
        ]);
    }

    private function normalizeRules($rules)
    {
        if ($rules === null || $rules === '') return null;

        // لو جاي array
        if (is_array($rules)) return json_encode($rules, JSON_UNESCAPED_UNICODE);

        // لو string لازم يكون JSON صالح
        if (is_string($rules)) {
            $decoded = json_decode($rules, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                abort(422, 'rules must be valid JSON.');
            }
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}