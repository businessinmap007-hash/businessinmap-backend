@extends('business.layouts.master')

@section('title', __('حاسبة الوزن المناسب'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('حاسبة الوزن المناسب') }}</h1>
        <div class="a2-page-subtitle">{{ __('اكتب الجنس والعمر والطول والوزن (والخصر والرقبة للدهون) لتظهر النتيجة. الحساب في متصفحك ولا يُرسل أي بيان.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.training-plans.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع للخطط') }}</a>
    </div>
</div>

<div class="a2-card" id="calc" style="max-width:760px">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
        <div class="a2-field"><label class="a2-label" for="c-sex">{{ __('الجنس') }}</label>
            <select class="a2-input" id="c-sex"><option value="male">{{ __('ذكر') }}</option><option value="female">{{ __('أنثى') }}</option></select></div>
        <div class="a2-field"><label class="a2-label" for="c-age">{{ __('العمر (سنة)') }}</label><input class="a2-input" id="c-age" type="number" min="5" max="110"></div>
        <div class="a2-field"><label class="a2-label" for="c-height">{{ __('الطول (سم)') }}</label><input class="a2-input" id="c-height" type="number" step="0.1" min="100" max="250"></div>
        <div class="a2-field"><label class="a2-label" for="c-weight">{{ __('الوزن (كجم)') }}</label><input class="a2-input" id="c-weight" type="number" step="0.1" min="20" max="300"></div>
        <div class="a2-field"><label class="a2-label" for="c-activity">{{ __('مستوى النشاط') }}</label>
            <select class="a2-input" id="c-activity">
                <option value="1.2">{{ __('قليل الحركة') }}</option><option value="1.375" selected>{{ __('خفيف (1–3 أيام)') }}</option>
                <option value="1.55">{{ __('متوسط (3–5 أيام)') }}</option><option value="1.725">{{ __('عالٍ (6–7 أيام)') }}</option><option value="1.9">{{ __('شديد') }}</option>
            </select></div>
        <div class="a2-field"><label class="a2-label" for="c-goal">{{ __('الهدف') }}</label>
            <select class="a2-input" id="c-goal"><option value="lose">{{ __('إنقاص الوزن') }}</option><option value="maintain" selected>{{ __('تثبيت الوزن') }}</option><option value="gain">{{ __('زيادة الوزن') }}</option></select></div>
        <div class="a2-field"><label class="a2-label" for="c-waist">{{ __('الخصر (سم) — اختياري') }}</label><input class="a2-input" id="c-waist" type="number" step="0.1"></div>
        <div class="a2-field"><label class="a2-label" for="c-neck">{{ __('الرقبة (سم) — اختياري') }}</label><input class="a2-input" id="c-neck" type="number" step="0.1"></div>
        <div class="a2-field"><label class="a2-label" for="c-hip">{{ __('الأرداف (سم) — للمرأة') }}</label><input class="a2-input" id="c-hip" type="number" step="0.1"></div>
    </div>

    <div id="c-result" style="margin-top:16px"></div>
    <div class="a2-help" style="margin-top:12px">{{ __('تقدير تقريبي لا يغني عن رأي الطبيب أو أخصائي التغذية.') }}</div>
</div>

<script>
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var T = {
        under: @json(__('نحافة')), normal: @json(__('وزن طبيعي')), over: @json(__('وزن زائد')), obese: @json(__('سمنة')),
        minor: @json(__('جداول البالغين لا تنطبق تحت 18 سنة. اسأل الطبيب عن الوزن المناسب للعمر.')),
        bmi: @json(__('مؤشر كتلة الجسم')), range: @json(__('الوزن الصحي لطوله')), ideal: @json(__('الوزن المثالي التقريبي')),
        inRange: @json(__('الوزن داخل المدى الصحي.')), lose: @json(__('للوصول للمدى الصحي ينقص نحو')), gain: @json(__('للوصول للمدى الصحي يزيد نحو')),
        fat: @json(__('نسبة الدهون')), lean: @json(__('الكتلة الخالية من الدهون')), wht: @json(__('الخصر ÷ الطول')), whr: @json(__('الخصر ÷ الأرداف')),
        bmr: @json(__('حرق الراحة (سعر/يوم)')), keep: @json(__('سعرات تثبيت الوزن')), target: @json(__('سعرات الهدف')),
        protein: @json(__('البروتين (جرام/يوم)')), water: @json(__('الماء (لتر/يوم)')), kg: @json(__('كجم'))
    };
    var log10 = function (v) { return Math.log(v) / Math.LN10; };
    var f1 = function (v) { return (Math.round(v * 10) / 10).toString(); };

    function calc() {
        var male = $('c-sex').value === 'male', age = +$('c-age').value, h = +$('c-height').value, w = +$('c-weight').value;
        var out = $('c-result');
        if (!(age >= 5 && age <= 110 && h >= 100 && h <= 250 && w >= 20 && w <= 300)) { out.innerHTML = ''; return; }

        var m = h / 100, bmi = w / (m * m), act = +$('c-activity').value, goal = $('c-goal').value;
        var band = bmi < 18.5 ? T.under : bmi < 25 ? T.normal : bmi < 30 ? T.over : T.obese;
        var lo = 18.5 * m * m, hi = 24.9 * m * m;
        var inches = Math.max(h / 2.54 - 60, 0), devine = (male ? 50 : 45.5) + 2.3 * inches;
        var bmr = 10 * w + 6.25 * h - 5 * age + (male ? 5 : -161), keep = bmr * act;
        var target = goal === 'lose' ? Math.max(keep - 500, bmr) : goal === 'gain' ? keep + 300 : keep;
        var p = goal === 'lose' ? [1.8, 2.2] : goal === 'gain' ? [1.6, 2.0] : [1.2, 1.6];

        var waist = +$('c-waist').value, neck = +$('c-neck').value, hip = +$('c-hip').value, fat = null;
        if (waist > 0 && neck > 0) {
            if (male && waist > neck) { fat = 495 / (1.0324 - 0.19077 * log10(waist - neck) + 0.15456 * log10(h)) - 450; }
            if (!male && hip > 0 && waist + hip > neck) { fat = 495 / (1.29579 - 0.35004 * log10(waist + hip - neck) + 0.22100 * log10(h)) - 450; }
            if (fat !== null && !(fat > 2 && fat < 60)) { fat = null; }
        }

        var rows = [[T.bmi, f1(bmi) + (age >= 18 ? ' — ' + band : '')]];
        if (age < 18) { rows.push(['', T.minor]); }
        else {
            rows.push([T.range, f1(lo) + ' – ' + f1(hi) + ' ' + T.kg]);
            rows.push([T.ideal, f1(devine) + ' ' + T.kg]);
            rows.push(['', w > hi ? T.lose + ' ' + f1(w - hi) + ' ' + T.kg : w < lo ? T.gain + ' ' + f1(lo - w) + ' ' + T.kg : T.inRange]);
        }
        if (fat !== null) { rows.push([T.fat, f1(fat) + '%']); rows.push([T.lean, f1(w * (1 - fat / 100)) + ' ' + T.kg]); }
        if (waist > 0) { rows.push([T.wht, (waist / h).toFixed(2)]); }
        if (waist > 0 && hip > 0) { rows.push([T.whr, (waist / hip).toFixed(2)]); }
        rows.push([T.bmr, Math.round(bmr)]); rows.push([T.keep, Math.round(keep)]); rows.push([T.target, Math.round(target)]);
        rows.push([T.protein, Math.round(w * p[0]) + ' – ' + Math.round(w * p[1])]); rows.push([T.water, f1(w * 0.035)]);

        out.innerHTML = '<table class="a2-table" style="width:100%"><tbody>' + rows.map(function (r) {
            var cell = document.createElement('td'); cell.textContent = r[1];
            var label = document.createElement('td'); label.textContent = r[0]; label.style.fontWeight = '800';
            return '<tr>' + label.outerHTML + cell.outerHTML + '</tr>';
        }).join('') + '</tbody></table>';
    }

    ['c-sex', 'c-age', 'c-height', 'c-weight', 'c-activity', 'c-goal', 'c-waist', 'c-neck', 'c-hip'].forEach(function (id) {
        $(id).addEventListener('input', calc); $(id).addEventListener('change', calc);
    });
})();
</script>
@endsection
