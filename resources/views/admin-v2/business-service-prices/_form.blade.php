@php
    $isEdit = isset($row) && $row?->exists;

    $businessChildMap = [];
    foreach (($businesses ?? []) as $business) {
        $businessChildMap[(int) $business->id] = (int) ($business->category_child_id ?? 0);
    }

    $currentItemType = old('bookable_item_type', $row->bookable_item_type ?? '');
    $currentServiceId = (int) old('service_id', $row->service_id ?? 0);

    $itemTypesByServiceSafe = is_array($itemTypesByService ?? null)
        ? $itemTypesByService
        : [];
@endphp

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">ملاحظة مهمة</div>
    <div class="a2-section-subtitle">
        هذه الصفحة تحدد سعر الخدمة الذي يضعه البزنس.
        رسوم المنصة على العميل أو البزنس لا تُدار هنا، بل من شاشة
        <span dir="ltr">Service Fees</span>.
        أنواع العناصر تأتي من
        <span dir="ltr">Platform Service Item Types</span>.
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
                        data-service-key="{{ strtolower((string) ($service->key ?? '')) }}"
                        data-supports-deposit="{{ (int) ($service->supports_deposit ?? 0) }}"
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
            <select
                class="a2-select js-bookable-type-select"
                name="bookable_item_type"
                data-current-value="{{ $currentItemType }}"
            >
                <option value="">اختر نوع العنصر</option>
            </select>

            <div class="a2-hint a2-mt-8 js-bookable-type-hint">
                اختر الخدمة أولًا لعرض أنواع العناصر المتاحة لها.
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
    const bookableTypeSelect = document.querySelector('.js-bookable-type-select');
    const bookableTypeHint = document.querySelector('.js-bookable-type-hint');

    const depositEnabled = document.getElementById('deposit_enabled');
    const depositPercent = document.getElementById('deposit_percent');
    const depositHint = document.getElementById('deposit_hint');

    const discountEnabled = document.getElementById('discount_enabled');
    const discountPercent = document.getElementById('discount_percent');

    const itemTypesByService = @json($itemTypesByServiceSafe);

    function refreshBusinessChild() {
        if (!businessSelect || !childSelect) return;

        const selected = businessSelect.options[businessSelect.selectedIndex];
        const childId = selected ? String(selected.dataset.childId || '') : '';

        if (childId && childId !== '0') {
            childSelect.value = childId;
        }
    }

    function optionLabel(item) {
        if (!item) return '';

        const ar = String(item.name_ar || '').trim();
        const en = String(item.name_en || '').trim();
        const key = String(item.key || '').trim();

        return ar || en || key;
    }

    function refreshBookableTypeOptions() {
        if (!serviceSelect || !bookableTypeSelect) return;

        const serviceId = String(serviceSelect.value || '');
        const currentValue = String(bookableTypeSelect.value || bookableTypeSelect.dataset.currentValue || '');
        const savedValue = String(bookableTypeSelect.dataset.currentValue || '');

        const options = itemTypesByService[serviceId] || [];

        bookableTypeSelect.innerHTML = '';

        if (!serviceId) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'اختر الخدمة أولًا';
            bookableTypeSelect.appendChild(option);

            if (bookableTypeHint) {
                bookableTypeHint.textContent = 'اختر الخدمة أولًا لعرض أنواع العناصر المتاحة لها.';
            }

            return;
        }

        if (!options.length) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'لا توجد أنواع عناصر مفعلة لهذه الخدمة';
            bookableTypeSelect.appendChild(option);

            if (bookableTypeHint) {
                bookableTypeHint.textContent = 'أضف أنواع عناصر لهذه الخدمة من شاشة Platform Service Item Types.';
            }

            return;
        }

        let selectedApplied = false;
        let defaultKey = '';

        options.forEach(function (item) {
            if (item.is_default && !defaultKey) {
                defaultKey = String(item.key || '');
            }
        });

        options.forEach(function (item) {
            const value = String(item.key || '');
            const option = document.createElement('option');

            option.value = value;
            option.textContent = optionLabel(item);

            if (
                value === currentValue ||
                value === savedValue ||
                (!selectedApplied && !currentValue && !savedValue && defaultKey && value === defaultKey)
            ) {
                option.selected = true;
                selectedApplied = true;
            }

            bookableTypeSelect.appendChild(option);
        });

        if (!selectedApplied) {
            const first = bookableTypeSelect.querySelector('option');
            if (first) {
                first.selected = true;
            }
        }

        if (bookableTypeHint) {
            bookableTypeHint.textContent = 'هذه القائمة تأتي من Platform Service Item Types ويمكن تعديلها من لوحة الإدارة.';
        }
    }

    function refreshDepositUI() {
        if (!serviceSelect) return;

        const selected = serviceSelect.options[serviceSelect.selectedIndex];
        const supportsDeposit = selected ? String(selected.dataset.supportsDeposit || '0') === '1' : false;

        if (depositHint) {
            depositHint.textContent = supportsDeposit
                ? 'هذه الخدمة تدعم الديبوزت، لكن الحد أو السياسة لا تأتي من PlatformService.'
                : 'هذه الخدمة لا تدعم الديبوزت.';
        }

        if (depositEnabled) {
            depositEnabled.disabled = !supportsDeposit;
            if (!supportsDeposit) {
                depositEnabled.checked = false;
            }
        }

        if (depositPercent) {
            depositPercent.removeAttribute('max');
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
        serviceSelect.addEventListener('change', function () {
            bookableTypeSelect.dataset.currentValue = '';
            refreshBookableTypeOptions();
            refreshDepositUI();
        });
    }

    if (depositEnabled) {
        depositEnabled.addEventListener('change', refreshDepositUI);
    }

    if (discountEnabled) {
        discountEnabled.addEventListener('change', refreshDiscountUI);
    }

    refreshBusinessChild();
    refreshBookableTypeOptions();
    refreshDepositUI();
    refreshDiscountUI();
});
</script>
@endpush