@extends('admin-v2.layouts.master')

@section('title','Booking Details')
@section('body_class','admin-v2-bookings show')

@section('content')
@php
  $meta = $booking->meta ?? [];
  $exec = $meta['_execution_fee'] ?? null;

  $deposit = $deposit ?? null;
  $depositPolicy = $depositPolicy ?? [
      'required' => false,
      'hold' => 0,
      'percent' => 0,
      'max' => 0,
      'configured_percent' => 0,
      'source' => 'business_service_price',
  ];

  $clientConfirmed = $clientConfirmed ?? ((int) data_get($deposit, 'client_confirmed', 0) === 1);
  $businessConfirmed = $businessConfirmed ?? ((int) data_get($deposit, 'business_confirmed', 0) === 1);

  $depositStatus = (string) data_get($deposit, 'status', '');
  $canFreeze  = $deposit && !in_array($depositStatus, ['held','released','refunded'], true);
  $canRelease = $deposit && in_array($depositStatus, ['held','frozen'], true);
  $canRefund  = $deposit && in_array($depositStatus, ['held','frozen','dispute'], true);
  $canDispute = $deposit && !in_array($depositStatus, ['dispute','released','refunded'], true);
@endphp

@if(!empty($depositPolicy['required']))
  <div class="a2-alert a2-alert-warning" style="margin-top:10px;">
    هذا الحجز يتطلب <b>Deposit</b>.
    قيمة الـ Hold: <b>{{ number_format((float)$depositPolicy['hold'], 2) }}</b>
    — الحد الأقصى المسموح: <b>{{ $depositPolicy['percent'] }}%</b>
    ({{ number_format((float)$depositPolicy['max'], 2) }})
    — النسبة المطبقة: <b>{{ (int)($depositPolicy['configured_percent'] ?? 0) }}%</b>
    — المصدر: <b>{{ $depositPolicy['source'] }}</b>
  </div>
@else
  <div class="a2-alert a2-alert-info" style="margin-top:10px;">
    هذا الحجز لا يتطلب Deposit.
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
        <div style="font-weight:700;">{{ data_get($booking,'user.name') ?? ('#'.$booking->user_id) }}</div>
      </div>
      <div>
        <div class="a2-hint">Business</div>
        <div style="font-weight:700;">{{ data_get($booking,'business.name') ?? ('#'.$booking->business_id) }}</div>
      </div>
    </div>

    <div style="margin-top:12px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
      <div class="a2-card" style="padding:12px;">
        <div class="a2-title" style="font-size:14px;">الخدمة / العنصر المحجوز</div>

        <div style="margin-top:10px;">
          <div class="a2-hint">Service</div>
          <div style="font-weight:700;">
            {{ data_get($booking,'service.name_ar') ?? data_get($booking,'service.name_en') ?? data_get($booking,'service.key') ?? '—' }}
          </div>
        </div>

        <div style="margin-top:10px;">
          <div class="a2-hint">Bookable Item</div>
          @if($booking->bookable)
            <div style="font-weight:700;">{{ $booking->bookable->title ?? '—' }}</div>
            <div class="a2-hint">
              {{ $booking->bookable->item_type ?? '' }}
              @if(!empty($booking->bookable->code)) — {{ $booking->bookable->code }} @endif
            </div>
          @else
            <div style="font-weight:700;">—</div>
          @endif
        </div>

        <div style="margin-top:10px;">
          <div class="a2-hint">Time</div>
          <div style="font-weight:700;">
            {{ optional($booking->starts_at)->format('Y-m-d H:i') ?? '—' }}
            @if($booking->ends_at)
              — {{ optional($booking->ends_at)->format('Y-m-d H:i') }}
            @endif
          </div>
        </div>
      </div>

      <div class="a2-card" style="padding:12px;">
        <div class="a2-title" style="font-size:14px;">تأكيد الطرفين</div>
        <div class="a2-hint" style="margin-top:4px;">
          شرط أساسي لبدء التنفيذ
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
          @unless($clientConfirmed)
            <form method="POST" action="{{ route('admin.bookings.start_confirm.client', $booking) }}">
              @csrf
              <button class="a2-btn a2-btn-ghost" type="submit">تأكيد العميل</button>
            </form>
          @endunless

          @unless($businessConfirmed)
            <form method="POST" action="{{ route('admin.bookings.start_confirm.business', $booking) }}">
              @csrf
              <button class="a2-btn a2-btn-ghost" type="submit">تأكيد البزنس</button>
            </form>
          @endunless
        </div>
      </div>
    </div>

    <div style="margin-top:12px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
      <div class="a2-card" style="padding:12px;">
        <div class="a2-title" style="font-size:14px;">Deposit</div>
        <div class="a2-hint" style="margin-top:4px;">
          {{ !empty($depositPolicy['required']) ? 'مطلوب لهذا الحجز' : 'غير مطلوب لهذا الحجز' }}
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

        @if(!empty($depositPolicy['required']) && !$deposit)
          <div style="margin-top:10px;">
            <form method="POST" action="{{ route('admin.bookings.deposit.freeze', $booking) }}">
              @csrf
              <button class="a2-btn a2-btn-primary" type="submit">Create Deposit</button>
            </form>
          </div>
        @endif
        ////////////////
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
        ////////////////
   {{--
   يستبدل بعد انتهاء الاختبار بالكود اعلاه الخاص بالتحكم في حالة الديبوزت حسب الصلاحيات والحالة الحالية للديبوزت، وليس مجرد إظهار الأزرار كلها بدون اعتبار للحالة.
   @if($deposit)
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
            @if($canFreeze)
                <form method="POST" action="{{ route('admin.bookings.deposit.freeze', $booking) }}">
                    @csrf
                    <button class="a2-btn a2-btn-primary" type="submit">Freeze</button>
                </form>
            @endif

            @if($canRelease)
                <form method="POST" action="{{ route('admin.bookings.deposit.release', $booking) }}">
                    @csrf
                    <button class="a2-btn a2-btn-ghost" type="submit">Release</button>
                </form>
            @endif

            @if($canRefund)
                <form method="POST" action="{{ route('admin.bookings.deposit.refund', $booking) }}">
                    @csrf
                    <button class="a2-btn a2-btn-ghost" type="submit">Refund</button>
                </form>
            @endif

            @if($canDispute)
                <form method="POST" action="{{ route('admin.bookings.deposit.dispute.open', $booking) }}">
                    @csrf
                    <button class="a2-btn a2-btn-danger" type="submit">Open Dispute</button>
                </form>
            @endif
        </div>
    @elseif(!empty($depositPolicy['required']))
        <div style="margin-top:10px;">
            <form method="POST" action="{{ route('admin.bookings.deposit.freeze', $booking) }}">
                @csrf
                <button class="a2-btn a2-btn-primary" type="submit">Create Deposit</button>
            </form>
        </div>
    @endif
--}}
    
      ///////
      </div>

      <div class="a2-card" style="padding:12px;">
        <div class="a2-title" style="font-size:14px;">رسوم التنفيذ</div>
        <div class="a2-hint" style="margin-top:4px;">
          code: <b>{{ data_get($exec, 'code', 'platform_service_fee') }}</b>
        </div>

        <div style="margin-top:10px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
          <div>
            <div class="a2-hint">Fee Type</div>
            <div style="font-weight:700;">{{ data_get($exec, 'fee_type', '-') ?: '-' }}</div>
          </div>
          <div>
            <div class="a2-hint">Fee Value</div>
            <div style="font-weight:700;">
              {{ data_get($exec, 'fee_value') !== null ? number_format((float)data_get($exec,'fee_value'), 2) : '-' }}
            </div>
          </div>
          <div>
            <div class="a2-hint">Platform Amount</div>
            <div style="font-weight:800;">{{ number_format((float)data_get($exec, 'platform_amount', 0), 2) }}</div>
          </div>
          <div>
            <div class="a2-hint">Charged at</div>
            <div style="font-weight:700;">{{ data_get($exec, 'charged_at', '—') ?: '—' }}</div>
          </div>
        </div>
      </div>
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