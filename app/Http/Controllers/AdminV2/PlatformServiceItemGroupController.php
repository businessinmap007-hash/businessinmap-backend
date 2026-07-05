<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            ->with('success', 'تم إنشاء الفرع بنجاح.');
    }

    public function edit(PlatformServiceItemGroup $platformServiceItemGroup)
    {
        $row = $platformServiceItemGroup->load([
            'service:id,key,name_ar,name_en,is_active',
        ]);

        $row->loadCount('itemTypes');

        $services = $this->servicesForForm();

        return view('admin-v2.platform-service-item-groups.edit', compact('row', 'services'));
    }

    public function update(Request $request, PlatformServiceItemGroup $platformServiceItemGroup)
    {
        $data = $this->validateData($request, $platformServiceItemGroup->id);

        $platformServiceItemGroup->update($data);

        return back()->with('success', 'تم تحديث الفرع بنجاح.');
    }

    public function destroy(PlatformServiceItemGroup $platformServiceItemGroup)
    {
        // The group_id FK is nullOnDelete, so any item types under this branch
        // are simply moved to "بدون فرع" rather than deleted.
        $platformServiceItemGroup->delete();

        return redirect()
            ->route('admin.platform-service-item-groups.index')
            ->with('success', 'تم حذف الفرع، وأصبحت أنواعه بدون فرع.');
    }

    public function toggleActive(PlatformServiceItemGroup $platformServiceItemGroup)
    {
        $platformServiceItemGroup->update([
            'is_active' => ! (bool) $platformServiceItemGroup->is_active,
        ]);

        return back()->with('success', 'تم تحديث حالة الفرع بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $request->merge([
            'key' => $this->normalizeKey($request->input('key')),
        ]);

        $data = $request->validate([
            'platform_service_id' => ['required', 'integer', 'exists:platform_services,id'],

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
            'key.regex' => 'مفتاح الفرع يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو _ أو - فقط.',
        ], [
            'platform_service_id' => 'الخدمة',
            'key' => 'المفتاح',
            'name_ar' => 'الاسم العربي',
            'name_en' => 'الاسم الإنجليزي',
            'sort_order' => 'الترتيب',
            'is_active' => 'التفعيل',
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
