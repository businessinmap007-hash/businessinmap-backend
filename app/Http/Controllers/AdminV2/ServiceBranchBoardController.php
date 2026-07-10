<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service-branch board: pick a service, see all its item types as rows and the
 * shared pool of branches as columns, and tick each type into any branches it
 * belongs to (many-to-many — a type can be in several branches at once, like
 * "room" under both "hotel" and "residential units").
 *
 * Branches are a global pool (platform_service_id is nullable) — a branch spans
 * several services when it holds item types from more than one. Organizational
 * only: booking/pricing keys on the item type key, never the branch.
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
            ->with('groups:id')
            ->ordered()
            ->get(['id', 'platform_service_id', 'key', 'name_ar', 'name_en', 'is_active'])
            ->map(fn (PlatformServiceItemType $t) => [
                'id' => (int) $t->id,
                'key' => (string) $t->key,
                'name' => $t->displayName('ar'),
                'group_ids' => $t->groups->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
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

    public function toggle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'item_type_id' => ['required', 'integer'],
            'group_id' => ['required', 'integer', 'exists:platform_service_item_groups,id'],
            'attached' => ['required', 'boolean'],
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

        if ((bool) $data['attached']) {
            $type->groups()->syncWithoutDetaching([(int) $data['group_id']]);
        } else {
            $type->groups()->detach((int) $data['group_id']);
        }

        return response()->json([
            'ok' => true,
            'counts' => $this->countsForService((int) $data['service_id']),
        ]);
    }

    /**
     * Bulk "save all": re-sync the whole matrix for one service in a single
     * transaction. Belongs alongside the per-toggle auto-save as a safety net /
     * explicit confirmation — the client sends each type's COMPLETE branch set
     * (including branches not shown as columns), so hidden memberships survive.
     */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'types' => ['nullable', 'array'],
            'types.*.item_type_id' => ['required', 'integer'],
            'types.*.group_ids' => ['nullable', 'array'],
            'types.*.group_ids.*' => ['integer', 'exists:platform_service_item_groups,id'],
        ], [], [
            'service_id' => 'الخدمة',
        ]);

        $serviceId = (int) $data['service_id'];

        // Desired branch set per item type, keyed by type id.
        $desired = collect($data['types'] ?? [])
            ->keyBy(fn ($row) => (int) $row['item_type_id']);

        // Only touch item types that actually belong to this service.
        $types = PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->whereIn('id', $desired->keys()->map(fn ($id) => (int) $id)->all())
            ->get(['id']);

        DB::transaction(function () use ($types, $desired) {
            foreach ($types as $type) {
                $groupIds = collect($desired->get((int) $type->id)['group_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $type->groups()->sync($groupIds);
            }
        });

        return response()->json([
            'ok' => true,
            'saved' => $types->count(),
            'counts' => $this->countsForService($serviceId),
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
        // The pivot rows cascade on the group's FK, so memberships are removed
        // and the affected item types simply lose this branch.
        $platformServiceItemGroup->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Per-branch usage rows: [group_id => collection of {platform_service_id, n}].
     */
    protected function usageByBranch()
    {
        return DB::table('platform_service_item_group_type as p')
            ->join('platform_service_item_types as t', 't.id', '=', 'p.item_type_id')
            ->selectRaw('p.group_id, t.platform_service_id, COUNT(*) AS n')
            ->groupBy('p.group_id', 't.platform_service_id')
            ->get()
            ->groupBy('group_id');
    }

    /**
     * [group_id => count] of the given service's item types per branch, for the
     * live chip counts after a toggle.
     */
    protected function countsForService(int $serviceId): array
    {
        return DB::table('platform_service_item_group_type as p')
            ->join('platform_service_item_types as t', 't.id', '=', 'p.item_type_id')
            ->where('t.platform_service_id', $serviceId)
            ->selectRaw('p.group_id, COUNT(*) AS n')
            ->groupBy('p.group_id')
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
