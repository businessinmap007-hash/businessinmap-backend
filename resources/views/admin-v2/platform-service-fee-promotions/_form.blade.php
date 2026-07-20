@php
    $isEdit = isset($promotion) && $promotion->exists;

    $scopeVal = old('scope_type', $promotion->scope_type ?? 'service');
    $serviceVal = (int) old('service_id', $promotion->service_id ?? 0);
    $childVal = (int) old('child_id', $promotion->child_id ?? 0);
    $targetVal = old('target_party', $promotion->target_party ?? 'client');
    $discountVal = old('discount_type', $promotion->discount_type ?? 'waive');

    $startsVal = old('starts_at', optional($promotion->starts_at ?? null)->format('Y-m-d\TH:i'));
    $endsVal = old('ends_at', optional($promotion->ends_at ?? null)->format('Y-m-d\TH:i'));
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        <strong>{{ __('يوجد أخطاء:') }}</strong>
        <ul class="a2-errors-list">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="a2-card a2-card--section">
    <h2 class="a2-section-title">{{ __('بيانات العرض') }}</h2>
    <div class="a2-section-subtitle">
        {{ __('هذا العرض لا يغير القيم الأصلية للرسوم، بل يطبق مؤقتًا أثناء الحساب فقط.') }}
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('اسم العرض') }}</label>
            <input
                type="text"
                name="name"
                class="a2-input"
                value="{{ old('name', $promotion->name ?? '') }}"
                placeholder="{{ __('مثال: يوم الحجز بجنيه') }}"
                required
            >
            @error('name') <div class="a2-error">{{ $message }}</div> @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الحالة') }}</label>
            <label class="a2-check">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked(old('is_active', $promotion->is_active ?? true))
                >
                <span>{{ __('العرض مفعل') }}</span>
            </label>
            @error('is_active') <div class="a2-error">{{ $message }}</div> @enderror
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">{{ __('الوصف') }}</label>
            <textarea
                name="description"
                class="a2-textarea"
                placeholder="{{ __('وصف مختصر للعرض') }}"
            >{{ old('description', $promotion->description ?? '') }}</textarea>
            @error('description') <div class="a2-error">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <h2 class="a2-section-title">{{ __('نطاق تطبيق العرض') }}</h2>
    <div class="a2-section-subtitle">
        {{ __('اختر هل العرض على كل الخدمات، أو خدمة محددة، أو خدمة داخل قسم فرعي معين.') }}
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('نطاق العرض') }}</label>
            <select name="scope_type" id="scope_type" class="a2-select" required>
                @foreach($scopeTypes as $key => $label)
                    <option value="{{ $key }}" @selected($scopeVal === $key)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('scope_type') <div class="a2-error">{{ $message }}</div> @enderror
        </div>

        <div class="a2-form-group" id="serviceWrap">
            <label class="a2-label" for="service_id">{{ __('الخدمة') }}</label>
            <select name="service_id" id="service_id" class="a2-select js-psfp-search" data-placeholder="{{ __('ابحث عن الخدمة') }}">
                <option value="">{{ __('اختر الخدمة') }}</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected($serviceVal === (int) $service->id)>
                        {{ $service->name_ar ?? $service->name_en ?? $service->name ?? $service->key ?? ('#' . $service->id) }}
                    </option>
                @endforeach
            </select>
            @error('service_id') <div class="a2-error">{{ $message }}</div> @enderror
        </div>

        <div class="a2-form-group" id="childWrap">
            <label class="a2-label" for="child_id">{{ __('القسم الفرعي') }}</label>
            <select name="child_id" id="child_id" class="a2-select js-psfp-search" data-placeholder="{{ __('ابحث عن القسم الفرعي') }}">
                <option value="">{{ __('اختر القسم الفرعي') }}</option>
                @foreach($children as $child)
                    <option value="{{ $child->id }}" @selected($childVal === (int) $child->id)>
                        {{ $child->name_ar ?? $child->name_en ?? $child->name ?? ('#' . $child->id) }}
                    </option>
                @endforeach
            </select>
            @error('child_id') <div class="a2-error">{{ $message }}</div> @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الأولوية') }}</label>
            <input
                type="number"
                name="priority"
                class="a2-input"
                value="{{ old('priority', $promotion->priority ?? 100) }}"
                min="1"
            >
            <div class="a2-help-block">
                {{ __('الرقم الأقل يتم تطبيقه أولًا عند وجود أكثر من عرض فعال.') }}
            </div>
            @error('priority') <div class="a2-error">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <h2 class="a2-section-title">{{ __('نوع الخصم أو التعديل') }}</h2>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('الطرف المستهدف') }}</label>
            <select name="target_party" class="a2-select" required>
                @foreach($targetParties as $key => $label)
                    <option value="{{ $key }}" @selected($targetVal === $key)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('target_party') <div class="a2-error">{{ $message }}</div> @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('نوع العرض') }}</label>
            <select name="discount_type" id="discount_type" class="a2-select" required>
                @foreach($discountTypes as $key => $label)
                    <option value="{{ $key }}" @selected($discountVal === $key)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('discount_type') <div class="a2-error">{{ $message }}</div> @enderror
        </div>

        <div class="a2-form-group" id="discountValueWrap">
            <label class="a2-label">{{ __('قيمة العرض') }}</label>
            <input
                type="number"
                step="0.01"
                min="0"
                name="discount_value"
                id="discount_value"
                class="a2-input"
                value="{{ old('discount_value', $promotion->discount_value ?? '') }}"
                placeholder="{{ __('مثال: 1') }}"
            >
            <div class="a2-help-block">
                {{ __('تستخدم مع: قيمة ثابتة / خصم مبلغ / خصم نسبة. لا تستخدم مع إيقاف الرسوم.') }}
            </div>
            @error('discount_value') <div class="a2-error">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

@php
    $startDateVal = old('start_date');
    $startHourVal = old('start_hour', '00');
    $startMinuteVal = old('start_minute', '00');

    if (!$startDateVal && !empty($startsVal)) {
        try {
            $tmpStart = \Carbon\Carbon::parse(str_replace('T', ' ', $startsVal));
            $startDateVal = $tmpStart->format('Y-m-d');
            $startHourVal = $tmpStart->format('H');
            $startMinuteVal = $tmpStart->format('i');
        } catch (\Throwable $e) {
            $startDateVal = '';
        }
    }
@endphp

<div class="a2-card a2-card--section">
    <h2 class="a2-section-title">{{ __('مدة العرض') }}</h2>
    <div class="a2-section-subtitle">
        {{ __('اختر تاريخ البداية والوقت بنظام 24 ساعة، وسيتم تحديد النهاية تلقائيًا حسب مدة العرض.') }}
    </div>

    <input type="hidden" name="starts_at" id="starts_at" value="{{ $startsVal }}">
    <input type="hidden" name="ends_at" id="ends_at" value="{{ $endsVal }}">

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('تاريخ البداية') }}</label>
            <input
                type="date"
                id="start_date"
                class="a2-input"
                value="{{ $startDateVal }}"
            >
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('وقت البداية') }}</label>
            <div style="display:flex; gap:8px;">
                <select id="start_hour" class="a2-select">
                    @for($h = 0; $h <= 23; $h++)
                        @php $hh = str_pad((string) $h, 2, '0', STR_PAD_LEFT); @endphp
                        <option value="{{ $hh }}" @selected($startHourVal === $hh)>
                            {{ $hh }}
                        </option>
                    @endfor
                </select>

                <select id="start_minute" class="a2-select">
                    @foreach(['00','15','30','45'] as $mm)
                        <option value="{{ $mm }}" @selected($startMinuteVal === $mm)>
                            {{ $mm }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-help-block">
                {{ __('مثال: 00:00 تعني بداية اليوم.') }}
            </div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('مدة العرض') }}</label>
            <select id="duration_preset" class="a2-select">
                <option value="">{{ __('اختيار يدوي للنهاية') }}</option>
                <option value="24h">{{ __('24 ساعة') }}</option>
                <option value="3d">{{ __('3 أيام') }}</option>
                <option value="7d">{{ __('7 أيام') }}</option>
                <option value="14d">{{ __('14 يوم') }}</option>
                <option value="30d">{{ __('30 يوم') }}</option>
                <option value="custom_hours">{{ __('عدد ساعات مخصص') }}</option>
                <option value="custom_days">{{ __('عدد أيام مخصص') }}</option>
            </select>
        </div>

        <div class="a2-form-group a2-hidden" id="customDurationWrap">
            <label class="a2-label">{{ __('القيمة المخصصة') }}</label>
            <input
                type="number"
                min="1"
                step="1"
                id="custom_duration_value"
                class="a2-input"
                placeholder="{{ __('مثال: 5') }}"
            >
            <div class="a2-help-block" id="customDurationHelp">
                {{ __('أدخل القيمة المطلوبة.') }}
            </div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('النهاية المحسوبة') }}</label>
            <input
                type="text"
                id="ends_at_display"
                class="a2-input"
                value="{{ $endsVal ? str_replace('T', ' ', $endsVal) : '' }}"
                readonly
            >
            @error('starts_at') <div class="a2-error">{{ $message }}</div> @enderror
            @error('ends_at') <div class="a2-error">{{ $message }}</div> @enderror
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">{{ __('ملاحظات داخلية') }}</label>
            <textarea
                name="notes"
                class="a2-textarea"
                placeholder="{{ __('ملاحظات تظهر للإدارة فقط') }}"
            >{{ old('notes', $promotion->notes ?? '') }}</textarea>
            @error('notes') <div class="a2-error">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="a2-card">
    <div class="a2-page-actions">
        <button type="submit" class="a2-btn a2-btn-primary">
            {{ $isEdit ? 'حفظ التعديلات' : 'إنشاء العرض' }}
        </button>

        <a href="{{ route('admin.platform-service-fee-promotions.index') }}" class="a2-btn a2-btn-ghost">
            {{ __('رجوع') }}
        </a>
    </div>
</div>

<script>
(function () {
    const scopeSelect = document.getElementById('scope_type');
    const serviceWrap = document.getElementById('serviceWrap');
    const childWrap = document.getElementById('childWrap');

    const discountType = document.getElementById('discount_type');
    const discountValueWrap = document.getElementById('discountValueWrap');
    const discountValue = document.getElementById('discount_value');

    function syncScope() {
        const scope = scopeSelect.value;

        serviceWrap.classList.remove('a2-hidden');
        childWrap.classList.remove('a2-hidden');

        if (scope === 'all_services') {
            serviceWrap.classList.add('a2-hidden');
            childWrap.classList.add('a2-hidden');
        }

        if (scope === 'service') {
            childWrap.classList.add('a2-hidden');
        }
    }

    function syncDiscountValue() {
        const type = discountType.value;

        discountValueWrap.classList.remove('a2-hidden');

        if (type === 'waive') {
            discountValueWrap.classList.add('a2-hidden');
            discountValue.value = '';
        }
    }

    scopeSelect.addEventListener('change', syncScope);
    discountType.addEventListener('change', syncDiscountValue);

    syncScope();
    syncDiscountValue();

    // Make the service (5) and child (300+) pickers searchable instead of
    // rendering every option as a long static <select>. Client-side is enough
    // here - the option counts are small, so no remote lookup is needed.
    if (window.TomSelect) {
        document.querySelectorAll('.js-psfp-search').forEach(function (el) {
            if (el.tomselect) {
                return;
            }

            new TomSelect(el, {
                create: false,
                allowEmptyOption: true,
                maxOptions: 1000,
                placeholder: el.getAttribute('data-placeholder') || 'ابحث هنا',
                dropdownParent: 'body',
                sortField: {field: 'text', direction: 'asc'}
            });
        });
    }
const startDateInput = document.getElementById('start_date');
const startHourInput = document.getElementById('start_hour');
const startMinuteInput = document.getElementById('start_minute');

const startsAtInput = document.getElementById('starts_at');
const endsAtInput = document.getElementById('ends_at');
const endsAtDisplay = document.getElementById('ends_at_display');

const durationPreset = document.getElementById('duration_preset');
const customDurationWrap = document.getElementById('customDurationWrap');
const customDurationValue = document.getElementById('custom_duration_value');
const customDurationHelp = document.getElementById('customDurationHelp');

function pad(n) {
    return String(n).padStart(2, '0');
}

function toDateTimeLocalValue(date) {
    return date.getFullYear()
        + '-' + pad(date.getMonth() + 1)
        + '-' + pad(date.getDate())
        + 'T' + pad(date.getHours())
        + ':' + pad(date.getMinutes());
}

function toDisplayValue(value) {
    return value ? value.replace('T', ' ') : '';
}

function buildStartDate() {
    const date = startDateInput.value;
    const hour = startHourInput.value || '00';
    const minute = startMinuteInput.value || '00';

    if (!date) {
        return null;
    }

    return new Date(date + 'T' + hour + ':' + minute + ':00');
}

function addDuration(date, preset, customValue) {
    const end = new Date(date.getTime());

    if (preset === '24h') {
        end.setHours(end.getHours() + 24);
    } else if (preset === '3d') {
        end.setDate(end.getDate() + 3);
    } else if (preset === '7d') {
        end.setDate(end.getDate() + 7);
    } else if (preset === '14d') {
        end.setDate(end.getDate() + 14);
    } else if (preset === '30d') {
        end.setDate(end.getDate() + 30);
    } else if (preset === 'custom_hours') {
        end.setHours(end.getHours() + Math.max(1, parseInt(customValue || 1, 10)));
    } else if (preset === 'custom_days') {
        end.setDate(end.getDate() + Math.max(1, parseInt(customValue || 1, 10)));
    } else {
        return null;
    }

    return end;
}

function syncCustomDuration() {
    const preset = durationPreset.value;

    customDurationWrap.classList.add('a2-hidden');

    if (preset === 'custom_hours') {
        customDurationWrap.classList.remove('a2-hidden');
        customDurationHelp.textContent = 'أدخل عدد الساعات.';
    }

    if (preset === 'custom_days') {
        customDurationWrap.classList.remove('a2-hidden');
        customDurationHelp.textContent = 'أدخل عدد الأيام.';
    }
}

function calculatePromotionDates() {
    const start = buildStartDate();

    syncCustomDuration();

    if (!start || Number.isNaN(start.getTime())) {
        startsAtInput.value = '';
        endsAtInput.value = '';
        endsAtDisplay.value = '';
        return;
    }

    const startValue = toDateTimeLocalValue(start);
    startsAtInput.value = startValue;

    const preset = durationPreset.value;

    if (!preset) {
        endsAtDisplay.value = endsAtInput.value ? toDisplayValue(endsAtInput.value) : '';
        return;
    }

    const end = addDuration(start, preset, customDurationValue.value);

    if (end) {
        const endValue = toDateTimeLocalValue(end);
        endsAtInput.value = endValue;
        endsAtDisplay.value = toDisplayValue(endValue);
    }
}

startDateInput.addEventListener('change', calculatePromotionDates);
startHourInput.addEventListener('change', calculatePromotionDates);
startMinuteInput.addEventListener('change', calculatePromotionDates);
durationPreset.addEventListener('change', calculatePromotionDates);
customDurationValue.addEventListener('input', calculatePromotionDates);

calculatePromotionDates();

})();
</script>