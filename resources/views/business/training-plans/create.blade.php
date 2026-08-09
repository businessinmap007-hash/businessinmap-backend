@extends('business.layouts.master')

@section('title', __('خطة تدريبية جديدة'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('خطة تدريبية جديدة') }}</h1>
        <div class="a2-page-subtitle">{{ __('ابدأ بالعميل وعنوان الخطة. التمارين والوجبات تُضاف بعد الحفظ.') }}</div>
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
