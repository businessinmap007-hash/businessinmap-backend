@extends('admin_v2.layouts.app')

@section('content')
<div class="a2-card" style="padding:14px;">
  <div class="a2-header" style="margin-bottom:10px;">
    <div>
      <div class="a2-title">Business Service Prices</div>
      <div class="a2-hint">تحديد سعر كل بزنس لكل خدمة</div>
    </div>
    <div class="a2-actionsbar">
      <a class="a2-btn a2-btn-primary" href="{{ route('admin.business-service-prices.create') }}">+ إضافة</a>
    </div>
  </div>

  <form method="GET" style="margin-bottom:10px; display:flex; gap:8px;">
    <input class="a2-input" name="q" value="{{ $q ?? '' }}" placeholder="بحث بالبزنس أو الخدمة..." style="max-width:320px;">
    <button class="a2-btn" type="submit">بحث</button>
    <a class="a2-btn" href="{{ route('admin.business-service-prices.index') }}">مسح</a>
  </form>

  <div class="a2-table-wrap">
    <table class="a2-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Service</th>
          <th>Business</th>
          <th>Active</th>
          <th>Price</th>
          <th>Fee Override</th>
          <th style="width:160px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td>
              <div style="font-weight:600;">{{ $r->service->name_ar ?? '-' }}</div>
              <div class="a2-hint"><code>{{ $r->service->key ?? '' }}</code></div>
            </td>
            <td>
              <div style="font-weight:600;">{{ $r->business->name ?? '-' }}</div>
              <div class="a2-hint">#{{ $r->business_id }}</div>
            </td>
            <td>{!! $r->is_active ? '<span class="a2-badge a2-badge-success">Yes</span>' : '<span class="a2-badge">No</span>' !!}</td>
            <td>{{ $r->price }}</td>
            <td>
              @if($r->fee_type)
                <span class="a2-badge a2-badge-warning">{{ $r->fee_type }}</span>
                <span class="a2-hint">{{ $r->fee_value }}</span>
              @else
                <span class="a2-hint">—</span>
              @endif
            </td>
            <td>
              <a class="a2-btn a2-btn-sm" href="{{ route('admin.business-service-prices.edit', $r) }}">Edit</a>
              <form method="POST" action="{{ route('admin.business-service-prices.destroy', $r) }}" style="display:inline;" onsubmit="return confirm('حذف؟')">
                @csrf @method('DELETE')
                <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" style="text-align:center;">لا توجد بيانات</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:10px;">
    {{ $rows->links() }}
  </div>
</div>
@endsection