@php
    $businessFee = $businessFee ?? null;
    $clientFee   = $clientFee ?? null;

    $selectedBusinessId = old('business_id', $businessFee->business_id ?? $clientFee->business_id ?? request('business_id'));
    $selectedChildId    = old('child_id', $businessFee->child_id ?? $clientFee->child_id ?? request('child_id'));
    $selectedServiceId  = old('service_id', $businessFee->service_id ?? $clientFee->service_id ?? request('service_id'));
    $selectedFeeCode    = old('fee_code', $businessFee->fee_code ?? $clientFee->fee_code ?? 'booking_execution');

    $businessFeeType    = old('business.fee_type', $businessFee->fee_type ?? 'platform_fee');
    $businessCalcType   = old('business.calc_type', $businessFee->calc_type ?? 'fixed');
    $businessAmount     = old('business.amount', $businessFee->amount ?? 0);
    $businessMin        = old('business.min_amount', $businessFee->min_amount ?? null);
    $businessMax        = old('business.max_amount', $businessFee->max_amount ?? null);
    $businessCurrency   = old('business.currency', $businessFee->currency ?? 'EGP');
    $businessPriority   = old('business.priority', $businessFee->priority ?? 100);
    $businessIsActive   = old('business.is_active', (int) ($businessFee->is_active ?? 1));
    $businessNotes      = old('business.notes', $businessFee->notes ?? '');
    $businessRules      = old(
        'business.rules',
        !empty($businessFee?->rules) ? json_encode($businessFee->rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ''
    );

    $clientFeeType      = old('client.fee_type', $clientFee->fee_type ?? 'platform_fee');
    $clientCalcType     = old('client.calc_type', $clientFee->calc_type ?? 'fixed');
    $clientAmount       = old('client.amount', $clientFee->amount ?? 0);
    $clientMin          = old('client.min_amount', $clientFee->min_amount ?? null);
    $clientMax          = old('client.max_amount', $clientFee->max_amount ?? null);
    $clientCurrency     = old('client.currency', $clientFee->currency ?? 'EGP');
    $clientPriority     = old('client.priority', $clientFee->priority ?? 100);
    $clientIsActive     = old('client.is_active', (int) ($clientFee->is_active ?? 1));
    $clientNotes        = old('client.notes', $clientFee->notes ?? '');
    $clientRules        = old(
        'client.rules',
        !empty($clientFee?->rules) ? json_encode($clientFee->rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ''
    );
@endphp

@if ($errors->any())
    <div class="a2-alert a2-alert-danger" style="margin-bottom:16px;">
        <div style="font-weight:800;margin-bottom:8px;">يوجد أخطاء في الإدخال:</div>
        <ul style="margin:0;padding-inline-start:18px;">
            @foreach ($errors->all() as $error)
                <li style="margin:4px 0;">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- البيانات الأساسية --}}
<div class="a2-card a2-sf-section-card">
    <div class="a2-header">
        <div>
            <h3 class="a2-sf-card-title" style="margin:0;">البيانات الأساسية</h3>
            <div class="a2-hint">اختيار البزنس والقسم الفرعي والخدمة وكود الرسم</div>
        </div>
    </div>

    <div class="a2-sf-grid-3">
        <div class="a2-sf-field">
            <label class="a2-hint" style="display:block;margin-bottom:6px;">البزنس</label>
            <select name="business_id" id="sf_business_id" class="js-a2-searchable" style="width:100%;">
                <option value="">Global</option>
                @foreach($businesses as $business)
                    <option
                        value="{{ $business->id }}"
                        data-child-id="{{ (int) ($business->category_child_id ?? 0) }}"
                        @selected((string) $selectedBusinessId === (string) $business->id)
                    >
                        {{ $business->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-sf-field">
            <label class="a2-hint" style="display:block;margin-bottom:6px;">القسم الفرعي</label>
            <select name="child_id" id="sf_child_id" class="js-a2-searchable" style="width:100%;">
                <option value="">Global / Auto from Business</option>
                @foreach($children as $child)
                    <option value="{{ $child->id }}" @selected((string) $selectedChildId === (string) $child->id)>
                        {{ $child->name_ar ?: $child->name_en ?: ('#' . $child->id) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-sf-field">
            <label class="a2-hint" style="display:block;margin-bottom:6px;">الخدمة</label>
            <select name="service_id" class="js-a2-searchable" style="width:100%;">
                <option value="">All Services</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected((string) $selectedServiceId === (string) $service->id)>
                        {{ $service->name_ar ?: $service->name_en }}
                        @if(!empty($service->key)) ({{ $service->key }}) @endif
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-sf-field a2-field-full">
            <label class="a2-hint" style="display:block;margin-bottom:6px;">كود الرسم</label>
            <select name="fee_code" class="js-a2-searchable" style="width:100%;" required>
                @foreach($feeCodeOptions as $value => $label)
                    <option value="{{ $value }}" @selected((string) $selectedFeeCode === (string) $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- كروت الرسوم --}}
<div class="a2-service-fee-form-grid">
    {{-- رسوم البزنس --}}
    <div class="a2-card a2-sf-section-card">
        <div class="a2-header">
            <div>
                <h3 class="a2-sf-card-title" style="margin:0;">رسوم البزنس</h3>
                <div class="a2-hint">إعداد الرسوم الخاصة بمقدم الخدمة</div>
            </div>
        </div>

        <div class="a2-sf-grid">
            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">نوع الرسم</label>
                <select name="business[fee_type]" class="js-a2-searchable" style="width:100%;" required>
                    @foreach($feeTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string) $businessFeeType === (string) $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">طريقة الحساب</label>
                <select name="business[calc_type]" class="js-a2-searchable" style="width:100%;" required>
                    @foreach($calcTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string) $businessCalcType === (string) $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">القيمة</label>
                <input type="number" step="0.01" min="0" name="business[amount]" class="a2-input" style="width:100%;" value="{{ $businessAmount }}">
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">الحد الأدنى</label>
                <input type="number" step="0.01" min="0" name="business[min_amount]" class="a2-input" style="width:100%;" value="{{ $businessMin }}">
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">الحد الأقصى</label>
                <input type="number" step="0.01" min="0" name="business[max_amount]" class="a2-input" style="width:100%;" value="{{ $businessMax }}">
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">العملة</label>
                <input type="text" name="business[currency]" class="a2-input" style="width:100%;" value="{{ $businessCurrency }}" maxlength="3">
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">الأولوية</label>
                <input type="number" min="0" name="business[priority]" class="a2-input" style="width:100%;" value="{{ $businessPriority }}">
            </div>

            <div class="a2-sf-check">
                <label>
                    <input type="checkbox" name="business[is_active]" value="1" {{ (string) $businessIsActive === '1' ? 'checked' : '' }}>
                    نشط
                </label>
            </div>
        </div>

        <div style="margin-top:14px;">
            <label class="a2-hint" style="display:block;margin-bottom:6px;">Rules JSON</label>
            <textarea name="business[rules]" class="a2-input" style="width:100%;height:140px;padding:12px;">{{ $businessRules }}</textarea>
        </div>

        <div style="margin-top:14px;">
            <label class="a2-hint" style="display:block;margin-bottom:6px;">ملاحظات</label>
            <textarea name="business[notes]" class="a2-input" style="width:100%;height:100px;padding:12px;">{{ $businessNotes }}</textarea>
        </div>
    </div>

    {{-- رسوم العميل --}}
    <div class="a2-card a2-sf-section-card">
        <div class="a2-header">
            <div>
                <h3 class="a2-sf-card-title" style="margin:0;">رسوم العميل</h3>
                <div class="a2-hint">إعداد الرسوم الخاصة بالعميل</div>
            </div>
        </div>

        <div class="a2-sf-grid">
            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">نوع الرسم</label>
                <select name="client[fee_type]" class="js-a2-searchable" style="width:100%;" required>
                    @foreach($feeTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string) $clientFeeType === (string) $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">طريقة الحساب</label>
                <select name="client[calc_type]" class="js-a2-searchable" style="width:100%;" required>
                    @foreach($calcTypeOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string) $clientCalcType === (string) $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">القيمة</label>
                <input type="number" step="0.01" min="0" name="client[amount]" class="a2-input" style="width:100%;" value="{{ $clientAmount }}">
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">الحد الأدنى</label>
                <input type="number" step="0.01" min="0" name="client[min_amount]" class="a2-input" style="width:100%;" value="{{ $clientMin }}">
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">الحد الأقصى</label>
                <input type="number" step="0.01" min="0" name="client[max_amount]" class="a2-input" style="width:100%;" value="{{ $clientMax }}">
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">العملة</label>
                <input type="text" name="client[currency]" class="a2-input" style="width:100%;" value="{{ $clientCurrency }}" maxlength="3">
            </div>

            <div class="a2-sf-field">
                <label class="a2-hint" style="display:block;margin-bottom:6px;">الأولوية</label>
                <input type="number" min="0" name="client[priority]" class="a2-input" style="width:100%;" value="{{ $clientPriority }}">
            </div>

            <div class="a2-sf-check">
                <label>
                    <input type="checkbox" name="client[is_active]" value="1" {{ (string) $clientIsActive === '1' ? 'checked' : '' }}>
                    نشط
                </label>
            </div>
        </div>

        <div style="margin-top:14px;">
            <label class="a2-hint" style="display:block;margin-bottom:6px;">Rules JSON</label>
            <textarea name="client[rules]" class="a2-input" style="width:100%;height:140px;padding:12px;">{{ $clientRules }}</textarea>
        </div>

        <div style="margin-top:14px;">
            <label class="a2-hint" style="display:block;margin-bottom:6px;">ملاحظات</label>
            <textarea name="client[notes]" class="a2-input" style="width:100%;height:100px;padding:12px;">{{ $clientNotes }}</textarea>
        </div>
    </div>
</div>

{{-- كارت الإجراءات أسفل الكروت --}}
<div class="a2-card a2-sf-section-card" style="margin-top:16px;">
    <div class="a2-header">
        <div>
            <h3 class="a2-sf-card-title" style="margin:0;">الإجراءات</h3>
            <div class="a2-hint">احفظ الإعداد للطرفين في نفس العملية</div>
        </div>
    </div>

    <div class="bk-action-grid">
        <button type="submit" class="a2-btn a2-btn-primary bk-action-btn">
            {{ $submitLabel ?? 'حفظ' }}
        </button>

        <a href="{{ route('admin.service-fees.index') }}" class="a2-btn bk-action-btn">
            رجوع
        </a>

        @if(!empty($groupKey))
            <a href="{{ route('admin.service-fees.show', $groupKey) }}" class="a2-btn bk-action-btn">
                عرض
            </a>
        @endif
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-a2-searchable').forEach(function (el) {
        if (el.tomselect) return;

        new TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            placeholder: 'ابحث أو اختر...',
            maxOptions: 500,
            closeAfterSelect: true,
            searchField: ['text'],
            dropdownParent: 'body',
            render: {
                no_results: function () {
                    return '<div class="no-results" style="padding:10px 12px;">لا توجد نتائج</div>';
                }
            }
        });
    });

    const businessSelect = document.getElementById('sf_business_id');
    const childSelect = document.getElementById('sf_child_id');

    function syncChildFromBusiness() {
        if (!businessSelect || !childSelect) return;
        if (!businessSelect.value) return;
        if (childSelect.value) return;

        const selectedOption = businessSelect.options[businessSelect.selectedIndex];
        if (!selectedOption) return;

        const childId = selectedOption.getAttribute('data-child-id');
        if (!childId || childId === '0') return;

        childSelect.value = childId;

        if (childSelect.tomselect) {
            childSelect.tomselect.setValue(childId, true);
        }
    }

    if (businessSelect) {
        businessSelect.addEventListener('change', function () {
            if (childSelect && childSelect.tomselect && !childSelect.tomselect.getValue()) {
                syncChildFromBusiness();
            }
        });

        setTimeout(syncChildFromBusiness, 50);
    }
});
</script>