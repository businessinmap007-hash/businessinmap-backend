<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\CategoryChild;
use App\Models\Governorate;
use App\Models\PlatformService;
use App\Models\ServiceFeeRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * BIM-3.5 — admin CRUD for dynamic fee rules.
 *
 * The conditions are stored as one JSON blob (see the model), but an admin
 * should not have to write JSON: the form exposes a field per supported
 * condition and this assembles/splits the blob. A blank field means "don't
 * care" and is dropped rather than stored as null, so an untouched condition
 * can never narrow a rule by accident.
 */
class ServiceFeeRuleController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $serviceId = (int) $request->get('platform_service_id', 0);
        $payer = (string) $request->get('payer', '');
        $effect = (string) $request->get('effect', '');
        $active = (string) $request->get('active', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;

        $rules = ServiceFeeRule::query()
            ->with('platformService:id,key,name_ar,name_en')
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")->orWhere('notes', 'like', "%{$q}%");
            }))
            ->when($serviceId > 0, fn ($query) => $query->where('platform_service_id', $serviceId))
            ->when($payer !== '', fn ($query) => $query->where('payer', $payer))
            ->when($effect !== '', fn ($query) => $query->where('effect', $effect))
            ->when($active !== '', fn ($query) => $query->where('is_active', (int) $active))
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('admin-v2.service-fee-rules.index', [
            'rules' => $rules,
            'services' => $this->servicesList(),
            'payers' => $this->payers(),
            'effects' => $this->effects(),
            'filters' => [
                'q' => $q,
                'platform_service_id' => $serviceId,
                'payer' => $payer,
                'effect' => $effect,
                'active' => $active,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function create()
    {
        return view('admin-v2.service-fee-rules.create', $this->formData(new ServiceFeeRule([
            'payer' => ServiceFeeRule::PAYER_ANY,
            'effect' => ServiceFeeRule::EFFECT_PERCENT_ADJUST,
            'priority' => 100,
            'is_active' => true,
            'stop_on_match' => false,
        ])));
    }

    public function store(Request $request)
    {
        ServiceFeeRule::create($this->validatedData($request));

        return redirect()
            ->route('admin.service-fee-rules.index')
            ->with('success', 'تم إنشاء قاعدة الرسوم بنجاح.');
    }

    public function edit(ServiceFeeRule $serviceFeeRule)
    {
        return view('admin-v2.service-fee-rules.edit', $this->formData($serviceFeeRule));
    }

    public function update(Request $request, ServiceFeeRule $serviceFeeRule)
    {
        $serviceFeeRule->update($this->validatedData($request));

        return redirect()
            ->route('admin.service-fee-rules.index')
            ->with('success', 'تم تحديث قاعدة الرسوم بنجاح.');
    }

    public function destroy(ServiceFeeRule $serviceFeeRule)
    {
        $serviceFeeRule->delete();

        return redirect()
            ->route('admin.service-fee-rules.index')
            ->with('success', 'تم حذف قاعدة الرسوم بنجاح.');
    }

    public function toggle(ServiceFeeRule $serviceFeeRule)
    {
        $serviceFeeRule->update(['is_active' => ! (bool) $serviceFeeRule->is_active]);

        return back()->with('success', 'تم تغيير حالة القاعدة بنجاح.');
    }

    private function formData(ServiceFeeRule $rule): array
    {
        return [
            'rule' => $rule,
            'conditions' => is_array($rule->conditions) ? $rule->conditions : [],
            'services' => $this->servicesList(),
            'children' => CategoryChild::query()->orderBy('id')->get(),
            'governorates' => Governorate::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'payers' => $this->payers(),
            'effects' => $this->effects(),
            'days' => [0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت'],
        ];
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'platform_service_id' => ['nullable', 'integer', 'exists:platform_services,id'],
            'category_id' => ['nullable', 'integer'],
            'child_id' => ['nullable', 'integer', 'exists:category_children_master,id'],
            'payer' => ['required', Rule::in(array_keys($this->payers()))],
            'fee_code' => ['nullable', 'string', 'max:191'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'stop_on_match' => ['nullable', 'boolean'],
            'effect' => ['required', Rule::in(ServiceFeeRule::EFFECTS)],
            'effect_value' => ['nullable', 'numeric', 'required_unless:effect,waive'],
            'min_fee' => ['nullable', 'numeric', 'min:0'],
            'max_fee' => ['nullable', 'numeric', 'min:0', 'gte:min_fee'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],

            // Conditions, one field each rather than raw JSON.
            'c_min_base_amount' => ['nullable', 'numeric', 'min:0'],
            'c_max_base_amount' => ['nullable', 'numeric', 'min:0', 'gte:c_min_base_amount'],
            'c_governorate_ids' => ['nullable', 'array'],
            'c_governorate_ids.*' => ['integer', 'exists:governorates,id'],
            'c_days_of_week' => ['nullable', 'array'],
            'c_days_of_week.*' => ['integer', 'between:0,6'],
            'c_time_from' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'c_time_to' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'c_min_success_operations' => ['nullable', 'integer', 'min:0'],
            'c_max_success_operations' => ['nullable', 'integer', 'min:0'],
            'c_subscribed' => ['nullable', Rule::in(['', '0', '1'])],
            'c_service_keys' => ['nullable', 'array'],
            'c_service_keys.*' => ['string'],
        ], [], [
            'name' => 'الاسم',
            'effect' => 'التأثير',
            'effect_value' => 'قيمة التأثير',
        ]);

        $data['conditions'] = $this->conditionsFrom($request);
        $data['is_active'] = $request->boolean('is_active');
        $data['stop_on_match'] = $request->boolean('stop_on_match');
        $data['priority'] = (int) ($data['priority'] ?? 100);

        if ($data['effect'] === ServiceFeeRule::EFFECT_WAIVE) {
            $data['effect_value'] = null;
        }

        // Strip the form-only condition inputs before they reach the model.
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'c_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Assemble the conditions blob, keeping only fields the admin actually
     * filled. An empty field is an absent condition, never a null one — the
     * model reads an unknown/odd key as "no match".
     */
    private function conditionsFrom(Request $request): array
    {
        $conditions = [];

        foreach (['min_base_amount', 'max_base_amount'] as $key) {
            $value = $request->input('c_' . $key);
            if ($value !== null && $value !== '') {
                $conditions[$key] = (float) $value;
            }
        }

        foreach (['min_success_operations', 'max_success_operations'] as $key) {
            $value = $request->input('c_' . $key);
            if ($value !== null && $value !== '') {
                $conditions[$key] = (int) $value;
            }
        }

        foreach (['governorate_ids', 'days_of_week'] as $key) {
            $value = array_filter((array) $request->input('c_' . $key, []), fn ($v) => $v !== '' && $v !== null);
            if (! empty($value)) {
                $conditions[$key] = array_values(array_map('intval', $value));
            }
        }

        $serviceKeys = array_filter((array) $request->input('c_service_keys', []), fn ($v) => $v !== '' && $v !== null);
        if (! empty($serviceKeys)) {
            $conditions['service_keys'] = array_values(array_map('strval', $serviceKeys));
        }

        foreach (['time_from', 'time_to'] as $key) {
            $value = trim((string) $request->input('c_' . $key, ''));
            if ($value !== '') {
                $conditions[$key] = $value;
            }
        }

        $subscribed = (string) $request->input('c_subscribed', '');
        if ($subscribed !== '') {
            $conditions['subscribed'] = $subscribed === '1';
        }

        return $conditions;
    }

    private function servicesList()
    {
        return PlatformService::query()->orderBy('id')->get();
    }

    private function payers(): array
    {
        return [
            ServiceFeeRule::PAYER_ANY => 'الطرفان',
            ServiceFeeRule::PAYER_BUSINESS => 'البزنس',
            ServiceFeeRule::PAYER_CLIENT => 'العميل',
        ];
    }

    private function effects(): array
    {
        return [
            ServiceFeeRule::EFFECT_PERCENT_ADJUST => 'تعديل بنسبة % (+/-)',
            ServiceFeeRule::EFFECT_FIXED_ADJUST => 'تعديل بمبلغ ثابت (+/-)',
            ServiceFeeRule::EFFECT_MULTIPLY => 'ضرب في معامل',
            ServiceFeeRule::EFFECT_OVERRIDE_FIXED => 'جعل الرسوم مبلغًا ثابتًا',
            ServiceFeeRule::EFFECT_OVERRIDE_PERCENT => 'جعل الرسوم % من قيمة العملية',
            ServiceFeeRule::EFFECT_WAIVE => 'إعفاء (صفر)',
        ];
    }
}
