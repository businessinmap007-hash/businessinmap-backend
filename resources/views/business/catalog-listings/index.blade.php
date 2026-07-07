@extends('business.layouts.master')

@section('title', 'منتجاتي')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">منتجاتي للبيع</h1>
        <div class="a2-page-subtitle">المنتجات اللي بتبيعها من كتالوج المنصة — بسعرك ومخزونك.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.products.create') }}" class="a2-btn a2-btn-primary">إضافة منتج</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <form method="GET" action="{{ route('business.products.index') }}" class="a2-filterbar">
        <div class="a2-filter-search">
            <label class="a2-label">بحث</label>
            <input class="a2-input" name="q" value="{{ $q }}" placeholder="اسم المنتج">
        </div>
        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">تصفية</button>
            <a href="{{ route('business.products.index') }}" class="a2-btn a2-btn-ghost">إعادة</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>الماركة</th>
                    <th>السعر</th>
                    <th>المخزون</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>
                            <div class="a2-fw-900">{{ $row->product_name ?: '—' }}</div>
                            <div class="a2-muted" dir="ltr">{{ $row->default_barcode ?: ($row->sku ?: '') }}</div>
                        </td>
                        <td>{{ $row->brand_name ?: '—' }}</td>
                        <td class="a2-fw-900">{{ number_format((float) $row->price, 2) }} {{ $row->currency }}</td>
                        <td>{{ (int) $row->stock }}</td>
                        <td>
                            @if($row->is_active)
                                <span class="a2-pill a2-pill-success">متاح</span>
                            @else
                                <span class="a2-pill a2-pill-gray">موقوف</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions">
                                <a href="{{ route('business.products.edit', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">تعديل</a>
                                <form method="POST" action="{{ route('business.products.destroy', $row->id) }}" onsubmit="return confirm('إزالة هذا المنتج من قائمتك؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="a2-empty">لا توجد منتجات بعد. اضغط «إضافة منتج» واختر من كتالوج المنصة.</td>
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
