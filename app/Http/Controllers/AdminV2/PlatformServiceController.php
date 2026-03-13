<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformServiceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $isActive = $request->get('is_active', '');

        $rows = PlatformService::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('key', 'like', "%{$q}%")
                        ->orWhere('name_ar', 'like', "%{$q}%")
                        ->orWhere('name_en', 'like', "%{$q}%");
                });
            })
            ->when($isActive !== '' && $isActive !== null, function ($query) use ($isActive) {
                $query->where('is_active', (int) $isActive);
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.platform-services.index', compact(
            'rows',
            'q',
            'isActive'
        ));
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
            ->with('success', 'تم إنشاء خدمة النظام بنجاح.');
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

        return back()->with('success', 'تم تحديث خدمة النظام بنجاح.');
    }

    public function destroy(PlatformService $platformService)
    {
        $platformService->delete();

        return redirect()
            ->route('admin.platform-services.index')
            ->with('success', 'تم حذف خدمة النظام بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'key' => [
                'required',
                'string',
                'max:191',
                Rule::unique('platform_services', 'key')->ignore($ignoreId),
            ],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'is_active' => ['nullable'],
            'supports_deposit' => ['nullable'],
            'max_deposit_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'fee_type' => ['nullable', 'in:fixed,percent'],
            'fee_value' => ['nullable', 'numeric', 'min:0'],
            'rules' => ['nullable'],
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');
        $data['supports_deposit'] = (int) $request->boolean('supports_deposit');

        if (!$data['supports_deposit']) {
            $data['max_deposit_percent'] = 0;
        }

        if (isset($data['rules']) && is_string($data['rules']) && trim($data['rules']) !== '') {
            $decoded = json_decode($data['rules'], true);
            $data['rules'] = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } else {
            $data['rules'] = null;
        }

        if (($data['fee_type'] ?? null) === null || $data['fee_type'] === '') {
            $data['fee_type'] = null;
            $data['fee_value'] = null;
        }

        return $data;
    }
}