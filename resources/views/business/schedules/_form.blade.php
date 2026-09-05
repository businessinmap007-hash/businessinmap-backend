@php
    use App\Models\TripSchedule;

    $isEdit = isset($row) && $row?->exists;
    $currentStops = old('stops', isset($stops) ? $stops->map(fn ($s) => [
        'label' => $s->label,
        'address' => $s->address,
        'business_id' => $s->business_id,
        'business_name' => optional($s->business)->name,
    ])->all() : []);

    $currentMode = (string) old('mode', $row->mode ?? '');
    $currentScope = (string) old('scope', $row->scope ?? TripSchedule::SCOPE_DOMESTIC);
    $currentPattern = (string) old('schedule_pattern', $row->schedule_pattern ?? TripSchedule::PATTERN_WEEKLY);
    $currentVehicleType = (int) old('vehicle_type_id', $row->vehicle_type_id ?? 0);
    $currentOriginGov = (int) old('origin_governorate_id', $row->origin_governorate_id ?? 0);
    $currentDestGov = (int) old('destination_governorate_id', $row->destination_governorate_id ?? 0);
    $currentOriginCity = (int) old('origin_city_id', $row->origin_city_id ?? 0);
    $currentDestCity = (int) old('destination_city_id', $row->destination_city_id ?? 0);
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('نوع الرحلة والمركبة') }}</div>
            <div class="a2-card-sub">{{ __('اختر نمط التشغيل، ثم فئة المركبة المعتمدة — ويمكنك تسميتها باسمك الخاص.') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="mode">{{ __('نمط التشغيل') }} <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-mode" id="mode" name="mode" required>
                <option value="">{{ __('اختر النمط') }}</option>
                @foreach($modes as $key => $label)
                    <option value="{{ $key }}" @selected($currentMode === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="vehicle_type_id">{{ __('فئة المركبة / الشحنة') }}</label>
            <select class="a2-select js-ts-vehicle" id="vehicle_type_id" name="vehicle_type_id" data-current-value="{{ $currentVehicleType ?: '' }}">
                <option value="">{{ __('اختر النمط أولًا') }}</option>
            </select>
            <div class="a2-hint a2-mt-8">{{ __('الفئات المعتمدة في المنصة لهذا النمط.') }}</div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="vehicle_label">{{ __('اسمك للمركبة (اختياري)') }}</label>
            <input class="a2-input" id="vehicle_label" name="vehicle_label" value="{{ old('vehicle_label', $row->vehicle_label ?? '') }}" placeholder="{{ __('ميكروباص 14 راكب') }}">
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('الطريق') }}</div>
            <div class="a2-card-sub">{{ __('الرحلة المحلية تُحدَّد بالمحافظة، والدولية بالدولة.') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="scope">{{ __('نطاق الرحلة') }} <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-scope" id="scope" name="scope" required>
                @foreach($scopeLabels as $key => $label)
                    <option value="{{ $key }}" @selected($currentScope === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Domestic: governorate (+ optional city) on each end. --}}
    <div class="a2-form-grid js-ts-domestic">
        <div class="a2-form-group">
            <label class="a2-label" for="origin_governorate_id">{{ __('محافظة القيام') }} <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-origin-gov" id="origin_governorate_id" name="origin_governorate_id">
                <option value="">{{ __('اختر المحافظة') }}</option>
                @foreach($governorates as $gov)
                    <option value="{{ $gov->id }}" @selected($currentOriginGov === (int) $gov->id)>{{ $gov->name_ar }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="origin_city_id">{{ __('مدينة القيام (اختياري)') }}</label>
            <select class="a2-select js-ts-origin-city" id="origin_city_id" name="origin_city_id" data-current-value="{{ $currentOriginCity ?: '' }}">
                <option value="">{{ __('كل المدن') }}</option>
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="destination_governorate_id">{{ __('محافظة الوصول') }} <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-dest-gov" id="destination_governorate_id" name="destination_governorate_id">
                <option value="">{{ __('اختر المحافظة') }}</option>
                @foreach($governorates as $gov)
                    <option value="{{ $gov->id }}" @selected($currentDestGov === (int) $gov->id)>{{ $gov->name_ar }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="destination_city_id">{{ __('مدينة الوصول (اختياري)') }}</label>
            <select class="a2-select js-ts-dest-city" id="destination_city_id" name="destination_city_id" data-current-value="{{ $currentDestCity ?: '' }}">
                <option value="">{{ __('كل المدن') }}</option>
            </select>
        </div>
    </div>

    {{-- International: country on each end. --}}
    <div class="a2-form-grid js-ts-international">
        <div class="a2-form-group">
            <label class="a2-label" for="origin_country_id">{{ __('دولة القيام') }} <span class="a2-danger">*</span></label>
            <select class="a2-select" id="origin_country_id" name="origin_country_id">
                <option value="">{{ __('اختر الدولة') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" @selected((int) old('origin_country_id', $row->origin_country_id ?? 0) === (int) $country->id)>{{ $country->name_ar }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="destination_country_id">{{ __('دولة الوصول') }} <span class="a2-danger">*</span></label>
            <select class="a2-select" id="destination_country_id" name="destination_country_id">
                <option value="">{{ __('اختر الدولة') }}</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" @selected((int) old('destination_country_id', $row->destination_country_id ?? 0) === (int) $country->id)>{{ $country->name_ar }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('الموعد') }}</div>
            <div class="a2-card-sub">{{ __('رحلة أسبوعية متكررة، أو رحلة بتاريخ واحد، أو متاح عند الطلب.') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="schedule_pattern">{{ __('التكرار') }} <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-pattern" id="schedule_pattern" name="schedule_pattern" required>
                @foreach($patterns as $key => $label)
                    <option value="{{ $key }}" @selected($currentPattern === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group js-ts-weekly">
            <label class="a2-label" for="day_of_week">{{ __('يوم الأسبوع') }} <span class="a2-danger">*</span></label>
            <select class="a2-select" id="day_of_week" name="day_of_week">
                <option value="">{{ __('اختر اليوم') }}</option>
                @foreach($days as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('day_of_week', $row->day_of_week ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group js-ts-one-off">
            <label class="a2-label" for="trip_date">{{ __('تاريخ الرحلة') }} <span class="a2-danger">*</span></label>
            <input class="a2-input" id="trip_date" name="trip_date" type="date" value="{{ old('trip_date', optional($row->trip_date ?? null)->toDateString()) }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="departure_time">{{ __('موعد القيام') }}</label>
            <input class="a2-input" id="departure_time" name="departure_time" type="time" value="{{ old('departure_time', $row->departure_time ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="return_time">{{ __('موعد العودة (اختياري)') }}</label>
            <input class="a2-input" id="return_time" name="return_time" type="time" value="{{ old('return_time', $row->return_time ?? '') }}">
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('السعة والسعر') }}</div>
            <div class="a2-card-sub">{{ __('اترك السعة فارغة إذا كانت غير محدودة. العربون يُحجز من محفظة العميل ويُرد عند الإكمال أو الإلغاء.') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="capacity">{{ __('السعة') }}</label>
            <input class="a2-input" id="capacity" name="capacity" type="number" min="0" value="{{ old('capacity', $row->capacity ?? '') }}" placeholder="14">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="capacity_unit">{{ __('وحدة السعة') }}</label>
            <input class="a2-input js-ts-unit" id="capacity_unit" name="capacity_unit" value="{{ old('capacity_unit', $row->capacity_unit ?? '') }}" placeholder="{{ __('مقعد / طرد / متر مكعب') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="price">{{ __('السعر للوحدة') }}</label>
            <input class="a2-input" id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $row->price ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="deposit_per_unit">{{ __('العربون للوحدة (اختياري)') }}</label>
            <input class="a2-input" id="deposit_per_unit" name="deposit_per_unit" type="number" step="0.01" min="0" value="{{ old('deposit_per_unit', $row->deposit_per_unit ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="currency">{{ __('العملة') }}</label>
            <input class="a2-input" id="currency" name="currency" value="{{ old('currency', $row->currency ?? 'EGP') }}" maxlength="10">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="status">{{ __('الحالة') }}</label>
            <select class="a2-select" id="status" name="status">
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" @selected((string) old('status', $row->status ?? TripSchedule::STATUS_ACTIVE) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('نقاط التوقف على الطريق (اختياري)') }}</div>
            <div class="a2-card-sub">{{ __('اسم مختصر + عنوان نصي لكل نقطة — يُستخدم العنوان لفتح خرائط جوجل مباشرة على هاتف السائق دون أي تكلفة إضافية على المنصة. رتّب النقاط بترتيب المرور عليها؛ يلزم وجود نقطة واحدة على الأقل قبل بدء تنفيذ الرحلة من التطبيق.') }}</div>
        </div>
    </div>

    <div id="js-stops-list">
        @foreach($currentStops as $i => $stop)
            <div class="a2-form-grid js-stop-row" style="grid-template-columns:1.3fr 1fr 1.6fr auto;align-items:end;">
                <div class="a2-form-group">
                    <label class="a2-label">{{ __('نشاط تجاري مسجّل (اختياري)') }}</label>
                    <select class="a2-select js-stop-business"
                            name="stops[{{ $i }}][business_id]"
                            data-remote-url="{{ route('business.schedules.business-lookup', [], false) }}"
                            data-current-value="{{ $stop['business_id'] ?? '' }}"
                            data-current-label="{{ $stop['business_name'] ?? '' }}">
                        <option value="">{{ __('بدون — أدخل العنوان يدويًا') }}</option>
                        @if(!empty($stop['business_id']))
                            <option value="{{ $stop['business_id'] }}" selected>{{ $stop['business_name'] }}</option>
                        @endif
                    </select>
                </div>
                <div class="a2-form-group">
                    <label class="a2-label">{{ __('اسم النقطة') }}</label>
                    <input class="a2-input js-stop-label" name="stops[{{ $i }}][label]" value="{{ $stop['label'] ?? '' }}" placeholder="{{ __('فرع المهندسين') }}">
                </div>
                <div class="a2-form-group">
                    <label class="a2-label">{{ __('العنوان') }}</label>
                    <input class="a2-input" name="stops[{{ $i }}][address]" value="{{ $stop['address'] ?? '' }}" placeholder="{{ __('15 شارع جامعة الدول العربية، المهندسين، الجيزة') }}">
                </div>
                <div class="a2-form-group">
                    <button type="button" class="a2-btn a2-btn-ghost js-stop-remove">{{ __('حذف') }}</button>
                </div>
            </div>
        @endforeach
    </div>

    <button type="button" id="js-stop-add" class="a2-btn a2-btn-ghost a2-mt-8">{{ __('+ أضف نقطة') }}</button>
    <div class="a2-hint a2-mt-8">{{ __('عند اختيار نشاط تجاري مسجّل، يُستخدم موقعه الفعلي (GPS) لفتح خرائط جوجل بدقة عند التنفيذ.') }}</div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('رحلة عودة (اختياري)') }}</div>
            <div class="a2-card-sub">{{ __('عائد فارغاً من رحلة سابقة؟ اربط هذا الخط بها واعرضه بسعر مخفّض ليجده من يريد نفس الاتجاه.') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('النوع') }}</label>
            <label class="a2-check" style="margin-top:10px;">
                <input type="checkbox" name="is_return_leg" value="1" @checked((bool) old('is_return_leg', (bool) ($row->is_return_leg ?? false)))>
                <span>{{ __('هذا الخط رحلة عودة') }}</span>
            </label>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="parent_trip_id">{{ __('الرحلة الأصلية') }}</label>
            <select class="a2-select" id="parent_trip_id" name="parent_trip_id">
                <option value="">{{ __('بدون') }}</option>
                @foreach($parentLegs as $leg)
                    <option value="{{ $leg->id }}" @selected((int) old('parent_trip_id', $row->parent_trip_id ?? 0) === (int) $leg->id)>
                        #{{ $leg->id }} — {{ optional($leg->originGovernorate)->name_ar ?: '—' }} → {{ optional($leg->destinationGovernorate)->name_ar ?: '—' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group" style="grid-column:1/-1;">
            <label class="a2-label" for="notes">{{ __('ملاحظات') }}</label>
            <textarea class="a2-input" id="notes" name="notes" rows="3" placeholder="{{ __('نقطة التجمع، شروط الشحن...') }}">{{ old('notes', $row->notes ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('business.schedules.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    <button type="submit" class="a2-btn a2-btn-primary">{{ $isEdit ? __('تحديث') : __('نشر الخط') }}</button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const vehiclesByMode = @json($vehicleTypesByMode ?? []);
    const citiesByGov = @json($citiesByGovernorate ?? []);
    const i18n = {
        pickCategory: @json(__('اختر الفئة')),
        pickModeFirst: @json(__('اختر النمط أولًا')),
        allCities: @json(__('كل المدن')),
    };

    const modeSelect = document.querySelector('.js-ts-mode');
    const vehicleSelect = document.querySelector('.js-ts-vehicle');
    const scopeSelect = document.querySelector('.js-ts-scope');
    const patternSelect = document.querySelector('.js-ts-pattern');
    const unitInput = document.querySelector('.js-ts-unit');

    // Rebuild a <select> from a list, preserving the saved value when present.
    function fill(select, list, placeholder) {
        if (!select) return;
        const keep = String(select.dataset.currentValue || select.value || '');
        select.innerHTML = '';

        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);

        list.forEach(function (item) {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = String(item.label);
            if (String(item.id) === keep) option.selected = true;
            select.appendChild(option);
        });
    }

    function show(el, visible) {
        if (el) el.style.display = visible ? '' : 'none';
    }

    function syncVehicles() {
        const list = vehiclesByMode[String(modeSelect.value || '')] || [];
        fill(vehicleSelect, list, list.length ? i18n.pickCategory : i18n.pickModeFirst);

        // Offer the class's standard unit when the carrier hasn't named one.
        const chosen = list.find(function (v) { return String(v.id) === String(vehicleSelect.value); });
        if (unitInput && chosen && chosen.unit && !unitInput.value) unitInput.value = chosen.unit;
    }

    function syncScope() {
        const isIntl = String(scopeSelect.value) === 'international';
        show(document.querySelector('.js-ts-domestic'), !isIntl);
        show(document.querySelector('.js-ts-international'), isIntl);
    }

    function syncPattern() {
        const pattern = String(patternSelect.value);
        show(document.querySelector('.js-ts-weekly'), pattern === 'weekly');
        show(document.querySelector('.js-ts-one-off'), pattern === 'one_off');
    }

    function syncCities(govSelect, citySelect) {
        if (!govSelect || !citySelect) return;
        const list = citiesByGov[String(govSelect.value || '')] || [];
        fill(citySelect, list, i18n.allCities);
    }

    const originGov = document.querySelector('.js-ts-origin-gov');
    const originCity = document.querySelector('.js-ts-origin-city');
    const destGov = document.querySelector('.js-ts-dest-gov');
    const destCity = document.querySelector('.js-ts-dest-city');

    if (modeSelect) modeSelect.addEventListener('change', function () {
        vehicleSelect.dataset.currentValue = '';
        syncVehicles();
    });
    if (vehicleSelect) vehicleSelect.addEventListener('change', syncVehicles);
    if (scopeSelect) scopeSelect.addEventListener('change', syncScope);
    if (patternSelect) patternSelect.addEventListener('change', syncPattern);
    if (originGov) originGov.addEventListener('change', function () {
        originCity.dataset.currentValue = '';
        syncCities(originGov, originCity);
    });
    if (destGov) destGov.addEventListener('change', function () {
        destCity.dataset.currentValue = '';
        syncCities(destGov, destCity);
    });

    syncVehicles();
    syncScope();
    syncPattern();
    syncCities(originGov, originCity);
    syncCities(destGov, destCity);

    // Stops: a plain add/remove row list, no framework — indices are just
    // re-derived from DOM position on every change so a removed middle row
    // never leaves a gap in the submitted stops[] array. The business picker
    // is the one exception to "no AJAX" in this form: the business list (like
    // the admin panel's own business-lookup pickers) is too large to ship
    // inline the way governorates/cities are.
    const stopsList = document.getElementById('js-stops-list');
    const stopAddBtn = document.getElementById('js-stop-add');
    const stopLabels = {
        business: @json(__('نشاط تجاري مسجّل (اختياري)')),
        noBusiness: @json(__('بدون — أدخل العنوان يدويًا')),
        name: @json(__('اسم النقطة')), address: @json(__('العنوان')),
        namePh: @json(__('فرع المهندسين')), addrPh: @json(__('15 شارع جامعة الدول العربية، المهندسين، الجيزة')),
        remove: @json(__('حذف')),
    };
    const stopBusinessLookupUrl = @json(route('business.schedules.business-lookup', [], false));

    function reindexStops() {
        if (!stopsList) return;
        stopsList.querySelectorAll('.js-stop-row').forEach(function (row, i) {
            row.querySelectorAll('input, select').forEach(function (field) {
                field.name = field.name.replace(/stops\[\d+\]/, 'stops[' + i + ']');
            });
        });
    }

    function initStopBusinessSelect(select) {
        if (!select || select.tomselect || !window.TomSelect) return;
        const ts = new TomSelect(select, {
            valueField: 'value',
            labelField: 'text',
            searchField: 'text',
            create: false,
            maxOptions: 30,
            placeholder: stopLabels.noBusiness,
            dropdownParent: 'body',
            shouldLoad: function (query) { return query.length >= 1; },
            load: function (query, callback) {
                const url = new URL(stopBusinessLookupUrl, window.location.origin);
                url.searchParams.set('q', query);
                fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        const rows = (data && data.ok && Array.isArray(data.businesses)) ? data.businesses : [];
                        callback(rows.map(function (b) { return { value: String(b.id), text: b.name }; }));
                    })
                    .catch(function () { callback(); });
            },
            onChange: function (value) {
                const row = select.closest('.js-stop-row');
                const labelInput = row ? row.querySelector('.js-stop-label') : null;
                if (!labelInput || !value) return;
                const opt = ts.options[value];
                if (opt) labelInput.value = opt.text;
            },
        });

        // Preload the saved business as a labeled option so edit shows its name.
        const currentValue = select.dataset.currentValue;
        const currentLabel = select.dataset.currentLabel;
        if (currentValue && currentLabel) {
            ts.addOption({ value: currentValue, text: currentLabel });
            ts.setValue(currentValue, true);
        }
    }

    function addStopRow() {
        if (!stopsList) return;
        const row = document.createElement('div');
        row.className = 'a2-form-grid js-stop-row';
        row.style.gridTemplateColumns = '1.3fr 1fr 1.6fr auto';
        row.style.alignItems = 'end';
        row.innerHTML =
            '<div class="a2-form-group"><label class="a2-label">' + stopLabels.business + '</label>' +
            '<select class="a2-select js-stop-business" name="stops[0][business_id]"><option value="">' + stopLabels.noBusiness + '</option></select></div>' +
            '<div class="a2-form-group"><label class="a2-label">' + stopLabels.name + '</label>' +
            '<input class="a2-input js-stop-label" name="stops[0][label]" placeholder="' + stopLabels.namePh + '"></div>' +
            '<div class="a2-form-group"><label class="a2-label">' + stopLabels.address + '</label>' +
            '<input class="a2-input" name="stops[0][address]" placeholder="' + stopLabels.addrPh + '"></div>' +
            '<div class="a2-form-group"><button type="button" class="a2-btn a2-btn-ghost js-stop-remove">' + stopLabels.remove + '</button></div>';
        stopsList.appendChild(row);
        initStopBusinessSelect(row.querySelector('.js-stop-business'));
        reindexStops();
    }

    if (stopAddBtn) stopAddBtn.addEventListener('click', addStopRow);
    if (stopsList) stopsList.addEventListener('click', function (e) {
        if (e.target && e.target.classList.contains('js-stop-remove')) {
            e.target.closest('.js-stop-row').remove();
            reindexStops();
        }
    });

    stopsList?.querySelectorAll('.js-stop-business').forEach(initStopBusinessSelect);
});
</script>
@endpush
