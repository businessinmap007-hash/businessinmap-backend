<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service-branch board: pick a service, see all its item types as rows and the
 * shared pool of branches, and assign each type to one branch (or none) via a
 * dropdown. Branches are a global pool (platform_service_id is nullable) — a
 * branch spans several services when it holds item types from more than one.
 *
 * Organizational only: booking/pricing keys on the item type key, never the
 * branch. Kept separate from the simple branch CRUD
 * ({@see PlatformServiceItemGroupController}).
 */
class ServiceBranchBoardController extends Controller
{
    public function index(Request $request)
    {
        $services = $this->services();

        $serviceId = (int) $request->get('service_id', 0);

        if ($serviceId <= 0 || ! $services->firstWhere('id', $serviceId)) {
            $serviceId = (int) ($services->firstWhere('is_active', true)->id
                ?? $services->first()->id
                ?? 0);
        }

        $types = PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->ordered()
            ->get(['id', 'platform_service_id', 'group_id', 'key', 'name_ar', 'name_en', 'is_active'])
            ->map(fn (PlatformServiceItemType $t) => [
                'id' => (int) $t->id,
                'key' => (string) $t->key,
                'name' => $t->displayName('ar'),
                'group_id' => $t->group_id !== null ? (int) $t->group_id : null,
                'is_active' => (bool) $t->is_active,
            ]);

        $branches = PlatformServiceItemGroup::query()
            ->ordered()
            ->get(['id', 'key', 'name_ar', 'name_en', 'platform_service_id']);

        $usage = $this->usageByBranch();
        $serviceNames = $services->keyBy('id');

        $branchData = $branches->map(function (PlatformServiceItemGroup $b) use ($serviceId, $usage, $serviceNames) {
            $rows = $usage->get($b->id, collect());

            $countHere = (int) (optional($rows->firstWhere('platform_service_id', $serviceId))->n ?? 0);

            $cross = $rows
                ->where('platform_service_id', '!=', $serviceId)
                ->map(fn ($r) => $this->serviceLabel($serviceNames->get((int) $r->platform_service_id)))
                ->filter()
                ->values()
                ->all();

            return [
                'id' => (int) $b->id,
                'name' => $b->displayName('ar'),
                'count_here' => $countHere,
                'cross' => $cross,
            ];
        })->values();

        return view('admin-v2.service-branches.index', [
            'services' => $services,
            'serviceId' => $serviceId,
            'types' => $types,
            'branches' => $branchData,
        ]);
    }

    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'item_type_id' => ['required', 'integer'],
            'group_id' => ['nullable', 'integer', 'exists:platform_service_item_groups,id'],
        ]);

        $type = PlatformServiceItemType::query()
            ->where('id', (int) $data['item_type_id'])
            ->where('platform_service_id', (int) $data['service_id'])
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                'item_type_id' => 'نوع العنصر لا يتبع هذه الخدمة.',
            ]);
        }

        $type->group_id = $data['group_id'] ?? null;
        $type->save();

        return response()->json([
            'ok' => true,
            'counts' => $this->countsForService((int) $data['service_id']),
        ]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
        ], [], [
            'name_ar' => 'اسم الفرع',
            'name_en' => 'الاسم الإنجليزي',
        ]);

        $branch = PlatformServiceItemGroup::create([
            'platform_service_id' => null,
            'key' => $this->uniqueKey($data['name_en'] ?? $data['name_ar']),
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'sort_order' => 0,
            'is_active' => 1,
        ]);

        return response()->json([
            'ok' => true,
            'id' => (int) $branch->id,
            'name' => $branch->displayName('ar'),
        ]);
    }

    public function renameBranch(Request $request, PlatformServiceItemGroup $platformServiceItemGroup): JsonResponse
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
        ], [], [
            'name_ar' => 'اسم الفرع',
            'name_en' => 'الاسم الإنجليزي',
        ]);

        $platformServiceItemGroup->update([
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
        ]);

        return response()->json([
            'ok' => true,
            'name' => $platformServiceItemGroup->displayName('ar'),
        ]);
    }

    public function destroyBranch(PlatformServiceItemGroup $platformServiceItemGroup): JsonResponse
    {
        // group_id on item types is nullOnDelete, so members fall back to
        // "بدون فرع" instead of being deleted.
        $platformServiceItemGroup->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Per-branch usage rows: [group_id => collection of {platform_service_id, n}].
     */
    protected function usageByBranch()
    {
        return PlatformServiceItemType::query()
            ->whereNotNull('group_id')
            ->selectRaw('group_id, platform_service_id, COUNT(*) AS n')
            ->groupBy('group_id', 'platform_service_id')
            ->get()
            ->groupBy('group_id');
    }

    /**
     * [group_id => count] of the given service's item types per branch, for the
     * live chip counts after an assign.
     */
    protected function countsForService(int $serviceId): array
    {
        return PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->whereNotNull('group_id')
            ->selectRaw('group_id, COUNT(*) AS n')
            ->groupBy('group_id')
            ->pluck('n', 'group_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    protected function uniqueKey(string $source): string
    {
        $base = Str::slug($source, '_');

        if ($base === '') {
            $base = 'branch';
        }

        $key = $base;
        $i = 1;

        while (PlatformServiceItemGroup::query()->where('key', $key)->exists()) {
            $key = $base . '_' . (++$i);
        }

        return $key;
    }

    protected function serviceLabel(?PlatformService $service): ?string
    {
        if (! $service) {
            return null;
        }

        $ar = trim((string) ($service->name_ar ?? ''));
        $en = trim((string) ($service->name_en ?? ''));

        return $ar !== '' ? $ar : ($en !== '' ? $en : (string) $service->key);
    }

    protected function services()
    {
        return PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'is_active'])
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();
    }
}
