<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryChild;
use App\Models\CategoryChildServiceFee;
use App\Models\PlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CategoryChildServiceFeeBulkController extends Controller
{
    private function resolveParentOrAbort(int $parentId): Category
    {
        abort_if($parentId <= 0, 404);

        return Category::query()
            ->where('parent_id', 0)
            ->findOrFail($parentId, ['id', 'name_ar', 'name_en', 'parent_id']);
    }

    /**
     * @return array<int>
     */
    private function normalizeIds($ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, CategoryChild>
     */
    private function childrenForParent(int $parentId, array $childIds): Collection
    {
        return CategoryChild::query()
            ->whereIn('id', $childIds)
            ->whereHas('parents', function ($q) use ($parentId) {
                $q->where('categories.id', $parentId);
            })
            ->with([
                'parents:id,name_ar,name_en',
                'platformServices:id,key,name_ar,name_en,is_active,supports_deposit,max_deposit_percent',
            ])
            ->orderByRaw('COALESCE(reorder, 999999) ASC')
            ->orderBy('id')
            ->get();
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $parentId = (int) $request->get('parent_id', 0);
        $parent   = $this->resolveParentOrAbort($parentId);

        $childIds = $this->normalizeIds($request->get('child_ids', []));

        if (empty($childIds)) {
            return redirect()
                ->route('admin.categories.index', ['root_id' => $parentId])
                ->withErrors(['child_ids' => 'اختر قسمًا فرعيًا واحدًا على الأقل.']);
        }

        $children = $this->childrenForParent($parentId, $childIds);

        if ($children->isEmpty()) {
            return redirect()
                ->route('admin.categories.index', ['root_id' => $parentId])
                ->withErrors(['child_ids' => 'الأقسام الفرعية المحددة غير مرتبطة بهذا القسم الرئيسي.']);
        }

        $validChildIds = $children->pluck('id')->map(fn ($id) => (int) $id)->all();

        $services = PlatformService::query()
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar', 'name_en', 'supports_deposit', 'max_deposit_percent', 'is_active']);

        $selectedServiceIds = [];
        $activeChildServiceMap = DB::table('category_platform_services')
        ->whereIn('child_id', $validChildIds)
        ->where('is_active', 1)
        ->get(['child_id', 'platform_service_id'])
        ->groupBy('child_id')
        ->map(function ($rows) {
            return collect($rows)
                ->pluck('platform_service_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        });

        $existingFees = CategoryChildServiceFee::query()
            ->whereIn('child_id', $validChildIds)
            ->whereIn('platform_service_id', $services->pluck('id')->all())
            ->get()
            ->groupBy(function ($row) {
                return $row->child_id . ':' . $row->platform_service_id;
            });

        return view('admin-v2.category-child-service-fees.bulk-edit', [
            'parent' => $parent,
            'parentId' => $parentId,
            'children' => $children,
            'childIds' => $validChildIds,
            'services' => $services,
            'existingFees' => $existingFees,
            'selectedServiceIds' => $selectedServiceIds,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $parentId = (int) $request->input('parent_id', 0);
        $parent   = $this->resolveParentOrAbort($parentId);

        $childIds = $this->normalizeIds($request->input('child_ids', []));
        $selectedServiceIds = $this->normalizeIds($request->input('selected_service_ids', []));

        if (empty($childIds)) {
            return redirect()
                ->route('admin.categories.index', ['root_id' => $parentId])
                ->withErrors(['child_ids' => 'اختر قسمًا فرعيًا واحدًا على الأقل.']);
        }

        $children = $this->childrenForParent($parentId, $childIds);

        if ($children->isEmpty()) {
            return redirect()
                ->route('admin.categories.index', ['root_id' => $parentId])
                ->withErrors(['child_ids' => 'الأقسام الفرعية المحددة غير مرتبطة بهذا القسم الرئيسي.']);
        }

        $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.*.business_fee_enabled' => ['nullable'],
            'rows.*.*.business_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'rows.*.*.client_fee_enabled' => ['nullable'],
            'rows.*.*.client_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'rows.*.*.currency' => ['nullable', 'string', 'size:3'],
            'rows.*.*.is_active' => ['nullable'],
            'rows.*.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'rows.*.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $rowsInput = is_array($request->input('rows')) ? $request->input('rows') : [];

        DB::transaction(function () use ($children, $rowsInput, $selectedServiceIds, $parentId) {
            foreach ($children as $child) {
                $currentMaxSort = (int) DB::table('category_platform_services')
                    ->where('child_id', (int) $child->id)
                    ->max('sort_order');

                $nextSort = $currentMaxSort > 0 ? $currentMaxSort + 1 : 1;

                /*
                 * 1) الخدمات المختارة:
                 *    - إن وجد السجل لنفس child + service نحدّثه
                 *    - إن لم يوجد ننشئه
                 *
                 * ملاحظة مهمة:
                 * نبحث هنا بـ child_id + platform_service_id فقط
                 * لأن السجل قد يكون موجودًا مسبقًا تحت category_id مختلف.
                 */
                foreach ($selectedServiceIds as $serviceId) {
                    $serviceId = (int) $serviceId;

                    $existing = DB::table('category_platform_services')
                        ->where('child_id', (int) $child->id)
                        ->where('platform_service_id', $serviceId)
                        ->first();

                    if ($existing) {
                        DB::table('category_platform_services')
                            ->where('id', (int) $existing->id)
                            ->update([
                                'category_id' => $parentId,
                                'is_active' => 1,
                                'sort_order' => (int) ($existing->sort_order ?? $nextSort),
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('category_platform_services')->insert([
                            'category_id' => $parentId,
                            'child_id' => (int) $child->id,
                            'platform_service_id' => $serviceId,
                            'is_active' => 1,
                            'sort_order' => $nextSort,
                            'meta' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $nextSort++;
                    }
                }

                /*
                 * 2) الخدمات غير المختارة:
                 *    لا نحذفها، فقط نوقفها.
                 */
                DB::table('category_platform_services')
                    ->where('child_id', (int) $child->id)
                    ->when(! empty($selectedServiceIds), function ($q) use ($selectedServiceIds) {
                        $q->whereNotIn('platform_service_id', $selectedServiceIds);
                    })
                    ->update([
                        'is_active' => 0,
                        'updated_at' => now(),
                    ]);

                /*
                 * 3) حفظ رسوم الخدمات المختارة
                 */
                $childRows = is_array($rowsInput[$child->id] ?? null) ? $rowsInput[$child->id] : [];

                foreach ($selectedServiceIds as $serviceId) {
                    $serviceId = (int) $serviceId;
                    $payload = is_array($childRows[$serviceId] ?? null) ? $childRows[$serviceId] : [];

                    $businessFeeEnabled = (bool) ($payload['business_fee_enabled'] ?? false);
                    $clientFeeEnabled   = (bool) ($payload['client_fee_enabled'] ?? false);
                    $isActive           = (bool) ($payload['is_active'] ?? false);

                    $businessFeeAmount = round((float) ($payload['business_fee_amount'] ?? 0), 2);
                    $clientFeeAmount   = round((float) ($payload['client_fee_amount'] ?? 0), 2);

                    CategoryChildServiceFee::query()->updateOrCreate(
                        [
                            'child_id' => (int) $child->id,
                            'platform_service_id' => $serviceId,
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

                /*
                 * 4) رسوم الخدمات غير المختارة:
                 *    لا نحذفها، فقط نوقفها.
                 */
                CategoryChildServiceFee::query()
                    ->where('child_id', (int) $child->id)
                    ->when(! empty($selectedServiceIds), function ($q) use ($selectedServiceIds) {
                        $q->whereNotIn('platform_service_id', $selectedServiceIds);
                    })
                    ->update([
                        'is_active' => 0,
                        'updated_at' => now(),
                    ]);
            }
        });

        return redirect()
            ->route('admin.categories.index', ['root_id' => $parent->id])
            ->with('success', 'تم تحديث الخدمات ورسومها للأقسام الفرعية المحددة بنجاح.');
    }
}