@php
    use App\Models\TripSchedule;

    $isEdit = isset($row) && $row?->exists;

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
            <div class="a2-card-title">نوع الرحلة والمركبة</div>
            <div class="a2-card-sub">اختر نمط التشغيل، ثم فئة المركبة المعتمدة — ويمكنك تسميتها باسمك الخاص.</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="mode">نمط التشغيل <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-mode" id="mode" name="mode" required>
                <option value="">اختر النمط</option>
                @foreach($modes as $key => $label)
                    <option value="{{ $key }}" @selected($currentMode === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="vehicle_type_id">فئة المركبة / الشحنة</label>
            <select class="a2-select js-ts-vehicle" id="vehicle_type_id" name="vehicle_type_id" data-current-value="{{ $currentVehicleType ?: '' }}">
                <option value="">اختر النمط أولًا</option>
            </select>
            <div class="a2-hint a2-mt-8">الفئات المعتمدة في المنصة لهذا النمط.</div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="vehicle_label">اسمك للمركبة (اختياري)</label>
            <input class="a2-input" id="vehicle_label" name="vehicle_label" value="{{ old('vehicle_label', $row->vehicle_label ?? '') }}" placeholder="ميكروباص 14 راكب">
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">الطريق</div>
            <div class="a2-card-sub">الرحلة المحلية تُحدَّد بالمحافظة، والدولية بالدولة.</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="scope">نطاق الرحلة <span class="a2-danger">*</span></label>
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
            <label class="a2-label" for="origin_governorate_id">محافظة القيام <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-origin-gov" id="origin_governorate_id" name="origin_governorate_id">
                <option value="">اختر المحافظة</option>
                @foreach($governorates as $gov)
                    <option value="{{ $gov->id }}" @selected($currentOriginGov === (int) $gov->id)>{{ $gov->name_ar }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="origin_city_id">مدينة القيام (اختياري)</label>
            <select class="a2-select js-ts-origin-city" id="origin_city_id" name="origin_city_id" data-current-value="{{ $currentOriginCity ?: '' }}">
                <option value="">كل المدن</option>
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="destination_governorate_id">محافظة الوصول <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-dest-gov" id="destination_governorate_id" name="destination_governorate_id">
                <option value="">اختر المحافظة</option>
                @foreach($governorates as $gov)
                    <option value="{{ $gov->id }}" @selected($currentDestGov === (int) $gov->id)>{{ $gov->name_ar }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="destination_city_id">مدينة الوصول (اختياري)</label>
            <select class="a2-select js-ts-dest-city" id="destination_city_id" name="destination_city_id" data-current-value="{{ $currentDestCity ?: '' }}">
                <option value="">كل المدن</option>
            </select>
        </div>
    </div>

    {{-- International: country on each end. --}}
    <div class="a2-form-grid js-ts-international">
        <div class="a2-form-group">
            <label class="a2-label" for="origin_country_id">دولة القيام <span class="a2-danger">*</span></label>
            <select class="a2-select" id="origin_country_id" name="origin_country_id">
                <option value="">اختر الدولة</option>
                @foreach($countries as $country)
                    <option value="{{ $country->id }}" @selected((int) old('origin_country_id', $row->origin_country_id ?? 0) === (int) $country->id)>{{ $country->name_ar }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="destination_country_id">دولة الوصول <span class="a2-danger">*</span></label>
            <select class="a2-select" id="destination_country_id" name="destination_country_id">
                <option value="">اختر الدولة</option>
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
            <div class="a2-card-title">الموعد</div>
            <div class="a2-card-sub">رحلة أسبوعية متكررة، أو رحلة بتاريخ واحد، أو متاح عند الطلب.</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="schedule_pattern">التكرار <span class="a2-danger">*</span></label>
            <select class="a2-select js-ts-pattern" id="schedule_pattern" name="schedule_pattern" required>
                @foreach($patterns as $key => $label)
                    <option value="{{ $key }}" @selected($currentPattern === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group js-ts-weekly">
            <label class="a2-label" for="day_of_week">يوم الأسبوع <span class="a2-danger">*</span></label>
            <select class="a2-select" id="day_of_week" name="day_of_week">
                <option value="">اختر اليوم</option>
                @foreach($days as $value => $label)
                    <option value="{{ $value }}" @selected((string) old('day_of_week', $row->day_of_week ?? '') === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group js-ts-one-off">
            <label class="a2-label" for="trip_date">تاريخ الرحلة <span class="a2-danger">*</span></label>
            <input class="a2-input" id="trip_date" name="trip_date" type="date" value="{{ old('trip_date', optional($row->trip_date ?? null)->toDateString()) }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="departure_time">موعد القيام</label>
            <input class="a2-input" id="departure_time" name="departure_time" type="time" value="{{ old('departure_time', $row->departure_time ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="return_time">موعد العودة (اختياري)</label>
            <input class="a2-input" id="return_time" name="return_time" type="time" value="{{ old('return_time', $row->return_time ?? '') }}">
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">السعة والسعر</div>
            <div class="a2-card-sub">اترك السعة فارغة إذا كانت غير محدودة. العربون يُحجز من محفظة العميل ويُرد عند الإكمال أو الإلغاء.</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="capacity">السعة</label>
            <input class="a2-input" id="capacity" name="capacity" type="number" min="0" value="{{ old('capacity', $row->capacity ?? '') }}" placeholder="14">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="capacity_unit">وحدة السعة</label>
            <input class="a2-input js-ts-unit" id="capacity_unit" name="capacity_unit" value="{{ old('capacity_unit', $row->capacity_unit ?? '') }}" placeholder="مقعد / طرد / متر مكعب">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="price">السعر للوحدة</label>
            <input class="a2-input" id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $row->price ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="deposit_per_unit">العربون للوحدة (اختياري)</label>
            <input class="a2-input" id="deposit_per_unit" name="deposit_per_unit" type="number" step="0.01" min="0" value="{{ old('deposit_per_unit', $row->deposit_per_unit ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="currency">العملة</label>
            <input class="a2-input" id="currency" name="currency" value="{{ old('currency', $row->currency ?? 'EGP') }}" maxlength="10">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="status">الحالة</label>
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
            <div class="a2-card-title">رحلة عودة (اختياري)</div>
            <div class="a2-card-sub">عائد فارغاً من رحلة سابقة؟ اربط هذا الخط بها واعرضه بسعر مخفّض ليجده من يريد نفس الاتجاه.</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">النوع</label>
            <label class="a2-check" style="margin-top:10px;">
                <input type="checkbox" name="is_return_leg" value="1" @checked((bool) old('is_return_leg', (bool) ($row->is_return_leg ?? false)))>
                <span>هذا الخط رحلة عودة</span>
            </label>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="parent_trip_id">الرحلة الأصلية</label>
            <select class="a2-select" id="parent_trip_id" name="parent_trip_id">
                <option value="">بدون</option>
                @foreach($parentLegs as $leg)
                    <option value="{{ $leg->id }}" @selected((int) old('parent_trip_id', $row->parent_trip_id ?? 0) === (int) $leg->id)>
                        #{{ $leg->id }} — {{ optional($leg->originGovernorate)->name_ar ?: '—' }} → {{ optional($leg->destinationGovernorate)->name_ar ?: '—' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group" style="grid-column:1/-1;">
            <label class="a2-label" for="notes">ملاحظات</label>
            <textarea class="a2-input" id="notes" name="notes" rows="3" placeholder="نقطة التجمع، شروط الشحن...">{{ old('notes', $row->notes ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('business.schedules.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    <button type="submit" class="a2-btn a2-btn-primary">{{ $isEdit ? 'تحديث' : 'نشر الخط' }}</button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const vehiclesByMode = @json($vehicleTypesByMode ?? []);
    const citiesByGov = @json($citiesByGovernorate ?? []);

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
        fill(vehicleSelect, list, list.length ? 'اختر الفئة' : 'اختر النمط أولًا');

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
        fill(citySelect, list, 'كل المدن');
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
});
</script>
@endpush
