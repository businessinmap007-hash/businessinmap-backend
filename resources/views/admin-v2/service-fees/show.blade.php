@extends('admin-v2.layouts.master')

@section('title', 'تفاصيل رسوم الخدمة')
@section('body_class','admin-v2-service-fees-show')

@section('content')
<div class="a2-page">

    {{-- Header --}}
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تفاصيل إعداد الرسوم</h1>
            <div class="a2-page-subtitle">
                عرض الرسوم للبزنس والعميل
            </div>
        </div>

        <div class="a2-page-actions" style="display:flex;gap:8px;">
            <a href="{{ route('admin.service-fees.edit', $groupKey) }}" class="a2-btn a2-btn-primary">
                تعديل
            </a>
            <a href="{{ route('admin.service-fees.index') }}" class="a2-btn">
                رجوع
            </a>
        </div>
    </div>

    {{-- Context Card --}}
    <div class="a2-card" style="padding:16px;margin-bottom:16px;">
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;">

            <div>
                <div class="a2-muted">البزنس</div>
                <div style="font-weight:800;">
                    {{ $business?->name ?? 'Global' }}
                </div>
            </div>

            <div>
                <div class="a2-muted">القسم الفرعي</div>
                <div style="font-weight:800;">
                    {{ $child?->name_ar ?: $child?->name_en ?: 'Global' }}
                </div>
            </div>

            <div>
                <div class="a2-muted">الخدمة</div>
                <div style="font-weight:800;">
                    {{ $service?->name_ar ?: $service?->name_en ?: 'All Services' }}
                </div>
            </div>

            <div>
                <div class="a2-muted">كود الرسم</div>
                <div style="font-weight:800;">
                    {{ $feeCode }}
                </div>
            </div>

        </div>
    </div>

    {{-- Fees Cards --}}
    <div class="a2-service-fee-form-grid">

        {{-- Business --}}
        <div class="a2-card a2-sf-section-card">
            <div class="a2-header">
                <h3 class="a2-sf-card-title">رسوم البزنس</h3>
            </div>

            @if($businessFee)
                <div class="a2-grid" style="gap:10px;">
                    <div><b>Type:</b> {{ $businessFee->fee_type }}</div>
                    <div><b>Calc:</b> {{ $businessFee->calc_type }}</div>
                    <div><b>Amount:</b> {{ $businessFee->amount }}</div>
                    <div><b>Min:</b> {{ $businessFee->min_amount }}</div>
                    <div><b>Max:</b> {{ $businessFee->max_amount }}</div>
                    <div><b>Priority:</b> {{ $businessFee->priority }}</div>
                    <div><b>Status:</b> {{ $businessFee->is_active ? 'Active' : 'Inactive' }}</div>
                </div>
            @else
                <div class="a2-muted">لا توجد بيانات</div>
            @endif
        </div>

        {{-- Client --}}
        <div class="a2-card a2-sf-section-card">
            <div class="a2-header">
                <h3 class="a2-sf-card-title">رسوم العميل</h3>
            </div>

            @if($clientFee)
                <div class="a2-grid" style="gap:10px;">
                    <div><b>Type:</b> {{ $clientFee->fee_type }}</div>
                    <div><b>Calc:</b> {{ $clientFee->calc_type }}</div>
                    <div><b>Amount:</b> {{ $clientFee->amount }}</div>
                    <div><b>Min:</b> {{ $clientFee->min_amount }}</div>
                    <div><b>Max:</b> {{ $clientFee->max_amount }}</div>
                    <div><b>Priority:</b> {{ $clientFee->priority }}</div>
                    <div><b>Status:</b> {{ $clientFee->is_active ? 'Active' : 'Inactive' }}</div>
                </div>
            @else
                <div class="a2-muted">لا توجد بيانات</div>
            @endif
        </div>

    </div>

</div>
@endsection