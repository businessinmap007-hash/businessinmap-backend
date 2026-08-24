@extends('business.layouts.master')

@section('title', __('إضافة منتج'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('إضافة منتج للبيع') }}</h1>
        <div class="a2-page-subtitle">{{ __('ابحث في كتالوج المنصة، اختر المنتج، وحدّد سعرك ومخزونك.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.products.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    </div>
</div>

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<form method="POST" action="{{ route('business.products.store') }}">
    @csrf
    <input type="hidden" name="catalog_product_id" id="bclProductId" value="{{ old('catalog_product_id') }}">

    <div class="a2-card a2-card--section">
        <div class="a2-card-head"><div class="a2-card-title">{{ __('١) اختر المنتج من الكتالوج') }}</div></div>
        <div style="position:relative;">
            <input class="a2-input" id="bclSearch" placeholder="{{ __('ابحث بالاسم أو الباركود…') }}" autocomplete="off">
        </div>
        <div id="bclChosen" style="display:none; margin-top:10px; padding:8px 12px; border:1px solid var(--a2-border,#e5e7eb); border-radius:8px;">
            <span class="a2-fw-900" id="bclChosenName"></span>
            <span class="a2-muted" id="bclChosenMeta"></span>
            <button type="button" id="bclClear" class="a2-btn a2-btn-sm a2-btn-ghost" style="margin-inline-start:8px;">{{ __('تغيير') }}</button>
        </div>
        <div id="bclResults" style="margin-top:10px; display:flex; flex-direction:column; gap:6px;"></div>
        <div class="a2-hint" id="bclHint" style="margin-top:8px;">{{ __('اكتب حرفين على الأقل للبحث.') }}</div>
    </div>

    <div class="a2-card a2-card--section" id="bclPricing" style="opacity:.5; pointer-events:none;">
        <div class="a2-card-head"><div class="a2-card-title">{{ __('٢) سعرك ومخزونك') }}</div></div>
        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label">{{ __('السعر') }}</label>
                <input class="a2-input" name="price" value="{{ old('price', 0) }}" inputmode="decimal" placeholder="0.00" required>
            </div>
            <div class="a2-form-group">
                <label class="a2-label">{{ __('المخزون') }}</label>
                <input class="a2-input" name="stock" value="{{ old('stock', 0) }}" inputmode="numeric" placeholder="0">
            </div>
            <div class="a2-form-group">
                <label class="a2-label">{{ __('SKU (اختياري)') }}</label>
                <input class="a2-input" name="sku" value="{{ old('sku') }}" dir="ltr" placeholder="{{ __('كود داخلي') }}">
            </div>
            <div class="a2-form-group">
                <label class="a2-label">{{ __('الحالة') }}</label>
                <label class="a2-check" style="margin-top:10px;"><input type="checkbox" name="is_active" value="1" checked> <span>{{ __('متاح للبيع') }}</span></label>
            </div>
        </div>
    </div>

    @php $row = null; @endphp
    @include('business.catalog-listings._visibility')

    <div class="a2-actions" style="margin-top:14px;">
        <button class="a2-btn a2-btn-primary" id="bclSubmit" disabled>{{ __('إضافة المنتج') }}</button>
        <a href="{{ route('business.products.index') }}" class="a2-btn a2-btn-ghost">{{ __('إلغاء') }}</a>
    </div>
</form>

@push('scripts')
<script>
(function () {
    const lookup = @json(route('business.products.lookup'));
    const i18n = {
        min: @json(__('اكتب حرفين على الأقل للبحث.')),
        searching: @json(__('جاري البحث…')),
        none: @json(__('لا توجد نتائج مطابقة (أو كلها مضافة بالفعل).')),
        error: @json(__('تعذر البحث، حاول مرة أخرى.')),
    };
    const search = document.getElementById('bclSearch');
    const results = document.getElementById('bclResults');
    const hint = document.getElementById('bclHint');
    const chosen = document.getElementById('bclChosen');
    const chosenName = document.getElementById('bclChosenName');
    const chosenMeta = document.getElementById('bclChosenMeta');
    const pidInput = document.getElementById('bclProductId');
    const pricing = document.getElementById('bclPricing');
    const submit = document.getElementById('bclSubmit');
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    let t;

    function choose(item) {
        pidInput.value = item.id;
        chosenName.textContent = item.name;
        chosenMeta.textContent = ' — ' + (item.brand || '') + (item.barcode ? ' · ' + item.barcode : '');
        chosen.style.display = 'block';
        results.innerHTML = '';
        search.style.display = 'none';
        hint.style.display = 'none';
        pricing.style.opacity = '1';
        pricing.style.pointerEvents = 'auto';
        submit.disabled = false;
    }

    document.getElementById('bclClear').addEventListener('click', function () {
        pidInput.value = '';
        chosen.style.display = 'none';
        search.style.display = 'block';
        search.value = '';
        search.focus();
        hint.style.display = 'block';
        pricing.style.opacity = '.5';
        pricing.style.pointerEvents = 'none';
        submit.disabled = true;
    });

    search.addEventListener('input', function () {
        clearTimeout(t);
        const q = search.value.trim();
        if (q.length < 2) { results.innerHTML = ''; hint.textContent = i18n.min; return; }
        hint.textContent = i18n.searching;
        t = setTimeout(async () => {
            try {
                const url = new URL(lookup, window.location.origin);
                url.searchParams.set('q', q);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const items = (data && data.items) || [];
                hint.textContent = items.length ? '' : i18n.none;
                results.innerHTML = items.map(i =>
                    '<button type="button" class="a2-btn a2-btn-ghost" style="justify-content:flex-start;text-align:start;" ' +
                    "data-i='" + esc(JSON.stringify(i)) + "'>" +
                    '<span class="a2-fw-900">' + esc(i.name) + '</span>' +
                    '<span class="a2-muted" style="margin-inline-start:8px;">' + esc(i.brand || '') + (i.barcode ? ' · ' + esc(i.barcode) : '') + '</span>' +
                    '</button>'
                ).join('');
            } catch (e) { hint.textContent = i18n.error; }
        }, 250);
    });

    results.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-i]');
        if (btn) choose(JSON.parse(btn.dataset.i));
    });
})();
</script>
@endpush
@endsection
