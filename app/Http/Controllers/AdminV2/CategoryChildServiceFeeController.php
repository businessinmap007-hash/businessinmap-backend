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

        $parent = Category::query()
            ->where('parent_id', 0)
            ->findOrFail($parentId, ['id', 'name_ar', 'name_en', 'parent_id']);

        return $parent;
    }

    private function ensureChildBelongsToParent(CategoryChild $categoryChild, int $parentId): void
    {
        $belongs = $categoryChild->parents()
            ->where('categories.id', $parentId)
            ->exists();

        abort_if(! $belongs, 404);
    }

    public function edit(Request $request, CategoryChild $categoryChild): View
    {
        $parentId = (int) $request->get('parent_id', 0);

        $parent = $this->resolveParentOrAbort($parentId);
        $this->ensureChildBelongsToParent($categoryChild, $parentId);

        $categoryChild->load([
            'platformServices:id,key,name_ar,name_en,is_active,supports_deposit,max_deposit_percent',
            'serviceFees',
        ]);

        $feeRows = $categoryChild->serviceFees
            ->keyBy(fn ($row) => (int) $row->platform_service_id);

        $services = $categoryChild->platformServices
            ->sortBy([
                fn ($row) => (string) ($row->name_ar ?? ''),
                fn ($row) => (int) $row->id,
            ])
            ->values();

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

        $serviceIds = $categoryChild->platformServices()
            ->pluck('platform_services.id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($serviceIds)) {
            return back()->withErrors([
                'services' => 'لا توجد خدمات مرتبطة بهذا القسم الفرعي داخل هذا القسم الرئيسي.',
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
        ]);

        $rowsInput = is_array($request->input('rows')) ? $request->input('rows') : [];

        DB::transaction(function () use ($categoryChild, $serviceIds, $rowsInput) {
            foreach ($serviceIds as $serviceId) {
                $payload = is_array($rowsInput[$serviceId] ?? null) ? $rowsInput[$serviceId] : [];

                $businessFeeEnabled = (bool) ($payload['business_fee_enabled'] ?? false);
                $clientFeeEnabled   = (bool) ($payload['client_fee_enabled'] ?? false);
                $isActive           = (bool) ($payload['is_active'] ?? false);

                $businessFeeAmount = round((float) ($payload['business_fee_amount'] ?? 0), 2);
                $clientFeeAmount   = round((float) ($payload['client_fee_amount'] ?? 0), 2);

                CategoryChildServiceFee::query()->updateOrCreate(
                    [
                        'child_id' => (int) $categoryChild->id,
                        'platform_service_id' => (int) $serviceId,
                    ],
                    [
                        'business_fee_enabled' => $businessFeeEnabled ? 1 : 0,
                        'business_fee_amount'  => $businessFeeEnabled ? $businessFeeAmount : 0,
                        'client_fee_enabled'   => $clientFeeEnabled ? 1 : 0,
                        'client_fee_amount'    => $clientFeeEnabled ? $clientFeeAmount : 0,
                        'currency'             => strtoupper(trim((string) ($payload['currency'] ?? 'EGP'))) ?: 'EGP',
                        'is_active'            => $isActive ? 1 : 0,
                        'sort_order'           => (int) ($payload['sort_order'] ?? 0),
                        'notes'                => trim((string) ($payload['notes'] ?? '')) ?: null,
                    ]
                );
            }
        });

        return redirect()
            ->route('admin.category-child-service-fees.edit', [
                'categoryChild' => $categoryChild->id,
                'parent_id' => $parent->id,
            ])
            ->with('success', 'تم حفظ رسوم الخدمات لهذا الابن داخل القسم الرئيسي المحدد بنجاح.');
    }
}