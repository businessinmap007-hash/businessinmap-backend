<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\CategoryChildServiceFee;
use App\Models\CategoryPlatformService;
use App\Models\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            ->withCount([
                'categoryPlatformServices',
                'activeCategoryPlatformServices',
                'categoryChildServiceFees',
                'activeCategoryChildServiceFees',
            ])
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
            'fee_type' => null,
            'fee_value' => null,
            'rules' => null,
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
        $usage = $this->serviceUsageCounts($platformService);

        if ($usage['total'] > 0) {
            return redirect()
                ->route('admin.platform-services.index')
                ->with('error', 'لا يمكن حذف هذه الخدمة لأنها مستخدمة حاليًا. يمكنك تعطيلها بدلًا من حذفها.');
        }

        $platformService->delete();

        return redirect()
            ->route('admin.platform-services.index')
            ->with('success', 'تم حذف خدمة النظام بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'key' => $this->normalizeKey($request->input('key')),
        ]);

        $data = $request->validate([
            'key' => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9_\\-]+$/',
                Rule::unique('platform_services', 'key')->ignore($ignoreId),
            ],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],

            'is_active' => ['nullable'],
            'supports_deposit' => ['nullable'],
            'max_deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],

            'fee_type' => [
                'nullable',
                Rule::in([
                    PlatformService::FEE_TYPE_FIXED,
                    PlatformService::FEE_TYPE_PERCENT,
                ]),
            ],
            'fee_value' => ['nullable', 'numeric', 'min:0'],

            'rules' => ['nullable'],
        ], [
            'key.regex' => 'مفتاح الخدمة يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو _ أو - فقط.',
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');
        $data['supports_deposit'] = (int) $request->boolean('supports_deposit');

        if (! $data['supports_deposit']) {
            $data['max_deposit_percent'] = 0;
        } else {
            $data['max_deposit_percent'] = max(0, min((int) ($data['max_deposit_percent'] ?? 0), 100));
        }

        $feeType = trim((string) ($data['fee_type'] ?? ''));
        $feeValue = round((float) ($data['fee_value'] ?? 0), 2);

        if ($feeType === '') {
            $data['fee_type'] = null;
            $data['fee_value'] = null;
        } else {
            $data['fee_type'] = $feeType;
            $data['fee_value'] = $feeValue > 0 ? $feeValue : 0;
        }

        $data['rules'] = $this->normalizeRules($request->input('rules'));

        return $data;
    }

    protected function normalizeKey($value): string
    {
        $key = strtolower(trim((string) $value));
        $key = preg_replace('/\s+/', '_', $key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return (string) $key;
    }

    protected function normalizeRules($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw ValidationException::withMessages([
                'rules' => 'حقل Rules يجب أن يكون JSON صحيح.',
            ]);
        }

        return $decoded;
    }

    protected function serviceUsageCounts(PlatformService $service): array
    {
        $serviceId = (int) $service->id;

        $categoryLinks = CategoryPlatformService::query()
            ->where('platform_service_id', $serviceId)
            ->count();

        $businessPrices = BusinessServicePrice::query()
            ->where('service_id', $serviceId)
            ->count();

        $serviceFees = CategoryChildServiceFee::query()
            ->where('platform_service_id', $serviceId)
            ->count();

        $bookings = Booking::query()
            ->where('service_id', $serviceId)
            ->count();

        $total = $categoryLinks + $businessPrices + $serviceFees + $bookings;

        return [
            'category_links' => $categoryLinks,
            'business_prices' => $businessPrices,
            'service_fees' => $serviceFees,
            'bookings' => $bookings,
            'total' => $total,
        ];
    }
}