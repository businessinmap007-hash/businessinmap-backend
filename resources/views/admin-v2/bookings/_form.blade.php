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
    $selectedServiceId = (string) old('service_id', $booking->service_id ?? '');
    $selectedBusinessId = (string) old('business_id', $booking->business_id ?? '');
    $selectedClientId = (string) old('user_id', $booking->user_id ?? '');
    $selectedBookableId = (string) old('bookable_id', $booking->bookable_id ?? '');

    $startDateValue = old('date', optional($booking->date)->format('Y-m-d'));
    $startTimeValue = old('time', $booking->time ? \Illuminate\Support\Str::limit($booking->time, 5, '') : '');
    $endsAtValue = old('ends_at', optional($booking->ends_at)->format('Y-m-d\TH:i'));
    $durationMode = old('duration_unit', $booking->duration_unit ?? 'day');
    $durationValueOld = old('duration_value', $booking->duration_value ?? 1);
    $quantityValue = old('quantity', $booking->quantity ?? 1);

    $selectedClient = $clients->firstWhere('id', (int) $selectedClientId);
    $selectedBusiness = $businesses->firstWhere('id', (int) $selectedBusinessId);
    $selectedService = $services->firstWhere('id', (int) $selectedServiceId);
@endphp

<div style="display:grid;grid-template-columns:minmax(0,1fr);gap:16px;">

    <div class="a2-card" style="padding:18px;">
        <div class="a2-title" style="font-size:18px;margin-bottom:14px;">البيانات الأساسية</div>

        <div class="bk-form-grid">

            {{-- العميل --}}
            <div class="bk-field-search" id="client_search_box">
                <label class="a2-label">العميل</label>
                <input type="hidden" name="user_id" id="user_id" value="{{ $selectedClientId }}">

                <input
                    type="text"
                    id="client_search"
                    class="a2-input"
                    placeholder="ابحث باسم العميل..."
                    autocomplete="off"
                    value="{{ $selectedClient->name ?? '' }}"
                    required
                >

                <div class="bk-dropdown" id="client_dropdown">
                    @foreach($clients as $client)
                        <button
                            type="button"
                            class="bk-option client-option"
                            data-id="{{ $client->id }}"
                            data-name="{{ $client->name }}"
                        >
                            {{ $client->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- البزنس --}}
            <div class="bk-field-search" id="business_search_box">
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

                <div class="bk-dropdown" id="business_dropdown">
                    @foreach($businesses as $business)
                        <button
                            type="button"
                            class="bk-option business-option"
                            data-id="{{ $business->id }}"
                            data-name="{{ $business->name }}"
                        >
                            {{ $business->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- الخدمة --}}
            <div class="bk-field-search" id="service_search_box">
                <label class="a2-label">الخدمة</label>
                <input type="hidden" name="service_id" id="service_id" value="{{ $selectedServiceId }}">

                <input
                    type="text"
                    id="service_search"
                    class="a2-input"
                    placeholder="ابحث باسم الخدمة..."
                    autocomplete="off"
                    value="{{ $selectedService ? ($selectedService->name_ar ?: $selectedService->name_en) : '' }}"
                    required
                >

                <div class="bk-dropdown" id="service_dropdown">
                    @foreach($services as $service)
                        <button
                            type="button"
                            class="bk-option service-option"
                            data-id="{{ $service->id }}"
                            data-name="{{ $service->name_ar ?: $service->name_en }}"
                            data-supports-deposit="{{ (int)($service->supports_deposit ?? 0) }}"
                            data-max-deposit-percent="{{ (int)($service->max_deposit_percent ?? 0) }}"
                        >
                            {{ $service->name_ar ?: $service->name_en }}
                            @if(!empty($service->key))
                                <span class="bk-option-sub">({{ $service->key }})</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- العنصر القابل للحجز --}}
            <div>
                <label class="a2-label">العنصر القابل للحجز</label>
                <select name="bookable_id" id="bookable_id" class="a2-select">
                    <option value="">بدون عنصر محدد</option>
                </select>
                <div id="bookable_hint" class="a2-hint" style="margin-top:6px;">
                    اختر البزنس والخدمة أولًا لتحميل العناصر القابلة للحجز.
                </div>
            </div>

            {{-- نمط المدة --}}
            <div>
                <label class="a2-label">نمط المدة</label>
                <select name="duration_unit" id="duration_unit" class="a2-select">
                    <option value="day" @selected($durationMode === 'day')>يوم</option>
                    <option value="hour" @selected($durationMode === 'hour')>ساعة</option>
                </select>
            </div>

            {{-- تاريخ البداية --}}
            <div>
                <label class="a2-label">تاريخ البداية</label>
                <input type="date" id="start_date" name="date" class="a2-input" value="{{ $startDateValue }}" required>
            </div>

            {{-- وقت البداية --}}
            <div id="start_time_wrap">
                <label class="a2-label">وقت البداية</label>
                <input type="time" id="start_time" name="time" class="a2-input" value="{{ $startTimeValue }}">
            </div>

            {{-- تاريخ/وقت النهاية --}}
            <div>
                <label class="a2-label">تاريخ/وقت النهاية</label>
                <input type="datetime-local" id="ends_at" name="ends_at" class="a2-input" value="{{ $endsAtValue }}">
            </div>

            {{-- الكمية --}}
            <div>
                <label class="a2-label">الكمية</label>
                <input type="number" min="1" id="quantity" name="quantity" class="a2-input" value="{{ $quantityValue }}">
            </div>

            {{-- المدة المحسوبة --}}
            <div>
                <label class="a2-label">المدة المحسوبة</label>
                <input type="text" id="duration_preview" class="a2-input" value="—" readonly>
                <input type="hidden" id="duration_value" name="duration_value" value="{{ $durationValueOld }}">
            </div>

            {{-- عدد الأفراد --}}
            <div>
                <label class="a2-label">عدد الأفراد</label>
                <input type="number" min="1" name="party_size" class="a2-input" value="{{ old('party_size', $booking->party_size) }}">
            </div>

            {{-- الحالة --}}
            <div>
                <label class="a2-label">الحالة</label>
                <select name="status" class="a2-select" required>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $booking->status ?? \App\Models\Booking::STATUS_PENDING) === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- timezone --}}
            <div>
                <label class="a2-label">Timezone</label>
                <input type="text" name="timezone" class="a2-input" value="{{ old('timezone', $booking->timezone ?? 'Africa/Cairo') }}">
            </div>

            {{-- all_day --}}
            <div style="display:flex;align-items:end;">
                <label style="display:flex;gap:8px;align-items:center;font-weight:800;">
                    <input type="checkbox" name="all_day" value="1" id="all_day_checkbox" @checked(old('all_day', $booking->all_day ?? false))>
                    طوال اليوم
                </label>
            </div>

            {{-- notes --}}
            <div style="grid-column:1 / -1;">
                <label class="a2-label">ملاحظات</label>
                <textarea name="notes" class="a2-textarea" rows="4">{{ old('notes', $booking->notes) }}</textarea>
            </div>
        </div>
    </div>

    <div class="bk-summary-grid">
        <div class="a2-card" style="padding:18px;">
            <div class="a2-title" style="font-size:17px;margin-bottom:12px;">ملخص التسعير</div>

            <div class="bk-kv-grid">
                <div class="bk-kv"><span>سعر الوحدة</span><strong id="summary_unit_price">0.00 EGP</strong></div>
                <div class="bk-kv"><span>السعر الأصلي</span><strong id="summary_original_price">0.00 EGP</strong></div>
                <div class="bk-kv"><span>الخصم</span><strong id="summary_discount">0.00 EGP</strong></div>
                <div class="bk-kv"><span>السعر بعد الخصم</span><strong id="summary_final_price">0.00 EGP</strong></div>
                <div class="bk-kv"><span>رسوم المنصة</span><strong id="summary_platform_fee">0.00 EGP</strong></div>
                <div class="bk-kv"><span>الإجمالي النهائي</span><strong id="summary_total_cost">0.00 EGP</strong></div>
            </div>
        </div>

        <div class="a2-card" style="padding:18px;">
            <div class="a2-title" style="font-size:17px;margin-bottom:12px;">ملخص الديبوزت</div>

            <div class="bk-kv-grid">
                <div class="bk-kv"><span>الخدمة تدعم ديبوزت؟</span><strong id="summary_supports_deposit">—</strong></div>
                <div class="bk-kv"><span>أقصى نسبة</span><strong id="summary_max_deposit_percent">—</strong></div>
                <div class="bk-kv"><span>النسبة المطبقة</span><strong id="summary_applied_deposit_percent">—</strong></div>
                <div class="bk-kv"><span>قيمة الديبوزت</span><strong id="summary_deposit_amount">0.00 EGP</strong></div>
            </div>
        </div>
    </div>

    <div class="a2-card" style="padding:16px;">
        <div style="display:flex;justify-content:flex-end;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('admin.bookings.index') }}" class="a2-btn">رجوع</a>
            <button type="submit" class="a2-btn a2-btn-primary">
                {{ !empty($isEdit) ? 'حفظ التعديلات' : 'إنشاء الحجز' }}
            </button>
        </div>
    </div>
</div>

<style>
.bk-form-grid{
    display:grid;
    grid-template-columns:repeat(2, minmax(280px,1fr));
    gap:16px;
    align-items:end;
}
.bk-summary-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.bk-kv-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.bk-kv{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:12px 14px;
}
.bk-kv span{
    display:block;
    font-size:12px;
    color:#6b7280;
    margin-bottom:6px;
}
.bk-kv strong{
    display:block;
    font-size:15px;
    font-weight:800;
    line-height:1.5;
    word-break:break-word;
}

.bk-field-search{
    position:relative;
}
.bk-dropdown{
    display:none;
    position:absolute;
    inset-inline:0;
    top:calc(100% + 6px);
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    box-shadow:0 12px 30px rgba(15,23,42,.08);
    max-height:250px;
    overflow:auto;
    z-index:30;
}
.bk-option{
    width:100%;
    text-align:right;
    padding:12px 14px;
    border:0;
    background:#fff;
    cursor:pointer;
    display:block;
}
.bk-option:hover{
    background:#f8fafc;
}
.bk-option-sub{
    color:#6b7280;
    font-size:12px;
}

@media (max-width: 1200px){
    .bk-summary-grid{
        grid-template-columns:1fr;
    }
}
@media (max-width: 900px){
    .bk-form-grid{
        grid-template-columns:1fr;
    }
}
@media (max-width: 700px){
    .bk-kv-grid{
        grid-template-columns:1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');

    const hiddenClientId = document.getElementById('user_id');
    const clientSearch = document.getElementById('client_search');
    const clientDropdown = document.getElementById('client_dropdown');
    const clientBox = document.getElementById('client_search_box');

    const hiddenBusinessId = document.getElementById('business_id');
    const businessSearch = document.getElementById('business_search');
    const businessDropdown = document.getElementById('business_dropdown');
    const businessBox = document.getElementById('business_search_box');

    const hiddenServiceId = document.getElementById('service_id');
    const serviceSearch = document.getElementById('service_search');
    const serviceDropdown = document.getElementById('service_dropdown');
    const serviceBox = document.getElementById('service_search_box');

    const bookableSelect = document.getElementById('bookable_id');
    const bookableHint = document.getElementById('bookable_hint');

    const durationUnit = document.getElementById('duration_unit');
    const startDate = document.getElementById('start_date');
    const startTime = document.getElementById('start_time');
    const startTimeWrap = document.getElementById('start_time_wrap');
    const endsAt = document.getElementById('ends_at');
    const quantity = document.getElementById('quantity');
    const durationPreview = document.getElementById('duration_preview');
    const durationValue = document.getElementById('duration_value');
    const allDayCheckbox = document.getElementById('all_day_checkbox');

    const selectedBookableId = @json((string) $selectedBookableId);

    const summaryUnitPrice = document.getElementById('summary_unit_price');
    const summaryOriginalPrice = document.getElementById('summary_original_price');
    const summaryDiscount = document.getElementById('summary_discount');
    const summaryFinalPrice = document.getElementById('summary_final_price');
    const summaryPlatformFee = document.getElementById('summary_platform_fee');
    const summaryTotalCost = document.getElementById('summary_total_cost');
    const summarySupportsDeposit = document.getElementById('summary_supports_deposit');
    const summaryMaxDepositPercent = document.getElementById('summary_max_deposit_percent');
    const summaryAppliedDepositPercent = document.getElementById('summary_applied_deposit_percent');
    const summaryDepositAmount = document.getElementById('summary_deposit_amount');

    function money(v) {
        return Number(v || 0).toFixed(2) + ' EGP';
    }

    function normalizeText(value) {
        return (value || '').toString().trim().toLocaleLowerCase();
    }

    function setupSearchable(inputEl, hiddenEl, dropdownEl, boxEl, optionSelector) {
        const options = Array.from(dropdownEl.querySelectorAll(optionSelector));

        function close() {
            dropdownEl.style.display = 'none';
        }

        function filter() {
            const keyword = normalizeText(inputEl.value);
            let visible = 0;

            options.forEach(option => {
                const name = normalizeText(option.dataset.name);
                if (keyword === '' || name.includes(keyword)) {
                    option.style.display = 'block';
                    visible++;
                } else {
                    option.style.display = 'none';
                }
            });

            dropdownEl.style.display = visible > 0 ? 'block' : 'none';
        }

        inputEl.addEventListener('focus', filter);

        inputEl.addEventListener('input', function () {
            hiddenEl.value = '';
            filter();
        });

        options.forEach(option => {
            option.addEventListener('click', async function () {
                hiddenEl.value = option.dataset.id;
                inputEl.value = option.dataset.name;
                close();

                if (hiddenEl === hiddenBusinessId || hiddenEl === hiddenServiceId) {
                    await loadBookableItems();
                    await refreshPreview();
                }
            });
        });

        document.addEventListener('click', function (e) {
            if (!boxEl.contains(e.target)) {
                close();
            }
        });
    }

    setupSearchable(clientSearch, hiddenClientId, clientDropdown, clientBox, '.client-option');
    setupSearchable(businessSearch, hiddenBusinessId, businessDropdown, businessBox, '.business-option');
    setupSearchable(serviceSearch, hiddenServiceId, serviceDropdown, serviceBox, '.service-option');

    function parseDateTime(date, time = '00:00') {
        if (!date) return null;
        return new Date(date + 'T' + time + ':00');
    }

    function updateModeUI() {
        const mode = durationUnit.value;
        const isDay = mode === 'day';

        startTimeWrap.style.display = isDay ? 'none' : '';
        allDayCheckbox.checked = isDay;
    }

    function updateDurationAndEnd() {
        const mode = durationUnit.value;
        const qty = Math.max(parseInt(quantity.value || '1', 10), 1);
        const sDate = startDate.value;
        const sTime = startTime.value || '00:00';

        updateModeUI();

        if (!sDate) {
            durationPreview.value = '—';
            durationValue.value = '';
            return;
        }

        if (mode === 'day') {
            durationValue.value = qty;
            durationPreview.value = qty + ' يوم';

            // نظام فندقي/إيجار: 10 أيام => checkout بداية اليوم 11
            const end = new Date(sDate + 'T00:00:00');
            end.setDate(end.getDate() + qty);

            endsAt.value = end.toISOString().slice(0, 16);
        } else {
            const start = parseDateTime(sDate, sTime);
            if (!start) {
                durationPreview.value = '—';
                durationValue.value = '';
                return;
            }

            durationValue.value = qty;
            durationPreview.value = qty + ' ساعة';

            const end = new Date(start.getTime() + (qty * 60 * 60 * 1000));
            endsAt.value = end.toISOString().slice(0, 16);
        }
    }

    async function loadBookableItems() {
        const businessId = String(hiddenBusinessId.value || '').trim();
        const serviceId = String(hiddenServiceId.value || '').trim();
        const previousValue = String(bookableSelect.value || selectedBookableId || '').trim();

        bookableSelect.innerHTML = '<option value="">جاري تحميل العناصر...</option>';

        if (!businessId || !serviceId) {
            bookableSelect.innerHTML = '<option value="">بدون عنصر محدد</option>';
            if (bookableHint) {
                bookableHint.textContent = 'اختر البزنس والخدمة أولًا لتحميل العناصر القابلة للحجز.';
            }
            await refreshPreview();
            return;
        }

        try {
            const url = `{{ route('admin.bookings.bookableItemsLookup') }}?business_id=${encodeURIComponent(businessId)}&service_id=${encodeURIComponent(serviceId)}`;
            const res = await fetch(url);
            const data = await res.json();

            bookableSelect.innerHTML = '<option value="">بدون عنصر محدد</option>';

            if (!data.ok || !Array.isArray(data.items)) {
                if (bookableHint) {
                    bookableHint.textContent = 'تعذر تحميل العناصر القابلة للحجز.';
                }
                await refreshPreview();
                return;
            }

            if (data.items.length === 0) {
                if (bookableHint) {
                    bookableHint.textContent = 'لا توجد عناصر قابلة للحجز مرتبطة بهذا البزنس وهذه الخدمة.';
                }
                await refreshPreview();
                return;
            }

            data.items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = `${item.title}${item.code ? ' (' + item.code + ')' : ''}`;
                opt.dataset.price = item.price ?? 0;
                opt.dataset.depositEnabled = item.deposit_enabled ? '1' : '0';
                opt.dataset.depositPercent = item.deposit_percent ?? 0;
                opt.dataset.itemType = item.item_type ?? '';

                if (String(item.id) === previousValue) {
                    opt.selected = true;
                }

                bookableSelect.appendChild(opt);
            });

            if (bookableHint) {
                bookableHint.textContent = `تم تحميل ${data.items.length} عنصر قابل للحجز.`;
            }
        } catch (e) {
            console.error('bookableItemsLookup error:', e);
            bookableSelect.innerHTML = '<option value="">بدون عنصر محدد</option>';
            if (bookableHint) {
                bookableHint.textContent = 'حدث خطأ أثناء تحميل العناصر القابلة للحجز.';
            }
        }

        await refreshPreview();
    }

    async function refreshPreview() {
        const businessId = hiddenBusinessId.value;
        const serviceId = hiddenServiceId.value;
        const bookableId = bookableSelect.value || '';
        const qty = Math.max(parseInt(quantity.value || '1', 10), 1);

        if (!businessId || !serviceId) {
            summaryUnitPrice.textContent = money(0);
            summaryOriginalPrice.textContent = money(0);
            summaryDiscount.textContent = money(0);
            summaryFinalPrice.textContent = money(0);
            summaryPlatformFee.textContent = money(0);
            summaryTotalCost.textContent = money(0);
            summarySupportsDeposit.textContent = '—';
            summaryMaxDepositPercent.textContent = '—';
            summaryAppliedDepositPercent.textContent = '—';
            summaryDepositAmount.textContent = money(0);
            return;
        }

        try {
            const url = new URL(`{{ route('admin.bookings.pricingPreview') }}`, window.location.origin);
            url.searchParams.set('business_id', businessId);
            url.searchParams.set('service_id', serviceId);
            url.searchParams.set('quantity', qty);
            if (bookableId) {
                url.searchParams.set('bookable_id', bookableId);
            }

            const res = await fetch(url.toString());
            const data = await res.json();

            if (!data.ok) {
                return;
            }

            const pricing = data.pricing || {};
            const deposit = data.deposit_policy || {};
            const service = data.service || {};

            summaryUnitPrice.textContent = money(pricing.unit_price || 0);
            summaryOriginalPrice.textContent = money(pricing.original_price || 0);
            summaryDiscount.textContent = money(pricing.discount_amount || 0);
            summaryFinalPrice.textContent = money(pricing.final_price || 0);
            summaryPlatformFee.textContent = money(pricing.platform_fee || 0);
            summaryTotalCost.textContent = money(pricing.final_price || 0);

            summarySupportsDeposit.textContent = service.supports_deposit ? 'نعم' : 'لا';
            summaryMaxDepositPercent.textContent = (service.max_deposit_percent ?? 0) + '%';
            summaryAppliedDepositPercent.textContent = (deposit.configured_percent ?? 0) + '%';
            summaryDepositAmount.textContent = money(deposit.amount || 0);
        } catch (e) {
            console.error(e);
        }
    }

    bookableSelect.addEventListener('change', refreshPreview);

    quantity.addEventListener('input', function () {
        updateDurationAndEnd();
        refreshPreview();
    });

    durationUnit.addEventListener('change', function () {
        updateDurationAndEnd();
        refreshPreview();
    });

    startDate.addEventListener('change', updateDurationAndEnd);
    startTime.addEventListener('change', updateDurationAndEnd);

    form?.addEventListener('submit', function (e) {
        if (!hiddenClientId.value) {
            e.preventDefault();
            clientSearch.focus();
            alert('من فضلك اختر العميل من القائمة.');
            return;
        }

        if (!hiddenBusinessId.value) {
            e.preventDefault();
            businessSearch.focus();
            alert('من فضلك اختر البزنس من القائمة.');
            return;
        }

        if (!hiddenServiceId.value) {
            e.preventDefault();
            serviceSearch.focus();
            alert('من فضلك اختر الخدمة من القائمة.');
        }
    });

    (async function initBookingForm() {
        updateModeUI();
        updateDurationAndEnd();
        await loadBookableItems();
        await refreshPreview();
    })();
});
</script>