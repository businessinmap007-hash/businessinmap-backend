@extends('admin-v2.layouts.master')

@section('title','Delivery Drivers')
@section('body_class','admin-v2-delivery')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">موصّلو التوصيل</h2>
        <div class="a2-hint">حلقة التوصيل المتّصلة — العدّادات مدى الحياة</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.delivery.completions.index') }}">سجل التوصيلات</a>
      </div>
    </div>

    <form method="GET" class="a2-actionsbar" style="margin-bottom:12px;">
      <input class="a2-input" type="text" name="q" value="{{ $q }}" placeholder="بحث بالاسم أو البريد أو الهاتف">
      <button class="a2-btn a2-btn-primary" type="submit">بحث</button>
    </form>

    @if(session('success'))
      <div class="a2-alert a2-alert-ok">{{ session('success') }}</div>
    @endif

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>الموصّل</th>
            <th>الهاتف</th>
            <th>مُسنَد</th>
            <th>استُلم</th>
            <th>سُلّم</th>
            <th>الحالة</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($drivers as $d)
            <tr>
              <td>{{ $d->id }}</td>
              <td>{{ $d->user->name ?? '—' }}<div class="a2-hint">{{ $d->user->email ?? '' }}</div></td>
              <td dir="ltr">{{ $d->phone ?: ($d->user->phone ?? '—') }}</td>
              <td>{{ $d->assigned_count }}</td>
              <td>{{ $d->picked_up_count }}</td>
              <td><strong>{{ $d->delivered_count }}</strong></td>
              <td>
                @if($d->is_active)
                  <span class="a2-badge a2-badge-ok">مفعّل</span>
                @else
                  <span class="a2-badge a2-badge-muted">موقوف</span>
                @endif
              </td>
              <td>
                <form method="POST" action="{{ route('admin.delivery.drivers.toggle', $d->id) }}">
                  @csrf
                  <button class="a2-btn a2-btn-ghost" type="submit">{{ $d->is_active ? 'إيقاف' : 'تفعيل' }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="a2-empty">لا يوجد موصّلون.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="a2-pager">{{ $drivers->links() }}</div>
  </div>
</div>
@endsection
