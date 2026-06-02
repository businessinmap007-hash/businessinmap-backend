<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformServiceItemTypeController extends Controller
{
    public function index(Request $request)
    {
        $serviceId = (int) $request->get('service_id', 0);
        $active = $request->get('active', '');
        $q = trim((string) $request->get('q', ''));

        $services = PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'is_active'])
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();

        $rows = PlatformServiceItemType::query()
            ->with(['service:id,key,name_ar,name_en,is_active'])
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

        return view('admin-v2.platform-service-item-types.index', compact(
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

        $row = new PlatformServiceItemType([
            'platform_service_id' => (int) $request->get('service_id', 0) ?: null,
            'is_default' => 0,
            'is_active' => 1,
            'sort_order' => 0,
        ]);

        return view('admin-v2.platform-service-item-types.create', compact(
            'row',
            'services'
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $row = DB::transaction(function () use ($data) {
            $row = PlatformServiceItemType::create($data);

            if ((bool) ($data['is_default'] ?? false)) {
                $this->clearOtherDefaults($row);
            }

            return $row;
        });

        return redirect()
            ->route('admin.platform-service-item-types.edit', $row)
            ->with('success', 'تم إنشاء نوع العنصر بنجاح.');
    }

    public function edit(PlatformServiceItemType $platformServiceItemType)
    {
        $row = $platformServiceItemType->load([
            'service:id,key,name_ar,name_en,is_active',
        ]);

        $services = $this->servicesForForm();

        return view('admin-v2.platform-service-item-types.edit', compact(
            'row',
            'services'
        ));
    }

    public function update(Request $request, PlatformServiceItemType $platformServiceItemType)
    {
        $data = $this->validateData($request, $platformServiceItemType->id);

        DB::transaction(function () use ($platformServiceItemType, $data) {
            $oldServiceId = (int) $platformServiceItemType->platform_service_id;
            $oldKey = (string) $platformServiceItemType->key;

            $this->guardUsedKeyChange($platformServiceItemType, $data, $oldServiceId, $oldKey);

            $platformServiceItemType->update($data);

            if ((bool) ($data['is_default'] ?? false)) {
                $this->clearOtherDefaults($platformServiceItemType->fresh());
            }
        });

        return back()->with('success', 'تم تحديث نوع العنصر بنجاح.');
    }

    public function destroy(PlatformServiceItemType $platformServiceItemType)
    {
        $used = BusinessServicePrice::query()
            ->where('service_id', (int) $platformServiceItemType->platform_service_id)
            ->where('bookable_item_type', (string) $platformServiceItemType->key)
            ->exists();

        if ($used) {
            throw ValidationException::withMessages([
                'item_type' => 'لا يمكن حذف هذا النوع لأنه مستخدم داخل أسعار خدمات البزنس. يمكن إيقاف تفعيله بدلًا من الحذف.',
            ]);
        }

        $platformServiceItemType->delete();

        return redirect()
            ->route('admin.platform-service-item-types.index')
            ->with('success', 'تم حذف نوع العنصر بنجاح.');
    }

    protected function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'platform_service_id' => [
                'required',
                'integer',
                'exists:platform_services,id',
            ],

            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_\-]+$/',
                Rule::unique('platform_service_item_types', 'key')
                    ->where(fn ($query) => $query->where('platform_service_id', $request->input('platform_service_id')))
                    ->ignore($ignoreId),
            ],

            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],

            'is_default' => ['nullable'],
            'is_active' => ['nullable'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            'meta' => ['nullable', 'string'],
        ], [
            'key.regex' => 'المفتاح يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو _ أو - فقط.',
        ], [
            'platform_service_id' => 'الخدمة',
            'key' => 'المفتاح',
            'name_ar' => 'الاسم العربي',
            'name_en' => 'الاسم الإنجليزي',
            'is_default' => 'افتراضي',
            'is_active' => 'التفعيل',
            'sort_order' => 'الترتيب',
            'meta' => 'Meta',
        ]);

        $data['key'] = $this->normalizeKey($data['key'] ?? '');
        $data['name_ar'] = trim((string) ($data['name_ar'] ?? ''));
        $data['name_en'] = trim((string) ($data['name_en'] ?? '')) ?: null;

        $data['is_default'] = (int) $request->boolean('is_default');
        $data['is_active'] = (int) $request->boolean('is_active');
        $data['sort_order'] = max(0, (int) ($data['sort_order'] ?? 0));

        $data['meta'] = $this->parseMetaJson($request->input('meta'));

        return $data;
    }

    protected function servicesForForm()
    {
        return PlatformService::query()
            ->select(['id', 'key', 'name_ar', 'name_en', 'is_active'])
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get();
    }

    protected function normalizeKey($value): string
    {
        $key = strtolower(trim((string) $value));
        $key = preg_replace('/\s+/', '_', $key);
        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return (string) $key;
    }

    protected function parseMetaJson(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ValidationException::withMessages([
                'meta' => 'حقل Meta يجب أن يكون JSON صحيحًا.',
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'meta' => 'حقل Meta يجب أن يكون JSON object أو array.',
            ]);
        }

        return $decoded;
    }

    protected function clearOtherDefaults(PlatformServiceItemType $row): void
    {
        PlatformServiceItemType::query()
            ->where('platform_service_id', (int) $row->platform_service_id)
            ->where('id', '!=', (int) $row->id)
            ->update(['is_default' => 0]);
    }

    protected function guardUsedKeyChange(
        PlatformServiceItemType $row,
        array $data,
        int $oldServiceId,
        string $oldKey
    ): void {
        $newServiceId = (int) ($data['platform_service_id'] ?? 0);
        $newKey = (string) ($data['key'] ?? '');

        if ($newServiceId === $oldServiceId && $newKey === $oldKey) {
            return;
        }

        $used = BusinessServicePrice::query()
            ->where('service_id', $oldServiceId)
            ->where('bookable_item_type', $oldKey)
            ->exists();

        if ($used) {
            throw ValidationException::withMessages([
                'key' => 'لا يمكن تغيير مفتاح هذا النوع أو الخدمة لأنه مستخدم داخل أسعار خدمات البزنس.',
            ]);
        }
    }
}