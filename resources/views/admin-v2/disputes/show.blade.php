<<<<<<< HEAD
{{-- resources/views/admin-v2/disputes/show.blade.php --}}
@extends('admin-v2.layouts.app')

@section('content')
<div class="a2-card" style="padding:14px;">
  <div class="a2-header" style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <div>
      <div class="a2-title" style="font-size:16px;">تفاصيل النزاع</div>
      <div class="a2-hint">Deposit #{{ $deposit->id }}</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a class="a2-btn a2-btn-ghost" href="{{ route('admin.disputes.index') }}">رجوع</a>
      @if($deposit->target_type === \App\Models\Booking::class)
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookings.show', $deposit->target_id) }}">فتح الحجز</a>
      @endif
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
    <div>
      <div class="a2-hint">Status</div>
      <div style="font-weight:800;">{{ $deposit->status }}</div>
    </div>
    <div>
      <div class="a2-hint">Total</div>
      <div style="font-weight:800;">{{ number_format((float)$deposit->total_amount, 2) }}</div>
    </div>
    <div>
      <div class="a2-hint">Client</div>
      <div style="font-weight:700;">{{ number_format((float)$deposit->client_amount, 2) }} ({{ (int)$deposit->client_percent }}%)</div>
    </div>
    <div>
      <div class="a2-hint">Business</div>
      <div style="font-weight:700;">{{ number_format((float)$deposit->business_amount, 2) }} ({{ (int)$deposit->business_percent }}%)</div>
    </div>
  </div>

  <div class="a2-alert a2-alert-warning" style="margin-top:12px;">
    هذه الصفحة للعرض حاليًا. لو تريد أزرار (Release / Refund / Agree) سأربطها مباشرة بـ routes الموجودة في BookingController.
  </div>
</div>
=======
@extends('admin-v2.layouts.master')
@section('title','Dispute')
@section('body_class','admin-v2-disputes')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">تفاصيل النزاع</h2>
        <div class="a2-hint">Booking #{{ $booking->id }} — Deposit #{{ $deposit->id }}</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.disputes.index') }}">رجوع</a>
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookings.show', $booking) }}">فتح الحجز</a>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
      <div class="a2-card" style="padding:12px;">
        <div class="a2-hint">Status</div>
        <div style="font-weight:700">{{ $deposit->status }}</div>
      </div>

      <div class="a2-card" style="padding:12px;">
        <div class="a2-hint">Total Amount</div>
        <div style="font-weight:700">{{ number_format((float)$deposit->total_amount, 2) }}</div>
      </div>

      <div class="a2-card" style="padding:12px;">
        <div class="a2-hint">Client / Business</div>
        <div>
          Client: {{ number_format((float)$deposit->client_amount, 2) }} ({{ (int)$deposit->client_percent }}%)<br>
          Business: {{ number_format((float)$deposit->business_amount, 2) }} ({{ (int)$deposit->business_percent }}%)
        </div>
      </div>

      <div class="a2-card" style="padding:12px;">
        <div class="a2-hint">Opened</div>
        <div>
          {{ $deposit->dispute_opened_at ? \Carbon\Carbon::parse($deposit->dispute_opened_at)->format('Y-m-d H:i') : '—' }}
          <div class="a2-muted">by: {{ $deposit->dispute_opened_by ?: '—' }}</div>
        </div>
      </div>
    </div>

    @if(!empty($deposit->dispute_reason))
      <div class="a2-card" style="padding:12px;margin-top:12px;">
        <div class="a2-hint">Reason</div>
        <div>{{ $deposit->dispute_reason }}</div>
      </div>
    @endif
  </div>
</div>
>>>>>>> 712afe4 (Fix booking update bug + fix disputes.show 404)
@endsection