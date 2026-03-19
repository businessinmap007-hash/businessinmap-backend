<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessServicePriceController extends Controller
{
    public function index(Request $request)
    {
        $serviceId        = (int) $request->get('service_id', 0);
        $businessId       = (int) $request->get('business_id', 0);
        $isActive         = $request->get('is_active', '');
        $qBusiness        = trim((string) $request->get('q_business', ''));
        $qService         = trim((string) $request->get('q_service', ''));
        $qItemType        = trim((string) $request->get('q_item_type', ''));

        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $baseQuery = BusinessServicePrice::query()
            ->selectRaw("
                business_service_prices.*,

                CASE
                    WHEN discount_enabled = 1
                    THEN ROUND(price * discount_percent / 100, 2)
                    ELSE 0
                END as discount_amount,

                CASE
                    WHEN discount_enabled = 1
                    THEN ROUND(price - (price * discount_percent / 100), 2)
                    ELSE ROUND(price, 2)
                END as final_service_price,

                CASE
                    WHEN deposit_enabled = 1
                    THEN ROUND(
                        (
                            CASE
                                WHEN discount_enabled = 1
                                THEN price - (price * discount_percent / 100)
                                ELSE price
                            END
                        ) * deposit_percent / 100,
                    2)
                    ELSE 0
                END as deposit_hold_amount,

                ROUND(
                    CASE
                        WHEN discount_enabled = 1
                        THEN price - (price * discount_percent / 100)
                        ELSE price
                    END,
                2) as cash_due_on_execution
            ")
            ->with([
                'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent',
                'business:id,name,type',
            ])
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when($businessId > 0, fn ($query) => $query->where('business_id', $businessId))
            ->when($isActive !== '' && $isActive !== null, fn ($query) => $query->where('is_active', (int) $isActive))
            ->when($qItemType !== '', function ($query) use ($qItemType) {
                $query->whereRaw('LOWER(bookable_item_type) LIKE ?', ['%' . mb_strtolower($qItemType) . '%']);
            })
            ->when($qBusiness !== '', function ($query) use ($qBusiness) {
                $query->whereHas('business', function ($sub) use ($qBusiness) {
                    $sub->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($qBusiness) . '%']);
                });
            })
            ->when($qService !== '', function ($query) use ($qService) {
                $query->whereHas('service', function ($sub) use ($qService) {
                    $term = '%' . mb_strtolower($qService) . '%';

                    $sub->whereRaw('LOWER(name_ar) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(`key`) LIKE ?', [$term]);
                });
            });

        $stats = [
            'total_rows'     => BusinessServicePrice::count(),
            'active_rows'    => BusinessServicePrice::where('is_active', 1)->count(),
            'deposit_rows'   => BusinessServicePrice::where('deposit_enabled', 1)->count(),
            'avg_price'      => BusinessServicePrice::avg('price'),
            'business_count' => BusinessServicePrice::distinct('business_id')->count(),
            'services_count' => BusinessServicePrice::distinct('service_id')->count(),
            'item_types_count'=> BusinessServicePrice::query()
                ->whereNotNull('bookable_item_type')
                ->where('bookable_item_type', '!=', '')
                ->distinct('bookable_item_type')
                ->count('bookable_item_type'),
            'max_price'      => BusinessServicePrice::max('price'),
            'min_price'      => BusinessServicePrice::min('price'),
        ];

        $rows = $baseQuery
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.business-service-prices.index', compact(
            'rows',
            'services',
            'businesses',
            'serviceId',
            'businessId',
            'isActive',
            'qBusiness',
            'qService',
            'qItemType',
            'stats'
        ));
    }

    public function create()
    {
        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $row = new BusinessServicePrice([
            'is_active'          => 1,
            'price'              => 0,
            'currency'           => 'EGP',
            'deposit_enabled'    => 0,
            'deposit_percent'    => 0,
            'discount_enabled'   => 0,
            'discount_percent'   => 0,
            'bookable_item_type' => 'category',
        ]);

        return view('admin-v2.business-service-prices.create', compact('row', 'services', 'businesses'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = BusinessServicePrice::query()->updateOrCreate(
            [
                'business_id'         => $data['business_id'],
                'service_id'          => $data['service_id'],
                'bookable_item_type'  => $data['bookable_item_type'],
            ],
            $data
        );

        return redirect()
            ->route('admin.business_service_prices.edit', $row)
            ->with('success', 'تم حفظ سعر الخدمة ونوع العنصر وإعدادات الديبوزت والخصم بنجاح.');
    }

    public function edit(BusinessServicePrice $row)
    {
        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent'])
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        $businesses = User::query()
            ->select(['id', 'name'])
            ->where('type', 'business')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $row->load([
            'service:id,key,name_ar,name_en,supports_deposit,max_deposit_percent',
            'business:id,name,type',
        ]);

        return view('admin-v2.business-service-prices.edit', compact('row', 'services', 'businesses'));
    }

    public function update(Request $request, BusinessServicePrice $row)
    {
        $data = $this->validateData($request, $row->id);

        $duplicate = BusinessServicePrice::query()
            ->where('business_id', $data['business_id'])
            ->where('service_id', $data['service_id'])
            ->where('bookable_item_type', $data['bookable_item_type'])
            ->where('id', '!=', $row->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'bookable_item_type' => 'يوجد سجل آخر لنفس البزنس والخدمة ونوع العنصر.',
            ]);
        }

        $row->update($data);

        return back()->with('success', 'تم تحديث السجل بنجاح.');
    }

    public function destroy(BusinessServicePrice $row)
    {
        $row->delete();

        return redirect()
            ->route('admin.business_service_prices.index')
            ->with('success', 'تم حذف السجل بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'business_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) {
                    return $query->where('type', 'business');
                }),
            ],

            'service_id' => [
                'required',
                'integer',
                'exists:platform_services,id',
            ],

            'bookable_item_type' => [
                'required',
                'string',
                'max:100',
                Rule::unique('business_service_prices', 'bookable_item_type')
                    ->where(function ($query) use ($request) {
                        return $query
                            ->where('business_id', $request->input('business_id'))
                            ->where('service_id', $request->input('service_id'));
                    })
                    ->ignore($ignoreId),
            ],

            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],

            'is_active' => ['nullable'],
            'deposit_enabled' => ['nullable'],
            'deposit_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'discount_enabled' => ['nullable'],
            'discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [], [
            'business_id'         => 'البزنس',
            'service_id'          => 'الخدمة',
            'bookable_item_type'  => 'نوع العنصر',
            'price'               => 'السعر',
            'currency'            => 'العملة',
            'deposit_enabled'     => 'تفعيل الديبوزت',
            'deposit_percent'     => 'نسبة الديبوزت',
            'discount_enabled'    => 'تفعيل الخصم',
            'discount_percent'    => 'نسبة الخصم',
        ]);

        $data['bookable_item_type'] = trim((string) ($data['bookable_item_type'] ?? ''));
        $data['currency'] = trim((string) ($data['currency'] ?? 'EGP'));
        $data['is_active'] = (int) $request->boolean('is_active');
        $data['deposit_enabled'] = (int) $request->boolean('deposit_enabled');
        $data['deposit_percent'] = (int) ($data['deposit_percent'] ?? 0);
        $data['discount_enabled'] = (int) $request->boolean('discount_enabled');
        $data['discount_percent'] = (int) ($data['discount_percent'] ?? 0);

        if ($data['bookable_item_type'] === '') {
            throw ValidationException::withMessages([
                'bookable_item_type' => 'نوع العنصر مطلوب.',
            ]);
        }

        if (! $data['discount_enabled']) {
            $data['discount_percent'] = 0;
        }

        $service = PlatformService::query()->find($data['service_id']);

        if (! $service) {
            throw ValidationException::withMessages([
                'service_id' => 'الخدمة غير موجودة.',
            ]);
        }

        if (! (bool) $service->supports_deposit) {
            $data['deposit_enabled'] = 0;
            $data['deposit_percent'] = 0;

            return $data;
        }

        if (! $data['deposit_enabled']) {
            $data['deposit_percent'] = 0;

            return $data;
        }

        $maxAllowed = (int) ($service->max_deposit_percent ?? 0);

        if ($data['deposit_percent'] > $maxAllowed) {
            throw ValidationException::withMessages([
                'deposit_percent' => "نسبة الديبوزت المطلوبة تتجاوز الحد المسموح لهذه الخدمة ({$maxAllowed}%).",
            ]);
        }

        return $data;
    }
}