@extends('admin-v2.layouts.master')

@section('title','Wallet Top-ups')
@section('body_class','admin-v2-wallet-topups')

@section('content')
@php $money = fn($v) => number_format((float)$v, 2); @endphp
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">شحنات المحفظة (Money-in)</h2>
        <div class="a2-hint">نيّات الشحن عبر بوابة الدفع — للمطابقة والمتابعة</div>
      </div>
      <form class="a2-actionsbar" method="get">
        <input class="a2-input" type="text" name="q" value="{{ $q }}" placeholder="بحث بالمرجع / مرجع البوابة">
        <select class="a2-input" name="status" onchange="this.form.submit()">
          <option value="">كل الحالات</option>
          @foreach(['pending' => 'قيد الانتظار','paid' => 'مدفوع','failed' => 'فشل','expired' => 'منتهٍ'] as $val => $label)
            <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
          @endforeach
        </select>
        <button class="a2-btn a2-btn-ghost" type="submit">تصفية</button>
      </form>
    </div>

    <div class="a2-statgrid">
      @foreach(['paid' => 'مدفوع','pending' => 'قيد الانتظار','failed' => 'فشل'] as $val => $label)
        <div class="a2-stat">
          <div class="a2-stat-label">{{ $label }}</div>
          <div class="a2-stat-value">{{ (int) optional($summary->get($val))->c }}</div>
          <div class="a2-hint">{{ $money(optional($summary->get($val))->total ?? 0) }}</div>
        </div>
      @endforeach
    </div>

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>المستخدم</th>
            <th>المبلغ</th>
            <th>البوابة</th>
            <th>الطريقة</th>
            <th>مرجعنا</th>
            <th>مرجع البوابة</th>
            <th>الحالة</th>
            <th>الدفع</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            <tr>
              <td>{{ $r->id }}</td>
              <td>{{ $r->user->name ?? ('#'.$r->user_id) }}</td>
              <td>{{ $money($r->amount) }} {{ $r->currency }}</td>
              <td>{{ $r->gateway }}</td>
              <td>{{ $r->method ?? '—' }}</td>
              <td dir="ltr">{{ $r->merchant_ref }}</td>
              <td dir="ltr">{{ $r->gateway_ref ?? '—' }}</td>
              <td><span class="a2-badge a2-badge-{{ $r->status }}">{{ $r->status }}</span></td>
              <td dir="ltr">{{ optional($r->paid_at)->format('Y-m-d H:i') ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="9" class="a2-empty">لا توجد شحنات.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="a2-pager">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
