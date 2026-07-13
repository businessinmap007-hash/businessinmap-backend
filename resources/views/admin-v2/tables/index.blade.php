@extends('admin-v2.layouts.master')

@section('title','Restaurant Tables')
@section('body_class','admin-v2-tables')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">طاولات المطاعم</h2>
        <div class="a2-hint">رموز QR للطاولات (BIM-13.3) — عرض على مستوى المنصّة</div>
      </div>
    </div>

    <form method="GET" class="a2-actionsbar" style="margin-bottom:12px;">
      <input class="a2-input" type="text" name="q" value="{{ $q }}" placeholder="بحث بالاسم أو المطعم">
      <button class="a2-btn a2-btn-primary" type="submit">بحث</button>
    </form>

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>المطعم</th>
            <th>الطاولة</th>
            <th>الطلبات</th>
            <th>الحالة</th>
          </tr>
        </thead>
        <tbody>
          @forelse($tables as $t)
            <tr>
              <td>{{ $t->id }}</td>
              <td>{{ $t->business->name ?? ('#'.$t->business_id) }}</td>
              <td>{{ $t->label ?: '—' }}</td>
              <td>{{ $t->orders_count }}</td>
              <td>
                @if($t->is_active)
                  <span class="a2-badge a2-badge-ok">مفعّلة</span>
                @else
                  <span class="a2-badge a2-badge-muted">موقوفة</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="a2-empty">لا توجد طاولات.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="a2-pager">{{ $tables->links() }}</div>
  </div>
</div>
@endsection
