@extends('business.layouts.master')

@section('title', __('كشف الحساب'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('كشف الوارد والصادر والمكسب') }}</h1>
        <div class="a2-page-subtitle">
            {{ __('الوارد = ما دخل من كل مبيعاتك. الصادر = تكلفة البضاعة المباعة + رسوم المنصة المخصومة منك. المكسب = الوارد ناقص الصادر.') }}
        </div>
    </div>
</div>

@php
    $fmt = fn ($n) => number_format((float) $n, 2);
@endphp

@if(! $total)
    <div class="a2-card a2-card--soft" style="margin-top:16px;">
        <div class="a2-section-title">{{ __('لا توجد عمليات بعد') }}</div>
        <div class="a2-section-subtitle">
            {{ __('سيظهر هنا كشفك أولاً بأول بمجرد إتمام أول عملية بيع أو حجز — لا حاجة لتحديث الصفحة، كل عملية تُضاف فور وقوعها.') }}
        </div>
    </div>
@else
    <div class="a2-stat-grid" style="margin-top:16px;">
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('إجمالي الوارد') }}</div>
            <div class="a2-stat-value">{{ $fmt($total->revenue_total) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('تكلفة البضاعة') }}</div>
            <div class="a2-stat-value">{{ $fmt($total->cost_of_goods_total) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('رسوم المنصة') }}</div>
            <div class="a2-stat-value">{{ $fmt($total->platform_fees_total) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('صافي المكسب') }}</div>
            <div class="a2-stat-value">{{ $fmt($total->profitTotal()) }}</div>
        </div>
    </div>

    @if($bySource->isNotEmpty())
        <div class="a2-card a2-card--soft" style="margin-top:16px;">
            <div class="a2-section-title">{{ __('حسب الخدمة') }}</div>
            <div class="a2-table-wrap" style="margin-top:8px;overflow-x:auto;">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>{{ __('الخدمة') }}</th>
                            <th>{{ __('الوارد') }}</th>
                            <th>{{ __('تكلفة البضاعة') }}</th>
                            <th>{{ __('رسوم المنصة') }}</th>
                            <th>{{ __('صافي المكسب') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bySource as $source => $row)
                            <tr>
                                <td>{{ $labels[$source] ?? $source }}</td>
                                <td>{{ $fmt($row->revenue_total) }}</td>
                                <td>{{ $fmt($row->cost_of_goods_total) }}</td>
                                <td>{{ $fmt($row->platform_fees_total) }}</td>
                                <td>{{ $fmt($row->profitTotal()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
@endsection
