@php
    $isEdit = isset($row) && $row?->exists;

    $bookableTypeOptions = [
        'category'          => 'Category Default',
        'single_room'       => 'Single Room',
        'double_room'       => 'Double Room',
        'suite'             => 'Suite',
        'family_room'       => 'Family Room',
        'apartment'         => 'Apartment',
        'villa'             => 'Villa',
        'five_side_field'   => 'Five Side Field',
        'full_field'        => 'Full Field',
        'padel_court'       => 'Padel Court',
        'consultation_slot' => 'Consultation Slot',
        'followup_slot'     => 'Follow-up Slot',
        'hall_standard'     => 'Standard Hall',
        'hall_vip'          => 'VIP Hall',
        'table_2'           => 'Table 2',
        'table_4'           => 'Table 4',
        'table_6'           => 'Table 6',
        'vip_table'         => 'VIP Table',
    ];

    $businessChildMap = [];
    foreach (($businesses ?? []) as $business) {
        $businessChildMap[(int) $business->id] = (int) ($business->category_child_id ?? 0);
    }
@endphp

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">ملاحظة مهمة</div>
    <div class="a2-section-subtitle">
        هذه الصفحة تحدد سعر الخدمة الذي يضعه البزنس.
        رسوم المنصة على العميل أو البزنس لا تُدار هنا، بل من شاشة
        <span dir="ltr">Service Fees</span>.
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">البيانات الأساسية</div>
            <div class="a2-card-sub">البزنس والقسم الفرعي والخدمة ونوع العنصر</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">البزنس <span class="a2-danger">*</span></label>
            <select class="a2-select js-business-select" name="business_id">
                <option value="">اختر البزنس</option>
                @foreach(($businesses ?? []) as $business)
                    <option
                        value="{{ $business->id }}"
                        data-child-id="{{ (int) ($business->category_child_id ?? 0) }}"
                        @selected((string) old('business_id', $row->business_id ?? '') === (string) $business->id)
                    >
                        {{ $business->name ?: ('#' . $business->id) }}
                    </option>
                @endforeach
            </select>

            <div class="a2-hint a2-mt-8">
                يجب أن يكون البزنس مرتبطًا بقسم فرعي.
            </div>

            @error('business_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">القسم الفرعي <span class="a2-danger">*</span></label>
            <select class="a2-select js-child-select" name="child_id">
                <option value="">اختر القسم الفرعي</option>
                @foreach(($children ?? []) as $child)
                    <option
                        value="{{ $child->id }}"
                        @selected((string) old('child_id', $row->child_id ?? '') === (string) $child->id)
                    >
                        {{ $child->name_ar ?: ($child->name_en ?: ('#' . $child->id)) }}
                    </option>
                @endforeach
            </select>

            <div class="a2-hint a2-mt-8">
                سيتم رفض الحفظ إذا كان القسم الفرعي لا يطابق القسم المرتبط بالبزنس.
            </div>

            @error('child_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الخدمة <span class="a2-danger">*</span></label>
            <select class="a2-select js-service-select" name="service_id">
                <option value="">اختر الخدمة</option>
                @foreach(($services ?? []) as $service)
                    <option
                        value="{{ $service->id }}"
                        data-supports-deposit="{{ (int) ($service->supports_deposit ?? 0) }}"
                        data-max-deposit-percent="{{ (int) ($service->max_deposit_percent ?? 0) }}"
                        @selected((string) old('service_id', $row->service_id ?? '') === (string) $service->id)
                    >
                        {{ $service->name_ar ?: ($service->name_en ?: $service->key) }}
                    </option>
                @endforeach
            </select>

            <div class="a2-hint a2-mt-8">
                يجب أن تكون الخدمة مربوطة بهذا القسم الفرعي من شاشة التصنيفات.
            </div>

            @error('service_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">نوع العنصر <span class="a2-danger">*</span></label>
            <select class="a2-select" name="bookable_item_type">
                @foreach($bookableTypeOptions as $value => $label)
                    <option
                        value="{{ $value }}"
                        @selected((string) old('bookable_item_type', $row->bookable_item_type ?? 'category') === (string) $value)
                    >
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <div class="a2-hint a2-mt-8">
                استخدم category للسعر الافتراضي، واستخدم الأنواع الأخرى عند وجود عناصر قابلة للحجز.
            </div>

            @error('bookable_item_type')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">السعر والحالة</div>
            <div class="a2-card-sub">السعر الأساسي والتفعيل</div>
        </div>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">السعر <span class="a2-danger">*</span></label>
            <input
                class="a2-input"
                name="price"
                value="{{ old('price', $row->price ?? 0) }}"
                inputmode="decimal"
                placeholder="0.00"
            >

            @error('price')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">العملة</label>
            <input
                class="a2-input"
                name="currency"
                value="{{ old('currency', $row->currency ?? 'EGP') }}"
                dir="ltr"
                maxlength="3"
                placeholder="EGP"
                style="text-transform:uppercase;"
            >

            @error('currency')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>

            <label class="a2-check" style="margin-top:10px;">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked((bool) old('is_active', (int) ($row->is_active ?? 1)))
                >
                <span>السعر مفعل</span>
            </label>

            @error('is_active')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">الخصم والديبوزت</div>
            <div class="a2-card-sub">إعدادات الخصم والدفعة المقدمة حسب الخدمة</div>
        </div>
    </div>

    <div class="a2-check-grid" style="margin-bottom:16px;">
        <label class="a2-check-card">
            <input
                type="checkbox"
                name="discount_enabled"
                value="1"
                id="discount_enabled"
                @checked((bool) old('discount_enabled', (int) ($row->discount_enabled ?? 0)))
            >
            <span>تفعيل الخصم</span>
        </label>

        <label class="a2-check-card">
            <input
                type="checkbox"
                name="deposit_enabled"
                value="1"
                id="deposit_enabled"
                @checked((bool) old('deposit_enabled', (int) ($row->deposit_enabled ?? 0)))
            >
            <span>تفعيل الديبوزت</span>
        </label>
    </div>

    <div class="a2-form-grid-3">
        <div class="a2-form-group">
            <label class="a2-label">نسبة الخصم %</label>
            <input
                class="a2-input"
                name="discount_percent"
                id="discount_percent"
                value="{{ old('discount_percent', (int) ($row->discount_percent ?? 0)) }}"
                inputmode="numeric"
                placeholder="0"
            >

            @error('discount_percent')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">نسبة الديبوزت %</label>
            <input
                class="a2-input"
                name="deposit_percent"
                id="deposit_percent"
                value="{{ old('deposit_percent', (int) ($row->deposit_percent ?? 0)) }}"
                inputmode="numeric"
                placeholder="0"
            >

            <div class="a2-section-subtitle" id="deposit_hint" style="margin-top:8px;margin-bottom:0;"></div>

            @error('deposit_percent')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    @if(!empty($backUrl ?? null))
        <a href="{{ $backUrl }}" class="a2-btn a2-btn-ghost">رجوع</a>
    @else
        <a href="{{ route('admin.business_service_prices.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    @endif

    <button type="submit" class="a2-btn a2-btn-primary">
        {{ $isEdit ? 'تحديث' : 'حفظ' }}
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const businessSelect = document.querySelector('.js-business-select');
    const childSelect = document.querySelector('.js-child-select');

    const serviceSelect = document.querySelector('.js-service-select');
    const depositEnabled = document.getElementById('deposit_enabled');
    const depositPercent = document.getElementById('deposit_percent');
    const depositHint = document.getElementById('deposit_hint');

    const discountEnabled = document.getElementById('discount_enabled');
    const discountPercent = document.getElementById('discount_percent');

    function refreshBusinessChild() {
        if (!businessSelect || !childSelect) return;

        const selected = businessSelect.options[businessSelect.selectedIndex];
        const childId = selected ? String(selected.dataset.childId || '') : '';

        if (childId && childId !== '0') {
            childSelect.value = childId;
        }
    }

    function refreshDepositUI() {
        if (!serviceSelect) return;

        const selected = serviceSelect.options[serviceSelect.selectedIndex];
        const supportsDeposit = selected ? String(selected.dataset.supportsDeposit || '0') === '1' : false;
        const maxPercent = selected ? parseInt(selected.dataset.maxDepositPercent || '0', 10) : 0;

        if (depositHint) {
            if (!supportsDeposit) {
                depositHint.textContent = 'هذه الخدمة لا تدعم الديبوزت.';
            } else {
                depositHint.textContent = 'الحد الأقصى للديبوزت لهذه الخدمة: ' + maxPercent + '%';
            }
        }

        if (depositEnabled) {
            depositEnabled.disabled = !supportsDeposit;
            if (!supportsDeposit) {
                depositEnabled.checked = false;
            }
        }

        if (depositPercent) {
            depositPercent.max = String(maxPercent || 100);
            depositPercent.disabled = !supportsDeposit || !depositEnabled.checked;

            if (!supportsDeposit) {
                depositPercent.value = 0;
            }
        }
    }

    function refreshDiscountUI() {
        if (!discountEnabled || !discountPercent) return;

        discountPercent.disabled = !discountEnabled.checked;

        if (!discountEnabled.checked) {
            discountPercent.value = 0;
        }
    }

    if (businessSelect) {
        businessSelect.addEventListener('change', refreshBusinessChild);
    }

    if (serviceSelect) {
        serviceSelect.addEventListener('change', refreshDepositUI);
    }

    if (depositEnabled) {
        depositEnabled.addEventListener('change', refreshDepositUI);
    }

    if (discountEnabled) {
        discountEnabled.addEventListener('change', refreshDiscountUI);
    }

    refreshDepositUI();
    refreshDiscountUI();
});
</script>
@endpush