@if ($errors->any())
    <div class="a2-alert a2-alert-danger" style="margin-bottom:12px;">
        <ul style="margin:0;padding-inline-start:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $selectedServiceId = old('service_id', $row->service_id ?? '');
    $selectedService = $services->firstWhere('id', (int) $selectedServiceId);

    $supportsDeposit = (int) old('supports_deposit_preview', $selectedService->supports_deposit ?? 0);
    $maxDepositPercent = (int) old('max_deposit_percent_preview', $selectedService->max_deposit_percent ?? 0);

    $selectedBusinessId = old('business_id', $row->business_id ?? '');
    $selectedBusiness = $businesses->firstWhere('id', (int) $selectedBusinessId);

    $priceValue = old('price', $row->price ?? 0);

    $depositEnabledValue = old('deposit_enabled', $row->deposit_enabled ?? 0);
    $depositPercentValue = old('deposit_percent', $row->deposit_percent ?? 0);

    $discountEnabledValue = old('discount_enabled', $row->discount_enabled ?? 0);
    $discountPercentValue = old('discount_percent', $row->discount_percent ?? 0);
@endphp

<div style="display:grid;grid-template-columns:minmax(0,1fr);gap:16px;">

    {{-- البيانات الأساسية --}}
    <div class="a2-card" style="padding:18px;">
        <div class="a2-title" style="font-size:18px;margin-bottom:14px;">البيانات الأساسية</div>

        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">

            <div style="position:relative;">
                <label class="a2-label">البزنس</label>

                <input type="hidden" name="business_id" id="business_id" value="{{ $selectedBusinessId }}">

                <input
                    type="text"
                    id="business_search"
                    class="a2-input"
                    placeholder="ابحث باسم البزنس..."
                    autocomplete="off"
                    value="{{ $selectedBusiness->name ?? '' }}"
                    required
                >

                <div id="business_dropdown"
                     style="
                        display:none;
                        position:absolute;
                        inset-inline:0;
                        top:calc(100% + 6px);
                        background:#fff;
                        border:1px solid #e5e7eb;
                        border-radius:14px;
                        box-shadow:0 12px 30px rgba(15,23,42,.08);
                        max-height:260px;
                        overflow:auto;
                        z-index:50;
                     ">
                    @foreach($businesses as $business)
                        <button
                            type="button"
                            class="business-option"
                            data-id="{{ $business->id }}"
                            data-name="{{ $business->name }}"
                            style="
                                width:100%;
                                text-align:right;
                                padding:12px 14px;
                                border:0;
                                background:#fff;
                                cursor:pointer;
                                display:block;
                            "
                            onmouseover="this.style.background='#f8fafc'"
                            onmouseout="this.style.background='#fff'"
                        >
                            {{ $business->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="a2-label">الخدمة</label>
                <select name="service_id" id="service_id" class="a2-select" required>
                    <option value="">اختر الخدمة</option>
                    @foreach($services as $service)
                        <option
                            value="{{ $service->id }}"
                            data-supports-deposit="{{ (int) $service->supports_deposit }}"
                            data-max-deposit-percent="{{ (int) ($service->max_deposit_percent ?? 0) }}"
                            data-service-name="{{ $service->name_ar ?: $service->name_en }}"
                            @selected((string) old('service_id', $row->service_id) === (string) $service->id)
                        >
                            {{ $service->name_ar ?: $service->name_en }}
                            @if(!empty($service->key)) ({{ $service->key }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="a2-label">السعر قبل الخصم</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="price"
                    id="price"
                    class="a2-input"
                    value="{{ $priceValue }}"
                    required
                >
            </div>

            <div style="display:flex;align-items:end;">
                <label style="display:flex;gap:8px;align-items:center;font-weight:800;">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked(old('is_active', $row->is_active ?? 1))
                    >
                    نشط
                </label>
            </div>
        </div>
    </div>

    {{-- إعدادات الخصم --}}
    <div class="a2-card" style="padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
            <div>
                <div class="a2-title" style="font-size:18px;">إعدادات الخصم</div>
                <div class="a2-muted">إذا فعّل البزنس الخصم سيتم حساب السعر بعد الخصم مباشرة</div>
            </div>

            <label style="display:flex;gap:8px;align-items:center;font-weight:800;">
                <input
                    type="checkbox"
                    name="discount_enabled"
                    id="discount_enabled"
                    value="1"
                    @checked($discountEnabledValue)
                >
                تفعيل الخصم
            </label>
        </div>

        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;">
            <div>
                <label class="a2-label">نسبة الخصم %</label>
                <input
                    type="number"
                    min="0"
                    max="100"
                    name="discount_percent"
                    id="discount_percent"
                    class="a2-input"
                    value="{{ $discountPercentValue }}"
                >
            </div>

            <div>
                <label class="a2-label">قيمة الخصم</label>
                <input
                    type="text"
                    id="discount_amount_preview"
                    class="a2-input"
                    value="0.00"
                    readonly
                >
            </div>

            <div>
                <label class="a2-label">السعر بعد الخصم</label>
                <input
                    type="text"
                    id="price_after_discount_preview"
                    class="a2-input"
                    value="0.00"
                    readonly
                >
            </div>

            <div>
                <label class="a2-label">الحالة</label>
                <input
                    type="text"
                    id="discount_status_preview"
                    class="a2-input"
                    value="غير مفعل"
                    readonly
                >
            </div>
        </div>

        <div id="discount_help_box" class="a2-alert" style="margin-top:14px;background:#f8fafc;border:1px solid #e5e7eb;">
            سيتم حساب الخصم مباشرة من السعر الأساسي.
        </div>
    </div>

    {{-- إعدادات الديبوزت --}}
    <div class="a2-card" style="padding:18px;">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
            <div>
                <div class="a2-title" style="font-size:18px;">إعدادات الديبوزت</div>
                <div class="a2-muted">يتم حساب الديبوزت على السعر بعد الخصم إن وجد</div>
            </div>

            <label style="display:flex;gap:8px;align-items:center;font-weight:800;">
                <input
                    type="checkbox"
                    name="deposit_enabled"
                    id="deposit_enabled"
                    value="1"
                    @checked($depositEnabledValue)
                >
                تفعيل الديبوزت
            </label>
        </div>

        <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;">
            <div>
                <label class="a2-label">نسبة الديبوزت %</label>
                <input
                    type="number"
                    min="0"
                    max="100"
                    name="deposit_percent"
                    id="deposit_percent"
                    class="a2-input"
                    value="{{ $depositPercentValue }}"
                >
            </div>

            <div>
                <label class="a2-label">الحد الأقصى المسموح</label>
                <input
                    type="text"
                    id="max_deposit_percent_preview"
                    class="a2-input"
                    value="{{ $maxDepositPercent }}%"
                    readonly
                >
            </div>

            <div>
                <label class="a2-label">الخدمة تدعم ديبوزت؟</label>
                <input
                    type="text"
                    id="supports_deposit_preview"
                    class="a2-input"
                    value="{{ $supportsDeposit ? 'نعم' : 'لا' }}"
                    readonly
                >
            </div>

            <div>
                <label class="a2-label">قيمة الديبوزت</label>
                <input
                    type="text"
                    id="deposit_amount_preview"
                    class="a2-input"
                    value="0.00"
                    readonly
                >
            </div>

            <div>
                <label class="a2-label">المتبقي بعد الديبوزت</label>
                <input
                    type="text"
                    id="remaining_amount_preview"
                    class="a2-input"
                    value="0.00"
                    readonly
                >
            </div>
        </div>

        <div id="deposit_help_box" class="a2-alert" style="margin-top:14px;background:#f8fafc;border:1px solid #e5e7eb;">
            سيتم حساب قيمة الديبوزت تلقائيًا من السعر بعد الخصم × نسبة الديبوزت.
        </div>
    </div>

    {{-- أزرار الحفظ --}}
    <div class="a2-card" style="padding:16px;">
        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;">
            <div class="a2-muted" style="line-height:1.8;">
                السعر الأساسي ← الخصم ← السعر بعد الخصم ← الديبوزت ← المتبقي.
            </div>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('admin.business_service_prices.index') }}" class="a2-btn">رجوع</a>
                <button type="submit" class="a2-btn a2-btn-primary">
                    {{ !empty($isEdit) ? 'حفظ التعديلات' : 'إنشاء السجل' }}
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const businessSearch = document.getElementById('business_search');
    const businessIdInput = document.getElementById('business_id');
    const businessDropdown = document.getElementById('business_dropdown');

    const serviceSelect = document.getElementById('service_id');
    const priceInput = document.getElementById('price');

    const discountEnabled = document.getElementById('discount_enabled');
    const discountPercentInput = document.getElementById('discount_percent');
    const discountAmountPreview = document.getElementById('discount_amount_preview');
    const priceAfterDiscountPreview = document.getElementById('price_after_discount_preview');
    const discountStatusPreview = document.getElementById('discount_status_preview');
    const discountHelpBox = document.getElementById('discount_help_box');

    const depositEnabled = document.getElementById('deposit_enabled');
    const depositPercentInput = document.getElementById('deposit_percent');
    const supportsDepositPreview = document.getElementById('supports_deposit_preview');
    const maxDepositPercentPreview = document.getElementById('max_deposit_percent_preview');
    const depositAmountPreview = document.getElementById('deposit_amount_preview');
    const remainingAmountPreview = document.getElementById('remaining_amount_preview');
    const depositHelpBox = document.getElementById('deposit_help_box');

    // business search
    if (businessSearch && businessIdInput && businessDropdown) {
        const options = Array.from(businessDropdown.querySelectorAll('.business-option'));
        const businessWrapper = businessSearch.closest('div');

        function normalizeText(value) {
            return (value || '').toString().trim().toLocaleLowerCase();
        }

        function closeBusinessDropdown() {
            businessDropdown.style.display = 'none';
        }

        function filterBusinesses() {
            const keyword = normalizeText(businessSearch.value);
            let visibleCount = 0;

            options.forEach(option => {
                const name = normalizeText(option.dataset.name);

                if (keyword === '' || name.includes(keyword)) {
                    option.style.display = 'block';
                    visibleCount++;
                } else {
                    option.style.display = 'none';
                }
            });

            businessDropdown.style.display = visibleCount > 0 ? 'block' : 'none';
        }

        businessSearch.addEventListener('focus', filterBusinesses);

        businessSearch.addEventListener('input', function () {
            businessIdInput.value = '';
            filterBusinesses();
        });

        options.forEach(option => {
            option.addEventListener('click', function () {
                businessIdInput.value = option.dataset.id;
                businessSearch.value = option.dataset.name;
                closeBusinessDropdown();
            });
        });

        document.addEventListener('click', function (e) {
            if (!businessWrapper.contains(e.target)) {
                closeBusinessDropdown();
            }
        });

        businessSearch.form?.addEventListener('submit', function (e) {
            if (!businessIdInput.value) {
                e.preventDefault();
                businessSearch.focus();
                alert('من فضلك اختر البزنس من القائمة.');
            }
        });
    }

    function getSelectedOption() {
        return serviceSelect.options[serviceSelect.selectedIndex] || null;
    }

    function getSupportsDeposit() {
        const option = getSelectedOption();
        return option ? parseInt(option.dataset.supportsDeposit || '0', 10) : 0;
    }

    function getMaxDepositPercent() {
        const option = getSelectedOption();
        return option ? parseInt(option.dataset.maxDepositPercent || '0', 10) : 0;
    }

    function getPrice() {
        return parseFloat(priceInput.value || '0');
    }

    function getDiscountPercent() {
        return parseFloat(discountPercentInput.value || '0');
    }

    function getDepositPercent() {
        return parseFloat(depositPercentInput.value || '0');
    }

    function formatMoney(value) {
        return Number(value || 0).toFixed(2);
    }

    function refreshDiscountUI() {
        const price = getPrice();
        let discountPercent = getDiscountPercent();

        if (!discountEnabled.checked) {
            discountPercent = 0;
            discountAmountPreview.value = '0.00';
            priceAfterDiscountPreview.value = formatMoney(price);
            discountStatusPreview.value = 'غير مفعل';
            discountHelpBox.innerHTML = 'الخصم غير مفعل حاليًا.';
            return {
                price: price,
                discountPercent: 0,
                discountAmount: 0,
                priceAfterDiscount: price
            };
        }

        if (discountPercent < 0 || isNaN(discountPercent)) {
            discountPercent = 0;
            discountPercentInput.value = 0;
        }

        if (discountPercent > 100) {
            discountPercent = 100;
            discountPercentInput.value = 100;
        }

        const discountAmount = price > 0 ? (price * discountPercent / 100) : 0;
        const priceAfterDiscount = price - discountAmount;

        discountAmountPreview.value = formatMoney(discountAmount);
        priceAfterDiscountPreview.value = formatMoney(priceAfterDiscount);
        discountStatusPreview.value = 'مفعل';

        discountHelpBox.innerHTML =
            'السعر قبل الخصم: <strong>' + formatMoney(price) + '</strong> — ' +
            'نسبة الخصم: <strong>' + discountPercent + '%</strong> — ' +
            'قيمة الخصم: <strong>' + formatMoney(discountAmount) + '</strong> — ' +
            'السعر بعد الخصم: <strong>' + formatMoney(priceAfterDiscount) + '</strong>';

        return {
            price: price,
            discountPercent: discountPercent,
            discountAmount: discountAmount,
            priceAfterDiscount: priceAfterDiscount
        };
    }

    function refreshDepositUI() {
        const supportsDeposit = getSupportsDeposit();
        const maxPercent = getMaxDepositPercent();
        const discountState = refreshDiscountUI();

        let depositPercent = getDepositPercent();
        const priceAfterDiscount = discountState.priceAfterDiscount;

        supportsDepositPreview.value = supportsDeposit ? 'نعم' : 'لا';
        maxDepositPercentPreview.value = maxPercent + '%';

        if (!supportsDeposit) {
            depositEnabled.checked = false;
            depositPercentInput.value = 0;
            depositPercentInput.setAttribute('readonly', 'readonly');
            depositAmountPreview.value = '0.00';
            remainingAmountPreview.value = formatMoney(priceAfterDiscount);
            depositHelpBox.innerHTML = 'الخدمة المختارة لا تدعم الديبوزت، لذلك تم تعطيل الإعدادات.';
            return;
        }

        depositPercentInput.removeAttribute('readonly');

        if (!depositEnabled.checked) {
            depositAmountPreview.value = '0.00';
            remainingAmountPreview.value = formatMoney(priceAfterDiscount);
            depositHelpBox.innerHTML = 'الديبوزت غير مفعل حاليًا لهذا البزنس.';
            return;
        }

        if (depositPercent > maxPercent) {
            depositPercent = maxPercent;
            depositPercentInput.value = maxPercent;
        }

        if (depositPercent < 0 || isNaN(depositPercent)) {
            depositPercent = 0;
            depositPercentInput.value = 0;
        }

        const depositAmount = priceAfterDiscount > 0 ? (priceAfterDiscount * depositPercent / 100) : 0;
        const remainingAmount = priceAfterDiscount - depositAmount;

        depositAmountPreview.value = formatMoney(depositAmount);
        remainingAmountPreview.value = formatMoney(remainingAmount);

        depositHelpBox.innerHTML =
            'السعر بعد الخصم: <strong>' + formatMoney(priceAfterDiscount) + '</strong> — ' +
            'نسبة الديبوزت: <strong>' + depositPercent + '%</strong> — ' +
            'قيمة الديبوزت: <strong>' + formatMoney(depositAmount) + '</strong> — ' +
            'المتبقي: <strong>' + formatMoney(remainingAmount) + '</strong>';
    }

    serviceSelect.addEventListener('change', refreshDepositUI);
    priceInput.addEventListener('input', refreshDepositUI);

    discountEnabled.addEventListener('change', refreshDepositUI);
    discountPercentInput.addEventListener('input', refreshDepositUI);

    depositEnabled.addEventListener('change', refreshDepositUI);
    depositPercentInput.addEventListener('input', refreshDepositUI);

    refreshDepositUI();
});
</script>