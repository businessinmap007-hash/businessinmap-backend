<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manage the "branches" that group item types under a platform service
 * (e.g. hotel / clinic / sports under booking). Item types point at a branch
 * via group_id; see {@see PlatformServiceItemTypeController}.
 */
class PlatformServiceItemGroupController extends Controller
{
    public function index(Request $request)
    {
        $serviceId = (int) $request->get('service_id', 0);
        $active = $request->get('active', '');
        $q = trim((string) $request->get('q', ''));

        $services = $this->servicesForForm();

        $rows = PlatformServiceItemGroup::query()
            ->with(['service:id,key,name_ar,name_en,is_active'])
            ->withCount('itemTypes')
            ->when($serviceId > 0, fn ($query) => $query->where('platform_service_id', $serviceId))
            ->when($active !== '' && $active !== null, fn ($query) => $query->where('is_active', (int) $active))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%' . mb_strtolower($q) . '%';

                $query->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(`key`) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name_ar) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name_en) LIKE ?', [$term]);
                });
            })
            ->orderBy('platform_service_id')
            ->ordered()
            ->paginate(50)
            ->withQueryString();

        return view('admin-v2.platform-service-item-groups.index', compact(
            'rows',
            'services',
            'serviceId',
            'active',
            'q'
        ));
    }

    /**
     * «قم بمراجعة فروع الخدمات وقم بتجميع الفروع الغير مرتبطة بأي بزنس فى
     * مجموعة لمراجعتها يدويا» — owner, 2026-08-09.
     *
     * A branch is only a container, so "unused" has to be measured through what
     * it contains. This walks the whole chain a branch has to survive before a
     * customer can buy anything through it:
     *
     *     branch → item types → a child's allowed_item_types → a merchant's
     *     price row / bookable unit
     *
     * and files every branch into one of three buckets by how far it got. The
     * screen is READ-ONLY on purpose: a branch that reaches nobody may be dead,
     * or may be a section nobody has filled in yet, and only the owner knows
     * which. Nothing here decides that for him.
     */
    public function review()
    {
        $branches = PlatformServiceItemGroup::query()
            ->with(['service:id,key,name_ar,name_en,is_active', 'itemTypes:id,key,name_ar,name_en,is_active'])
            ->orderBy('platform_service_id')
            ->ordered()
            ->get();

        // allowed_item_types per service, read once — the configs are the only
        // place that says a child may actually list a type.
        $allowedByService = [];

        foreach (DB::table('category_service_configs')->where('is_active', 1)->get(['platform_service_id', 'config']) as $row) {
            $config = json_decode((string) $row->config, true) ?: [];
            $serviceId = (int) $row->platform_service_id;

            // An EMPTY list means EVERY type, not none — the trap that has cost
            // this project twice. Such a config reaches every type the service
            // has, so it is recorded as a wildcard rather than skipped.
            if (($config['allowed_item_types'] ?? []) === []) {
                $allowedByService[$serviceId]['*'] = ($allowedByService[$serviceId]['*'] ?? 0) + 1;

                continue;
            }

            foreach ($config['allowed_item_types'] as $key) {
                $allowedByService[$serviceId][$key] = ($allowedByService[$serviceId][$key] ?? 0) + 1;
            }
        }

        $pricedByType = DB::table('business_service_prices')
            ->select('bookable_item_type', DB::raw('COUNT(*) as total'))
            ->groupBy('bookable_item_type')
            ->pluck('total', 'bookable_item_type');

        $unitsByKind = DB::table('bookable_items')
            ->whereNull('deleted_at')
            ->whereNotNull('item_type')
            ->select('item_type', DB::raw('COUNT(*) as total'))
            ->groupBy('item_type')
            ->pluck('total', 'item_type');

        $buckets = ['unused' => [], 'offered' => [], 'in_use' => []];

        foreach ($branches as $branch) {
            $serviceId = (int) $branch->platform_service_id;
            $keys = $branch->itemTypes->pluck('key')->map(fn ($key) => (string) $key)->all();

            $configs = 0;
            $priced = 0;
            $units = 0;

            foreach ($keys as $key) {
                $configs += (int) ($allowedByService[$serviceId][$key] ?? 0);
                $priced += (int) ($pricedByType[$key] ?? 0);
                $units += (int) ($unitsByKind[$key] ?? 0);
            }

            if ($keys !== []) {
                $configs += (int) ($allowedByService[$serviceId]['*'] ?? 0);
            }

            $row = [
                'branch' => $branch,
                'types' => count($keys),
                'configs' => $configs,
                'priced' => $priced,
                'units' => $units,
            ];

            // in_use: a merchant has priced something or listed a unit here.
            // offered: children may list it, but no merchant has yet.
            // unused: it reaches no child at all — no merchant can ever see it.
            $bucket = ($priced + $units) > 0 ? 'in_use' : ($configs > 0 ? 'offered' : 'unused');

            $buckets[$bucket][] = $row;
        }

        return view('admin-v2.platform-service-item-groups.review', compact('buckets'));
    }

    public function create(Request $request)
    {
        $services = $this->servicesForForm();

        $row = new PlatformServiceItemGroup([
            'platform_service_id' => (int) $request->get('service_id', 0) ?: null,
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        return view('admin-v2.platform-service-item-groups.create', compact('row', 'services'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = PlatformServiceItemGroup::create($data);

        return redirect()
            ->route('admin.platform-service-item-groups.edit', $row)
            ->with('success', __('تم إنشاء الفرع بنجاح.'));
    }

    public function edit(PlatformServiceItemGroup $platformServiceItemGroup)
    {
        $row = $platformServiceItemGroup->load([
            'service:id,key,name_ar,name_en,is_active',
        ]);

        $row->loadCount('itemTypes');

        $services = $this->servicesForForm();

        // Every item type + which branches it is in, so the edit page can show
        // this branch's members and let you add types from other branches.
        $allTypes = PlatformServiceItemType::query()
            ->with(['service:id,key,name_ar,name_en', 'groups:id'])
            ->orderBy('platform_service_id')
            ->ordered()
            ->get(['id', 'platform_service_id', 'key', 'name_ar', 'name_en', 'is_active'])
            ->map(fn (PlatformServiceItemType $t) => [
                'id' => (int) $t->id,
                'key' => (string) $t->key,
                'name' => $t->displayName('ar'),
                'service_id' => (int) $t->platform_service_id,
                'service_name' => $t->service ? $this->groupServiceLabel($t->service) : '',
                'is_active' => (bool) $t->is_active,
                'group_ids' => $t->groups->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ])->values();

        $branches = PlatformServiceItemGroup::query()
            ->ordered()
            ->get(['id', 'name_ar', 'name_en'])
            ->map(fn (PlatformServiceItemGroup $b) => ['id' => (int) $b->id, 'name' => $b->displayName('ar')])
            ->values();

        return view('admin-v2.platform-service-item-groups.edit', compact('row', 'services', 'allTypes', 'branches'));
    }

    public function attachType(Request $request, PlatformServiceItemGroup $platformServiceItemGroup): JsonResponse
    {
        $data = $request->validate([
            'item_type_id' => ['required', 'integer', 'exists:platform_service_item_types,id'],
        ]);

        $platformServiceItemGroup->itemTypes()->syncWithoutDetaching([(int) $data['item_type_id']]);

        return response()->json(['ok' => true, 'count' => $platformServiceItemGroup->itemTypes()->count()]);
    }

    public function detachType(Request $request, PlatformServiceItemGroup $platformServiceItemGroup): JsonResponse
    {
        $data = $request->validate([
            'item_type_id' => ['required', 'integer'],
        ]);

        $platformServiceItemGroup->itemTypes()->detach((int) $data['item_type_id']);

        return response()->json(['ok' => true, 'count' => $platformServiceItemGroup->itemTypes()->count()]);
    }

    public function storeType(Request $request, PlatformServiceItemGroup $platformServiceItemGroup): JsonResponse
    {
        $request->merge(['key' => $this->normalizeKey($request->input('key'))]);

        $data = $request->validate([
            'platform_service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/'],
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
        ], [
            'key.regex' => __('المفتاح يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو _ أو - فقط.'),
        ], [
            'platform_service_id' => __('الخدمة'),
            'key' => __('المفتاح'),
            'name_ar' => __('الاسم العربي'),
        ]);

        $serviceId = (int) $data['platform_service_id'];
        $key = $this->normalizeKey($data['key']);

        $exists = PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->where('key', $key)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'key' => __('يوجد نوع عنصر بنفس المفتاح داخل هذه الخدمة.'),
            ]);
        }

        $type = PlatformServiceItemType::create([
            'platform_service_id' => $serviceId,
            'key' => $key,
            'name_ar' => trim((string) $data['name_ar']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        $platformServiceItemGroup->itemTypes()->syncWithoutDetaching([$type->id]);

        $type->loadMissing('service:id,key,name_ar,name_en');

        return response()->json([
            'ok' => true,
            'count' => $platformServiceItemGroup->itemTypes()->count(),
            'type' => [
                'id' => (int) $type->id,
                'key' => (string) $type->key,
                'name' => $type->displayName('ar'),
                'service_id' => (int) $type->platform_service_id,
                'service_name' => $type->service ? $this->groupServiceLabel($type->service) : '',
                'is_active' => true,
                'group_ids' => [(int) $platformServiceItemGroup->id],
            ],
        ]);
    }

    protected function groupServiceLabel(PlatformService $service): string
    {
        return (string) ($service->name_ar ?: ($service->name_en ?: $service->key));
    }

    public function update(Request $request, PlatformServiceItemGroup $platformServiceItemGroup)
    {
        $data = $this->validateData($request, $platformServiceItemGroup->id);

        $platformServiceItemGroup->update($data);

        return back()->with('success', __('تم تحديث الفرع بنجاح.'));
    }

    /**
     * Refuses to delete a branch anything still depends on, and says what.
     *
     * This used to delete unconditionally, reassured by a comment claiming the
     * FK was nullOnDelete and the types would merely fall to «بدون فرع». That
     * was wrong: membership lives in `platform_service_item_group_type`, whose
     * group_id is ON DELETE **CASCADE**. Deleting a branch destroys its
     * membership rows outright, and every `category_service_configs.item_groups`
     * naming its id is left pointing at nothing — a plain integer in a JSON
     * column, which no foreign key protects and nothing warns about.
     *
     * On 2026-08-05 that cost seventeen branches, five of them live delivery
     * ones; 21 delivery types collapsed onto a single surviving branch and 315
     * configs were left dangling, with a success message each time.
     *
     * A branch that is in use is switched off instead — the same choice
     * ServiceKindsCollapseSeeder::prune() makes, and for the same reason.
     */
    public function destroy(PlatformServiceItemGroup $platformServiceItemGroup)
    {
        $groupId = (int) $platformServiceItemGroup->id;

        $types = DB::table('platform_service_item_group_type')->where('group_id', $groupId)->count();

        $configs = DB::table('category_service_configs')
            ->whereRaw('JSON_CONTAINS(COALESCE(JSON_EXTRACT(config, "$.item_groups"), JSON_ARRAY()), ?)', [(string) $groupId])
            ->count();

        if ($types > 0 || $configs > 0) {
            return back()->with('error', __(
                'لا يمكن حذف الفرع: ما زال يحمل :types نوعًا وتشير إليه :configs من إعدادات الأقسام. عطِّله بدل حذفه.',
                ['types' => $types, 'configs' => $configs]
            ));
        }

        $platformServiceItemGroup->delete();

        return redirect()
            ->route('admin.platform-service-item-groups.index')
            ->with('success', __('تم حذف الفرع الفارغ.'));
    }

    public function toggleActive(PlatformServiceItemGroup $platformServiceItemGroup)
    {
        $platformServiceItemGroup->update([
            'is_active' => ! (bool) $platformServiceItemGroup->is_active,
        ]);

        return back()->with('success', __('تم تحديث حالة الفرع بنجاح.'));
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'key' => $this->normalizeKey($request->input('key')),
        ]);

        $data = $request->validate([
            'platform_service_id' => ['nullable', 'integer', 'exists:platform_services,id'],

            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('platform_service_item_groups', 'key')
                    ->where(fn ($query) => $query->where('platform_service_id', $request->input('platform_service_id')))
                    ->ignore($ignoreId),
            ],

            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ], [
            'key.regex' => __('مفتاح الفرع يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو _ أو - فقط.'),
        ], [
            'platform_service_id' => __('الخدمة'),
            'key' => __('المفتاح'),
            'name_ar' => __('الاسم العربي'),
            'name_en' => __('الاسم الإنجليزي'),
            'sort_order' => __('الترتيب'),
            'is_active' => __('التفعيل'),
        ]);

        $data['key'] = $this->normalizeKey($data['key'] ?? '');
        $data['name_ar'] = trim((string) ($data['name_ar'] ?? ''));
        $data['name_en'] = trim((string) ($data['name_en'] ?? '')) ?: null;
        $data['sort_order'] = max(0, (int) ($data['sort_order'] ?? 0));
        $data['is_active'] = (int) $request->boolean('is_active');

        return $data;
    }

    protected function normalizeKey($value): string
    {
        $key = strtolower(trim((string) $value));
        $key = preg_replace('/\s+/', '_', $key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return (string) $key;
    }

    protected function servicesForForm()
    {
        return PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'is_active'])
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();
    }
}
