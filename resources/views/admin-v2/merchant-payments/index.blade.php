@extends('admin-v2.layouts.master')

@section('title','Merchant Payments')
@section('body_class','admin-v2-merchant-payments-oversight')

@section('content')
@php $money = fn($v) => number_format((float)$v, 2); @endphp
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('مدفوعات التجّار (Money-in)') }}</h2>
        <div class="a2-hint">{{ __('دفعات العملاء للتجّار عبر البوابة — وإلى أي حساب وُجِّهت (التاجر أم المنصّة) للمطابقة') }}</div>
      </div>
    </div>

    <form class="a2-filterbar" method="get">
      <input class="a2-input a2-filter-search" type="text" name="q" value="{{ $q }}" placeholder="{{ __('بحث بالمرجع / مرجع البوابة') }}">
      <select class="a2-input a2-select a2-filter-sm" name="status" onchange="this.form.submit()">
        <option value="">{{ __('كل الحالات') }}</option>
        @foreach(['pending' => 'قيد الانتظار','paid' => 'مدفوع','failed' => 'فشل','expired' => 'منتهٍ'] as $val => $label)
          <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
        @endforeach
      </select>
      <div class="a2-filter-actions">
        <button class="a2-btn a2-btn-ghost" type="submit">{{ __('تصفية') }}</button>
      </div>
    </form>

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
            <th>{{ __('العميل') }}</th>
            <th>{{ __('التاجر') }}</th>
            <th>{{ __('المبلغ') }}</th>
            <th>{{ __('وُجِّهت إلى') }}</th>
            <th>{{ __('مرجعنا') }}</th>
            <th>{{ __('مرجع البوابة') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('الدفع') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            <tr>
              <td>{{ $r->id }}</td>
              <td>{{ optional($r->customer)->name ?? ('#'.$r->customer_id) }}</td>
              <td>{{ optional($r->business)->name ?? ('#'.$r->business_id) }}</td>
              <td>{{ $money($r->amount) }} {{ $r->currency }}</td>
              <td>
                <span class="a2-badge a2-badge-{{ $r->routed_to === 'merchant' ? 'success' : 'muted' }}">
                  {{ $r->routed_to === 'merchant' ? __('التاجر') : __('المنصّة') }}
                </span>
              </td>
              <td dir="ltr">{{ $r->merchant_ref }}</td>
              <td dir="ltr">{{ $r->gateway_ref ?? '—' }}</td>
              <td><span class="a2-badge a2-badge-{{ $r->status }}">{{ $r->status }}</span></td>
              <td dir="ltr">{{ optional($r->paid_at)->format('Y-m-d H:i') ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="9" class="a2-empty">{{ __('لا توجد مدفوعات.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="a2-pager">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
