@extends('admin-v2.layouts.master')

@section('title','Delivery Completions')
@section('body_class','admin-v2-delivery')

@section('content')
@php $money = fn($v) => $v === null ? '—' : number_format((float)$v, 2); @endphp
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">سجل التوصيلات المكتملة</h2>
        <div class="a2-hint">صف واحد لكل طلب سُلّم — النجاح المسجّل للمطعم والموصّل</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.delivery.drivers.index') }}">الموصّلون</a>
      </div>
    </div>

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>الطلب</th>
            <th>المطعم</th>
            <th>الموصّل</th>
            <th>الإجمالي</th>
            <th>وقت التسليم</th>
          </tr>
        </thead>
        <tbody>
          @forelse($completions as $c)
            <tr>
              <td>{{ $c->id }}</td>
              <td>#{{ $c->order_id }}</td>
              <td>{{ $c->business->name ?? ('#'.$c->business_id) }}</td>
              <td>{{ optional($c->driver)->user->name ?? ('#'.$c->driver_user_id) }}</td>
              <td>{{ $money(optional($c->order)->final_total) }}</td>
              <td dir="ltr">{{ optional($c->completed_at)->format('Y-m-d H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="a2-empty">لا توجد توصيلات مكتملة بعد.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="a2-pager">{{ $completions->links() }}</div>
  </div>
</div>
@endsection
