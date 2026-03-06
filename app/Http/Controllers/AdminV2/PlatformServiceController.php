<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use Illuminate\Http\Request;

class PlatformServiceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = PlatformService::query()
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('key', 'like', "%{$q}%")
                   ->orWhere('name_ar', 'like', "%{$q}%")
                   ->orWhere('name_en', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.platform-services.index', compact('rows', 'q'));
    }

    public function create()
    {
        $row = new PlatformService([
            'is_active' => 1,
            'supports_deposit' => 0,
            'max_deposit_percent' => 0,
        ]);

        return view('admin-v2.platform-services.create', compact('row'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = PlatformService::create($data);

        return redirect()
            ->route('admin.platform-services.edit', $row)
            ->with('success', 'تم إنشاء الخدمة بنجاح.');
    }

    public function edit(PlatformService $platformService)
    {
        $row = $platformService;
        return view('admin-v2.platform-services.edit', compact('row'));
    }

    public function update(Request $request, PlatformService $platformService)
    {
        $data = $this->validateData($request, $platformService->id);

        $platformService->update($data);

        return back()->with('success', 'تم تحديث الخدمة بنجاح.');
    }

    public function destroy(PlatformService $platformService)
    {
        $platformService->delete();
        return redirect()->route('admin.platform-services.index')->with('success', 'تم الحذف بنجاح.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $uniqueKeyRule = 'unique:platform_services,key';
        if ($ignoreId) $uniqueKeyRule .= ',' . $ignoreId;

        $data = $request->validate([
            'key' => ['required','string','max:60', $uniqueKeyRule],
            'name_ar' => ['required','string','max:190'],
            'name_en' => ['nullable','string','max:190'],

            'is_active' => ['nullable'],
            'supports_deposit' => ['nullable'],
            'max_deposit_percent' => ['required','integer','min:0','max:100'],

            'fee_type' => ['nullable','in:fixed,percent'],
            'fee_value' => ['nullable','numeric','min:0'],

            'rules' => ['nullable'], // JSON textarea
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');
        $data['supports_deposit'] = (int) $request->boolean('supports_deposit');

        // rules: لو جالك string JSON حوّله array
        if (isset($data['rules']) && is_string($data['rules']) && trim($data['rules']) !== '') {
            $decoded = json_decode($data['rules'], true);
            $data['rules'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        // لو supports_deposit=0 اجبر max_deposit_percent=0
        if (!$data['supports_deposit']) {
            $data['max_deposit_percent'] = 0;
        }

        return $data;
    }
}