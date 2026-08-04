@extends('business.layouts.master')

@section('title', __('عروضي'))

@section('content')
@php
    $sourcePill = ['bespoke' => 'a2-pill-sub', 'menu' => 'a2-pill-warning', 'retail' => 'a2-pill-success'];
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('عروضي') }}</h1>
        <div class="a2-page-subtitle">{{ __('كل ما تبيعه في مكان واحد — خدمات، منيو، ومنتجات تجزئة.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.prices.create') }}" class="a2-btn a2-btn-ghost">{{ __('+ سعر خدمة') }}</a>
        <a href="{{ route('business.menu.create') }}" class="a2-btn a2-btn-ghost">{{ __('+ صنف منيو') }}</a>
        <a href="{{ route('business.products.create') }}" class="a2-btn a2-btn-primary">{{ __('+ منتج تجزئة') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-filterbar" style="gap:8px;flex-wrap:wrap;">
        <button type="button" class="a2-btn a2-btn-sm a2-btn-primary" data-src-filter="all">{{ __('الكل') }} ({{ $counts['all'] }})</button>
        @foreach($sources as $key => $label)
            <button type="button" class="a2-btn a2-btn-sm a2-btn-ghost" data-src-filter="{{ $key }}">
                {{ __($label) }} ({{ $counts[$key] ?? 0 }})
            </button>
        @endforeach
        <span class="a2-muted" style="margin-inline-start:auto;align-self:center;">{{ __('مفعّل') }}: {{ $counts['active'] }}</span>
    </div>
</div>

<form method="POST" action="{{ route('business.offerings.reorder', [], false) }}" id="offerings-form">
@csrf
<div class="a2-card">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('ترتيب العروض') }}</div>
            <div class="a2-card-sub">
                {{ __('اسحب الصف لتغيير ترتيبه، وعلّم «مميّز» لما يتصدّر. الترتيب يخص قائمتك أنت — لا يرفعك فوق نشاط آخر في نتائج البحث.') }}
            </div>
        </div>
        <div class="a2-page-actions">
            <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ الترتيب') }}</button>
        </div>
    </div>
    <div class="a2-table-wrap">
        <table class="a2-table" id="offerings-table">
            <thead>
                <tr>
                    <th style="width:36px;"></th>
                    <th style="width:70px;">{{ __('مميّز') }}</th>
                    <th>{{ __('المصدر') }}</th>
                    <th>{{ __('الاسم') }}</th>
                    <th>{{ __('التفاصيل') }}</th>
                    <th>{{ __('السعر') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th class="a2-text-right">{{ __('إجراء') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($offerings as $o)
                    <tr data-source="{{ $o['source'] }}" data-id="{{ $o['id'] }}" draggable="true" class="of-row">
                        <td class="of-handle" title="{{ __('اسحب لإعادة الترتيب') }}">⠿</td>
                        <td>
                            <input type="hidden" name="order[{{ $o['source'] }}][]" value="{{ $o['id'] }}">
                            <label class="a2-check">
                                <input type="checkbox" name="featured[{{ $o['source'] }}][]" value="{{ $o['id'] }}"
                                       @checked($o['is_featured'])>
                            </label>
                        </td>
                        <td><span class="a2-pill {{ $sourcePill[$o['source']] ?? 'a2-pill-gray' }}">{{ __($o['source_label']) }}</span></td>
                        <td>{{ $o['name'] }}</td>
                        <td class="a2-muted">{{ $o['detail'] }}</td>
                        <td class="a2-fw-900">{{ number_format($o['price'], 2) }} {{ $o['currency'] }}</td>
                        <td>
                            @if($o['is_active'])
                                <span class="a2-pill a2-pill-success">{{ __('مفعّل') }}</span>
                            @else
                                <span class="a2-pill a2-pill-gray">{{ __('موقوف') }}</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <a href="{{ $o['edit_url'] }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('تعديل') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="a2-empty">{{ __('لا توجد عروض بعد. أضف سعر خدمة، صنف منيو، أو منتج تجزئة.') }}</td></tr>
                @endforelse
                <tr id="offerings-empty-filter" style="display:none;"><td colspan="8" class="a2-empty">{{ __('لا عروض في هذا المصدر.') }}</td></tr>
            </tbody>
        </table>
    </div>
</div>
</form>
@endsection

@push('styles')
<style>
    .of-handle { cursor: grab; user-select: none; opacity: .5; font-size: 18px; text-align: center; }
    .of-row.is-dragging { opacity: .4; }
    .of-row.is-over { outline: 2px dashed currentColor; outline-offset: -2px; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var buttons = document.querySelectorAll('[data-src-filter]');
        var rows = document.querySelectorAll('#offerings-table tbody tr[data-source]');
        var emptyRow = document.getElementById('offerings-empty-filter');

        function apply(filter) {
            var shown = 0;
            rows.forEach(function (row) {
                var match = filter === 'all' || row.getAttribute('data-source') === filter;
                row.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            if (emptyRow) emptyRow.style.display = (rows.length && shown === 0) ? '' : 'none';
            buttons.forEach(function (b) {
                var active = b.getAttribute('data-src-filter') === filter;
                b.classList.toggle('a2-btn-primary', active);
                b.classList.toggle('a2-btn-ghost', !active);
            });
        }

        buttons.forEach(function (b) {
            b.addEventListener('click', function () { apply(b.getAttribute('data-src-filter')); });
        });
    })();

    /* Drag a row to say what leads the list. A row may only move among rows of
       its own source: the form posts one ordered list per source, and position
       in that list is the order. */
    (function () {
        var tbody = document.querySelector('#offerings-table tbody');
        if (!tbody) return;

        var dragged = null;

        tbody.addEventListener('dragstart', function (e) {
            var row = e.target.closest('.of-row');
            if (!row) return;
            dragged = row;
            row.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });

        tbody.addEventListener('dragend', function () {
            if (dragged) dragged.classList.remove('is-dragging');
            tbody.querySelectorAll('.is-over').forEach(function (r) { r.classList.remove('is-over'); });
            dragged = null;
        });

        tbody.addEventListener('dragover', function (e) {
            var row = e.target.closest('.of-row');
            if (!row || !dragged || row === dragged) return;
            if (row.dataset.source !== dragged.dataset.source) return;

            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            tbody.querySelectorAll('.is-over').forEach(function (r) { r.classList.remove('is-over'); });
            row.classList.add('is-over');
        });

        tbody.addEventListener('drop', function (e) {
            var row = e.target.closest('.of-row');
            if (!row || !dragged || row === dragged) return;
            if (row.dataset.source !== dragged.dataset.source) return;

            e.preventDefault();
            row.classList.remove('is-over');

            var rows = Array.prototype.slice.call(tbody.querySelectorAll('.of-row'));
            var before = rows.indexOf(dragged) < rows.indexOf(row);

            row.parentNode.insertBefore(dragged, before ? row.nextSibling : row);
        });
    })();
</script>
@endpush
