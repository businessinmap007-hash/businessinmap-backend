@php
    /*
    |--------------------------------------------------------------------------
    | Admin V2 - 24H DateTime Range Component
    |--------------------------------------------------------------------------
    | Usage:
    |
    | @include('admin-v2.components.datetime-range-24', [
    |     'startName' => 'start_at',
    |     'endName' => 'end_at',
    |     'startValue' => old('start_at', $booking->start_at ?? null),
    |     'endValue' => old('end_at', $booking->end_at ?? null),
    |     'labelStart' => 'تاريخ / وقت البداية',
    |     'labelEnd' => 'تاريخ / وقت النهاية',
    |     'minuteStep' => 15,
    |     'required' => false,
    | ])
    */

    $startName = $startName ?? 'start_at';
    $endName = $endName ?? 'end_at';

    $labelStart = $labelStart ?? 'تاريخ / وقت البداية';
    $labelEnd = $labelEnd ?? 'تاريخ / وقت النهاية';

    $startValue = $startValue ?? null;
    $endValue = $endValue ?? null;

    $minuteStep = (int) ($minuteStep ?? 15);
    $minuteStep = in_array($minuteStep, [1, 5, 10, 15, 30], true) ? $minuteStep : 15;

    $required = (bool) ($required ?? false);

    $uid = $uid ?? ('dt24_' . str_replace('.', '_', uniqid('', true)));

    $parseDateTime = function ($value) {
        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    };

    $startCarbon = $parseDateTime($startValue);
    $endCarbon = $parseDateTime($endValue);

    $startDateVal = old($startName . '_date', $startCarbon ? $startCarbon->format('Y-m-d') : '');
    $startHourVal = old($startName . '_hour', $startCarbon ? $startCarbon->format('H') : '00');
    $startMinuteVal = old($startName . '_minute', $startCarbon ? $startCarbon->format('i') : '00');

    $endDateVal = old($endName . '_date', $endCarbon ? $endCarbon->format('Y-m-d') : '');
    $endHourVal = old($endName . '_hour', $endCarbon ? $endCarbon->format('H') : '00');
    $endMinuteVal = old($endName . '_minute', $endCarbon ? $endCarbon->format('i') : '00');

    $startHiddenVal = $startCarbon ? $startCarbon->format('Y-m-d\TH:i') : '';
    $endHiddenVal = $endCarbon ? $endCarbon->format('Y-m-d\TH:i') : '';

    $minuteOptions = [];
    for ($m = 0; $m <= 59; $m += $minuteStep) {
        $minuteOptions[] = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }

    if (!in_array($startMinuteVal, $minuteOptions, true)) {
        $minuteOptions[] = $startMinuteVal;
    }

    if (!in_array($endMinuteVal, $minuteOptions, true)) {
        $minuteOptions[] = $endMinuteVal;
    }

    sort($minuteOptions);
@endphp

<div class="a2-dt24" id="{{ $uid }}">
    <input type="hidden" name="{{ $startName }}" id="{{ $uid }}_start_hidden" value="{{ $startHiddenVal }}">
    <input type="hidden" name="{{ $endName }}" id="{{ $uid }}_end_hidden" value="{{ $endHiddenVal }}">

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">{{ $labelStart }}</label>

            <div class="a2-dt24-row">
                <input
                    type="date"
                    id="{{ $uid }}_start_date"
                    class="a2-input"
                    value="{{ $startDateVal }}"
                    @if($required) required @endif
                >

                <select id="{{ $uid }}_start_hour" class="a2-select">
                    @for($h = 0; $h <= 23; $h++)
                        @php $hh = str_pad((string) $h, 2, '0', STR_PAD_LEFT); @endphp
                        <option value="{{ $hh }}" @selected($startHourVal === $hh)>
                            {{ $hh }}
                        </option>
                    @endfor
                </select>

                <select id="{{ $uid }}_start_minute" class="a2-select">
                    @foreach($minuteOptions as $mm)
                        <option value="{{ $mm }}" @selected($startMinuteVal === $mm)>
                            {{ $mm }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-help-block">
                الوقت بنظام 24 ساعة. مثال: 00:00 تعني بداية اليوم.
            </div>

            @error($startName)
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ $labelEnd }}</label>

            <div class="a2-dt24-row">
                <input
                    type="date"
                    id="{{ $uid }}_end_date"
                    class="a2-input"
                    value="{{ $endDateVal }}"
                    @if($required) required @endif
                >

                <select id="{{ $uid }}_end_hour" class="a2-select">
                    @for($h = 0; $h <= 23; $h++)
                        @php $hh = str_pad((string) $h, 2, '0', STR_PAD_LEFT); @endphp
                        <option value="{{ $hh }}" @selected($endHourVal === $hh)>
                            {{ $hh }}
                        </option>
                    @endfor
                </select>

                <select id="{{ $uid }}_end_minute" class="a2-select">
                    @foreach($minuteOptions as $mm)
                        <option value="{{ $mm }}" @selected($endMinuteVal === $mm)>
                            {{ $mm }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-help-block">
                اختر النهاية بنفس نظام 24 ساعة.
            </div>

            @error($endName)
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
        <div class="a2-alert a2-alert-info a2-mt-12" id="{{ $uid }}_duration_box">
    المدة: —
</div>
    </div>
</div>

@once
    <style>
        .a2-dt24-row{
            display:grid;
            grid-template-columns: minmax(160px, 1fr) 90px 90px;
            gap:8px;
            align-items:center;
        }

        .a2-dt24-row .a2-select{
            min-width:0;
        }

        @media (max-width: 700px){
            .a2-dt24-row{
                grid-template-columns: 1fr;
            }
        }
    </style>
@endonce

<script>
(function () {
    const root = document.getElementById(@json($uid));

    if (!root) {
        return;
    }

    const startHidden = document.getElementById(@json($uid . '_start_hidden'));
    const endHidden = document.getElementById(@json($uid . '_end_hidden'));

    const startDate = document.getElementById(@json($uid . '_start_date'));
    const startHour = document.getElementById(@json($uid . '_start_hour'));
    const startMinute = document.getElementById(@json($uid . '_start_minute'));

    const endDate = document.getElementById(@json($uid . '_end_date'));
    const endHour = document.getElementById(@json($uid . '_end_hour'));
    const endMinute = document.getElementById(@json($uid . '_end_minute'));

    const durationBox = document.getElementById(@json($uid . '_duration_box'));

    function buildValue(dateInput, hourInput, minuteInput) {
        const date = dateInput.value;
        const hour = hourInput.value || '00';
        const minute = minuteInput.value || '00';

        if (!date) {
            return '';
        }

        return date + 'T' + hour + ':' + minute;
    }

    function parseLocalDateTime(value) {
        if (!value) {
            return null;
        }

        const date = new Date(value + ':00');

        if (Number.isNaN(date.getTime())) {
            return null;
        }

        return date;
    }

    function formatDuration(diffMs) {
        if (diffMs <= 0) {
            return 'المدة غير صحيحة: تاريخ النهاية يجب أن يكون بعد البداية';
        }

        const totalMinutes = Math.floor(diffMs / 60000);
        const days = Math.floor(totalMinutes / (60 * 24));
        const hours = Math.floor((totalMinutes % (60 * 24)) / 60);
        const minutes = totalMinutes % 60;

        const parts = [];

        if (days > 0) {
            parts.push(days + ' يوم');
        }

        if (hours > 0) {
            parts.push(hours + ' ساعة');
        }

        if (minutes > 0) {
            parts.push(minutes + ' دقيقة');
        }

        if (!parts.length) {
            parts.push('أقل من دقيقة');
        }

        return parts.join(' و ');
    }

    function syncDuration(startValue, endValue) {
        if (!durationBox) {
            return;
        }

        const start = parseLocalDateTime(startValue);
        const end = parseLocalDateTime(endValue);

        durationBox.className = 'a2-alert a2-alert-info a2-mt-12';

        if (!start || !end) {
            durationBox.textContent = 'المدة: —';
            return;
        }

        const diffMs = end.getTime() - start.getTime();

        if (diffMs <= 0) {
            durationBox.className = 'a2-alert a2-alert-danger a2-mt-12';
            durationBox.textContent = formatDuration(diffMs);
            return;
        }

        durationBox.textContent = 'المدة: ' + formatDuration(diffMs);
    }

    function sync() {
        const startValue = buildValue(startDate, startHour, startMinute);
        const endValue = buildValue(endDate, endHour, endMinute);

        startHidden.value = startValue;
        endHidden.value = endValue;

        syncDuration(startValue, endValue);
    }

    [
        startDate,
        startHour,
        startMinute,
        endDate,
        endHour,
        endMinute
    ].forEach(function (el) {
        el.addEventListener('change', sync);
        el.addEventListener('input', sync);
    });

    sync();
})();
</script>