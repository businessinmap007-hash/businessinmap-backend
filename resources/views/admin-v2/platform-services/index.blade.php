@extends('admin-v2.layouts.master')
@section('title','Platform Services')

@section('body_class','admin-v2-platform-services index')

@section('content')
<div class="a2-card" style="padding:14px;">
  <div class="a2-header" style="margin-bottom:10px;">
    <div>
      <div class="a2-title">خدمات النظام</div>
      <div class="a2-hint">تعريف الخدمات الأساسية (booking / menu / delivery …) + قواعدها</div>
    </div>
    <div class="a2-actionsbar">
      <a class="a2-btn a2-btn-primary" href="{{ route('admin.platform-services.create') }}">+ إضافة خدمة</a>
    </div>
  </div>

  <form method="GET" style="margin-bottom:10px; display:flex; gap:8px;">
    <input class="a2-input" name="q" value="{{ $q ?? '' }}" placeholder="بحث بالـ key أو الاسم..." style="max-width:320px;">
    <button class="a2-btn" type="submit">بحث</button>
    <a class="a2-btn" href="{{ route('admin.platform-services.index') }}">مسح</a>
  </form>

  <div class="a2-table-wrap">
    <table class="a2-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Key</th>
          <th>الاسم</th>
          <th>Active</th>
          <th>Deposit</th>
          <th>Fee</th>
          <th style="width:160px;">إجراءات</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td>{{ $r->id }}</td>
            <td><code>{{ $r->key }}</code></td>
            <td>
              <div style="font-weight:600;">{{ $r->name_ar }}</div>
              @if($r->name_en)<div class="a2-hint">{{ $r->name_en }}</div>@endif
            </td>
            <td>{!! $r->is_active ? '<span class="a2-badge a2-badge-success">Yes</span>' : '<span class="a2-badge">No</span>' !!}</td>
            <td>
              @if($r->supports_deposit)
                <span class="a2-badge a2-badge-info">Yes</span>
                <span class="a2-hint">Max: {{ $r->max_deposit_percent }}%</span>
              @else
                <span class="a2-badge">No</span>
              @endif
            </td>
            <td>
              @if($r->fee_type)
                <span class="a2-badge a2-badge-warning">{{ $r->fee_type }}</span>
                <span class="a2-hint">{{ $r->fee_value }}</span>
              @else
                <span class="a2-hint">—</span>
              @endif
            </td>
            <td>
              <a class="a2-btn a2-btn-sm" href="{{ route('admin.platform-services.edit', $r) }}">Edit</a>
              <form method="POST" action="{{ route('admin.platform-services.destroy', $r) }}" style="display:inline;" onsubmit="return confirm('حذف الخدمة؟')">
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