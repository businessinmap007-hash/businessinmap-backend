@extends('business.layouts.master')

@section('title', __('الخطط التدريبية'))

@php
    $statusLabels = [
        'active' => __('نشطة'),
        'paused' => __('متوقفة'),
        'completed' => __('منتهية'),
        'cancelled' => __('ملغاة'),
    ];
@endphp

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('الخطط التدريبية') }}</h1>
        <div class="a2-page-subtitle">{{ __('خطة التمرين والنظام الغذائي لكل عميل. لا يراها إلا أنت والعميل نفسه.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.training-plans.create') }}" class="a2-btn a2-btn-primary">{{ __('خطة جديدة') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--tight">
    <form method="GET" action="{{ route('business.training-plans.index') }}" class="a2-filterbar">
        <select class="a2-select a2-filter-sm" name="status">
            <option value="">{{ __('كل الحالات') }}</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
            <a class="a2-btn a2-btn-ghost" href="{{ route('business.training-plans.index') }}">{{ __('إعادة ضبط') }}</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('العميل') }}</th>
                    <th>{{ __('الخطة') }}</th>
                    <th>{{ __('الهدف') }}</th>
                    <th>{{ __('التمارين') }}</th>
                    <th>{{ __('الوجبات') }}</th>
                    <th>{{ __('تقارير القياس') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th class="a2-text-right">{{ __('إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>
                            {{ optional($row->client)->name ?: '—' }}
                            <div class="a2-muted">{{ optional($row->client)->phone }}</div>
                        </td>
                        <td>{{ $row->title }}</td>
                        <td>{{ $row->goal ?: '—' }}</td>
                        <td>{{ $row->exercises_count }}</td>
                        <td>{{ $row->meals_count }}</td>
                        <td>{{ $row->body_reports_count }}</td>
                        <td>
                            <span class="a2-pill {{ $row->status === 'active' ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                {{ $statusLabels[$row->status] ?? $row->status }}
                            </span>
                        </td>
                        <td class="a2-text-right">
                            <a href="{{ route('business.training-plans.show', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('فتح') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="a2-empty">{{ __('لا خطط بعد. ابدأ بخطة لعميل، ثم أضف التمارين والوجبات.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="a2-pagination">{{ $rows->links() }}</div>
</div>
@endsection
