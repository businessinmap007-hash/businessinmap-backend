<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildServiceFee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryChildServiceFeeController extends Controller
{
    private function resolveParentOrAbort(int $parentId): Category
    {
        abort_if($parentId <= 0, 404);

        return Category::query()
            ->where('parent_id', 0)
            ->findOrFail($parentId, [
                'id',
                'name_ar',
                'name_en',
                'parent_id',
            ]);
    }

    private function ensureChildBelongsToParent(CategoryChild $categoryChild, int $parentId): void
    {
        $belongs = $categoryChild->parents()
            ->where('categories.id', $parentId)
            ->exists();

        abort_if(! $belongs, 404);
    }

    private function activeServiceIdsForChildAndParent(CategoryChild $categoryChild, int $parentId): array
    {
        if ($parentId <= 0 || (int) $categoryChild->id <= 0) {
            return [];
        }

        return DB::table('category_platform_services')
            ->where('category_id', $parentId)
            ->where('child_id', (int) $categoryChild->id)
            ->where('is_active', 1)
            ->pluck('platform_service_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function edit(Request $request, CategoryChild $categoryChild): View
    {
        $parentId = (int) $request->get('parent_id', 0);

        $parent = $this->resolveParentOrAbort($parentId);

        $this->ensureChildBelongsToParent($categoryChild, $parentId);

        $serviceIds = $this->activeServiceIdsForChildAndParent($categoryChild, $parentId);

        $categoryChild->load([
            'parents:id,name_ar,name_en,parent_id',
            'serviceFees.platformService:id,key,name_ar,name_en,is_active',
        ]);

        $services = $categoryChild->platformServices()
            ->wherePivot('category_id', $parentId)
            ->wherePivot('is_active', 1)
            ->whereIn('platform_services.id', $serviceIds)
            ->select([
                'platform_services.id',
                'platform_services.key',
                'platform_services.name_ar',
                'platform_services.name_en',
                'platform_services.is_active',
                'platform_services.supports_deposit',
            ])
            ->orderBy('category_platform_services.sort_order')
            ->orderBy('platform_services.id')
            ->get();

        $feeRows = CategoryChildServiceFee::query()
            ->with(['platformService:id,key,name_ar,name_en,is_active'])
            ->forChild((int) $categoryChild->id)
            ->whereIn('platform_service_id', $serviceIds)
            ->ordered()
            ->get()
            ->keyBy(fn ($row) => (int) $row->platform_service_id);

        return view('admin-v2.category-child-service-fees.edit', [
            'categoryChild' => $categoryChild,
            'services' => $services,
            'feeRows' => $feeRows,
            'parentId' => $parentId,
            'parent' => $parent,
        ]);
    }

    public function update(Request $request, CategoryChild $categoryChild): RedirectResponse
    {
        $parentId = (int) $request->get('parent_id', 0);

        $parent = $this->resolveParentOrAbort($parentId);

        $this->ensureChildBelongsToParent($categoryChild, $parentId);

        $serviceIds = $this->activeServiceIdsForChildAndParent($categoryChild, $parentId);

        if (empty($serviceIds)) {
            return back()
                ->withInput()
                ->withErrors([
                    'services' => 'لا توجد خدمات مفعلة مرتبطة بهذا القسم الفرعي داخل هذا القسم الرئيسي.',
                ]);
        }

        $request->validate([
            'rows' => ['nullable', 'array'],

            'rows.*.business_fee_enabled' => ['nullable'],
            'rows.*.business_fee_amount' => ['nullable', 'numeric', 'min:0'],

            'rows.*.client_fee_enabled' => ['nullable'],
            'rows.*.client_fee_amount' => ['nullable', 'numeric', 'min:0'],

            'rows.*.currency' => ['nullable', 'string', 'size:3'],
            'rows.*.is_active' => ['nullable'],
            'rows.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'rows.*.notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'rows.*.business_fee_amount' => 'قيمة رسوم البزنس',
            'rows.*.client_fee_amount' => 'قيمة رسوم المستخدم',
            'rows.*.currency' => 'العملة',
            'rows.*.sort_order' => 'الترتيب',
            'rows.*.notes' => 'الملاحظات',
        ]);

        $rowsInput = is_array($request->input('rows'))
            ? $request->input('rows')
            : [];

        DB::transaction(function () use ($categoryChild, $serviceIds, $rowsInput) {
            foreach ($serviceIds as $serviceId) {
                $payload = is_array($rowsInput[$serviceId] ?? null)
                    ? $rowsInput[$serviceId]
                    : [];

                $businessFeeAmount = round((float) ($payload['business_fee_amount'] ?? 0), 2);
                $clientFeeAmount = round((float) ($payload['client_fee_amount'] ?? 0), 2);

                $businessFeeEnabled = (bool) ($payload['business_fee_enabled'] ?? false);
                $clientFeeEnabled = (bool) ($payload['client_fee_enabled'] ?? false);

                if ($businessFeeAmount <= 0) {
                    $businessFeeAmount = 0.00;
                    $businessFeeEnabled = false;
                }

                if ($clientFeeAmount <= 0) {
                    $clientFeeAmount = 0.00;
                    $clientFeeEnabled = false;
                }

                $hasAnyFee = $businessFeeEnabled || $clientFeeEnabled;

                $isActive = (bool) ($payload['is_active'] ?? false);
                $isActive = $hasAnyFee && $isActive;

                $currency = strtoupper(trim((string) ($payload['currency'] ?? CategoryChildServiceFee::DEFAULT_CURRENCY)));
                $currency = $currency !== '' ? mb_substr($currency, 0, 3) : CategoryChildServiceFee::DEFAULT_CURRENCY;

                CategoryChildServiceFee::query()->updateOrCreate(
                    [
                        'child_id' => (int) $categoryChild->id,
                        'platform_service_id' => (int) $serviceId,
                    ],
                    [
                        'business_fee_enabled' => $businessFeeEnabled ? 1 : 0,
                        'business_fee_amount' => $businessFeeAmount,

                        'client_fee_enabled' => $clientFeeEnabled ? 1 : 0,
                        'client_fee_amount' => $clientFeeAmount,

                        'currency' => $currency,
                        'is_active' => $isActive ? 1 : 0,
                        'sort_order' => max(0, (int) ($payload['sort_order'] ?? 0)),
                        'notes' => trim((string) ($payload['notes'] ?? '')) ?: null,
                    ]
                );
            }
        });

        return redirect()
            ->route('admin.category-child-service-fees.edit', [
                'categoryChild' => $categoryChild->id,
                'parent_id' => $parent->id,
            ])
            ->with('success', 'تم حفظ رسوم الخدمات لهذا القسم الفرعي بنجاح.');
    }
}