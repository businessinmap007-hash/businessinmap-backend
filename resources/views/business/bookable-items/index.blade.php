@extends('business.layouts.master')

@section('title', __('وحداتي'))

@section('content')
@php
    $displayName = function ($s) {
        return $s ? ($s->name_ar ?: ($s->name_en ?: $s->key)) : '—';
    };
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('وحداتي القابلة للحجز') }}</h1>
        <div class="a2-page-subtitle">{{ __('الوحدات الفعلية التي تعرضها للحجز — تخصّك أنت فقط.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.bookable-items.create') }}" class="a2-btn a2-btn-primary">{{ __('إضافة وحدة') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($childId <= 0)
    <div class="a2-alert a2-alert-warning">
        {{ __('حسابك غير مرتبط بقسم فرعي بعد، لذا لا يمكن تحديد الأنواع المتاحة. تواصل مع إدارة التطبيق.') }}
    </div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <form method="GET" action="{{ route('business.bookable-items.index') }}" class="a2-filterbar">
        <div class="a2-filter-search">
            <label class="a2-label">{{ __('بحث') }}</label>
            <input class="a2-input" name="q" value="{{ $q }}" placeholder="{{ __('كود / اسم / نوع') }}">
        </div>

        <div class="a2-filter-md">
            <label class="a2-label">{{ __('الخدمة') }}</label>
            <select class="a2-select" name="service_id">
                <option value="0">{{ __('كل الخدمات') }}</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected((int) $serviceId === (int) $service->id)>
                        {{ $displayName($service) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">{{ __('تصفية') }}</button>
            <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('إعادة') }}</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('الكود') }}</th>
                    <th>{{ __('الاسم') }}</th>
                    <th>{{ __('الخدمة') }}</th>
                    <th>{{ __('النوع') }}</th>
                    <th>{{ __('نوع الوحدة') }}</th>
                    <th>{{ __('السعة') }}</th>
                    <th>{{ __('الكمية') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th class="a2-text-right">{{ __('إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td dir="ltr">{{ $row->code }}</td>
                        <td>{{ $row->title ?: '—' }}</td>
                        <td>{{ $displayName($row->service) }}</td>
                        <td dir="ltr">{{ $row->item_type }}</td>
                        <td>{{ optional($row->lineOption)->name_ar ?: '—' }}</td>
                        <td>{{ $row->capacity ?: '—' }}</td>
                        <td>{{ (int) $row->quantity }}</td>
                        <td>
                            @if($row->is_active)
                                <span class="a2-pill a2-pill-success">{{ __('مفعّلة') }}</span>
                            @else
                                <span class="a2-pill a2-pill-gray">{{ __('موقوفة') }}</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions">
                                <a href="{{ route('business.bookable-items.edit', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('تعديل') }}</a>
                                <form method="POST" action="{{ route('business.bookable-items.destroy', $row->id) }}" onsubmit="return confirm('{{ __('حذف هذه الوحدة؟') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="a2-empty">{{ __('لا توجد وحدات بعد. ابدأ بإضافة وحداتك.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($rows, 'links'))
        <div class="a2-pagination">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
