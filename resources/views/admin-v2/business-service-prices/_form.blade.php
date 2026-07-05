@php
    $isEdit = isset($row) && $row?->exists;

    $currentItemType = old('bookable_item_type', $row->bookable_item_type ?? '');
    $currentServiceId = (int) old('service_id', $row->service_id ?? 0);
@endphp

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-section-title">ملاحظة مهمة</div>
    <div class="a2-section-subtitle">
        هذه الصفحة تحدد سعر الخدمة الذي يضعه البزنس.
        رسوم المنصة على العميل أو البزنس لا تُدار هنا، بل من شاشة
        <span dir="ltr">Service Fees</span>.
        أنواع العناصر تأتي من
        <span dir="ltr">Platform Service Item Types</span>
        ثم يتم تضييقها حسب
        <span dir="ltr">Service Catalog Matrix</span>
        للقسم الفرعي.
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
            <label class="a2-label" for="business_id">البزنس <span class="a2-danger">*</span></label>
            <select
                class="a2-select js-business-select"
                id="business_id"
                name="business_id"
                data-remote-url="{{ route('admin.business_service_prices.business-lookup') }}"
                data-placeholder="اكتب اسم البزنس"
            >
                <option value="">اختر البزنس</option>
                @if($selectedBusiness ?? null)
                    <option
                        value="{{ $selectedBusiness->id }}"
                        data-child-id="{{ (int) ($selectedBusiness->category_child_id ?? 0) }}"
                        selected
                    >
                        {{ $selectedBusiness->name ?: ('#' . $selectedBusiness->id) }}
                    </option>
                @endif
            </select>

            <div class="a2-hint a2-mt-8">
                يجب أن يكون البزنس مرتبطًا بقسم فرعي.
            </div>

            @error('business_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="child_id">القسم الفرعي <span class="a2-danger">*</span></label>
            <select class="a2-select js-child-select" id="child_id" name="child_id">
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
            <label class="a2-label" for="service_id">الخدمة <span class="a2-danger">*</span></label>
            <select class="a2-select js-service-select" id="service_id" name="service_id">
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
                يجب أن تكون الخدمة مربوطة بهذا القسم الفرعي من Service Catalog Matrix.
            </div>

            @error('service_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="bookable_item_type">نوع العنصر <span class="a2-danger">*</span></label>
            <select
                class="a2-select js-bookable-type-select"
                id="bookable_item_type"
                name="bookable_item_type"
                data-current-value="{{ $currentItemType }}"
            >
                <option value="">اختر نوع العنصر</option>
            </select>

            <div class="a2-hint a2-mt-8 js-bookable-type-hint">
                اختر البزنس والخدمة أولًا لعرض أنواع العناصر المتاحة لهذا القسم الفرعي.
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
            <div class="a2-card-sub">الديبوزت ضمان/حجز فقط. إلغاءه لا يلغي رسوم استخدام الخدمة لصالح التطبيق.</div>
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

    const itemTypesUrl = @json(route('admin.business_service_prices.item-types-lookup'));
    let requestSeq = 0;

    function setTypeHint(message) {
        if (bookableTypeHint) bookableTypeHint.textContent = message;
    }

    function setBusinessChild(childId) {
        childId = String(childId || '');
        if (childId && childId !== '0' && childSelect) {
            childSelect.value = childId;
        }
    }

    // Business picker: search-as-you-type against the server instead of
    // embedding ~1,750 businesses as static <option> tags on every load.
    function initBusinessSelect() {
        if (!businessSelect) return;
        const remoteUrl = businessSelect.dataset.remoteUrl;

        if (!window.TomSelect || !remoteUrl) {
            businessSelect.addEventListener('change', onNativeBusinessChange);
            return;
        }

        new TomSelect(businessSelect, {
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            create: false,
            maxOptions: 30,
            placeholder: businessSelect.dataset.placeholder || 'ابحث هنا',
            dropdownParent: 'body',
            shouldLoad: function (query) { return query.length >= 1; },
            load: function (query, callback) {
                const url = new URL(remoteUrl, window.location.origin);
                url.searchParams.set('q', query);
                fetch(url.toString(), {headers: {'Accept': 'application/json'}})
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        const rows = (data && data.ok && Array.isArray(data.businesses)) ? data.businesses : [];
                        callback(rows.map(function (b) {
                            return {value: String(b.id), text: b.name, child_id: String(b.category_child_id || '')};
                        }));
                    })
                    .catch(function () { callback(); });
            },
            onChange: function (value) {
                const opt = this.options[value];
                if (opt && typeof opt.child_id !== 'undefined') {
                    setBusinessChild(opt.child_id);
                }
                if (bookableTypeSelect) bookableTypeSelect.dataset.currentValue = '';
                refreshBookableTypeOptions();
            },
        });
    }

    function onNativeBusinessChange() {
        const selected = businessSelect.options[businessSelect.selectedIndex];
        setBusinessChild(selected ? selected.dataset.childId : '');
        if (bookableTypeSelect) bookableTypeSelect.dataset.currentValue = '';
        refreshBookableTypeOptions();
    }

    function optionLabel(item) {
        if (!item) return '';
        return String(item.label || item.key || '').trim();
    }

    function refreshBookableTypeOptions() {
        if (!serviceSelect || !bookableTypeSelect) return;

        const serviceId = String(serviceSelect.value || '');
        const childId = String(childSelect ? childSelect.value : '');
        const currentValue = String(bookableTypeSelect.dataset.currentValue || '');

        if (!serviceId || !childId) {
            bookableTypeSelect.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'اختر البزنس والخدمة أولًا';
            bookableTypeSelect.appendChild(option);
            setTypeHint('اختر البزنس والخدمة أولًا لعرض أنواع العناصر المتاحة لهذا القسم الفرعي.');
            return;
        }

        // On-demand lookup for the one selected (child, service) pair instead
        // of a precomputed 304 x 5 matrix embedded in the page. Sequence guard
        // drops stale responses if the user changes selection mid-flight.
        const seq = ++requestSeq;
        const url = new URL(itemTypesUrl, window.location.origin);
        url.searchParams.set('child_id', childId);
        url.searchParams.set('service_id', serviceId);

        setTypeHint('جاري تحميل أنواع العناصر...');

        fetch(url.toString(), {headers: {'Accept': 'application/json'}})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (seq !== requestSeq) return;

                const options = (data && data.ok && Array.isArray(data.items)) ? data.items : [];

                bookableTypeSelect.innerHTML = '';

                if (!options.length) {
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = 'لا توجد أنواع مسموحة لهذا القسم مع هذه الخدمة';
                    bookableTypeSelect.appendChild(option);
                    setTypeHint((data && data.has_base_types)
                        ? 'الخدمة لها أنواع عامة، لكن لم يتم السماح بأي نوع لهذا القسم الفرعي داخل Service Catalog Matrix.'
                        : 'أضف أنواع عناصر لهذه الخدمة من Platform Service Item Types ثم اسمح بها من Service Catalog Matrix.');
                    return;
                }

                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'اختر نوع العنصر';
                bookableTypeSelect.appendChild(emptyOption);

                let selectedApplied = false;
                options.forEach(function (item) {
                    const value = String(item.key || '');
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = optionLabel(item);
                    if (value === currentValue) {
                        option.selected = true;
                        selectedApplied = true;
                    }
                    bookableTypeSelect.appendChild(option);
                });

                if (!selectedApplied && bookableTypeSelect.options.length > 1) {
                    bookableTypeSelect.options[1].selected = true;
                }

                setTypeHint('هذه القائمة تعرض فقط الأنواع المسموحة لهذا القسم الفرعي والخدمة حسب Service Catalog Matrix.');
            })
            .catch(function () {
                if (seq !== requestSeq) return;
                bookableTypeSelect.innerHTML = '';
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'تعذر تحميل أنواع العناصر';
                bookableTypeSelect.appendChild(option);
                setTypeHint('تعذر تحميل أنواع العناصر. حاول مرة أخرى.');
            });
    }

    function refreshDepositUI() {
        if (!serviceSelect) return;

        const selected = serviceSelect.options[serviceSelect.selectedIndex];
        const supportsDeposit = selected ? String(selected.dataset.supportsDeposit || '0') === '1' : false;

        if (depositHint) {
            depositHint.textContent = supportsDeposit
                ? 'هذه الخدمة تدعم الديبوزت كضمان فقط، ورسوم استخدام الخدمة مستقلة عنه.'
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

    if (childSelect) {
        childSelect.addEventListener('change', function () {
            if (bookableTypeSelect) bookableTypeSelect.dataset.currentValue = '';
            refreshBookableTypeOptions();
        });
    }

    if (serviceSelect) {
        serviceSelect.addEventListener('change', function () {
            if (bookableTypeSelect) bookableTypeSelect.dataset.currentValue = '';
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

    initBusinessSelect();
    refreshBookableTypeOptions();
    refreshDepositUI();
    refreshDiscountUI();
});
</script>
@endpush
