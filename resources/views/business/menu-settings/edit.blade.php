@extends('business.layouts.master')

@section('title', 'إعدادات المنيو')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">إعدادات المنيو</h1>
        <div class="a2-page-subtitle">هل أسعار أصنافك شاملة رسوم الخدمة والضريبة؟</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">المنيو</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('business.menu-settings.update') }}">
    @csrf
    @method('PUT')

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">شمول السعر</div>
                <div class="a2-card-sub">إذا كان السعر شاملاً لن يُضاف فوقه؛ وإلا يُضاف على الطلب.</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-check">
                    <input type="checkbox" name="prices_include_service" value="1" @checked((bool) old('prices_include_service', $row->prices_include_service))>
                    <span>الأسعار شاملة رسوم الخدمة (لا تُضاف فوق السعر)</span>
                </label>
            </div>

            <div class="a2-form-group a2-field-full">
                <label class="a2-check">
                    <input type="checkbox" name="prices_include_tax" value="1" @checked((bool) old('prices_include_tax', $row->prices_include_tax))>
                    <span>الأسعار شاملة الضريبة (لا تُضاف فوق السعر)</span>
                </label>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">نسبة الضريبة</div>
                <div class="a2-card-sub">اترك الحقل فارغاً لاستخدام النسبة الافتراضية للمنصة ({{ rtrim(rtrim(number_format($defaultTaxRate, 2), '0'), '.') }}%).</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="tax_rate_percent">نسبة الضريبة على المنيو (%)</label>
                <input type="number" step="0.01" min="0" max="100" id="tax_rate_percent" name="tax_rate_percent"
                    class="a2-input @error('tax_rate_percent') a2-input-error @enderror"
                    value="{{ old('tax_rate_percent', $row->tax_rate_percent) }}"
                    placeholder="{{ rtrim(rtrim(number_format($defaultTaxRate, 2), '0'), '.') }}">
                @error('tax_rate_percent')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
    </div>
</form>
@endsection
