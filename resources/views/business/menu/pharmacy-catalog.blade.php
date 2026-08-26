@extends('business.layouts.master')

@section('title', __('قاموس الأدوية'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('قاموس الأدوية') }}</h1>
        <div class="a2-page-subtitle">
            {{ __('ابحث عن الدواء وأضِفه بسعرك — قاموس الأدوية 25,065 دواءً، فلا يظهر إلا ما تبحث عنه.') }}
        </div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">{{ __('الأصناف') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="a2-card a2-mb-16">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('بحث') }}</div>
            <div class="a2-card-sub">{{ __('اكتب اسم الدواء أو المادة الفعالة') }}</div>
        </div>
    </div>

    <div style="padding:0 16px 16px;">
        <input class="a2-input" id="pharmacy-search-input" placeholder="{{ __('مثال: AUGMENTIN') }}" autocomplete="off">
        <div id="pharmacy-search-results" style="margin-top:12px;"></div>
    </div>
</div>

<div class="a2-card">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('أدويتك المضافة') }}</div>
            <div class="a2-card-sub">{{ __('عدّل السعر أو الحالة من شاشة «الأصناف» العادية.') }}</div>
        </div>
    </div>

    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('الدواء') }}</th>
                    <th>{{ __('الشركة المنتجة') }}</th>
                    <th>{{ __('السعر') }}</th>
                    <th>{{ __('الكمية') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th class="a2-text-right">{{ __('إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="a2-fw-900">{{ $item->name_en ?: $item->name_ar }}</td>
                        <td>{{ $item->brand_name ?: '—' }}</td>
                        <td>{{ number_format((float) $item->base_price, 2) }}</td>
                        <td>{{ $item->available_quantity !== null ? (int) $item->available_quantity : '—' }}</td>
                        <td>
                            @if($item->is_active)
                                <span class="a2-pill a2-pill-success">{{ __('متاح') }}</span>
                            @else
                                <span class="a2-pill a2-pill-gray">{{ __('غير متاح') }}</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <a href="{{ route('business.menu.edit', $item->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('تعديل') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="a2-empty">{{ __('لم تُضِف أى دواء بعد — ابحث عنه أعلاه.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<template id="pharmacy-result-row-tpl">
    <div class="a2-card a2-card--soft" style="padding:12px;margin-bottom:8px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <div class="a2-fw-900 result-name"></div>
                <div class="a2-muted result-meta"></div>
            </div>
            <form method="POST" action="{{ route('business.menu.pharmacy.store') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                @csrf
                <input type="hidden" name="medicine_id" class="result-medicine-id">
                <input class="a2-input" style="width:100px;" type="number" step="0.01" min="0" name="base_price" placeholder="{{ __('السعر') }}" required>
                <input class="a2-input" style="width:90px;" type="number" min="0" name="quantity" placeholder="{{ __('الكمية') }}">
                <button type="submit" class="a2-btn a2-btn-sm a2-btn-primary">{{ __('إضافة') }}</button>
            </form>
        </div>
    </div>
</template>

<script>
(function () {
    var input = document.getElementById('pharmacy-search-input');
    var results = document.getElementById('pharmacy-search-results');
    var tpl = document.getElementById('pharmacy-result-row-tpl');
    var searchUrl = @json(route('business.menu.pharmacy.search', [], false));
    var timer = null;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var term = input.value.trim();

        if (term.length < 2) {
            results.innerHTML = '';
            return;
        }

        timer = setTimeout(function () { runSearch(term); }, 300);
    });

    function runSearch(term) {
        fetch(searchUrl + '?q=' + encodeURIComponent(term), {
            headers: { 'Accept': 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (payload) { render(payload.data || []); })
            .catch(function () { results.innerHTML = '<div class="a2-muted">{{ __('تعذّر البحث الآن.') }}</div>'; });
    }

    function render(rows) {
        results.innerHTML = '';

        if (!rows.length) {
            results.innerHTML = '<div class="a2-muted">{{ __('لا نتائج.') }}</div>';
            return;
        }

        rows.forEach(function (row) {
            var node = tpl.content.cloneNode(true);
            var name = row.name + (row.manufacturer ? ' — ' + row.manufacturer : '');
            node.querySelector('.result-name').textContent = name;

            var meta = [];
            if (row.price_egp !== null) meta.push('{{ __('سعر إرشادى') }}: ' + row.price_egp);
            if (row.already_added) meta.push('{{ __('مُضاف بالفعل بسعر') }} ' + row.current_price);
            node.querySelector('.result-meta').textContent = meta.join(' · ');

            node.querySelector('.result-medicine-id').value = row.id;

            var priceInput = node.querySelector('input[name="base_price"]');
            if (row.price_egp !== null) priceInput.value = row.price_egp;
            if (row.already_added) priceInput.value = row.current_price;

            results.appendChild(node);
        });
    }
})();
</script>
@endsection
