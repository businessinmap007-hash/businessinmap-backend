@extends('business.layouts.master')

@section('title', 'تعديل منتج')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تعديل منتج</h1>
        <div class="a2-page-subtitle">{{ $product?->displayName() ?? ('#' . $row->catalog_product_id) }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.products.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<form method="POST" action="{{ route('business.products.update', $row->id) }}">
    @csrf
    @method('PUT')

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ $product?->displayName() ?? 'المنتج' }}</div>
                <div class="a2-card-sub" dir="ltr">{{ $product?->default_barcode ?: '' }}</div>
            </div>
        </div>
        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label">السعر</label>
                <input class="a2-input" name="price" value="{{ old('price', $row->price) }}" inputmode="decimal" required>
            </div>
            <div class="a2-form-group">
                <label class="a2-label">المخزون</label>
                <input class="a2-input" name="stock" value="{{ old('stock', $row->stock) }}" inputmode="numeric">
            </div>
            <div class="a2-form-group">
                <label class="a2-label">SKU (اختياري)</label>
                <input class="a2-input" name="sku" value="{{ old('sku', $row->sku) }}" dir="ltr">
            </div>
            <div class="a2-form-group">
                <label class="a2-label">الحالة</label>
                <label class="a2-check" style="margin-top:10px;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', (int) $row->is_active))> <span>متاح للبيع</span></label>
            </div>
        </div>
    </div>

    <div class="a2-actions" style="margin-top:14px;">
        <button class="a2-btn a2-btn-primary">حفظ</button>
        <a href="{{ route('business.products.index') }}" class="a2-btn a2-btn-ghost">إلغاء</a>
    </div>
</form>
@endsection
