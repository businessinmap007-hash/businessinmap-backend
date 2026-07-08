@extends('business.layouts.master')

@section('title', 'عروضي')

@section('content')
@php
    $sourcePill = ['bespoke' => 'a2-pill-sub', 'menu' => 'a2-pill-warning', 'retail' => 'a2-pill-success'];
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">عروضي</h1>
        <div class="a2-page-subtitle">كل ما تبيعه في مكان واحد — خدمات، منيو، ومنتجات تجزئة.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.prices.create') }}" class="a2-btn a2-btn-ghost">+ سعر خدمة</a>
        <a href="{{ route('business.menu.create') }}" class="a2-btn a2-btn-ghost">+ صنف منيو</a>
        <a href="{{ route('business.products.create') }}" class="a2-btn a2-btn-primary">+ منتج تجزئة</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <div class="a2-filterbar" style="gap:8px;flex-wrap:wrap;">
        <button type="button" class="a2-btn a2-btn-sm a2-btn-primary" data-src-filter="all">الكل ({{ $counts['all'] }})</button>
        @foreach($sources as $key => $label)
            <button type="button" class="a2-btn a2-btn-sm a2-btn-ghost" data-src-filter="{{ $key }}">
                {{ $label }} ({{ $counts[$key] ?? 0 }})
            </button>
        @endforeach
        <span class="a2-muted" style="margin-inline-start:auto;align-self:center;">مفعّل: {{ $counts['active'] }}</span>
    </div>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table" id="offerings-table">
            <thead>
                <tr>
                    <th>المصدر</th>
                    <th>الاسم</th>
                    <th>التفاصيل</th>
                    <th>السعر</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراء</th>
                </tr>
            </thead>
            <tbody>
                @forelse($offerings as $o)
                    <tr data-source="{{ $o['source'] }}">
                        <td><span class="a2-pill {{ $sourcePill[$o['source']] ?? 'a2-pill-gray' }}">{{ $o['source_label'] }}</span></td>
                        <td>{{ $o['name'] }}</td>
                        <td class="a2-muted">{{ $o['detail'] }}</td>
                        <td class="a2-fw-900">{{ number_format($o['price'], 2) }} {{ $o['currency'] }}</td>
                        <td>
                            @if($o['is_active'])
                                <span class="a2-pill a2-pill-success">مفعّل</span>
                            @else
                                <span class="a2-pill a2-pill-gray">موقوف</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <a href="{{ $o['edit_url'] }}" class="a2-btn a2-btn-sm a2-btn-ghost">تعديل</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="a2-empty">لا توجد عروض بعد. أضف سعر خدمة، صنف منيو، أو منتج تجزئة.</td></tr>
                @endforelse
                <tr id="offerings-empty-filter" style="display:none;"><td colspan="6" class="a2-empty">لا عروض في هذا المصدر.</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

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
</script>
@endpush
