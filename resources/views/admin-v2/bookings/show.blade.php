{{-- resources/views/admin-v2/bookings/show.blade.php --}}
@extends('admin-v2.layouts.master')

@section('title','Booking Details')
@section('body_class','admin-v2-bookings show')

@section('content')
@php
  /** @var \App\Models\Booking $booking */
  /** @var \App\Models\Deposit|null $deposit */

  $meta = $booking->meta ?? [];
  $exec = $meta['_execution_fee'] ?? null;

  // confirmations may be passed from controller
  $clientConfirmed = $clientConfirmed ?? ((int)($deposit->client_confirmed ?? 0) === 1);
  $businessConfirmed = $businessConfirmed ?? ((int)($deposit->business_confirmed ?? 0) === 1);

  $depositRequired = (bool) data_get($booking, 'business.booking_hold_enabled', false)
      && (float) data_get($booking, 'business.booking_hold_amount', 0) > 0;
@endphp
@if(!empty($depositPolicy['required']))
  <div class="a2-alert a2-alert-warning" style="margin-top:10px;">
    هذا البزنس <b>يشترط Deposit</b>.
    قيمة الـ Hold: <b>{{ number_format((float)$depositPolicy['hold'], 2) }}</b>
    — الحد الأقصى المسموح: <b>{{ $depositPolicy['percent'] }}%</b>
    ({{ number_format((float)$depositPolicy['max'], 2) }})
  </div>
@else
  <div class="a2-alert a2-alert-info" style="margin-top:10px;">
    هذا البزنس لا يشترط Deposit (اختياري).
  </div>
@endif

<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <div class="a2-title">تفاصيل الحجز</div>
      <div class="a2-hint">Booking #{{ $booking->id }}</div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">رجوع</a>
      <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookings.edit', $booking) }}">تعديل</a>
    </div>
  </div>

  <div class="a2-card" style="padding:14px;">
    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;">
      <div>
        <div class="a2-hint">Status</div>
        <div style="font-weight:800;">{{ $booking->status }}</div>
      </div>
      <div>
        <div class="a2-hint">Price</div>
        <div style="font-weight:800;">{{ number_format((float)$booking->price, 2) }}</div>
      </div>
      <div>
        <div class="a2-hint">Client</div>
        <div style="font-weight:700;">
          {{ data_get($booking,'user.name') ?? ('#'.$booking->user_id) }}
        </div>
      </div>
      <div>
        <div class="a2-hint">Business</div>
        <div style="font-weight:700;">
          {{ data_get($booking,'business.name') ?? ('#'.$booking->business_id) }}
        </div>
      </div>
    </div>

    <div style="margin-top:12px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
      <div class="a2-card" style="padding:12px;">
        <div class="a2-title" style="font-size:14px;">تأكيد الطرفين</div>
        <div class="a2-hint" style="margin-top:4px;">
          شرط أساسي لبدء التنفيذ (حتى بدون Deposit)
        </div>

        <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;">
          <span class="a2-badge {{ $clientConfirmed ? 'a2-badge-success' : 'a2-badge-warning' }}">
            Client: {{ $clientConfirmed ? 'Confirmed' : 'Pending' }}
          </span>
          <span class="a2-badge {{ $businessConfirmed ? 'a2-badge-success' : 'a2-badge-warning' }}">
            Business: {{ $businessConfirmed ? 'Confirmed' : 'Pending' }}
          </span>
        </div>

        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
          <form method="POST" action="{{ route('admin.bookings.start_confirm.client', $booking) }}">
            @csrf
            <button class="a2-btn a2-btn-ghost" type="submit">تأكيد العميل</button>
          </form>

          <form method="POST" action="{{ route('admin.bookings.start_confirm.business', $booking) }}">
            @csrf
            <button class="a2-btn a2-btn-ghost" type="submit">تأكيد البزنس</button>
          </form>
        </div>
      </div>

      <div class="a2-card" style="padding:12px;">
        <div class="a2-title" style="font-size:14px;">Deposit</div>
        <div class="a2-hint" style="margin-top:4px;">
          {{ $depositRequired ? 'مطلوب (صاحب الخدمة مفعل الـ hold)' : 'اختياري (غير مطلوب)' }}
        </div>

        @if($deposit)
          <div style="margin-top:10px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
            <div>
              <div class="a2-hint">Status</div>
              <div style="font-weight:800;">{{ $deposit->status }}</div>
            </div>
            <div>
              <div class="a2-hint">Total</div>
              <div style="font-weight:800;">{{ number_format((float)$deposit->total_amount, 2) }}</div>
            </div>
            <div>
              <div class="a2-hint">Client amount</div>
              <div style="font-weight:700;">{{ number_format((float)$deposit->client_amount, 2) }}</div>
            </div>
            <div>
              <div class="a2-hint">Business amount</div>
              <div style="font-weight:700;">{{ number_format((float)$deposit->business_amount, 2) }}</div>
            </div>
          </div>
        @else
          <div class="a2-alert a2-alert-warning" style="margin-top:10px;">
            لا يوجد Deposit لهذا الحجز.
          </div>
        @endif

        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
          <form method="POST" action="{{ route('admin.bookings.deposit.freeze', $booking) }}">
            @csrf
            <button class="a2-btn a2-btn-primary" type="submit">Freeze</button>
          </form>

          <form method="POST" action="{{ route('admin.bookings.deposit.release', $booking) }}">
            @csrf
            <button class="a2-btn a2-btn-ghost" type="submit">Release</button>
          </form>

          <form method="POST" action="{{ route('admin.bookings.deposit.refund', $booking) }}">
            @csrf
            <button class="a2-btn a2-btn-ghost" type="submit">Refund</button>
          </form>

          <form method="POST" action="{{ route('admin.bookings.deposit.dispute.open', $booking) }}">
            @csrf
            <button class="a2-btn a2-btn-danger" type="submit">Open Dispute</button>
          </form>
        </div>
      </div>
    </div>

    <div class="a2-card" style="padding:12px;margin-top:12px;">
      <div class="a2-title" style="font-size:14px;">رسوم بدء التنفيذ</div>
      <div class="a2-hint" style="margin-top:4px;">
        code: <b>booking_execution_fee</b> — يتم خصمها عند الانتقال إلى in_progress بعد تأكيد الطرفين
      </div>

      @if(is_array($exec) && !empty($exec['charged_at']))
        <div style="margin-top:10px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
          <div>
            <div class="a2-hint">Client fee</div>
            <div style="font-weight:800;">{{ number_format((float)($exec['client_amount'] ?? 0), 2) }}</div>
          </div>
          <div>
            <div class="a2-hint">Business fee</div>
            <div style="font-weight:800;">{{ number_format((float)($exec['business_amount'] ?? 0), 2) }}</div>
          </div>
          <div>
            <div class="a2-hint">Charged at</div>
            <div style="font-weight:700;">{{ $exec['charged_at'] }}</div>
          </div>
        </div>
      @else
        <div class="a2-alert a2-alert-info" style="margin-top:10px;">
          لم يتم خصم رسوم التنفيذ بعد.
        </div>
      @endif
    </div>

    @if(!empty($booking->notes))
      <div class="a2-card" style="padding:12px;margin-top:12px;">
        <div class="a2-title" style="font-size:14px;">ملاحظات</div>
        <div style="margin-top:8px;white-space:pre-wrap;">{{ $booking->notes }}</div>
      </div>
    @endif
  </div>
</div>
@endsection