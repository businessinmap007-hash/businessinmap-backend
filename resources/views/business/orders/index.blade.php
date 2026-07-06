@extends('business.layouts.master')

@section('title', 'طلبات المنيو')

@section('content')
@php
    $typeLabels = ['delivery' => 'توصيل', 'pickup' => 'استلام', 'dine_in' => 'في المكان'];
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">طلبات المنيو</h1>
        <div class="a2-page-subtitle">طلبات التوصيل والاستلام (غير المرتبطة بحجز) — تخصّك أنت فقط.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.orders.create') }}" class="a2-btn a2-btn-primary">طلب جديد</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <form method="GET" action="{{ route('business.orders.index') }}" class="a2-filterbar">
        <div class="a2-filter-sm">
            <label class="a2-label">النوع</label>
            <select class="a2-select" name="fulfillment_type">
                <option value="">الكل</option>
                <option value="delivery" @selected($type === 'delivery')>توصيل</option>
                <option value="pickup" @selected($type === 'pickup')>استلام</option>
            </select>
        </div>
        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">تصفية</button>
            <a href="{{ route('business.orders.index') }}" class="a2-btn a2-btn-ghost">إعادة</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>النوع</th>
                    <th>الأصناف</th>
                    <th>الإجمالي</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td><span class="a2-pill a2-pill-sub">{{ $typeLabels[$row->fulfillment_type] ?? $row->fulfillment_type }}</span></td>
                        <td>{{ (int) $row->items_count }}</td>
                        <td class="a2-fw-900">{{ number_format((float) $row->final_total, 2) }}</td>
                        <td>{{ $row->status }}</td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions">
                                <a href="{{ route('business.orders.show', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">عرض</a>
                                <form method="POST" action="{{ route('business.orders.destroy', $row->id) }}" onsubmit="return confirm('حذف الطلب؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="a2-empty">لا توجد طلبات بعد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($rows, 'links'))
        <div class="a2-pagination">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
