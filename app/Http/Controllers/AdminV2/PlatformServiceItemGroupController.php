<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use App\Models\PlatformServiceItemType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function destroy(PlatformServiceItemGroup $platformServiceItemGroup)
    {
        // The group_id FK is nullOnDelete, so any item types under this branch
        // are simply moved to "بدون فرع" rather than deleted.
        $platformServiceItemGroup->delete();

        return redirect()
            ->route('admin.platform-service-item-groups.index')
            ->with('success', __('تم حذف الفرع، وأصبحت أنواعه بدون فرع.'));
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
