@extends('admin-v2.layouts.master')

@section('title','Bookings')
@section('body_class','admin-v2-bookings')

@section('content')
@php
  $qVal       = (string)($q ?? '');
  $statusVal  = (string)($status ?? '');
  $dateVal    = (string)($date ?? '');
  $perPageVal = (int)($perPage ?? 50);

  $sortNow = (string)($sort ?? 'starts_at');
  $dirNow  = (string)($dir ?? 'desc');

  $qsKeep = [
    'q' => $qVal,
    'status' => $statusVal,
    'date' => $dateVal,
    'per_page' => $perPageVal,
    'sort' => $sortNow,
    'dir' => $dirNow,
  ];

  $sortUrl = function(string $col) use ($qsKeep, $sortNow, $dirNow) {
    $nextDir = ($sortNow === $col && $dirNow === 'asc') ? 'desc' : 'asc';
    return route('admin.bookings.index', array_merge($qsKeep, ['sort' => $col, 'dir' => $nextDir]));
  };

  $badge = function(string $st) {
    return match($st) {
      'accepted'    => 'a2-badge a2-badge-success',
      'rejected'    => 'a2-badge a2-badge-danger',
      'cancelled'   => 'a2-badge a2-badge-muted',
      'in_progress' => 'a2-badge a2-badge-primary',
      'completed'   => 'a2-badge a2-badge-success',
      default       => 'a2-badge a2-badge-warning',
    };
  };

  $fmt = function($dt) {
    if (!$dt) return '—';
    try {
      return \Carbon\Carbon::parse($dt)->format('Y-m-d H:i');
    } catch (\Throwable $e) {
      return (string) $dt;
    }
  };

  $kindOf = function($row) {
    $k = data_get($row, 'meta.booking_kind', '');
    return $k !== '' ? $k : '—';
  };
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <div>
        <h2 class="a2-title">Bookings</h2>
        <div class="a2-hint">حجوزات عامة + عناصر قابلة للحجز</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookings.create') }}">إضافة حجز</a>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="GET" class="a2-filters" style="display:grid;grid-template-columns:1.4fr .8fr .8fr .6fr auto;gap:10px;align-items:end;">
      <div>
        <label class="a2-label">بحث</label>
        <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="ID / notes / user_id / business_id / bookable_id">
      </div>

      <div>
        <label class="a2-label">الحالة</label>
        <select class="a2-select" name="status">
          <option value="">الكل</option>
          @foreach($statusOptions as $k => $label)
            <option value="{{ $k }}" @selected($statusVal === (string)$k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      <div>
        <label class="a2-label">يوم البداية</label>
        <input class="a2-input" type="date" name="date" value="{{ $dateVal }}">
      </div>

      <div>
        <label class="a2-label">Per page</label>
        <select class="a2-select" name="per_page">
          @foreach([10,20,50,100] as $pp)
            <option value="{{ $pp }}" @selected($perPageVal === $pp)>{{ $pp }}</option>
          @endforeach
        </select>
      </div>

      <div style="display:flex;gap:8px;">
        <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">تفريغ</a>
      </div>

      <input type="hidden" name="sort" value="{{ $sortNow }}">
      <input type="hidden" name="dir" value="{{ $dirNow }}">
    </form>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th><a href="{{ $sortUrl('id') }}">#</a></th>
            <th>Client</th>
            <th>Business</th>
            <th>Service</th>
            <th>Bookable Item</th>
            <th>Kind</th>
            <th><a href="{{ $sortUrl('starts_at') }}">Start</a></th>
            <th><a href="{{ $sortUrl('ends_at') }}">End</a></th>
            <th><a href="{{ $sortUrl('status') }}">Status</a></th>
            <th class="a2-col-actions">إجراءات</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            <tr>
              <td>#{{ $r->id }}</td>

              <td>
                <a class="a2-link" href="{{ route('admin.users.show', $r->user_id) }}">
                  {{ $r->user->name ?? ('User #'.$r->user_id) }}
                </a>
                <div class="a2-hint">{{ $r->user->phone ?? $r->user->email ?? '' }}</div>
              </td>

              <td>
                <a class="a2-link" href="{{ route('admin.users.show', $r->business_id) }}">
                  {{ $r->business->name ?? ('Business #'.$r->business_id) }}
                </a>
                <div class="a2-hint">{{ $r->business->phone ?? $r->business->email ?? '' }}</div>
              </td>

              <td>
                {{ $r->service ? ($r->service->name_ar ?? $r->service->name_en ?? $r->service->key ?? '—') : '—' }}
              </td>

              <td>
                @if($r->bookable)
                  <div style="font-weight:700;">{{ $r->bookable->title ?? '—' }}</div>
                  <div class="a2-hint">
                    {{ $r->bookable->item_type ?? '' }}
                    @if(!empty($r->bookable->code)) — {{ $r->bookable->code }} @endif
                  </div>
                @else
                  <span class="a2-hint">—</span>
                @endif
              </td>

              <td><span class="a2-badge a2-badge-muted">{{ $kindOf($r) }}</span></td>

              <td>{{ $fmt($r->starts_at) }}</td>

              <td>
                @if($r->ends_at)
                  {{ $fmt($r->ends_at) }}
                @elseif($r->duration_value && $r->duration_unit)
                  <span class="a2-muted">{{ $r->duration_value }} {{ $r->duration_unit }}</span>
                @else
                  —
                @endif
              </td>

              <td>
                <span class="{{ $badge((string)$r->status) }}">
                  {{ $statusOptions[$r->status] ?? $r->status }}
                </span>
              </td>

              <td class="a2-actions">
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.show', $r) }}">عرض</a>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.edit', $r) }}">تعديل</a>

                <form method="POST" action="{{ route('admin.bookings.destroy', $r) }}" onsubmit="return confirm('حذف الحجز؟');" style="display:inline;">
                  @csrf
                  @method('DELETE')
                  <button class="a2-btn a2-btn-danger" type="submit">حذف</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="a2-muted" style="text-align:center;padding:18px;">لا توجد بيانات</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="a2-footer" style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
      <div class="a2-hint">Total: {{ $rows->total() }}</div>
      <div>{{ $rows->links() }}</div>
    </div>

  </div>
</div>
@endsection