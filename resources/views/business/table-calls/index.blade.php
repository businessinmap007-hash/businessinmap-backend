@extends('business.layouts.master')

@section('title', __('نداءات الطاولات'))

@section('content')
{{-- Auto-refresh so staff see new calls without reloading. --}}
<meta http-equiv="refresh" content="20">

@php
    $typeClasses = ['waiter' => 'a2-pill-info', 'bill' => 'a2-pill-warning', 'assistance' => 'a2-pill-sub'];
    $typeLabels = [
        'waiter' => __('نداء الطاقم'),
        'bill' => __('طلب الحساب'),
        'assistance' => __('طلب مساعدة'),
    ];
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('نداءات الطاولات') }}</h1>
        <div class="a2-page-subtitle">{{ __('طلبات العملاء من الطاولات (نداء الطاقم / الحساب / مساعدة) — تُحدَّث تلقائيًا.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.table-calls.index') }}" class="a2-btn a2-btn-ghost">{{ __('تحديث الآن') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('الطاولة') }}</th>
                    <th>{{ __('النوع') }}</th>
                    <th>{{ __('ملاحظة') }}</th>
                    <th>{{ __('منذ') }}</th>
                    <th class="a2-text-right">{{ __('إجراء') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($calls as $call)
                    <tr>
                        <td class="a2-fw-900">{{ optional($call->table)->label ? __('طاولة :label', ['label' => $call->table->label]) : '—' }}</td>
                        <td><span class="a2-pill {{ $typeClasses[$call->type] ?? 'a2-pill-sub' }}">{{ $typeLabels[$call->type] ?? $call->labelAr() }}</span></td>
                        <td>{{ $call->note ?: '—' }}</td>
                        <td>{{ optional($call->created_at)->diffForHumans() }}</td>
                        <td class="a2-text-right">
                            <form method="POST" action="{{ route('business.table-calls.resolve', $call->id) }}">
                                @csrf
                                <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">{{ __('تم التنفيذ') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="a2-empty">{{ __('لا توجد نداءات مفتوحة الآن.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
