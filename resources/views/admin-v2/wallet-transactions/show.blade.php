@extends('admin-v2.layouts.master')

@section('title','تفاصيل المعاملة')
@section('body_class','admin-v2 admin-v2-wallet-transactions-show')

@section('content')
@php
    /** @var \App\Models\WalletTransaction $tx */

    $backUrl = url()->previous() ?: route('admin.wallet-transactions.index');

    $userName  = (string)($tx->user->name ?? '');
    $userId    = (int)($tx->user_id ?? 0);
    $walletId  = (int)($tx->wallet_id ?? 0);

    $inVal  = ($tx->direction === 'in')  ? (float)$tx->amount : 0.0;
    $outVal = ($tx->direction === 'out') ? (float)$tx->amount : 0.0;

    $created = $tx->created_at ? \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d H:i') : '—';
    $noteTxt = (string)($tx->note ?? '');

    $userLedgerUrl  = $userId ? route('admin.wallet-transactions.user', ['user' => $userId]) : null;
    $userProfileUrl = $userId ? route('admin.users.show', ['user' => $userId]) : null;

    $nf = fn($n) => number_format((float)$n, 2);

    $refType = (string)($tx->reference_type ?? '');
    $refId   = (string)($tx->reference_id ?? '');
    $idemKey = (string)($tx->idempotency_key ?? '');

    $metaArr = method_exists($tx, 'metaArray')
        ? $tx->metaArray()
        : (is_array($tx->meta ?? null) ? $tx->meta : []);

    $payer = method_exists($tx, 'payer') ? ($tx->payer() ?: null) : data_get($metaArr, 'payer');
    $feeCode = method_exists($tx, 'feeCode') ? ($tx->feeCode() ?: null) : data_get($metaArr, 'fee_code');
    $feeType = method_exists($tx, 'feeType') ? ($tx->feeType() ?: null) : data_get($metaArr, 'fee_type');

    $bookingId = method_exists($tx, 'bookingId') ? ($tx->bookingId() ?: null) : data_get($metaArr, 'booking_id');
    $serviceId = method_exists($tx, 'serviceId') ? ($tx->serviceId() ?: null) : data_get($metaArr, 'service_id');
    $businessId = method_exists($tx, 'businessId') ? ($tx->businessId() ?: null) : data_get($metaArr, 'business_id');
    $clientId = method_exists($tx, 'clientId') ? ($tx->clientId() ?: null) : data_get($metaArr, 'client_id');
    $childId = method_exists($tx, 'childId') ? ($tx->childId() ?: null) : data_get($metaArr, 'child_id');
    $feeRowId = method_exists($tx, 'categoryChildServiceFeeId') ? ($tx->categoryChildServiceFeeId() ?: null) : data_get($metaArr, 'category_child_service_fee_id');

    $bookingUrl = $bookingId ? route('admin.bookings.show', $bookingId) : null;

    $metaPretty = !empty($metaArr)
        ? json_encode($metaArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        : '';
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تفاصيل المعاملة #{{ $tx->id }}</h1>
            <div class="a2-page-subtitle">
                عرض الحركة المالية وربطها بالحجز ورسوم التنفيذ إن وجدت.
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ $backUrl }}">رجوع</a>

            @if($userLedgerUrl)
                <a class="a2-btn a2-btn-primary" href="{{ $userLedgerUrl }}">كشف حساب المستخدم</a>
            @endif

            @if($userProfileUrl)
                <a class="a2-btn a2-btn-ghost" href="{{ $userProfileUrl }}">بيانات المستخدم</a>
            @endif

            @if($bookingUrl)
                <a class="a2-btn a2-btn-ghost" href="{{ $bookingUrl }}">عرض الحجز</a>
            @endif
        </div>
    </div>

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">ملخص الحركة</div>
        <div class="a2-section-subtitle">
            إذا كانت هذه الحركة من نوع
            <span dir="ltr">platform_fee</span>
            ومرتبطة بـ
            <span dir="ltr">booking</span>
            فهي تمثل خصم رسوم تنفيذ من محفظة العميل أو البزنس.
        </div>
    </div>

    <div class="tx-grid">
        <div class="a2-card tx-card">
            <div class="a2-section-title">القيم المالية</div>

            <div class="a2-table-wrap a2-mt-12">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th class="a2-text-center">IN</th>
                        <th class="a2-text-center">OUT</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="a2-text-center a2-fw-900">{{ $nf($inVal) }}</td>
                        <td class="a2-text-center a2-fw-900">{{ $nf($outVal) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="a2-table-wrap a2-mt-16">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th class="a2-text-center">Before</th>
                        <th class="a2-text-center">After</th>
                        <th class="a2-text-right">البند</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="a2-text-center">{{ $nf($tx->balance_before ?? 0) }}</td>
                        <td class="a2-text-center">{{ $nf($tx->balance_after ?? 0) }}</td>
                        <td class="a2-text-right">Balance</td>
                    </tr>
                    <tr>
                        <td class="a2-text-center">{{ $nf($tx->locked_before ?? 0) }}</td>
                        <td class="a2-text-center">{{ $nf($tx->locked_after ?? 0) }}</td>
                        <td class="a2-text-right">Locked</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <aside class="a2-card tx-card">
            <div class="a2-section-title">بيانات أساسية</div>

            <div class="tx-kv">
                <div><span>ID</span><strong>{{ $tx->id }}</strong></div>
                <div><span>Wallet</span><strong>{{ $walletId ?: '—' }}</strong></div>
                <div><span>User</span><strong>{{ $userName ?: ($userId ? '#'.$userId : '—') }}</strong></div>
                <div><span>Type</span><strong>{{ $tx->type ?: '—' }}</strong></div>
                <div><span>Direction</span><strong>{{ $tx->direction ?: '—' }}</strong></div>
                <div><span>Status</span><strong>{{ $tx->status ?: '—' }}</strong></div>
                <div><span>Created</span><strong dir="ltr">{{ $created }}</strong></div>
                <div><span>Note Template</span><strong>{{ $tx->noteTemplate->title ?? '—' }}</strong></div>
            </div>
        </aside>
    </div>

    <div class="a2-card a2-mt-16">
        <div class="a2-section-title">Booking Fee Details</div>
        <div class="a2-section-subtitle">
            هذه البيانات تُقرأ من
            <span dir="ltr">wallet_transactions.meta</span>.
        </div>

        <div class="tx-kv tx-kv-4 a2-mt-12">
            <div>
                <span>Payer</span>
                <strong>{{ $payer ?: '—' }}</strong>
            </div>

            <div>
                <span>Fee Code</span>
                <strong dir="ltr">{{ $feeCode ?: '—' }}</strong>
            </div>

            <div>
                <span>Fee Type</span>
                <strong>{{ $feeType ?: '—' }}</strong>
            </div>

            <div>
                <span>Fee Row ID</span>
                <strong>{{ $feeRowId ?: '—' }}</strong>
            </div>

            <div>
                <span>Booking ID</span>
                <strong>
                    @if($bookingUrl)
                        <a class="a2-link" href="{{ $bookingUrl }}">#{{ $bookingId }}</a>
                    @else
                        —
                    @endif
                </strong>
            </div>

            <div>
                <span>Service ID</span>
                <strong>{{ $serviceId ?: '—' }}</strong>
            </div>

            <div>
                <span>Business ID</span>
                <strong>{{ $businessId ?: '—' }}</strong>
            </div>

            <div>
                <span>Client ID</span>
                <strong>{{ $clientId ?: '—' }}</strong>
            </div>

            <div>
                <span>Child ID</span>
                <strong>{{ $childId ?: '—' }}</strong>
            </div>

            <div>
                <span>Reference Type</span>
                <strong dir="ltr">{{ $refType !== '' ? $refType : '—' }}</strong>
            </div>

            <div>
                <span>Reference ID</span>
                <strong>{{ $refId !== '' ? $refId : '—' }}</strong>
            </div>

            <div>
                <span>Idempotency Key</span>
                <strong dir="ltr" title="{{ $idemKey }}">
                    {{ $idemKey !== '' ? \Illuminate\Support\Str::limit($idemKey, 42) : '—' }}
                </strong>
            </div>
        </div>
    </div>

    <div class="a2-card a2-mt-16">
        <div class="a2-section-title">Notes</div>
        <div class="a2-section-subtitle">
            ملاحظة الحركة كما سُجلت وقت التنفيذ.
        </div>

        <div class="tx-note">
            {{ $noteTxt !== '' ? $noteTxt : '—' }}
        </div>
    </div>

    <div class="a2-card a2-mt-16">
        <div class="a2-page-actions" style="justify-content:space-between;">
            <div>
                <div class="a2-section-title">Meta JSON</div>
                <div class="a2-section-subtitle">نسخة كاملة من بيانات meta للتشخيص.</div>
            </div>

            <button type="button" class="a2-btn a2-btn-ghost" data-a2-copy="#tx_meta_json">
                نسخ JSON
            </button>
        </div>

        <pre id="tx_meta_json" class="tx-json">{{ $metaPretty !== '' ? $metaPretty : '{}' }}</pre>
    </div>
</div>

<style>
.tx-grid{
    display:grid;
    grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);
    gap:16px;
}
.tx-card{
    padding:18px;
}
.tx-kv{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    margin-top:12px;
}
.tx-kv-4{
    grid-template-columns:repeat(4,minmax(0,1fr));
}
.tx-kv > div{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:12px 14px;
    min-width:0;
}
.tx-kv span{
    display:block;
    font-size:12px;
    color:#6b7280;
    margin-bottom:6px;
}
.tx-kv strong{
    display:block;
    font-size:14px;
    font-weight:800;
    line-height:1.5;
    word-break:break-word;
}
.tx-note{
    background:#f8fafc;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:14px;
    margin-top:12px;
    white-space:pre-wrap;
}
.tx-json{
    margin-top:12px;
    padding:14px;
    background:#0f172a;
    color:#e5e7eb;
    border-radius:14px;
    overflow:auto;
    direction:ltr;
    text-align:left;
    font-size:13px;
    line-height:1.6;
}
@media(max-width:1100px){
    .tx-grid,
    .tx-kv-4{
        grid-template-columns:1fr;
    }
}
@media(max-width:700px){
    .tx-kv{
        grid-template-columns:1fr;
    }
}
</style>

<script>
document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-a2-copy]');
    if(!btn) return;

    const selector = btn.getAttribute('data-a2-copy');
    const el = document.querySelector(selector);

    if(!el) return;

    const text = el.innerText || el.textContent || '';

    if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(text).then(function(){
            const old = btn.textContent;
            btn.textContent = 'تم النسخ';
            setTimeout(function(){ btn.textContent = old || 'نسخ'; }, 900);
        }).catch(function(){
            alert('تعذر النسخ');
        });
    }
});
</script>
@endsection