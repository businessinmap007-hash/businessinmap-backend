@extends('business.layouts.master')

@section('title', 'طلب جديد')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">طلب منيو جديد</h1>
        <div class="a2-page-subtitle">توصيل أو استلام. بعد الإنشاء تضيف الأصناف.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.orders.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

@if($errors->any())
    <div class="a2-alert a2-alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<form method="POST" action="{{ route('business.orders.store') }}">
    @csrf
    <div class="a2-card a2-card--section">
        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label" for="fulfillment_type">نوع التنفيذ <span class="a2-danger">*</span></label>
                <select class="a2-select js-order-type" id="fulfillment_type" name="fulfillment_type" required>
                    <option value="delivery" @selected(old('fulfillment_type','delivery')==='delivery')>توصيل</option>
                    <option value="pickup" @selected(old('fulfillment_type')==='pickup')>استلام عند المرور</option>
                </select>
            </div>

            <div class="a2-form-group js-order-delivery">
                <label class="a2-label" for="delivery_fee">رسوم التوصيل</label>
                <input class="a2-input" id="delivery_fee" name="delivery_fee" value="{{ old('delivery_fee', 0) }}" inputmode="decimal" placeholder="0.00">
            </div>

            <div class="a2-form-group a2-field-full js-order-delivery">
                <label class="a2-label" for="address">عنوان التوصيل</label>
                <input class="a2-input" id="address" name="address" value="{{ old('address') }}" placeholder="العنوان بالتفصيل">
            </div>

            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="notes">ملاحظات</label>
                <textarea class="a2-textarea" id="notes" name="notes" placeholder="ملاحظات على الطلب">{{ old('notes') }}</textarea>
            </div>
        </div>
    </div>

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <a href="{{ route('business.orders.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
        <button type="submit" class="a2-btn a2-btn-primary">إنشاء</button>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const type = document.querySelector('.js-order-type');
    const deliveryFields = document.querySelectorAll('.js-order-delivery');
    function refresh() {
        const isDelivery = type.value === 'delivery';
        deliveryFields.forEach(function (el) { el.style.display = isDelivery ? '' : 'none'; });
    }
    type.addEventListener('change', refresh);
    refresh();
});
</script>
@endpush
@endsection
