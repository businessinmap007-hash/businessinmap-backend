@if ($errors->any())
    <div class="a2-alert a2-alert-danger bk-form-errors">
        <ul class="bk-error-list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    $booking = $booking ?? new \App\Models\Booking();

    $selectedServiceId = (string) old('service_id', $booking->service_id ?? '');
    $selectedBusinessId = (string) old('business_id', $booking->business_id ?? '');
    $selectedRequesterId = (string) old('user_id', $booking->user_id ?? $booking->client_id ?? '');

    $selectedBookableId = (string) old(
        'bookable_id',
        old(
            'bookable_item_id',
            $selectedBookableItemId
                ?? $booking->bookable_id
                ?? $booking->bookable_item_id
                ?? data_get($booking->meta, 'booking_test_form.bookable_item_id')
                ?? data_get($booking->meta, 'bookable_item.id')
                ?? ''
        )
    );

    if ($selectedServiceId === '') {
        $defaultBookingService = $services->firstWhere('key', 'booking');
        if ($defaultBookingService) {
            $selectedServiceId = (string) $defaultBookingService->id;
        }
    }

    $parseDate = function ($value, $fallback = null) {
        if (!$value) return $fallback;
        try { return \Carbon\Carbon::parse($value); } catch (\Throwable $e) { return $fallback; }
    };

    $startCarbon = $parseDate(
        old('starts_at', $booking->starts_at ?? null),
        $parseDate(old('date', $booking->date ?? null), now('Africa/Cairo'))
    );

    $startDateValue = old('date', $startCarbon ? $startCarbon->format('Y-m-d') : now('Africa/Cairo')->format('Y-m-d'));

    $timeRaw = old('time', $booking->time ?? null);
    if (!$timeRaw && $startCarbon) {
        $timeRaw = $startCarbon->format('H:i:s');
    }
    $startTimeValue = $timeRaw ? \Illuminate\Support\Str::limit((string) $timeRaw, 5, '') : '';

    $endCarbon = $parseDate(old('ends_at', $booking->ends_at ?? $booking->end_at ?? null));
    $endsAtValue = $endCarbon ? $endCarbon->format('Y-m-d\TH:i') : '';

    $durationMode = old('duration_unit', $booking->duration_unit ?? 'day');
    $durationValueOld = old('duration_value', $booking->duration_value ?? $booking->quantity ?? 1);
    $quantityValue = old('quantity', $booking->quantity ?? 1);
    $partySizeValue = old('party_size', $booking->party_size ?? $booking->guest_count ?? $booking->guests ?? 1);

    $selectedRequester = $clients->firstWhere('id', (int) $selectedRequesterId);
    $selectedBusiness = $businesses->firstWhere('id', (int) $selectedBusinessId);
    $selectedService = $services->firstWhere('id', (int) $selectedServiceId);

    $bookingStatus = old('status', $booking->status ?? \App\Models\Booking::STATUS_PENDING);
@endphp

<div class="bk-form-layout">
    <div class="bk-main">
        <div class="a2-card bk-card">
            <div class="bk-card-head">
                <div>
                    <div class="a2-title">بيانات الحجز الأساسية</div>
                    <div class="a2-section-subtitle">
                        اختر طالب الحجز، مقدم الخدمة، الخدمة، ثم العنصر القابل للحجز إن كانت الخدمة تتطلب ذلك.
                    </div>
                </div>
            </div>

            <div class="bk-form-grid">
                <div class="bk-field-search" id="requester_search_box">
                    <label class="a2-label">طالب الحجز</label>
                    <input type="hidden" name="user_id" id="user_id" value="{{ $selectedRequesterId }}">
                    <input type="text" id="requester_search" class="a2-input" placeholder="ابحث باسم طالب الحجز..." autocomplete="off" value="{{ $selectedRequester->name ?? '' }}" required>
                    <div class="a2-hint">يمكن أن يكون طالب الحجز عميلًا أو بزنس.</div>

                    <div class="bk-dropdown" id="requester_dropdown">
                        @foreach($clients as $client)
                            @php
                                $clientType = (string) ($client->type ?? '');
                                $typeLabel = $clientType === 'business' ? 'Business' : 'Client';
                                $phone = (string) ($client->phone ?? '');
                                $email = (string) ($client->email ?? '');
                            @endphp
                            <button type="button" class="bk-option requester-option" data-id="{{ $client->id }}" data-name="{{ $client->name }}" data-type="{{ $clientType }}" data-phone="{{ $phone }}" data-email="{{ $email }}">
                                <strong>{{ $client->name }}</strong>
                                <span class="bk-option-sub">
                                    {{ $typeLabel }}
                                    @if($phone) — {{ $phone }} @elseif($email) — {{ $email }} @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="bk-field-search" id="business_search_box">
                    <label class="a2-label">مقدم الخدمة</label>
                    <input type="hidden" name="business_id" id="business_id" value="{{ $selectedBusinessId }}">
                    <input type="text" id="business_search" class="a2-input" placeholder="ابحث باسم الفندق / البزنس..." autocomplete="off" value="{{ $selectedBusiness->name ?? '' }}" required>
                    <div class="a2-hint">مقدم الخدمة يجب أن يكون حساب Business.</div>

                    <div class="bk-dropdown" id="business_dropdown">
                        @foreach($businesses as $business)
                            <button type="button" class="bk-option business-option" data-id="{{ $business->id }}" data-name="{{ $business->name }}" data-category-id="{{ (int) ($business->category_id ?? 0) }}" data-child-id="{{ (int) ($business->category_child_id ?? 0) }}" data-phone="{{ (string) ($business->phone ?? '') }}" data-email="{{ (string) ($business->email ?? '') }}">
                                <strong>{{ $business->name }}</strong>
                                <span class="bk-option-sub">
                                    Business
                                    @if(!empty($business->phone)) — {{ $business->phone }} @endif
                                    — Root: {{ (int) ($business->category_id ?? 0) }} / Child: {{ (int) ($business->category_child_id ?? 0) }}
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="bk-field-search" id="service_search_box">
                    <label class="a2-label">الخدمة</label>
                    <input type="hidden" name="service_id" id="service_id" value="{{ $selectedServiceId }}">
                    <input type="text" id="service_search" class="a2-input" placeholder="ابحث باسم الخدمة..." autocomplete="off" value="{{ $selectedService ? ($selectedService->name_ar ?: $selectedService->name_en) : '' }}" required>
                    <div class="a2-hint">هذه الصفحة مخصصة لإنشاء حجوزات فقط، لذلك يتم اختيار خدمة الحجز تلقائيًا.</div>

                    <div class="bk-dropdown" id="service_dropdown">
                        @foreach($services->where('key', 'booking') as $service)
                            <button type="button" class="bk-option service-option" data-id="{{ $service->id }}" data-name="{{ $service->name_ar ?: $service->name_en }}" data-key="{{ $service->key }}" data-supports-deposit="{{ (int)($service->supports_deposit ?? 0) }}">
                                <strong>{{ $service->name_ar ?: $service->name_en }}</strong>
                                @if(!empty($service->key)) <span class="bk-option-sub">({{ $service->key }})</span> @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <div id="bookable_wrap">
                    <label class="a2-label">
                        العنصر القابل للحجز
                        <span id="bookable_required_badge" class="bk-mini-badge is-hidden">مطلوب</span>
                    </label>
                    <input type="hidden" name="bookable_item_id" id="bookable_item_id" value="{{ $selectedBookableId }}">
                    <select name="bookable_id" id="bookable_id" class="a2-select">
                        <option value="">بدون عنصر محدد</option>
                    </select>
                    <div id="bookable_hint" class="a2-hint">اختر البزنس والخدمة أولًا لتحميل الغرف أو العناصر القابلة للحجز.</div>
                </div>

                <div id="duration_unit_wrap">
                    <label class="a2-label">نمط المدة</label>
                    <select name="duration_unit" id="duration_unit" class="a2-select">
                        <option value="day" @selected($durationMode === 'day')>يوم</option>
                        <option value="hour" @selected($durationMode === 'hour')>ساعة</option>
                        <option value="minute" @selected($durationMode === 'minute')>دقيقة</option>
                    </select>
                </div>

                <div id="start_date_wrap">
                    <label class="a2-label">تاريخ البداية</label>
                    <input type="date" id="start_date" name="date" class="a2-input" value="{{ $startDateValue }}" required>
                </div>

                <div id="start_time_wrap">
                    <label class="a2-label">وقت البداية</label>
                    <input type="time" id="start_time" name="time" class="a2-input" value="{{ $startTimeValue }}">
                </div>

                <div id="ends_at_wrap">
                    <label class="a2-label">تاريخ/وقت النهاية</label>
                    <input type="datetime-local" id="ends_at" name="ends_at" class="a2-input" value="{{ $endsAtValue }}">
                </div>

                <div id="quantity_wrap">
                    <label class="a2-label">الكمية / المدة</label>
                    <input type="number" min="1" id="quantity" name="quantity" class="a2-input" value="{{ $quantityValue }}">
                </div>

                <div id="duration_preview_wrap">
                    <label class="a2-label">المدة المحسوبة</label>
                    <input type="text" id="duration_preview" class="a2-input" value="—" readonly>
                    <input type="hidden" id="duration_value" name="duration_value" value="{{ $durationValueOld }}">
                </div>

                <div id="party_size_wrap">
                    <label class="a2-label">عدد الأفراد</label>
                    <input type="number" min="1" id="party_size" name="party_size" class="a2-input" value="{{ $partySizeValue }}">
                </div>

                <div>
                    <label class="a2-label">الحالة</label>
                    <select name="status" class="a2-select" required>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected($bookingStatus === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="a2-label">Timezone</label>
                    <input type="text" name="timezone" class="a2-input" value="{{ old('timezone', $booking->timezone ?? 'Africa/Cairo') }}">
                </div>

                <div id="all_day_wrap" class="bk-check-wrap">
                    <label class="bk-check">
                        <input type="checkbox" name="all_day" value="1" id="all_day_checkbox" @checked(old('all_day', $booking->all_day ?? false))>
                        <span>طوال اليوم</span>
                    </label>
                </div>

                <div class="bk-form-span-full">
                    <label class="a2-label">ملاحظات</label>
                    <textarea name="notes" class="a2-textarea" rows="4">{{ old('notes', $booking->notes) }}</textarea>
                </div>
            </div>
        </div>

        <div class="bk-form-actions">
            <a href="{{ route('admin.bookings.index') }}" class="a2-btn">رجوع</a>
            <button type="submit" class="a2-btn a2-btn-primary">{{ !empty($isEdit) ? 'حفظ التعديلات' : 'إنشاء الحجز' }}</button>
        </div>
    </div>

    <div class="bk-side">
        <div class="a2-card bk-card">
            <div class="a2-title">إعداد الخدمة</div>
            <div class="bk-kv-grid">
                <div class="bk-kv"><span>Service Config</span><strong id="summary_config_exists">—</strong></div>
                <div class="bk-kv"><span>يتطلب عنصر؟</span><strong id="summary_requires_bookable">—</strong></div>
                <div class="bk-kv"><span>أنواع العناصر</span><strong id="summary_allowed_types">—</strong></div>
                <div class="bk-kv"><span>نمط الخدمة</span><strong id="summary_item_family">—</strong></div>
            </div>
        </div>

        <div class="a2-card bk-card">
            <div class="a2-title">ملخص التسعير</div>
            <div class="bk-kv-grid">
                <div class="bk-kv"><span>مصدر السعر</span><strong id="summary_price_source">—</strong></div>
                <div class="bk-kv"><span>سعر الوحدة</span><strong id="summary_unit_price">0.00 EGP</strong></div>
                <div class="bk-kv"><span>السعر الأصلي</span><strong id="summary_original_price">0.00 EGP</strong></div>
                <div class="bk-kv"><span>الخصم</span><strong id="summary_discount">0.00 EGP</strong></div>
                <div class="bk-kv"><span>السعر النهائي</span><strong id="summary_final_price">0.00 EGP</strong></div>
                <div class="bk-kv"><span>الإجمالي</span><strong id="summary_total_cost">0.00 EGP</strong></div>
            </div>
        </div>

        <div class="a2-card bk-card">
            <div class="a2-title">العنصر المختار</div>
            <div class="bk-kv-grid">
                <div class="bk-kv"><span>العنوان</span><strong id="summary_bookable_title">—</strong></div>
                <div class="bk-kv"><span>الكود</span><strong id="summary_bookable_code">—</strong></div>
                <div class="bk-kv"><span>النوع</span><strong id="summary_bookable_type">—</strong></div>
                <div class="bk-kv"><span>السعة</span><strong id="summary_bookable_capacity">—</strong></div>
            </div>
        </div>

        <div class="a2-card bk-card">
            <div class="a2-title">ملخص الديبوزت</div>
            <div class="bk-kv-grid">
                <div class="bk-kv"><span>الخدمة تدعم ديبوزت؟</span><strong id="summary_supports_deposit">—</strong></div>
                <div class="bk-kv"><span>مصدر سياسة الديبوزت</span><strong id="summary_deposit_policy_source">—</strong></div>
                <div class="bk-kv"><span>النسبة المطبقة</span><strong id="summary_applied_deposit_percent">—</strong></div>
                <div class="bk-kv"><span>قيمة الديبوزت</span><strong id="summary_deposit_amount">0.00 EGP</strong></div>
                <div class="bk-kv"><span>مصدر الديبوزت</span><strong id="summary_deposit_source">—</strong></div>
                <div class="bk-kv"><span>مطلوب؟</span><strong id="summary_deposit_required">—</strong></div>
            </div>
        </div>

        <div class="a2-card bk-card">
            <div class="a2-title">رسوم التنفيذ</div>
            <div class="a2-section-subtitle">هذه الرسوم تُخصم عند انتقال الحجز إلى <span dir="ltr">in_progress</span>، ولا تسترد بعد بدء التنفيذ.</div>
            <div class="bk-kv-grid">
                <div class="bk-kv"><span>رسوم العميل</span><strong id="summary_client_fee">0.00 EGP</strong></div>
                <div class="bk-kv"><span>رسوم البزنس</span><strong id="summary_business_fee">0.00 EGP</strong></div>
                <div class="bk-kv"><span>Fee Code</span><strong id="summary_fee_code">—</strong></div>
                <div class="bk-kv"><span>Fee Row ID</span><strong id="summary_fee_row_id">—</strong></div>
            </div>
        </div>

        <div id="preview_message" class="a2-alert a2-alert-info is-hidden"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');

    const hiddenRequesterId = document.getElementById('user_id');
    const requesterSearch = document.getElementById('requester_search');
    const requesterDropdown = document.getElementById('requester_dropdown');
    const requesterBox = document.getElementById('requester_search_box');

    const hiddenBusinessId = document.getElementById('business_id');
    const businessSearch = document.getElementById('business_search');
    const businessDropdown = document.getElementById('business_dropdown');
    const businessBox = document.getElementById('business_search_box');

    const hiddenServiceId = document.getElementById('service_id');
    const serviceSearch = document.getElementById('service_search');
    const serviceDropdown = document.getElementById('service_dropdown');
    const serviceBox = document.getElementById('service_search_box');

    const hiddenBookableItemId = document.getElementById('bookable_item_id');
    const bookableWrap = document.getElementById('bookable_wrap');
    const bookableSelect = document.getElementById('bookable_id');
    const bookableHint = document.getElementById('bookable_hint');
    const bookableRequiredBadge = document.getElementById('bookable_required_badge');

    const durationUnit = document.getElementById('duration_unit');
    const startDate = document.getElementById('start_date');
    const startTime = document.getElementById('start_time');
    const startTimeWrap = document.getElementById('start_time_wrap');
    const endsAt = document.getElementById('ends_at');
    const quantity = document.getElementById('quantity');
    const durationPreview = document.getElementById('duration_preview');
    const durationValue = document.getElementById('duration_value');
    const allDayCheckbox = document.getElementById('all_day_checkbox');
    const partySizeWrap = document.getElementById('party_size_wrap');
    const quantityWrap = document.getElementById('quantity_wrap');
    const previewMessage = document.getElementById('preview_message');

    const selectedBookableId = @json((string) $selectedBookableId);

    const el = (id) => document.getElementById(id);
    const summary = {
        configExists: el('summary_config_exists'),
        requiresBookable: el('summary_requires_bookable'),
        allowedTypes: el('summary_allowed_types'),
        itemFamily: el('summary_item_family'),
        priceSource: el('summary_price_source'),
        unitPrice: el('summary_unit_price'),
        originalPrice: el('summary_original_price'),
        discount: el('summary_discount'),
        finalPrice: el('summary_final_price'),
        totalCost: el('summary_total_cost'),
        bookableTitle: el('summary_bookable_title'),
        bookableCode: el('summary_bookable_code'),
        bookableType: el('summary_bookable_type'),
        bookableCapacity: el('summary_bookable_capacity'),
        supportsDeposit: el('summary_supports_deposit'),
        depositPolicySource: el('summary_deposit_policy_source'),
        appliedDepositPercent: el('summary_applied_deposit_percent'),
        depositAmount: el('summary_deposit_amount'),
        depositSource: el('summary_deposit_source'),
        depositRequired: el('summary_deposit_required'),
        clientFee: el('summary_client_fee'),
        businessFee: el('summary_business_fee'),
        feeCode: el('summary_fee_code'),
        feeRowId: el('summary_fee_row_id'),
    };

    let currentServiceConfig = {
        exists: false,
        requires_bookable_item: false,
        allowed_item_types: [],
        supports_quantity: true,
        supports_guest_count: false,
        requires_start_end: false,
    };

    function money(value, currency = 'EGP') {
        return Number(value || 0).toFixed(2) + ' ' + currency;
    }

    function normalizeText(value) {
        return (value || '').toString().trim().toLocaleLowerCase();
    }

    function yesNo(value) {
        return value ? 'نعم' : 'لا';
    }

    function showPreviewMessage(message, type = 'info') {
        if (!previewMessage) return;
        if (!message) {
            previewMessage.classList.add('is-hidden');
            previewMessage.textContent = '';
            return;
        }
        previewMessage.className = 'a2-alert a2-alert-' + type;
        previewMessage.textContent = message;
        previewMessage.classList.remove('is-hidden');
    }

    function setupSearchable(inputEl, hiddenEl, dropdownEl, boxEl, optionSelector, afterSelect) {
        const options = Array.from(dropdownEl.querySelectorAll(optionSelector));

        function close() { dropdownEl.style.display = 'none'; }

        function filter() {
            const keyword = normalizeText(inputEl.value);
            let visible = 0;
            options.forEach(option => {
                const name = normalizeText(option.dataset.name);
                const key = normalizeText(option.dataset.key || '');
                const show = keyword === '' || name.includes(keyword) || key.includes(keyword);
                option.style.display = show ? 'block' : 'none';
                if (show) visible++;
            });
            dropdownEl.style.display = visible > 0 ? 'block' : 'none';
        }

        inputEl.addEventListener('focus', filter);
        inputEl.addEventListener('input', function () {
            hiddenEl.value = '';
            filter();
            if (afterSelect) afterSelect(null);
        });

        options.forEach(option => {
            option.addEventListener('click', async function () {
                hiddenEl.value = option.dataset.id || '';
                inputEl.value = option.dataset.name || '';
                close();
                if (afterSelect) await afterSelect(option);
            });
        });

        document.addEventListener('click', function (e) {
            if (!boxEl.contains(e.target)) close();
        });
    }

    setupSearchable(requesterSearch, hiddenRequesterId, requesterDropdown, requesterBox, '.requester-option', null);
    setupSearchable(businessSearch, hiddenBusinessId, businessDropdown, businessBox, '.business-option', async function () {
        resetBookable();
        resetPreview();
        await loadBookableItems();
        await refreshPreview();
    });
    setupSearchable(serviceSearch, hiddenServiceId, serviceDropdown, serviceBox, '.service-option', async function () {
        resetBookable();
        resetPreview();
        await loadBookableItems();
        await refreshPreview();
    });

    function pad(n) { return String(n).padStart(2, '0'); }

    function toLocalDateTimeValue(date) {
        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function toServerDateTime(value) {
        if (!value) return '';
        return value.replace('T', ' ') + (value.length === 16 ? ':00' : '');
    }

    function parseDateTime(date, time = '00:00') {
        if (!date) return null;
        return new Date(date + 'T' + (time || '00:00') + ':00');
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
            const start = new Date(sDate + 'T00:00:00');
            if (endsAt.value) {
                const endDateOnly = endsAt.value.slice(0, 10);
                const end = new Date(endDateOnly + 'T00:00:00');
                const diffMs = end.getTime() - start.getTime();
                const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
                if (diffDays > 0) {
                    durationValue.value = diffDays;
                    durationPreview.value = diffDays + ' يوم';
                    quantity.value = diffDays;
                    return;
                }
            }
            durationValue.value = qty;
            durationPreview.value = qty + ' يوم';
            const end = new Date(start.getTime());
            end.setDate(end.getDate() + qty);
            end.setHours(12, 0, 0, 0);
            endsAt.value = toLocalDateTimeValue(end);
            return;
        }

        if (mode === 'hour') {
            const start = parseDateTime(sDate, sTime);
            if (!start) return;
            if (endsAt.value) {
                const end = new Date(endsAt.value);
                const diffMs = end.getTime() - start.getTime();
                const diffHours = Math.ceil(diffMs / (1000 * 60 * 60));
                if (diffHours > 0) {
                    durationValue.value = diffHours;
                    durationPreview.value = diffHours + ' ساعة';
                    quantity.value = diffHours;
                    return;
                }
            }
            durationValue.value = qty;
            durationPreview.value = qty + ' ساعة';
            endsAt.value = toLocalDateTimeValue(new Date(start.getTime() + (qty * 60 * 60 * 1000)));
            return;
        }

        if (mode === 'minute') {
            const start = parseDateTime(sDate, sTime);
            if (!start) return;
            if (endsAt.value) {
                const end = new Date(endsAt.value);
                const diffMs = end.getTime() - start.getTime();
                const diffMinutes = Math.ceil(diffMs / (1000 * 60));
                if (diffMinutes > 0) {
                    durationValue.value = diffMinutes;
                    durationPreview.value = diffMinutes + ' دقيقة';
                    quantity.value = diffMinutes;
                    return;
                }
            }
            durationValue.value = qty;
            durationPreview.value = qty + ' دقيقة';
            endsAt.value = toLocalDateTimeValue(new Date(start.getTime() + (qty * 60 * 1000)));
        }
    }

    function syncBookableHidden() {
        if (hiddenBookableItemId) hiddenBookableItemId.value = bookableSelect.value || '';
    }

    function resetBookable() {
        bookableSelect.innerHTML = '<option value="">بدون عنصر محدد</option>';
        bookableHint.textContent = 'اختر البزنس والخدمة أولًا لتحميل الغرف أو العناصر القابلة للحجز.';
    }

    function resetPreview() {
        summary.configExists.textContent = '—';
        summary.requiresBookable.textContent = '—';
        summary.allowedTypes.textContent = '—';
        summary.itemFamily.textContent = '—';
        summary.priceSource.textContent = '—';
        summary.unitPrice.textContent = money(0);
        summary.originalPrice.textContent = money(0);
        summary.discount.textContent = money(0);
        summary.finalPrice.textContent = money(0);
        summary.totalCost.textContent = money(0);
        summary.bookableTitle.textContent = '—';
        summary.bookableCode.textContent = '—';
        summary.bookableType.textContent = '—';
        summary.bookableCapacity.textContent = '—';
        summary.supportsDeposit.textContent = '—';
        summary.depositPolicySource.textContent = '—';
        summary.appliedDepositPercent.textContent = '—';
        summary.depositAmount.textContent = money(0);
        summary.depositSource.textContent = '—';
        summary.depositRequired.textContent = '—';
        summary.clientFee.textContent = money(0);
        summary.businessFee.textContent = money(0);
        summary.feeCode.textContent = '—';
        summary.feeRowId.textContent = '—';
        showPreviewMessage('');
    }

    function applyServiceConfig(config) {
        currentServiceConfig = config || currentServiceConfig;
        const requiresBookable = !!currentServiceConfig.requires_bookable_item;
        const supportsQuantity = currentServiceConfig.supports_quantity !== false;
        const supportsGuestCount = !!currentServiceConfig.supports_guest_count;

        if (bookableRequiredBadge) bookableRequiredBadge.classList.toggle('is-hidden', !requiresBookable);
        bookableWrap.style.display = requiresBookable || bookableSelect.options.length > 1 ? '' : '';
        quantityWrap.style.display = supportsQuantity ? '' : 'none';
        partySizeWrap.style.display = supportsGuestCount ? '' : 'none';

        summary.configExists.textContent = currentServiceConfig.exists ? 'نعم' : 'لا';
        summary.requiresBookable.textContent = yesNo(requiresBookable);
        summary.allowedTypes.textContent = Array.isArray(currentServiceConfig.allowed_item_types) && currentServiceConfig.allowed_item_types.length ? currentServiceConfig.allowed_item_types.join(', ') : '—';
        summary.itemFamily.textContent = currentServiceConfig.item_family || '—';
    }

    async function loadBookableItems() {
        const businessId = String(hiddenBusinessId.value || '').trim();
        const serviceId = String(hiddenServiceId.value || '').trim();
        const previousValue = String(bookableSelect.value || (hiddenBookableItemId ? hiddenBookableItemId.value : '') || selectedBookableId || '').trim();

        bookableSelect.innerHTML = '<option value="">جاري تحميل العناصر...</option>';

        if (!businessId || !serviceId) {
            resetBookable();
            return;
        }

        try {
            const url = `{{ route('admin.bookings.bookableItemsLookup') }}?business_id=${encodeURIComponent(businessId)}&service_id=${encodeURIComponent(serviceId)}`;
            const res = await fetch(url);
            const data = await res.json();

            bookableSelect.innerHTML = '<option value="">بدون عنصر محدد</option>';

            if (data.service_config) applyServiceConfig(data.service_config);

            if (!data.ok || !Array.isArray(data.items)) {
                bookableHint.textContent = 'تعذر تحميل العناصر القابلة للحجز.';
                return;
            }

            if (data.items.length === 0) {
                bookableHint.textContent = currentServiceConfig.requires_bookable_item
                    ? 'هذه الخدمة تتطلب عنصر قابل للحجز، لكن لا توجد غرف/عناصر نشطة مطابقة.'
                    : 'لا توجد عناصر قابلة للحجز مرتبطة بهذا البزنس وهذه الخدمة.';
                syncBookableHidden();
                return;
            }

            data.items.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                opt.textContent = `${item.title}${item.code ? ' (' + item.code + ')' : ''} — ${money(item.price || 0)}`;
                opt.dataset.price = item.price ?? 0;
                opt.dataset.depositEnabled = item.deposit_enabled ? '1' : '0';
                opt.dataset.depositPercent = item.deposit_percent ?? 0;
                opt.dataset.itemType = item.item_type ?? '';
                opt.dataset.title = item.title ?? '';
                opt.dataset.code = item.code ?? '';
                opt.dataset.capacity = item.capacity ?? '';
                if (String(item.id) === previousValue) opt.selected = true;
                bookableSelect.appendChild(opt);
            });

            bookableHint.textContent = previousValue && !bookableSelect.value
                ? `تم تحميل ${data.items.length} عنصر، لكن العنصر القديم #${previousValue} غير موجود ضمن النتائج الحالية.`
                : `تم تحميل ${data.items.length} عنصر قابل للحجز.`;

            syncBookableHidden();
        } catch (e) {
            console.error('bookableItemsLookup error:', e);
            resetBookable();
            bookableHint.textContent = 'حدث خطأ أثناء تحميل العناصر القابلة للحجز.';
        }
    }

    function selectedBookableOption() {
        return bookableSelect.options[bookableSelect.selectedIndex] || null;
    }

    function updateBookableSummary() {
        const opt = selectedBookableOption();
        if (!opt || !opt.value) {
            summary.bookableTitle.textContent = '—';
            summary.bookableCode.textContent = '—';
            summary.bookableType.textContent = '—';
            summary.bookableCapacity.textContent = '—';
            return;
        }
        summary.bookableTitle.textContent = opt.dataset.title || opt.textContent || '—';
        summary.bookableCode.textContent = opt.dataset.code || '—';
        summary.bookableType.textContent = opt.dataset.itemType || '—';
        summary.bookableCapacity.textContent = opt.dataset.capacity || '—';
    }

    async function refreshPreview() {
        syncBookableHidden();
        updateBookableSummary();

        const businessId = hiddenBusinessId.value;
        const serviceId = hiddenServiceId.value;
        const bookableId = bookableSelect.value || (hiddenBookableItemId ? hiddenBookableItemId.value : '');
        const qty = Math.max(parseInt(durationValue.value || quantity.value || '1', 10), 1);

        if (!businessId || !serviceId) {
            resetPreview();
            return;
        }

        try {
            const url = new URL(`{{ route('admin.bookings.pricingPreview') }}`, window.location.origin);
            url.searchParams.set('business_id', businessId);
            url.searchParams.set('service_id', serviceId);
            url.searchParams.set('quantity', qty);
            if (bookableId) url.searchParams.set('bookable_id', bookableId);

            const startsAt = startDate.value
                ? (durationUnit.value === 'day' ? startDate.value + ' 00:00:00' : startDate.value + ' ' + (startTime.value || '00:00') + ':00')
                : '';
            if (startsAt) url.searchParams.set('starts_at', startsAt);
            if (endsAt.value) url.searchParams.set('ends_at', toServerDateTime(endsAt.value));

            const res = await fetch(url.toString());
            const data = await res.json();

            if (!data.ok) {
                showPreviewMessage(data.message || 'تعذر حساب التسعير.', 'danger');
                if (data.service_config) applyServiceConfig(data.service_config);
                return;
            }

            if (data.service_config) applyServiceConfig(data.service_config);

            const pricing = data.pricing || {};
            const deposit = data.deposit_policy || {};
            const service = data.service || {};
            const feeSnapshot = data.fee_snapshot || {};
            const businessPrice = data.business_price || {};
            const currency = pricing.currency || businessPrice.currency || 'EGP';
            const clientFee = feeSnapshot.client || null;
            const businessFee = feeSnapshot.business || null;

            summary.priceSource.textContent = pricing.source || '—';
            summary.unitPrice.textContent = money(pricing.unit_price || 0, currency);
            summary.originalPrice.textContent = money(pricing.original_price || 0, currency);
            summary.discount.textContent = money(pricing.discount_amount || 0, currency);
            summary.finalPrice.textContent = money(pricing.final_price || 0, currency);
            summary.totalCost.textContent = money(pricing.final_price || 0, currency);
            summary.supportsDeposit.textContent = service.supports_deposit ? 'نعم' : 'لا';
            summary.depositPolicySource.textContent = deposit.source || '—';
            summary.appliedDepositPercent.textContent = (deposit.configured_percent ?? 0) + '%';
            summary.depositAmount.textContent = money(deposit.amount || 0, currency);
            summary.depositSource.textContent = deposit.source || '—';
            summary.depositRequired.textContent = deposit.required ? 'نعم' : 'لا';
            summary.clientFee.textContent = clientFee ? money(clientFee.amount || 0, clientFee.currency || currency) : money(0, currency);
            summary.businessFee.textContent = businessFee ? money(businessFee.amount || 0, businessFee.currency || currency) : money(0, currency);
            summary.feeCode.textContent = feeSnapshot.fee_code || '—';
            summary.feeRowId.textContent = (clientFee && clientFee.id) || (businessFee && businessFee.id) || feeSnapshot.fee_row_id || '—';
            showPreviewMessage('');
        } catch (e) {
            console.error(e);
            showPreviewMessage('حدث خطأ أثناء حساب التسعير.', 'danger');
        }
    }

    function warnIfSameRequesterAndProvider() {
        const requesterId = String(hiddenRequesterId.value || '').trim();
        const businessId = String(hiddenBusinessId.value || '').trim();
        if (requesterId && businessId && requesterId === businessId) {
            return confirm('طالب الحجز هو نفس مقدم الخدمة. هل تريد المتابعة؟');
        }
        return true;
    }

    bookableSelect.addEventListener('change', function () {
        syncBookableHidden();
        refreshPreview();
    });

    quantity.addEventListener('input', function () { updateDurationAndEnd(); refreshPreview(); });
    durationUnit.addEventListener('change', function () { updateDurationAndEnd(); refreshPreview(); });
    startDate.addEventListener('change', function () { updateDurationAndEnd(); refreshPreview(); });
    startTime.addEventListener('change', function () { updateDurationAndEnd(); refreshPreview(); });
    endsAt.addEventListener('change', function () { updateDurationAndEnd(); refreshPreview(); });

    form?.addEventListener('submit', function (e) {
        syncBookableHidden();

        if (!hiddenRequesterId.value) {
            e.preventDefault();
            requesterSearch.focus();
            alert('من فضلك اختر طالب الحجز من القائمة.');
            return;
        }

        if (!hiddenBusinessId.value) {
            e.preventDefault();
            businessSearch.focus();
            alert('من فضلك اختر مقدم الخدمة من القائمة.');
            return;
        }

        if (!hiddenServiceId.value) {
            e.preventDefault();
            serviceSearch.focus();
            alert('من فضلك اختر الخدمة من القائمة.');
            return;
        }

        if (currentServiceConfig.requires_bookable_item && !bookableSelect.value) {
            e.preventDefault();
            bookableSelect.focus();
            alert('هذه الخدمة تتطلب اختيار عنصر قابل للحجز مثل غرفة أو وحدة.');
            return;
        }

        if (!warnIfSameRequesterAndProvider()) {
            e.preventDefault();
            return;
        }
    });

    (async function initBookingForm() {
        updateModeUI();
        updateDurationAndEnd();
        resetPreview();
        if (selectedBookableId && hiddenBookableItemId) hiddenBookableItemId.value = selectedBookableId;
        if (hiddenBusinessId.value && hiddenServiceId.value) {
            await loadBookableItems();
            await refreshPreview();
        }
    })();
});
</script>
