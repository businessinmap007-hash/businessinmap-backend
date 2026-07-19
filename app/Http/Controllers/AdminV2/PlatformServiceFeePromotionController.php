<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\CategoryChild;
use App\Models\PlatformService;
use App\Models\PlatformServiceFeePromotion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformServiceFeePromotionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $serviceId = (int) $request->get('service_id', 0);
        $scopeType = (string) $request->get('scope_type', '');
        $targetParty = (string) $request->get('target_party', '');
        $discountType = (string) $request->get('discount_type', '');
        $active = (string) $request->get('active', '');
        $running = (string) $request->get('running', '');
        $perPage = (int) $request->get('per_page', 25);

        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $promotions = PlatformServiceFeePromotion::query()
            ->with(['service', 'child'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%");
                });
            })
            ->when($serviceId > 0, function ($query) use ($serviceId) {
                $query->where('service_id', $serviceId);
            })
            ->when($scopeType !== '', function ($query) use ($scopeType) {
                $query->where('scope_type', $scopeType);
            })
            ->when($targetParty !== '', function ($query) use ($targetParty) {
                $query->where('target_party', $targetParty);
            })
            ->when($discountType !== '', function ($query) use ($discountType) {
                $query->where('discount_type', $discountType);
            })
            ->when($active !== '', function ($query) use ($active) {
                $query->where('is_active', (int) $active);
            })
            ->when($running === '1', function ($query) {
                $query->currentlyRunning();
            })
            ->orderBy('is_active', 'desc')
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin-v2.platform-service-fee-promotions.index', [
            'promotions' => $promotions,
            'services' => $this->servicesList(),
            'filters' => [
                'q' => $q,
                'service_id' => $serviceId,
                'scope_type' => $scopeType,
                'target_party' => $targetParty,
                'discount_type' => $discountType,
                'active' => $active,
                'running' => $running,
                'per_page' => $perPage,
            ],
            'scopeTypes' => $this->scopeTypes(),
            'targetParties' => $this->targetParties(),
            'discountTypes' => $this->discountTypes(),
        ]);
    }

    public function create()
    {
        $promotion = new PlatformServiceFeePromotion([
            'scope_type' => PlatformServiceFeePromotion::SCOPE_SERVICE,
            'target_party' => PlatformServiceFeePromotion::TARGET_CLIENT,
            'discount_type' => PlatformServiceFeePromotion::DISCOUNT_WAIVE,
            'is_active' => true,
            'priority' => 100,
        ]);

        return view('admin-v2.platform-service-fee-promotions.create', [
            'promotion' => $promotion,
            'services' => $this->servicesList(),
            'children' => $this->childrenList(),
            'scopeTypes' => $this->scopeTypes(),
            'targetParties' => $this->targetParties(),
            'discountTypes' => $this->discountTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        PlatformServiceFeePromotion::create($data);

        return redirect()
            ->route('admin.platform-service-fee-promotions.index')
            ->with('success', __('تم إنشاء عرض رسوم المنصة بنجاح.'));
    }

    public function edit(PlatformServiceFeePromotion $platformServiceFeePromotion)
    {
        return view('admin-v2.platform-service-fee-promotions.edit', [
            'promotion' => $platformServiceFeePromotion,
            'services' => $this->servicesList(),
            'children' => $this->childrenList(),
            'scopeTypes' => $this->scopeTypes(),
            'targetParties' => $this->targetParties(),
            'discountTypes' => $this->discountTypes(),
        ]);
    }

    public function update(Request $request, PlatformServiceFeePromotion $platformServiceFeePromotion)
    {
        $data = $this->validatedData($request);

        $platformServiceFeePromotion->update($data);

        return redirect()
            ->route('admin.platform-service-fee-promotions.index')
            ->with('success', __('تم تحديث عرض رسوم المنصة بنجاح.'));
    }

    public function destroy(PlatformServiceFeePromotion $platformServiceFeePromotion)
    {
        $platformServiceFeePromotion->delete();

        return redirect()
            ->route('admin.platform-service-fee-promotions.index')
            ->with('success', __('تم حذف عرض رسوم المنصة بنجاح.'));
    }

    public function toggle(PlatformServiceFeePromotion $platformServiceFeePromotion)
    {
        $platformServiceFeePromotion->update([
            'is_active' => ! (bool) $platformServiceFeePromotion->is_active,
        ]);

        return redirect()
            ->back()
            ->with('success', __('تم تغيير حالة العرض بنجاح.'));
    }

    private function validatedData(Request $request): array
    {
        $scopeTypes = array_keys($this->scopeTypes());
        $targetParties = array_keys($this->targetParties());
        $discountTypes = array_keys($this->discountTypes());

        $data = $request->validate([
            'scope_type' => ['required', Rule::in($scopeTypes)],

            'service_id' => [
                'nullable',
                'integer',
                'exists:platform_services,id',
                Rule::requiredIf(fn () => in_array($request->input('scope_type'), [
                    PlatformServiceFeePromotion::SCOPE_SERVICE,
                    PlatformServiceFeePromotion::SCOPE_SERVICE_CHILD,
                ], true)),
            ],

            'child_id' => [
                'nullable',
                'integer',
                'exists:category_children_master,id',
                Rule::requiredIf(fn () => $request->input('scope_type') === PlatformServiceFeePromotion::SCOPE_SERVICE_CHILD),
            ],

            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],

            'target_party' => ['required', Rule::in($targetParties)],
            'discount_type' => ['required', Rule::in($discountTypes)],

            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                'required_if:discount_type,fixed_discount,percent_discount,override_to_fixed',
            ],

            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:999999'],

            'notes' => ['nullable', 'string'],
        ]);

        $scopeType = $data['scope_type'];

        if ($scopeType === PlatformServiceFeePromotion::SCOPE_ALL_SERVICES) {
            $data['service_id'] = null;
            $data['child_id'] = null;
        }

        if ($scopeType === PlatformServiceFeePromotion::SCOPE_SERVICE) {
            $data['child_id'] = null;
        }

        if (($data['discount_type'] ?? '') === PlatformServiceFeePromotion::DISCOUNT_WAIVE) {
            $data['discount_value'] = null;
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['priority'] = (int) ($data['priority'] ?? 100);

        return $data;
    }

    private function servicesList()
    {
        return PlatformService::query()
            ->orderBy('id')
            ->get();
    }

    private function childrenList()
    {
        return CategoryChild::query()
            ->orderBy('id')
            ->get();
    }

    private function scopeTypes(): array
    {
        return [
            PlatformServiceFeePromotion::SCOPE_ALL_SERVICES => __('كل الخدمات'),
            PlatformServiceFeePromotion::SCOPE_SERVICE => __('خدمة محددة'),
            PlatformServiceFeePromotion::SCOPE_SERVICE_CHILD => __('خدمة + قسم فرعي'),
        ];
    }

    private function targetParties(): array
    {
        return [
            PlatformServiceFeePromotion::TARGET_CLIENT => __('العميل'),
            PlatformServiceFeePromotion::TARGET_BUSINESS => __('البزنس'),
            PlatformServiceFeePromotion::TARGET_BOTH => __('العميل والبزنس'),
        ];
    }

    private function discountTypes(): array
    {
        return [
            PlatformServiceFeePromotion::DISCOUNT_WAIVE => __('إيقاف الرسوم مؤقتًا'),
            PlatformServiceFeePromotion::DISCOUNT_OVERRIDE_TO_FIXED => __('جعل الرسوم قيمة ثابتة'),
            PlatformServiceFeePromotion::DISCOUNT_FIXED_DISCOUNT => __('خصم مبلغ ثابت'),
            PlatformServiceFeePromotion::DISCOUNT_PERCENT_DISCOUNT => __('خصم نسبة مئوية'),
        ];
    }
}