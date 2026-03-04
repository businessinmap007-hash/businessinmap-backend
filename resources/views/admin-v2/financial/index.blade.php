@extends('admin-v2.layouts.master')

@section('title','Financial')
@section('body_class','admin-v2-financial')

@section('content')
@php
    $qVal = (string)($q ?? '');
    $typeVal = (string)($type ?? '');
    $statusVal = (string)($status ?? '');
    $perPageVal = (int)($perPage ?? 50);

    $sortNow = (string)($sort ?? 'id');
    $dirNow  = (string)($dir ?? 'desc');

    $perPageOptions = [10,20,50,100];

    $qsKeep = [
        'q' => $qVal,
        'type' => $typeVal,
        'status' => $statusVal,
        'per_page' => $perPageVal,
        'sort' => $sortNow,
        'dir' => $dirNow,
    ];

    $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow) {
        $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
        return route('admin.financial.index', array_merge($qsKeep, [
            'sort' => $col,
            'dir'  => $nextDir,
        ]));
    };

    $arrow = function(string $col) use ($sortNow, $dirNow) {
        if ($sortNow !== $col) return '';
        return $dirNow === 'asc' ? ' ▲' : ' ▼';
    };
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <h2 class="a2-title">المعاملات المالية</h2>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.financial.index') }}" class="a2-toolbar">
      <div class="a2-filters">

        <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="بحث بـ ID أو user_id أو amount أو ref">

        <select class="a2-select" name="type">
          @foreach(($typeOptions ?? []) as $k => $label)
            <option value="{{ $k }}" @selected((string)$typeVal === (string)$k)>{{ $label }}</option>
          @endforeach
        </select>

        <select class="a2-select" name="status">
          @foreach(($statusOptions ?? []) as $k => $label)
            <option value="{{ $k }}" @selected((string)$statusVal === (string)$k)>{{ $label }}</option>
          @endforeach
        </select>

        <select class="a2-select" name="per_page">
          @foreach($perPageOptions as $n)
            <option value="{{ $n }}" @selected((int)$perPageVal === (int)$n)>{{ $n }} / صفحة</option>
          @endforeach
        </select>

        <div class="a2-actionsbar">
          <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
          <a class="a2-btn a2-btn-ghost" href="{{ route('admin.financial.index') }}">تفريغ</a>
        </div>

      </div>

      <input type="hidden" name="sort" value="{{ $sortNow }}">
      <input type="hidden" name="dir" value="{{ $dirNow }}">
    </form>

    {{-- Table --}}
    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th style="width:90px;">
              <a class="a2-link" href="{{ $sortUrl('id') }}">ID{!! $arrow('id') !!}</a>
            </th>

            <th style="width:120px;">
              <a class="a2-link" href="{{ $sortUrl('user_id') }}">User{!! $arrow('user_id') !!}</a>
            </th>

            <th style="width:160px;">
              <a class="a2-link" href="{{ $sortUrl('amount') }}">Amount{!! $arrow('amount') !!}</a>
            </th>

            <th style="width:160px;">
              <a class="a2-link" href="{{ $sortUrl('type') }}">Type{!! $arrow('type') !!}</a>
            </th>

            <th style="width:160px;">
              <a class="a2-link" href="{{ $sortUrl('status') }}">Status{!! $arrow('status') !!}</a>
            </th>

            <th style="width:200px;">
              <a class="a2-link" href="{{ $sortUrl('created_at') }}">Created{!! $arrow('created_at') !!}</a>
            </th>

            <th style="width:140px;">Actions</th>
          </tr>
        </thead>

        <tbody>
          @forelse($items as $tx)
            @php $viewUrl = route('admin.financial.show', ['tx' => $tx->id] + $qsKeep); @endphp
            <tr>
              <td>{{ $tx->id }}</td>
              <td>{{ $tx->user_id ?? '—' }}</td>
              <td>{{ $tx->amount ?? '—' }}</td>
              <td>{{ $tx->type ?? '—' }}</td>

              <td>
                @php $st = (string)($tx->status ?? ''); @endphp
                <span class="a2-badge {{ $st==='success' ? 'a2-badge-success' : ($st==='failed' ? 'a2-badge-danger' : 'a2-badge-muted') }}">
                  {{ $st !== '' ? $st : '—' }}
                </span>
              </td>

              <td dir="ltr">
                {{ !empty($tx->created_at) ? \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d H:i') : '—' }}
              </td>

              <td>
                <a class="a2-btn a2-btn-ghost" href="{{ $viewUrl }}">عرض</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="a2-empty-cell">لا يوجد بيانات</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if(method_exists($items, 'links'))
      <div class="a2-paginate">
        {{ $items->links() }}
      </div>
    @endif

  </div>
</div>
@endsection
