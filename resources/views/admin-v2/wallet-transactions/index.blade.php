@extends('admin-v2.layouts.master')

@section('title','المعاملات المالية')
@section('body_class','admin-v2-wallet-transactions')

@section('content')
@php
  $qVal = (string)($q ?? '');
  $filterVal = (string)($filter ?? '');
  $noteNow = (string)($noteVal ?? '');
  $perPageVal = (int)($perPage ?? 50);

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

  $qsKeep = [
    'q' => $qVal,
    'filter' => $filterVal,
    'note' => $noteNow,
    'per_page' => $perPageVal,
    'sort' => $sortNow,
    'dir' => $dirNow,
  ];
  
@endphp

<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <h2 class="a2-title">المعاملات المالية</h2>
    </div>

    <form method="GET" action="{{ route('admin.wallet-transactions.index') }}" class="a2-toolbar">
      <div class="a2-filters">

        <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="بحث بالاسم أو user_id أو ID أو wallet_id">

        <select class="a2-select" name="filter">
          @foreach($filterOptions as $k => $label)
            <option value="{{ $k }}" @selected($filterVal===$k)>{{ $label }}</option>
          @endforeach
        </select>

        {{-- ✅ Notes dropdown من عمود note نفسه --}}
        <select class="a2-select" name="note_id">
          <option value="">الكل</option>
          @foreach($notesOptions as $opt)
            <option value="{{ $opt->id }}" @selected((int)$noteId === (int)$opt->id)>{{ $opt->title }}
            </option>
          @endforeach
        </select>

        <select class="a2-select" name="per_page">
          @foreach($perPageOptions as $n)
            <option value="{{ $n }}" @selected((int)$perPageVal===(int)$n)>{{ $n }} / صفحة</option>
          @endforeach
        </select>

        <div class="a2-actionsbar">
          <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
          <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-transactions.index') }}">تفريغ</a>
        </div>

      </div>
    </form>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
        <tr>
          <th style="width:90px;">ID</th>
          <th style="width:220px;">User</th>
          <th style="width:140px;">Type</th>
          <th style="width:120px;">Dir</th>
          <th style="width:160px;" class="a2-text-center">Amount</th>
          <th style="width:140px;">Status</th>
          <th style="width:260px;">Notes</th>
          <th style="width:190px;">Created</th>
        </tr>
        </thead>

        <tbody>
        @forelse($items as $tx)
          @php
            $userName = (string)($tx->user->name ?? '');
            $userLabel = $userName !== '' ? $userName : ('#'.$tx->user_id);

            // ✅ يفتح صفحة show لمعاملات المستخدم
            $userTxUrl = route('admin.wallet-transactions.user', ['user' => $tx->user_id] + $qsKeep);

            $noteTxt = (string)($tx->note ?? '');
          @endphp
          <tr>
            <td>{{ $tx->id }}</td>
            

            <td>
              @if($tx->user_id)
                <a class="a2-link a2-clip a2-clip-10" href="{{ $userTxUrl }}" title="{{ $userLabel }}">
                  {{ $userLabel }}
                </a>
              @else
                —
              @endif
            </td>

            <td><span class="a2-badge a2-badge-muted">{{ $tx->type }}</span></td>
            <td>{{ $tx->direction }}</td>
            <td class="a2-text-center a2-fw-900">{{ number_format((float)$tx->amount, 2) }}</td>
            <td>{{ $tx->status }}</td>

            {{-- ✅ قص 10 أحرف --}}
            <td class="a2-clip a2-clip-10" title="{{ $noteTxt }}">
              {{ $noteTxt !== '' ? $noteTxt : '—' }}
            </td>

            <td dir="ltr">{{ $tx->created_at ? $tx->created_at->format('Y-m-d H:i') : '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="9" class="a2-empty-cell">لا يوجد بيانات</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($items, 'links'))
      <div class="a2-paginate">{{ $items->links() }}</div>
    @endif

  </div>
</div>
@endsection