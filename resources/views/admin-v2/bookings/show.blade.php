@extends('admin-v2.layouts.master')

@section('title', 'Booking Details')

@section('content')
@php
    $pricing = is_array($booking->meta['pricing'] ?? null) ? $booking->meta['pricing'] : [];
    $executionFee = is_array($booking->meta['_execution_fee'] ?? null) ? $booking->meta['_execution_fee'] : [];
    $platformService = is_array($booking->meta['platform_service'] ?? null) ? $booking->meta['platform_service'] : [];
    $bookableMeta = is_array($booking->meta['bookable_item'] ?? null) ? $booking->meta['bookable_item'] : [];

    $originalPrice = (float)($pricing['original_price'] ?? $booking->price ?? 0);
    $discountEnabled = (bool)($pricing['discount_enabled'] ?? false);
    $discountPercent = (int)($pricing['discount_percent'] ?? 0);
    $discountAmount = (float)($pricing['discount_amount'] ?? 0);
    $finalPrice = (float)($pricing['final_price'] ?? $booking->price ?? 0);
    $platformFee = (float)($pricing['platform_fee'] ?? 0);

    $depositAmount = (float)($depositPolicy['amount'] ?? $depositPolicy['hold'] ?? 0);
    $remaining = max($finalPrice - $depositAmount, 0);

    $depositStatus = $deposit->status ?? null;
@endphp

<div class="a2-page-head bk-head">
    <div>
        <h1 class="a2-page-title" style="margin:0;">تفاصيل الحجز #{{ $booking->id }}</h1>
        <div class="a2-page-subtitle" style="margin-top:6px;">
            عرض كامل لبيانات الحجز والأسعار والديبوزت ورسوم التنفيذ
        </div>
    </div>

    <div class="bk-head-actions">
        <a href="{{ route('admin.bookings.edit', $booking) }}" class="a2-btn a2-btn-primary">تعديل</a>
        <a href="{{ route('admin.bookings.index') }}" class="a2-btn">رجوع</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success" style="margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="a2-alert a2-alert-danger" style="margin-bottom:16px;">
        {{ session('error') }}
    </div>
@endif

<div class="a2-card bk-actions-card">
    <div class="a2-title" style="font-size:17px;margin-bottom:12px;">إجراءات الحجز</div>

    <div class="bk-action-grid">
        <form method="POST" action="{{ route('admin.bookings.start_confirm.client', $booking) }}">
            @csrf
            <button type="submit" class="a2-btn a2-btn-primary bk-action-btn">تأكيد العميل</button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.start_confirm.business', $booking) }}">
            @csrf
            <button type="submit" class="a2-btn a2-btn-primary bk-action-btn">تأكيد البزنس</button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.deposit.freeze', $booking) }}">
            @csrf
            <button type="submit" class="a2-btn bk-action-btn" @disabled(!($depositPolicy['required'] ?? false) || $deposit)>
                Freeze Deposit
            </button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.deposit.release', $booking) }}">
            @csrf
            <button type="submit" class="a2-btn bk-action-btn" @disabled(!$deposit || !in_array($depositStatus, ['frozen', 'dispute']))>
                Release
            </button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.deposit.refund', $booking) }}">
            @csrf
            <button type="submit" class="a2-btn bk-action-btn" @disabled(!$deposit || !in_array($depositStatus, ['frozen', 'dispute']))>
                Refund
            </button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.deposit.dispute.open', $booking) }}">
            @csrf
            <button type="submit" class="a2-btn a2-btn-danger bk-action-btn" @disabled(!$deposit || $depositStatus === 'dispute')>
                Open Dispute
            </button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.deposit.agree.release', $booking) }}">
            @csrf
            <button type="submit" class="a2-btn bk-action-btn" @disabled(!$deposit)>
                Agree Release
            </button>
        </form>

        <form method="POST" action="{{ route('admin.bookings.deposit.agree.refund', $booking) }}">
            @csrf
            <button type="submit" class="a2-btn bk-action-btn" @disabled(!$deposit)>
                Agree Refund
            </button>
        </form>
    </div>
</div>

<div class="bk-show-grid">
    <div class="a2-card bk-show-card">
        <div class="a2-title bk-card-title">البيانات الأساسية</div>
        <div class="bk-kv-grid">
            <div class="bk-kv"><span>العميل</span><strong>{{ $booking->user->name ?? '-' }}</strong></div>
            <div class="bk-kv"><span>البزنس</span><strong>{{ $booking->business->name ?? '-' }}</strong></div>
            <div class="bk-kv"><span>الخدمة</span><strong>{{ $booking->service->name_ar ?? $booking->service->name_en ?? '-' }}</strong></div>
            <div class="bk-kv"><span>حالة الحجز</span><strong>{{ \App\Models\Booking::statusOptions()[$booking->status] ?? $booking->status }}</strong></div>
            <div class="bk-kv"><span>التاريخ</span><strong>{{ optional($booking->date)->format('Y-m-d') ?: '-' }}</strong></div>
            <div class="bk-kv"><span>الوقت</span><strong>{{ $booking->time ?: '-' }}</strong></div>
            <div class="bk-kv"><span>starts_at</span><strong>{{ optional($booking->starts_at)->format('Y-m-d H:i') ?: '-' }}</strong></div>
            <div class="bk-kv"><span>ends_at</span><strong>{{ optional($booking->ends_at)->format('Y-m-d H:i') ?: '-' }}</strong></div>
            <div class="bk-kv"><span>الكمية</span><strong>{{ $booking->quantity ?? 1 }}</strong></div>
            <div class="bk-kv"><span>عدد الأفراد</span><strong>{{ $booking->party_size ?: '-' }}</strong></div>
            <div class="bk-kv"><span>Timezone</span><strong>{{ $booking->timezone ?: '-' }}</strong></div>
            <div class="bk-kv"><span>ملاحظات</span><strong>{{ $booking->notes ?: '-' }}</strong></div>
        </div>
    </div>

    <div class="a2-card bk-show-card">
        <div class="a2-title bk-card-title">التسعير</div>
        <div class="bk-kv-grid">
            <div class="bk-kv"><span>السعر الأصلي</span><strong>{{ number_format($originalPrice, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>الخصم مفعل</span><strong>{{ $discountEnabled ? 'نعم' : 'لا' }}</strong></div>
            <div class="bk-kv"><span>نسبة الخصم</span><strong>{{ $discountPercent }}%</strong></div>
            <div class="bk-kv"><span>قيمة الخصم</span><strong>{{ number_format($discountAmount, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>السعر بعد الخصم</span><strong>{{ number_format($finalPrice, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>رسوم المنصة</span><strong>{{ number_format($platformFee, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>مصدر السعر</span><strong>{{ $pricing['source'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>نوع رسوم المنصة</span><strong>{{ $pricing['fee_type'] ?? '-' }}</strong></div>
        </div>
    </div>

    <div class="a2-card bk-show-card">
        <div class="a2-title bk-card-title">الديبوزت</div>
        <div class="bk-kv-grid">
            <div class="bk-kv"><span>Deposit مطلوب؟</span><strong>{{ !empty($depositPolicy['required']) ? 'نعم' : 'لا' }}</strong></div>
            <div class="bk-kv"><span>النسبة المطبقة</span><strong>{{ (int)($depositPolicy['configured_percent'] ?? 0) }}%</strong></div>
            <div class="bk-kv"><span>قيمة الديبوزت</span><strong>{{ number_format($depositAmount, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>المتبقي بعد الديبوزت</span><strong>{{ number_format($remaining, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>المصدر</span><strong>{{ $depositPolicy['source'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>الحد الأقصى المسموح</span><strong>{{ number_format((float)($depositPolicy['max'] ?? 0), 2) }} EGP</strong></div>
        </div>
    </div>

    <div class="a2-card bk-show-card">
        <div class="a2-title bk-card-title">التأكيدات ورسوم التنفيذ</div>
        <div class="bk-kv-grid">
            <div class="bk-kv"><span>تأكيد العميل</span><strong>{{ $clientConfirmed ? 'تم' : 'لم يتم' }}</strong></div>
            <div class="bk-kv"><span>تأكيد البزنس</span><strong>{{ $businessConfirmed ? 'تم' : 'لم يتم' }}</strong></div>
            <div class="bk-kv"><span>رسوم التنفيذ - العميل</span><strong>{{ number_format((float)($executionFee['client_amount'] ?? 0), 2) }} EGP</strong></div>
            <div class="bk-kv"><span>رسوم التنفيذ - البزنس</span><strong>{{ number_format((float)($executionFee['business_amount'] ?? 0), 2) }} EGP</strong></div>
            <div class="bk-kv"><span>تاريخ الخصم</span><strong>{{ $executionFee['charged_at'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>عدد الحركات</span><strong>{{ count($executionFee['transactions'] ?? []) }}</strong></div>
        </div>
    </div>
</div>

@if(!empty($platformService))
    <div class="a2-card bk-wide-card">
        <div class="a2-title bk-card-title">الخدمة على المنصة</div>
        <div class="bk-kv-grid bk-kv-grid-4">
            <div class="bk-kv"><span>رقم الخدمة</span><strong>{{ $platformService['id'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>Key</span><strong>{{ $platformService['key'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>الاسم العربي</span><strong>{{ $platformService['name_ar'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>الاسم الإنجليزي</span><strong>{{ $platformService['name_en'] ?? '-' }}</strong></div>
        </div>
    </div>
@endif

@if(!empty($bookableMeta))
    <div class="a2-card bk-wide-card">
        <div class="a2-title bk-card-title">العنصر القابل للحجز</div>
        <div class="bk-kv-grid bk-kv-grid-4">
            <div class="bk-kv"><span>ID</span><strong>{{ $bookableMeta['id'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>العنوان</span><strong>{{ $bookableMeta['title'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>الكود</span><strong>{{ $bookableMeta['code'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>النوع</span><strong>{{ $bookableMeta['item_type'] ?? '-' }}</strong></div>
            <div class="bk-kv"><span>السعر</span><strong>{{ number_format((float)($bookableMeta['price'] ?? 0), 2) }} EGP</strong></div>
            <div class="bk-kv"><span>Deposit Enabled</span><strong>{{ !empty($bookableMeta['deposit_enabled']) ? 'نعم' : 'لا' }}</strong></div>
            <div class="bk-kv"><span>Deposit %</span><strong>{{ (int)($bookableMeta['deposit_percent'] ?? 0) }}%</strong></div>
        </div>
    </div>
@endif

@if($deposit)
    <div class="a2-card bk-wide-card">
        <div class="a2-title bk-card-title">سجل Deposit</div>
        <div class="bk-kv-grid bk-kv-grid-4">
            <div class="bk-kv"><span>الحالة</span><strong>{{ $deposit->status }}</strong></div>
            <div class="bk-kv"><span>إجمالي القيمة</span><strong>{{ number_format((float)$deposit->total_amount, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>قيمة العميل</span><strong>{{ number_format((float)$deposit->client_amount, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>قيمة البزنس</span><strong>{{ number_format((float)$deposit->business_amount, 2) }} EGP</strong></div>
            <div class="bk-kv"><span>تأكيد العميل</span><strong>{{ (int)$deposit->client_confirmed === 1 ? 'نعم' : 'لا' }}</strong></div>
            <div class="bk-kv"><span>تأكيد البزنس</span><strong>{{ (int)$deposit->business_confirmed === 1 ? 'نعم' : 'لا' }}</strong></div>
            <div class="bk-kv"><span>Agree Release Client</span><strong>{{ (int)($deposit->release_agreed_client ?? 0) === 1 ? 'نعم' : 'لا' }}</strong></div>
            <div class="bk-kv"><span>Agree Release Business</span><strong>{{ (int)($deposit->release_agreed_business ?? 0) === 1 ? 'نعم' : 'لا' }}</strong></div>
            <div class="bk-kv"><span>Agree Refund Client</span><strong>{{ (int)($deposit->refund_agreed_client ?? 0) === 1 ? 'نعم' : 'لا' }}</strong></div>
            <div class="bk-kv"><span>Agree Refund Business</span><strong>{{ (int)($deposit->refund_agreed_business ?? 0) === 1 ? 'نعم' : 'لا' }}</strong></div>
        </div>
    </div>
@endif

<style>
.bk-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:16px;
}
.bk-head-actions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
}
.bk-actions-card{
    padding:18px;
    margin-bottom:16px;
}
.bk-action-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}
.bk-action-grid form{
    margin:0;
}
.bk-action-btn{
    width:100%;
    justify-content:center;
}

.bk-show-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:16px;
}
.bk-show-card{
    padding:18px;
    min-width:0;
}
.bk-wide-card{
    padding:18px;
    margin-top:16px;
}
.bk-card-title{
    font-size:17px;
    margin-bottom:14px;
}
.bk-kv-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
}
.bk-kv-grid-4{
    grid-template-columns:repeat(4,minmax(0,1fr));
}
.bk-kv{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:12px 14px;
    min-width:0;
}
.bk-kv span{
    display:block;
    font-size:12px;
    color:#6b7280;
    margin-bottom:6px;
}
.bk-kv strong{
    display:block;
    font-size:15px;
    font-weight:800;
    line-height:1.5;
    word-break:break-word;
}

@media (max-width: 1200px){
    .bk-action-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
    .bk-kv-grid-4{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}
@media (max-width: 900px){
    .bk-show-grid{
        grid-template-columns:1fr;
    }
}
@media (max-width: 700px){
    .bk-action-grid,
    .bk-kv-grid,
    .bk-kv-grid-4{
        grid-template-columns:1fr;
    }
}
</style>
@endsection