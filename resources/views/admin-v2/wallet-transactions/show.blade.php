@extends('admin-v2.layouts.master')

@section('title','تفاصيل المعاملة')
@section('body_class','admin-v2-wallet-transactions')

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

  // Reference / Meta
  $refType = (string)($tx->reference_type ?? '');
  $refId   = (string)($tx->reference_id ?? '');
  $idemKey = (string)($tx->idempotency_key ?? '');

  $metaRaw = $tx->meta ?? null;

  if (is_string($metaRaw)) {
      $decoded = json_decode($metaRaw, true);
      $metaArr = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
  } else {
      $metaArr = is_array($metaRaw) ? $metaRaw : (is_object($metaRaw) ? (array)$metaRaw : null);
  }

  $metaPretty = $metaArr
      ? json_encode($metaArr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
      : (is_string($metaRaw) ? $metaRaw : '');
@endphp

<div class="a2-page">
  <div class="a2-card">

    {{-- Header --}}
    <div class="a2-header a2-tx-header">
      <div>
        <h2 class="a2-title">تفاصيل المعاملة</h2>
        <div class="a2-hint">Transaction #{{ $tx->id }}</div>
      </div>

      <div class="a2-actionsbar a2-tx-actions">
        <a class="a2-btn a2-btn-ghost" href="{{ $backUrl }}">رجوع</a>

        @if($userLedgerUrl)
          <a class="a2-btn a2-btn-primary" href="{{ $userLedgerUrl }}">كشف حساب المستخدم</a>
        @endif

        @if($userProfileUrl)
          <a class="a2-btn a2-btn-ghost" href="{{ $userProfileUrl }}">بيانات المستخدم</a>
        @endif
      </div>
    </div>

    {{-- Main Grid --}}
    <div class="a2-tx-grid">

      {{-- Left: Financial tables --}}
      <div class="a2-card a2-tx-card a2-tx-main">
      
        <div class="a2-hint a2-mb-10">القيم المالية</div>

        {{-- IN / OUT --}}
        <div class="a2-table-wrap a2-mb-12">
          <table class="a2-table a2-table-compact">
            <thead>
              <tr>
                <th class="a2-text-center" style="width:50%;">IN</th>
                <th class="a2-text-center" style="width:50%;">OUT</th>
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

        {{-- Balance Before/After --}}
        <div class="a2-table-wrap">
          <table class="a2-table a2-table-compact">
            <thead>
              <tr>
                <th class="a2-text-center">Before</th>
                <th class="a2-text-center">After</th>
                <th class="a2-text-right" style="width:120px;">البند</th>
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

      {{-- Right: Basic info --}}
     
      <aside class="a2-card a2-tx-card a2-tx-side">

  <div class="a2-hint a2-mb-10">بيانات أساسية</div>

      <div class="a2-table-wrap a2-tx-info-wrap">
        <table class="a2-table a2-table-compact a2-tx-info-table">
          <tbody>
            <tr>
              <th>ID</th>
              <td>{{ $tx->id }}</td>
            </tr>
            <tr>
              <th>Wallet</th>
              <td>{{ $walletId ?: '—' }}</td>
            </tr>
            <tr>
              <th>User</th>
              <td>
                @if($userId)
                  <div class="a2-userline">
                    <span class="a2-clip-10" title="{{ $userName ?: ('#'.$userId) }}">
                      {{ $userName ?: ('#'.$userId) }}
                    </span>

                    @if($userLedgerUrl)
                      <a class="a2-link" href="{{ $userLedgerUrl }}">كشف الحساب</a>
                    @endif

                    @if($userProfileUrl)
                      <span class="a2-sep">|</span>
                      <a class="a2-link" href="{{ $userProfileUrl }}">بياناته</a>
                    @endif
                  </div>
                @else
                  —
                @endif
              </td>
            </tr>
            <tr>
              <th>Type</th>
              <td><span class="a2-badge a2-badge-muted">{{ (string)($tx->type ?? '—') }}</span></td>
            </tr>
            <tr>
              <th>Status</th>
              <td>{{ (string)($tx->status ?? '—') }}</td>
            </tr>
            <tr>
              <th>Created</th>
              <td dir="ltr">{{ $created }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      {{-- Reference / Meta --}}
      <div class="a2-mini-card">
        <div class="a2-mini-head">
          <div class="a2-hint">Reference</div>
          @if($refType !== '' || $refId !== '')
            <span class="a2-badge a2-badge-muted">linked</span>
          @endif
        </div>

        <div class="a2-table-wrap a2-tx-info-wrap" style="margin-top:10px;">
          <table class="a2-table a2-table-compact a2-tx-info-table">
            <tbody>
              <tr>
                <th>reference_type</th>
                <td class="a2-clip" title="{{ $refType }}">{{ $refType !== '' ? $refType : '—' }}</td>
              </tr>
              <tr>
                <th>reference_id</th>
                <td class="a2-clip" title="{{ $refId }}">{{ $refId !== '' ? $refId : '—' }}</td>
              </tr>
              <tr>
                <th>idempotency</th>
                <td class="a2-clip" title="{{ $idemKey }}">{{ $idemKey !== '' ? $idemKey : '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div> 
      </div>

    </aside>
    </div>

    {{-- Notes --}}
    <div class="a2-card a2-tx-notes">
      <div class="a2-hint a2-mb-6">Notes</div>
      <div class="a2-notes-body">
        {{ $noteTxt !== '' ? $noteTxt : '—' }}
      </div>
    </div>

  </div>
</div>



<script>
(function(){
  document.addEventListener('click', function(e){
    const btn = e.target.closest('[data-a2-copy]');
    if(!btn) return;
    const sel = btn.getAttribute('data-a2-copy');
    const el = document.querySelector(sel);
    if(!el) return;

    const text = el.innerText || el.textContent || '';
    (navigator.clipboard && navigator.clipboard.writeText ? navigator.clipboard.writeText(text) : Promise.reject())
      .then(()=>{
        const old = btn.textContent;
        btn.textContent = 'تم النسخ';
        setTimeout(()=> btn.textContent = old || 'نسخ', 900);
      })
      .catch(()=>{ alert('تعذر النسخ'); });
  });
})();
</script>
@endsection