@extends('business.layouts.master')

@section('title', __('شارك متجرك'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('شارك متجرك') }}</h1>
        <div class="a2-page-subtitle">{{ __('رمز QR ثابت لمتجرك — ضعه على ملصق أو بطاقة؛ مسحه يفتح صفحة متجرك.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('storefront.show', $businessId, false) }}" target="_blank" class="a2-btn a2-btn-ghost">{{ __('معاينة الصفحة') }}</a>
        <a href="{{ route('storefront.qr', $businessId, false) }}" target="_blank" class="a2-btn a2-btn-primary">{{ __('فتح الرمز للطباعة') }}</a>
    </div>
</div>

<div class="a2-card a2-card--section" style="text-align:center;">
    <img src="{{ route('storefront.qr', $businessId, false) }}" alt="{{ __('رمز المتجر') }}" width="240" height="240"
         style="border:1px solid var(--a2-line,#e6e9ef);border-radius:14px;background:#fff;">
    <div class="a2-card-sub" style="margin-top:10px;">{{ __('وجّه الكاميرا إلى الرمز لفتح صفحة المتجر.') }}</div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('رابط المتجر') }}</div>
            <div class="a2-card-sub">{{ __('انسخه وشاركه في السوشيال ميديا أو البطاقات.') }}</div>
        </div>
    </div>
    <div class="a2-form-grid" style="grid-template-columns:1fr auto;align-items:end;gap:12px;">
        <div class="a2-form-group">
            <input class="a2-input" id="store-link" dir="ltr" readonly value="{{ route('storefront.show', $businessId) }}">
        </div>
        <div class="a2-form-group">
            <button type="button" class="a2-btn a2-btn-primary" id="copy-link"
                    data-copy="{{ __('نسخ') }}" data-copied="{{ __('تم ✓') }}">{{ __('نسخ') }}</button>
        </div>
    </div>
</div>

<script>
    document.getElementById('copy-link').addEventListener('click', async function () {
        var input = document.getElementById('store-link');
        var b = this;
        try { await navigator.clipboard.writeText(input.value); b.textContent = b.dataset.copied; setTimeout(function(){ b.textContent = b.dataset.copy; }, 1600); }
        catch (e) { input.select(); }
    });
</script>
@endsection
