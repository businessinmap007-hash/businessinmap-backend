@extends('business.layouts.master')

@section('title', __('خطة تدريبية جديدة'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('خطة تدريبية جديدة') }}</h1>
        <div class="a2-page-subtitle">{{ __('ابدأ بالعميل وعنوان الخطة، ثم أضف تمارين البرنامج كلها ومدته وزيادة الأوزان دفعة واحدة. الوجبات تُضاف بعد الحفظ.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.training-plans.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        <ul style="margin:0;padding-inline-start:18px;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('business.training-plans.store') }}" class="a2-card">
    @csrf

    <div class="a2-field">
        <label class="a2-label">{{ __('العميل') }}</label>
        <div style="display:flex;gap:8px;align-items:center;">
            <input class="a2-input" id="clientTerm" type="text" placeholder="{{ __('رقم الهاتف أو البريد') }}" autocomplete="off">
            <button class="a2-btn a2-btn-ghost" type="button" id="clientFind">{{ __('بحث') }}</button>
        </div>
        <div class="a2-help" id="clientResult">{{ __('العميل لا بد أن يكون مسجّلًا في التطبيق.') }}</div>
        <input type="hidden" name="client_id" id="clientId" value="{{ old('client_id') }}">
    </div>

    <div class="a2-field">
        <label class="a2-label">{{ __('عنوان الخطة') }}</label>
        <input class="a2-input" name="title" value="{{ old('title') }}" maxlength="200" required>
    </div>

    <div class="a2-field">
        <label class="a2-label">{{ __('الهدف') }}</label>
        <input class="a2-input" name="goal" value="{{ old('goal') }}" maxlength="200" placeholder="{{ __('مثال: خسارة دهون مع الحفاظ على الكتلة العضلية') }}">
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="a2-field">
            <label class="a2-label">{{ __('تبدأ في') }}</label>
            <input class="a2-input" type="date" name="starts_on" value="{{ old('starts_on') }}">
        </div>
        <div class="a2-field">
            <label class="a2-label">{{ __('تنتهي في') }}</label>
            <input class="a2-input" type="date" name="ends_on" value="{{ old('ends_on') }}">
        </div>
    </div>

    {{-- ── البرنامج: المدة وزيادة الأوزان ───────────────────────────── --}}
    <div class="a2-field" style="border-top:1px solid var(--a2-border,#eee);padding-top:12px;">
        <label class="a2-label" style="font-weight:800;">{{ __('مدة البرنامج وزيادة الأوزان') }}</label>
        <div class="a2-help">{{ __('حدّد عدد الأسابيع وقاعدة الزيادة مرة واحدة، فتُحسب أوزان كل أسبوع تلقائياً. مثال: 20-25-30 لأول أسبوعين، ثم +5 كجم كل أسبوعين.') }}</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:8px;">
            <div>
                <label class="a2-label">{{ __('عدد الأسابيع') }}</label>
                <input class="a2-input" type="number" name="duration_weeks" min="1" max="52" value="{{ old('duration_weeks') }}" placeholder="8">
            </div>
            <div>
                <label class="a2-label">{{ __('زيادة الوزن كل (أسبوع)') }}</label>
                <input class="a2-input" type="number" name="progression[every_weeks]" min="1" max="26" value="{{ old('progression.every_weeks') }}" placeholder="2">
            </div>
            <div>
                <label class="a2-label">{{ __('مقدار الزيادة (كجم)') }}</label>
                <input class="a2-input" type="number" name="progression[increment_kg]" min="0" max="100" step="0.25" value="{{ old('progression.increment_kg') }}" placeholder="5">
            </div>
        </div>
    </div>

    {{-- ── تمارين البرنامج ────────────────────────────────────────── --}}
    <div class="a2-field">
        <label class="a2-label" style="font-weight:800;">{{ __('التمارين') }}</label>
        <div class="a2-help">{{ __('أضف كل تمارين الأسبوع (Push / Pull / Legs مثلاً بأيامها). الأوزان لكل مجموعة تُكتب هكذا: 20-25-30. اترك الوزن فارغاً لتمارين وزن الجسم.') }}</div>
        <div class="a2-table-wrap" style="margin-top:8px;">
            <table class="a2-table" id="exerciseRows">
                <thead>
                    <tr>
                        <th>{{ __('اليوم') }}</th>
                        <th>{{ __('اسم اليوم') }}</th>
                        <th>{{ __('التمرين') }}</th>
                        <th style="width:70px;">{{ __('مجموعات') }}</th>
                        <th style="width:80px;">{{ __('عدّات') }}</th>
                        <th>{{ __('الأوزان (20-25-30)') }}</th>
                        <th style="width:110px;"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <button type="button" class="a2-btn a2-btn-ghost" id="addRow" style="margin-top:8px;">{{ __('+ إضافة تمرين') }}</button>
    </div>

    <template id="rowTemplate">
        <tr>
            <td>
                <select class="a2-select" name="exercises[__i__][day_of_week]">
                    <option value="">—</option>
                    @foreach([0 => __('الأحد'), 1 => __('الاثنين'), 2 => __('الثلاثاء'), 3 => __('الأربعاء'), 4 => __('الخميس'), 5 => __('الجمعة'), 6 => __('السبت')] as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </td>
            <td><input class="a2-input" name="exercises[__i__][day_label]" maxlength="40" placeholder="Push"></td>
            <td><input class="a2-input" name="exercises[__i__][name]" maxlength="200" placeholder="{{ __('مثال: Bench press') }}"></td>
            <td><input class="a2-input" type="number" name="exercises[__i__][sets]" min="0" placeholder="3"></td>
            <td><input class="a2-input" name="exercises[__i__][reps]" maxlength="40" placeholder="8-10"></td>
            <td><input class="a2-input" name="exercises[__i__][weights_text]" maxlength="80" placeholder="20-25-30"></td>
            <td>
                <button type="button" class="a2-btn a2-btn-sm a2-btn-ghost" data-act="dup" title="{{ __('نسخ الصف') }}">⧉</button>
                <button type="button" class="a2-btn a2-btn-sm a2-btn-danger" data-act="del" title="{{ __('حذف') }}">×</button>
            </td>
        </tr>
    </template>

    <div class="a2-field">
        <label class="a2-label">{{ __('ملاحظات') }}</label>
        <textarea class="a2-input" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
    </div>

    <div class="a2-form-actions">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ الخطة') }}</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    // Row builder: every exercise of the programme is one row, posted together.
    var body = document.querySelector('#exerciseRows tbody');
    var tpl = document.getElementById('rowTemplate');
    var counter = 0;

    function addRow(copyFrom) {
        var html = tpl.innerHTML.replace(/__i__/g, counter++);
        var holder = document.createElement('tbody');
        holder.innerHTML = html;
        var row = holder.firstElementChild;

        if (copyFrom) {
            // Duplicate: same day / label / sets / reps / weights, name left to retype.
            ['day_of_week', 'day_label', 'sets', 'reps', 'weights_text'].forEach(function (f) {
                var from = copyFrom.querySelector('[name$="[' + f + ']"]');
                var to = row.querySelector('[name$="[' + f + ']"]');
                if (from && to) { to.value = from.value; }
            });
        }

        body.appendChild(row);
        return row;
    }

    document.getElementById('addRow').addEventListener('click', function () { addRow(null); });
    body.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-act]');
        if (!btn) { return; }
        var row = btn.closest('tr');
        if (btn.dataset.act === 'del') { row.remove(); }
        if (btn.dataset.act === 'dup') { row.insertAdjacentElement('afterend', addRow(row)); }
    });

    addRow(null);
    addRow(null);
})();

(function () {
    var term = document.getElementById('clientTerm');
    var result = document.getElementById('clientResult');
    var hidden = document.getElementById('clientId');

    document.getElementById('clientFind').addEventListener('click', function () {
        var q = (term.value || '').trim();
        if (!q) { return; }

        // Relative URL on purpose: an absolute route() would carry APP_URL's
        // host and be refused as cross-origin when the panel is opened on any
        // other hostname — the save then fails silently.
        fetch('{{ route('business.training-plans.lookup', [], false) }}?q=' + encodeURIComponent(q), {
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.found) {
                    hidden.value = data.id;
                    result.textContent = '{{ __('العميل') }}: ' + data.name + ' — ' + (data.phone || '');
                } else {
                    hidden.value = '';
                    result.textContent = '{{ __('لا يوجد عميل بهذا الرقم أو البريد.') }}';
                }
            })
            .catch(function () {
                result.textContent = '{{ __('تعذّر البحث. حاول مرة أخرى.') }}';
            });
    });
})();
</script>
@endpush
