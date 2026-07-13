@extends('business.layouts.master')

@section('title', 'شارك متجرك')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">شارك متجرك</h1>
        <div class="a2-page-subtitle">رمز QR ثابت لمتجرك — ضعه على ملصق أو بطاقة؛ مسحه يفتح صفحة متجرك.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('storefront.show', $businessId, false) }}" target="_blank" class="a2-btn a2-btn-ghost">معاينة الصفحة</a>
        <a href="{{ route('storefront.qr', $businessId, false) }}" target="_blank" class="a2-btn a2-btn-primary">فتح الرمز للطباعة</a>
    </div>
</div>

<div class="a2-card a2-card--section" style="text-align:center;">
    <img src="{{ route('storefront.qr', $businessId, false) }}" alt="رمز المتجر" width="240" height="240"
         style="border:1px solid var(--a2-line,#e6e9ef);border-radius:14px;background:#fff;">
    <div class="a2-card-sub" style="margin-top:10px;">وجّه الكاميرا إلى الرمز لفتح صفحة المتجر.</div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">رابط المتجر</div>
            <div class="a2-card-sub">انسخه وشاركه في السوشيال ميديا أو البطاقات.</div>
        </div>
    </div>
    <div class="a2-form-grid" style="grid-template-columns:1fr auto;align-items:end;gap:12px;">
        <div class="a2-form-group">
            <input class="a2-input" id="store-link" dir="ltr" readonly value="{{ route('storefront.show', $businessId) }}">
        </div>
        <div class="a2-form-group">
            <button type="button" class="a2-btn a2-btn-primary" id="copy-link">نسخ</button>
        </div>
    </div>
</div>

<script>
    document.getElementById('copy-link').addEventListener('click', async function () {
        var input = document.getElementById('store-link');
        try { await navigator.clipboard.writeText(input.value); this.textContent = 'تم ✓'; var b = this; setTimeout(function(){ b.textContent = 'نسخ'; }, 1600); }
        catch (e) { input.select(); }
    });
</script>
@endsection
