@extends('admin-v2.layouts.master')

@section('title','معاملات المستخدم')
@section('body_class','admin-v2-wallet-transactions')

@section('content')
@php
  $qVal      = (string)($q ?? '');
  $filterVal = (string)($filter ?? '');
  $notesVal  = (string)($notes ?? '');
  $perPageVal= (int)($perPage ?? 10);

  $sortNow = (string)($sort ?? 'id');
  $dirNow  = (string)($dir ?? 'desc');

  $filterOptions = [
    '' => 'الحالة أو النوع',
    'st:pending'   => 'Pending',
    'st:completed' => 'Completed',
    'st:failed'    => 'Failed',
    'st:reversed'  => 'Reversed',
    'dir:in'  => 'In',
    'dir:out' => 'Out',
    'tp:deposit'   => 'Deposit',
    'tp:withdraw'  => 'Withdraw',
    'tp:transfer'  => 'Transfer',
    'tp:hold'      => 'Hold',
    'tp:release'   => 'Release',
    'tp:refund'    => 'Refund',
    'combo:deposit_in'   => 'Depo (in)',
    'combo:withdraw_out' => 'Wdraw (out)',
  ];

  $perPageOptions = [10,20,50,100];

  // querystring keep
  $qsKeep = [
    'q' => $qVal,
    'filter' => $filterVal,
    'notes' => $notesVal,
    'per_page' => $perPageVal,
    'sort' => $sortNow,
    'dir' => $dirNow,
  ];

  $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow, $user) {
    $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
    return route('admin.wallet-transactions.user', ['user'=>$user->id] + array_merge($qsKeep, [
      'sort' => $col,
      'dir'  => $nextDir,
    ]));
  };

  $arrow = function(string $col) use ($sortNow, $dirNow) {
    if ($sortNow !== $col) return '';
    return $dirNow === 'asc' ? ' ▲' : ' ▼';
  };

  $sumIn  = (float)($totals['sum_in'] ?? 0);
  $sumOut = (float)($totals['sum_out'] ?? 0);
  $net    = (float)($totals['net'] ?? 0);
  $countAll= (int)($totals['count'] ?? 0);

  $balanceNow = (float)($balance ?? 0);
  $lockedNow  = (float)($locked ?? 0);

  // ✅ نفس class القص اللي عندك في posts (استخدمها)
  // لو اسمها مختلف عندك في admin.css غيّرها هنا
  $clip10 = 'a2-clip a2-clip-10';
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
      <div>
        <h2 class="a2-title">معاملات المستخدم ( {{ $user->name }} )</h2>
        
      </div>

      <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-transactions.index') }}">رجوع</a>
    </div>

    <!-- {{-- Summary top --}}
    <div class="a2-card" style="padding:12px;margin:10px 0;">
      <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:flex-end;">
        <div><b>IN:</b> {{ number_format($sumIn,2) }}</div>
        <div><b>OUT:</b> {{ number_format($sumOut,2) }}</div>
        <div><b>NET:</b> {{ number_format($net,2) }}</div>
        <div style="opacity:.7;">—</div>
        <div><b>الرصيد الحالي:</b> {{ number_format($balanceNow,2) }}</div>
        <div><b>Locked:</b> {{ number_format($lockedNow,2) }}</div>
      </div>
    </div> -->

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.wallet-transactions.user', ['user'=>$user->id]) }}" class="a2-toolbar">
      <div class="a2-filters">

        <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="بحث بـ ID أو wallet_id أو type ...">

        {{-- ✅ Notes Filter (Dropdown مثل index) --}}
        <select class="a2-select" name="note_id">
          <option value="">الكل</option>
            @foreach($notesOptions as $opt)
              <option value="{{ $opt->id }}" @selected((int)$noteId === (int)$opt->id)>{{ $opt->title }}
              </option>
            @endforeach
        </select>

        <select class="a2-select" name="filter">
          @foreach($filterOptions as $k => $label)
            <option value="{{ $k }}" @selected((string)$filterVal === (string)$k)>{{ $label }}</option>
          @endforeach
        </select>

        <select class="a2-select" name="per_page">
          @foreach($perPageOptions as $n)
            <option value="{{ $n }}" @selected((int)$perPageVal === (int)$n)>{{ $n }} / صفحة</option>
          @endforeach
        </select>

        <div class="a2-actionsbar">
          <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
          <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-transactions.user', ['user'=>$user->id]) }}">تفريغ</a>
        </div>

      </div>

      <input type="hidden" name="sort" value="{{ $sortNow }}">
      <input type="hidden" name="dir" value="{{ $dirNow }}">
    </form>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
        <tr>
          <th style="width:90px;"><a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a></th>
          <th style="width:140px;">Type</th>

          {{-- محاسبي: عمودين IN/OUT --}}
          <th style="width:160px;" class="a2-text-center">IN</th>
          <th style="width:160px;" class="a2-text-center">OUT</th>

          <th style="width:140px;">Status</th>
          <th style="width:260px;">Notes</th>

          <th style="width:190px;"><a class="a2-link" href="{{ $sortUrl('created_at') }}">Created{!! $arrow('created_at') !!}</a></th>
        </tr>
        </thead>

        <tbody>
        @forelse($items as $tx)
          @php
            $inVal  = ($tx->direction === 'in')  ? (float)$tx->amount : 0.0;
            $outVal = ($tx->direction === 'out') ? (float)$tx->amount : 0.0;
            $noteTxt = (string)($tx->note ?? '');

            // ✅ FIX: show route لا يقبل user param
            // نخلي user_id كـ querystring فقط علشان زر الرجوع داخل show (لو حابب)
            $showUrl = route('admin.wallet-transactions.show', ['walletTransaction'=>$tx->id] + $qsKeep);
          @endphp
          <tr>
            <td>
              <a class="a2-link" href="{{ $showUrl }}">{{ $tx->id }}</a>
            </td>

            <td><span class="a2-badge a2-badge-muted">{{ $tx->type }}</span></td>

            <td class="a2-text-center a2-fw-900">{{ $inVal ? number_format($inVal,2) : '0.00' }}</td>
            <td class="a2-text-center a2-fw-900">{{ $outVal ? number_format($outVal,2) : '0.00' }}</td>

            <td>{{ $tx->status }}</td>

            <td class="{{ $clip10 }}" title="{{ $noteTxt }}">{{ $noteTxt !== '' ? $noteTxt : '—' }}</td>

            <td dir="ltr">{{ $tx->created_at ? \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d H:i') : '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="7" class="a2-empty-cell">لا يوجد بيانات</td>
          </tr>
        @endforelse
        </tbody>

        {{-- ✅ Totals row (على كل النتائج بعد الفلترة) --}}
        {{-- توزيع محاسبي تحت كل عمود:
            ID => count
            IN => sum_in
            OUT => sum_out
            Status => NET
            Notes => Balance
            Created => Locked
        --}}
        <tfoot>
          <tr>
            <td class="a2-fw-900">{{ $countAll }}</td>
            <td class="a2-fw-900">Totals</td>
            <td class="a2-text-center a2-fw-900">{{ number_format($sumIn,2) }}</td>
            <td class="a2-text-center a2-fw-900">{{ number_format($sumOut,2) }}</td>
            <td class="a2-fw-900">NET: {{ number_format($net,2) }}</td>
            <td class="a2-fw-900">الرصيد: {{ number_format($balanceNow,2) }}</td>
            <td class="a2-fw-900">Locked: {{ number_format($lockedNow,2) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>

    @if(method_exists($items, 'links'))
      <div class="a2-paginate">{{ $items->links() }}</div>
    @endif

  </div>
</div>
@endsection