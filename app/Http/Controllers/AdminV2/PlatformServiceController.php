<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\CategoryChildServiceFee;
use App\Models\CategoryPlatformService;
use App\Models\PlatformService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'rules' => null,
        ]);

        return view('admin-v2.platform-services.create', compact('row'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = PlatformService::create($data);

        $this->clearLegacyFeeColumns($row);

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

        $this->clearLegacyFeeColumns($platformService);

        return back()->with('success', 'تم تحديث خدمة النظام بنجاح.');
    }

    public function destroy(PlatformService $platformService)
    {
        $usage = $this->serviceUsageCounts($platformService);

        if ($usage['total'] > 0) {
            return redirect()
                ->route('admin.platform-services.index')
                ->with('error', 'لا يمكن حذف هذه الخدمة لأنها مستخدمة بالفعل. يمكنك تعطيلها بدلًا من حذفها.');
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
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('platform_services', 'key')->ignore($ignoreId),
            ],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'is_active' => ['nullable'],
            'supports_deposit' => ['nullable'],
            'rules_json' => ['nullable', 'string'],
        ], [
            'key.regex' => 'مفتاح الخدمة يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو _ أو - فقط.',
        ]);

        $data['is_active'] = (int) $request->boolean('is_active');
        $data['supports_deposit'] = (int) $request->boolean('supports_deposit');
        $data['name_en'] = trim((string) ($data['name_en'] ?? '')) ?: null;

        $rulesJson = trim((string) ($data['rules_json'] ?? ''));
        unset($data['rules_json']);

        if ($rulesJson !== '') {
            $decoded = json_decode($rulesJson, true);

            if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'rules_json' => 'قواعد الخدمة يجب أن تكون JSON صحيح.',
                ]);
            }

            if (Schema::hasColumn('platform_services', 'rules')) {
                $data['rules'] = $decoded;
            }
        } elseif (Schema::hasColumn('platform_services', 'rules')) {
            $data['rules'] = null;
        }

        return $data;
    }

    protected function normalizeKey($value): string
    {
        $key = strtolower(trim((string) $value));
        $key = preg_replace('/\s+/', '_', $key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return (string) $key;
    }

    protected function clearLegacyFeeColumns(PlatformService $service): void
    {
        $legacyDefaults = [
            'max_deposit_percent' => 0,

            'fee_type' => null,
            'fee_value' => null,

            'business_fee_enabled' => 0,
            'business_fee_type' => null,
            'business_fee_value' => 0,

            'client_fee_enabled' => 0,
            'client_fee_type' => null,
            'client_fee_value' => 0,

            'fee_currency' => 'EGP',
            'fee_notes' => null,
        ];

        $updates = [];

        foreach ($legacyDefaults as $column => $value) {
            if (Schema::hasColumn('platform_services', $column)) {
                $updates[$column] = $value;
            }
        }

        if ($updates === []) {
            return;
        }

        DB::table('platform_services')
            ->where('id', (int) $service->id)
            ->update($updates);
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
