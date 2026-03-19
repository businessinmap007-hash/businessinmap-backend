@extends('admin-v2.layouts.master')

@section('title','Business Service Price Details')
@section('body_class','admin-v2-business-service-prices-show')

@section('content')
@php
    $discountAmount = ((int)$row->discount_enabled === 1)
        ? round(((float)$row->price * (int)$row->discount_percent) / 100, 2)
        : 0;

    $finalServicePrice = ((int)$row->discount_enabled === 1)
        ? round((float)$row->price - $discountAmount, 2)
        : round((float)$row->price, 2);

    $depositHoldAmount = ((int)$row->deposit_enabled === 1)
        ? round(($finalServicePrice * (int)$row->deposit_percent) / 100, 2)
        : 0;

    $cashDueOnExecution = $finalServicePrice;
@endphp

<div class="a2-page a2-page-narrow">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تفاصيل سعر الخدمة</h1>
            <div class="a2-page-subtitle">
                عرض تفصيلي للسعر حسب البزنس والخدمة ونوع العنصر
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.business_service_prices.edit', $row->id) }}" class="a2-btn a2-btn-primary">تعديل</a>
            <a href="{{ $backUrl }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-form-grid">
            <div><strong>Business:</strong> {{ $row->business->name ?? '—' }}</div>
            <div><strong>Service:</strong> {{ $row->service->name_ar ?? ($row->service->name_en ?? ($row->service->key ?? '—')) }}</div>
            <div><strong>Item Type:</strong> <span dir="ltr">{{ $row->bookable_item_type ?: 'category' }}</span></div>
            <div><strong>Status:</strong> {{ (int)$row->is_active === 1 ? 'Active' : 'Inactive' }}</div>
            <div><strong>Currency:</strong> {{ $row->currency ?: 'EGP' }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">تفصيل السعر</div>
            </div>
        </div>

        <div class="a2-form-grid">
        <div><strong>Base Price:</strong> {{ number_format((float)$row->price, 2) }}</div>
        <div><strong>Discount Enabled:</strong> {{ (int)$row->discount_enabled === 1 ? 'Yes' : 'No' }}</div>
        <div><strong>Discount Percent:</strong> {{ (int)$row->discount_percent }}%</div>
        <div><strong>Discount Amount:</strong> {{ number_format((float)$discountAmount, 2) }}</div>
        <div><strong>Final Service Price:</strong> {{ number_format((float)$finalServicePrice, 2) }}</div>
        <div><strong>Deposit Enabled:</strong> {{ (int)$row->deposit_enabled === 1 ? 'Yes' : 'No' }}</div>
        <div><strong>Deposit Percent:</strong> {{ (int)$row->deposit_percent }}%</div>
        <div><strong>Deposit Hold Amount:</strong> {{ number_format((float)$depositHoldAmount, 2) }}</div>
        <div><strong>Cash Due On Execution:</strong> {{ number_format((float)$cashDueOnExecution, 2) }}</div>
    </div>

    <div class="a2-alert a2-alert-warning" style="margin-top:14px;">
        الديبوزت هنا مبلغ حجز/ضمان مستقل، ولا يتم خصمه من السعر النهائي للخدمة.
    </div>
    </div>
</div>
@endsection