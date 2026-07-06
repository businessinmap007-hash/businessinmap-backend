@extends('business.layouts.master')

@section('title', 'حجوزاتي')

@section('content')
@php
    $serviceName = function ($s) {
        return $s ? ($s->name_ar ?: ($s->name_en ?: $s->key)) : '—';
    };
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">حجوزاتي</h1>
        <div class="a2-page-subtitle">حجوزات نشاطك — تخصّك أنت فقط.</div>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <form method="GET" action="{{ route('business.bookings.index') }}" class="a2-filterbar">
        <div class="a2-filter-sm">
            <label class="a2-label">الحالة</label>
            <input class="a2-input" name="status" value="{{ $status }}" placeholder="pending / accepted ...">
        </div>
        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">تصفية</button>
            <a href="{{ route('business.bookings.index') }}" class="a2-btn a2-btn-ghost">إعادة</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>التاريخ</th>
                    <th>الخدمة</th>
                    <th>الوحدة</th>
                    <th>الإجمالي</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $unitCode = $row->bookable?->code ?: data_get($row->bookableMeta(), 'code', '—');
                        $unitType = $row->bookable?->item_type ?: data_get($row->bookableMeta(), 'item_type', '');
                    @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>
                            <div>{{ $row->date ?: optional($row->starts_at)->format('Y-m-d') ?: '—' }}</div>
                            <div class="a2-muted">{{ $row->time }}</div>
                        </td>
                        <td>{{ $serviceName($row->service) }}</td>
                        <td>
                            <div dir="ltr">{{ $unitCode }}</div>
                            <div class="a2-muted" dir="ltr">{{ $unitType }}</div>
                        </td>
                        <td class="a2-fw-900">{{ number_format((float) $row->price, 2) }}</td>
                        <td><span class="a2-pill a2-pill-sub">{{ $row->status }}</span></td>
                        <td class="a2-text-right">
                            <a href="{{ route('business.bookings.show', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">عرض</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="a2-empty">لا توجد حجوزات بعد.</td>
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
