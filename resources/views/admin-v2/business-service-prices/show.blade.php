@extends('admin-v2.layouts.master')

@section('title', 'Booking Details')
@section('body_class', 'admin-v2 admin-v2-booking-show')

@section('content')
@php
    $meta = is_array($booking->meta ?? null) ? $booking->meta : [];

    $pricing = is_array($meta['pricing'] ?? null) ? $meta['pricing'] : [];
    $executionFee = is_array($meta['_execution_fee'] ?? null) ? $meta['_execution_fee'] : [];
    $serviceFeesSnapshot = is_array($meta['service_fees_snapshot'] ?? null) ? $meta['service_fees_snapshot'] : [];

    $executionSnapshot = is_array($executionFee['snapshot'] ?? null) ? $executionFee['snapshot'] : [];

    $businessFeeSnapshot = $serviceFeesSnapshot['business'] ?? ($executionSnapshot['business'] ?? null);
    $clientFeeSnapshot = $serviceFeesSnapshot['client'] ?? ($executionSnapshot['client'] ?? null);

    $businessFeeSnapshot = is_array($businessFeeSnapshot) ? $businessFeeSnapshot : null;
    $clientFeeSnapshot = is_array($clientFeeSnapshot) ? $clientFeeSnapshot : null;

    $platformService = is_array($meta['platform_service'] ?? null) ? $meta['platform_service'] : [];
    $businessContext = is_array($meta['business_context'] ?? null) ? $meta['business_context'] : [];
    $bookableMeta = is_array($meta['bookable_item'] ?? null) ? $meta['bookable_item'] : [];

    $originalPrice = (float)($pricing['original_price'] ?? $booking->price ?? 0);
    $discountEnabled = (bool)($pricing['discount_enabled'] ?? false);
    $discountPercent = (int)($pricing['discount_percent'] ?? 0);
    $discountAmount = (float)($pricing['discount_amount'] ?? 0);
    $finalPrice = (float)($pricing['final_price'] ?? $booking->price ?? 0);

    $currency = (string)($pricing['currency'] ?? 'EGP');

    $legacyPlatformFee = (float)($pricing['platform_fee'] ?? 0);

    $depositAmount = (float)($depositPolicy['amount'] ?? $depositPolicy['hold'] ?? 0);
    $remaining = max($finalPrice - $depositAmount, 0);

    $depositStatus = $deposit->status ?? null;

    $executionTransactions = is_array($executionFee['transactions'] ?? null)
        ? $executionFee['transactions']
        : [];

    $clientExecutionAmount = (float)($executionFee['client_amount'] ?? 0);
    $businessExecutionAmount = (float)($executionFee['business_amount'] ?? 0);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تفاصيل الحجز #') }}{{ $booking->id }}</h1>
            <div class="a2-page-subtitle">
                {{ __('عرض بيانات الحجز والسعر والديبوزت ورسوم التنفيذ Wallet Fees.') }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookings.edit', $booking) }}" class="a2-btn a2-btn-primary">
                {{ __('تعديل') }}
            </a>

            <a href="{{ route('admin.bookings.index') }}" class="a2-btn a2-btn-ghost">
                {{ __('رجوع') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">{{ __('يوجد أخطاء:') }}</div>
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card bk-actions-card">
        <div class="a2-section-title">{{ __('إجراءات الحجز') }}</div>
        <div class="a2-section-subtitle">
            {{ __('قبل بدء التنفيذ يجب تأكيد العميل والبزنس. وعند انتقال الحجز إلى') }}
            <span dir="ltr">in_progress</span>
            {{ __('يتم خصم رسوم التنفيذ مرة واحدة فقط.') }}
        </div>

        <div class="bk-action-grid">
            <form method="POST" action="{{ route('admin.bookings.start_confirm.client', $booking) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-primary bk-action-btn">
                    {{ __('تأكيد العميل') }}
                </button>
            </form>

            <form method="POST" action="{{ route('admin.bookings.start_confirm.business', $booking) }}">
                @csrf
                <button type="submit" class="a2-btn a2-btn-primary bk-action-btn">
                    {{ __('تأكيد البزنس') }}
                </button>
            </form>

            <form method="POST" action="{{ route('admin.bookings.deposit.freeze', $booking) }}">
                @csrf
                <button type="submit" class="a2-btn bk-action-btn" @disabled(!($depositPolicy['required'] ?? false) || $deposit)>
                    Freeze Deposit
                </button>
            </form>

            <form method="POST" action="{{ route('admin.bookings.deposit.release', $booking) }}">
                @csrf
                <button type="submit" class="a2-btn bk-action-btn" @disabled(!$deposit || !in_array($depositStatus, ['frozen', 'dispute'], true))>
                    Release
                </button>
            </form>

            <form method="POST" action="{{ route('admin.bookings.deposit.refund', $booking) }}">
                @csrf
                <button type="submit" class="a2-btn bk-action-btn" @disabled(!$deposit || !in_array($depositStatus, ['frozen', 'dispute'], true))>
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
            <div class="a2-section-title">{{ __('البيانات الأساسية') }}</div>

            <div class="bk-kv-grid">
                <div class="bk-kv"><span>{{ __('العميل') }}</span><strong>{{ $booking->user->name ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('البزنس') }}</span><strong>{{ $booking->business->name ?? '-' }}</strong></div>

                <div class="bk-kv">
                    <span>{{ __('الخدمة') }}</span>
                    <strong>{{ $booking->service->name_ar ?? $booking->service->name_en ?? $booking->service->key ?? '-' }}</strong>
                </div>

                <div class="bk-kv">
                    <span>{{ __('حالة الحجز') }}</span>
                    <strong>{{ \App\Models\Booking::statusOptions()[$booking->status] ?? $booking->status }}</strong>
                </div>

                <div class="bk-kv"><span>Category ID</span><strong>{{ $businessContext['category_id'] ?? ($booking->business->category_id ?? '-') }}</strong></div>
                <div class="bk-kv"><span>Category Child ID</span><strong>{{ $businessContext['category_child_id'] ?? ($booking->business->category_child_id ?? '-') }}</strong></div>

                <div class="bk-kv"><span>{{ __('التاريخ') }}</span><strong>{{ optional($booking->date)->format('Y-m-d') ?: '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('الوقت') }}</span><strong>{{ $booking->time ?: '-' }}</strong></div>

                <div class="bk-kv"><span>starts_at</span><strong>{{ optional($booking->starts_at)->format('Y-m-d H:i') ?: '-' }}</strong></div>
                <div class="bk-kv"><span>ends_at</span><strong>{{ optional($booking->ends_at)->format('Y-m-d H:i') ?: '-' }}</strong></div>

                <div class="bk-kv"><span>{{ __('الكمية') }}</span><strong>{{ $booking->quantity ?? 1 }}</strong></div>
                <div class="bk-kv"><span>{{ __('عدد الأفراد') }}</span><strong>{{ $booking->party_size ?: '-' }}</strong></div>

                <div class="bk-kv"><span>Timezone</span><strong>{{ $booking->timezone ?: '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('ملاحظات') }}</span><strong>{{ $booking->notes ?: '-' }}</strong></div>
            </div>
        </div>

        <div class="a2-card bk-show-card">
            <div class="a2-section-title">{{ __('التسعير') }}</div>
            <div class="a2-section-subtitle">
                {{ __('هذا هو سعر الخدمة الذي يحدده البزنس، وليس رسوم المنصة الجديدة.') }}
            </div>

            <div class="bk-kv-grid">
                <div class="bk-kv"><span>{{ __('السعر الأصلي') }}</span><strong>{{ number_format($originalPrice, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('الخصم مفعل') }}</span><strong>{{ $discountEnabled ? 'نعم' : 'لا' }}</strong></div>
                <div class="bk-kv"><span>{{ __('نسبة الخصم') }}</span><strong>{{ $discountPercent }}%</strong></div>
                <div class="bk-kv"><span>{{ __('قيمة الخصم') }}</span><strong>{{ number_format($discountAmount, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('السعر بعد الخصم') }}</span><strong>{{ number_format($finalPrice, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('مصدر السعر') }}</span><strong>{{ $pricing['source'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>Business Price ID</span><strong>{{ $pricing['business_service_price_id'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>Legacy Platform Fee</span><strong>{{ number_format($legacyPlatformFee, 2) }} {{ $currency }}</strong></div>
            </div>
        </div>

        <div class="a2-card bk-show-card">
            <div class="a2-section-title">{{ __('الديبوزت') }}</div>

            <div class="bk-kv-grid">
                <div class="bk-kv"><span>{{ __('Deposit مطلوب؟') }}</span><strong>{{ !empty($depositPolicy['required']) ? 'نعم' : 'لا' }}</strong></div>
                <div class="bk-kv"><span>{{ __('النسبة المطبقة') }}</span><strong>{{ (int)($depositPolicy['configured_percent'] ?? 0) }}%</strong></div>
                <div class="bk-kv"><span>{{ __('قيمة الديبوزت') }}</span><strong>{{ number_format($depositAmount, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('المتبقي بعد الديبوزت') }}</span><strong>{{ number_format($remaining, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('المصدر') }}</span><strong>{{ $depositPolicy['source'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>Service Max %</span><strong>{{ (int)($depositPolicy['service_max_percent'] ?? 0) }}%</strong></div>
            </div>
        </div>

        <div class="a2-card bk-show-card">
            <div class="a2-section-title">{{ __('التأكيدات ورسوم التنفيذ') }}</div>
            <div class="a2-section-subtitle">
                {{ __('رسوم التنفيذ تُخصم من المحافظ عند بداية التنفيذ فقط إذا كانت مفعلة وكان الطرف وافق على الخصم التلقائي.') }}
            </div>

            <div class="bk-kv-grid">
                <div class="bk-kv"><span>{{ __('تأكيد العميل') }}</span><strong>{{ $clientConfirmed ? 'تم' : 'لم يتم' }}</strong></div>
                <div class="bk-kv"><span>{{ __('تأكيد البزنس') }}</span><strong>{{ $businessConfirmed ? 'تم' : 'لم يتم' }}</strong></div>
                <div class="bk-kv"><span>{{ __('رسوم التنفيذ - العميل') }}</span><strong>{{ number_format($clientExecutionAmount, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('رسوم التنفيذ - البزنس') }}</span><strong>{{ number_format($businessExecutionAmount, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>Fee Code</span><strong>{{ $executionFee['code'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('تاريخ الخصم') }}</span><strong>{{ $executionFee['charged_at'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('عدد الحركات') }}</span><strong>{{ count($executionTransactions) }}</strong></div>
                <div class="bk-kv"><span>Child ID</span><strong>{{ $executionFee['child_id'] ?? '-' }}</strong></div>
            </div>
        </div>
    </div>

    <div class="a2-card bk-wide-card">
        <div class="a2-section-title">Service Fees Snapshot</div>
        <div class="a2-section-subtitle">
            {{ __('هذا snapshot من') }}
            <span dir="ltr">category_child_service_fees</span>
            {{ __('وقت إنشاء أو تحديث الحجز.') }}
        </div>

        <div class="bk-kv-grid bk-kv-grid-4">
            <div class="bk-kv">
                <span>Client Fee</span>
                <strong>
                    @if($clientFeeSnapshot)
                        {{ number_format((float)($clientFeeSnapshot['amount'] ?? 0), 2) }}
                        {{ $clientFeeSnapshot['currency'] ?? $currency }}
                    @else
                        —
                    @endif
                </strong>
            </div>

            <div class="bk-kv">
                <span>Client Fee Type</span>
                <strong>{{ $clientFeeSnapshot['fee_type'] ?? '—' }}</strong>
            </div>

            <div class="bk-kv">
                <span>Business Fee</span>
                <strong>
                    @if($businessFeeSnapshot)
                        {{ number_format((float)($businessFeeSnapshot['amount'] ?? 0), 2) }}
                        {{ $businessFeeSnapshot['currency'] ?? $currency }}
                    @else
                        —
                    @endif
                </strong>
            </div>

            <div class="bk-kv">
                <span>Business Fee Type</span>
                <strong>{{ $businessFeeSnapshot['fee_type'] ?? '—' }}</strong>
            </div>

            <div class="bk-kv">
                <span>Fee Row ID</span>
                <strong>{{ $clientFeeSnapshot['id'] ?? $businessFeeSnapshot['id'] ?? '—' }}</strong>
            </div>

            <div class="bk-kv">
                <span>Service ID</span>
                <strong>{{ $serviceFeesSnapshot['service_id'] ?? $booking->service_id }}</strong>
            </div>

            <div class="bk-kv">
                <span>Platform Service ID</span>
                <strong>{{ $serviceFeesSnapshot['platform_service_id'] ?? $booking->service_id }}</strong>
            </div>

            <div class="bk-kv">
                <span>Fee Code</span>
                <strong>{{ $serviceFeesSnapshot['fee_code'] ?? ($executionFee['code'] ?? '-') }}</strong>
            </div>
        </div>
    </div>

    @if(!empty($executionTransactions))
        <div class="a2-card bk-wide-card">
            <div class="a2-section-title">Wallet Transactions</div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User ID</th>
                            <th>Payer</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Direction</th>
                            <th>Status</th>
                            <th>Fee Row</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($executionTransactions as $tx)
                            <tr>
                                <td>{{ $tx['id'] ?? '—' }}</td>
                                <td>{{ $tx['user_id'] ?? '—' }}</td>
                                <td>{{ $tx['payer'] ?? '—' }}</td>
                                <td>{{ number_format((float)($tx['amount'] ?? 0), 2) }} {{ $currency }}</td>
                                <td>{{ $tx['type'] ?? '—' }}</td>
                                <td>{{ $tx['direction'] ?? '—' }}</td>
                                <td>{{ $tx['status'] ?? '—' }}</td>
                                <td>{{ $tx['category_child_service_fee_id'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if(!empty($platformService))
        <div class="a2-card bk-wide-card">
            <div class="a2-section-title">{{ __('الخدمة على المنصة') }}</div>

            <div class="bk-kv-grid bk-kv-grid-4">
                <div class="bk-kv"><span>{{ __('رقم الخدمة') }}</span><strong>{{ $platformService['id'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>Key</span><strong>{{ $platformService['key'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('الاسم العربي') }}</span><strong>{{ $platformService['name_ar'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('الاسم الإنجليزي') }}</span><strong>{{ $platformService['name_en'] ?? '-' }}</strong></div>
            </div>
        </div>
    @endif

    @if(!empty($bookableMeta))
        <div class="a2-card bk-wide-card">
            <div class="a2-section-title">{{ __('العنصر القابل للحجز') }}</div>

            <div class="bk-kv-grid bk-kv-grid-4">
                <div class="bk-kv"><span>ID</span><strong>{{ $bookableMeta['id'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('العنوان') }}</span><strong>{{ $bookableMeta['title'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('الكود') }}</span><strong>{{ $bookableMeta['code'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('النوع') }}</span><strong>{{ $bookableMeta['item_type'] ?? '-' }}</strong></div>
                <div class="bk-kv"><span>{{ __('السعر') }}</span><strong>{{ number_format((float)($bookableMeta['price'] ?? 0), 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>Deposit Enabled</span><strong>{{ !empty($bookableMeta['deposit_enabled']) ? 'نعم' : 'لا' }}</strong></div>
                <div class="bk-kv"><span>Deposit %</span><strong>{{ (int)($bookableMeta['deposit_percent'] ?? 0) }}%</strong></div>
            </div>
        </div>
    @endif

    @if($deposit)
        <div class="a2-card bk-wide-card">
            <div class="a2-section-title">{{ __('سجل Deposit') }}</div>

            <div class="bk-kv-grid bk-kv-grid-4">
                <div class="bk-kv"><span>{{ __('الحالة') }}</span><strong>{{ $deposit->status }}</strong></div>
                <div class="bk-kv"><span>{{ __('إجمالي القيمة') }}</span><strong>{{ number_format((float)$deposit->total_amount, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('قيمة العميل') }}</span><strong>{{ number_format((float)$deposit->client_amount, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('قيمة البزنس') }}</span><strong>{{ number_format((float)$deposit->business_amount, 2) }} {{ $currency }}</strong></div>
                <div class="bk-kv"><span>{{ __('تأكيد العميل') }}</span><strong>{{ (int)$deposit->client_confirmed === 1 ? 'نعم' : 'لا' }}</strong></div>
                <div class="bk-kv"><span>{{ __('تأكيد البزنس') }}</span><strong>{{ (int)$deposit->business_confirmed === 1 ? 'نعم' : 'لا' }}</strong></div>
                <div class="bk-kv"><span>Agree Release Client</span><strong>{{ (int)($deposit->release_agreed_client ?? 0) === 1 ? 'نعم' : 'لا' }}</strong></div>
                <div class="bk-kv"><span>Agree Release Business</span><strong>{{ (int)($deposit->release_agreed_business ?? 0) === 1 ? 'نعم' : 'لا' }}</strong></div>
                <div class="bk-kv"><span>Agree Refund Client</span><strong>{{ (int)($deposit->refund_agreed_client ?? 0) === 1 ? 'نعم' : 'لا' }}</strong></div>
                <div class="bk-kv"><span>Agree Refund Business</span><strong>{{ (int)($deposit->refund_agreed_business ?? 0) === 1 ? 'نعم' : 'لا' }}</strong></div>
            </div>
        </div>
    @endif
</div>

<style>
.bk-actions-card{
    padding:18px;
    margin-bottom:16px;
}
.bk-action-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    margin-top:14px;
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
.bk-kv-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    margin-top:14px;
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