{{-- resources/views/admin-v2/bookings/show.blade.php --}}
@extends('admin-v2.layouts.master')

@section('title','Booking Details')
@section('body_class','admin-v2-bookings')

@section('content')
@php
  /** @var \App\Models\Booking $booking */
  /** @var \App\Models\Deposit|null $deposit */

  $stMap = \App\Models\Booking::statusOptions();

  $badgeClass = function(?string $st): string {
    return match((string)$st) {
      'accepted'   => 'a2-badge a2-badge-success',
      'rejected'   => 'a2-badge a2-badge-danger',
      'cancelled'  => 'a2-badge a2-badge-muted',
      'in_progress'=> 'a2-badge a2-badge-primary',
      'completed'  => 'a2-badge a2-badge-success',
      default      => 'a2-badge a2-badge-warning',
    };
  };

  $depositBadge = function(?string $st): string {
    return match((string)$st) {
      'frozen'   => 'a2-badge a2-badge-warning',
      'released' => 'a2-badge a2-badge-success',
      'refunded' => 'a2-badge a2-badge-muted',
      'dispute'  => 'a2-badge a2-badge-danger',
      default    => 'a2-badge a2-badge-muted',
    };
  };

  $fmtMoney = function($v) {
    if ($v === null || $v === '') return '—';
    return number_format((float)$v, 2);
  };

  $serviceName = null;
  if ($booking->relationLoaded('service') && $booking->service) {
    $serviceName = $booking->service->name_ar ?: ($booking->service->name_en ?: ('Service #'.$booking->service->id));
  } elseif ($booking->service_id) {
    $serviceName = 'Service #'.$booking->service_id;
  }

  $userName = null;
  if ($booking->relationLoaded('user') && $booking->user) {
    $userName = $booking->user->name ?: ('User #'.$booking->user_id);
  } elseif ($booking->user_id) {
    $userName = 'User #'.$booking->user_id;
  }

  $businessName = null;
  if ($booking->relationLoaded('business') && $booking->business) {
    $businessName = $booking->business->name ?: ('Business #'.$booking->business_id);
  } elseif ($booking->business_id) {
    $businessName = 'Business #'.$booking->business_id;
  }

  $pricing = null;
  if (is_array($booking->meta ?? null) && isset($booking->meta['_pricing']) && is_array($booking->meta['_pricing'])) {
    $pricing = $booking->meta['_pricing'];
  }

  $can = function(string $routeName) {
    return \Illuminate\Support\Facades\Route::has($routeName);
  };
@endphp

<div class="a2-page">
  <div class="a2-card" style="max-width:980px;margin:0 auto;">

    {{-- Header --}}
    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div>
        <h2 class="a2-title">تفاصيل الحجز</h2>
        <div class="a2-hint">ID #{{ $booking->id }}</div>
      </div>

      <div class="a2-actionsbar" style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">رجوع</a>
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookings.edit', $booking) }}">تعديل</a>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
      <div class="a2-alert a2-alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{!! implode('<br>', $errors->all()) !!}</div>
    @endif

    {{-- Top Cards: Client / Business --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="a2-card" style="padding:14px;">
        <div class="a2-hint">العميل (Client)</div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          @if($booking->user_id && $can('admin.users.show'))
            <a class="a2-link" href="{{ route('admin.users.show', $booking->user_id) }}">{{ $userName ?? '—' }}</a>
          @else
            <div style="font-weight:700;">{{ $userName ?? '—' }}</div>
          @endif

          <div class="a2-muted">
            @if($booking->relationLoaded('user') && $booking->user)
              {{ $booking->user->phone ?: ($booking->user->email ?: '') }}
            @endif
          </div>

          <div class="a2-hint">ID: {{ $booking->user_id ?: '—' }}</div>
        </div>
      </div>

      <div class="a2-card" style="padding:14px;">
        <div class="a2-hint">مقدم الخدمة (Business)</div>
        <div style="display:flex;flex-direction:column;gap:4px;">
          @if($booking->business_id && $can('admin.users.show'))
            <a class="a2-link" href="{{ route('admin.users.show', $booking->business_id) }}">{{ $businessName ?? '—' }}</a>
          @else
            <div style="font-weight:700;">{{ $businessName ?? '—' }}</div>
          @endif

          <div class="a2-muted">
            @if($booking->relationLoaded('business') && $booking->business)
              {{ $booking->business->phone ?: ($booking->business->email ?: '') }}
            @endif
          </div>

          <div class="a2-hint">
            ID: {{ $booking->business_id ?: '—' }}
            @if($booking->relationLoaded('business') && $booking->business && !empty($booking->business->code))
              • Code: {{ $booking->business->code }}
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Booking Info --}}
    <div class="a2-card" style="padding:14px;margin-top:12px;">
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;align-items:start;">

        <div>
          <div class="a2-hint">الخدمة</div>
          <div style="font-weight:700;">{{ $serviceName ?: '—' }}</div>
          @if($booking->relationLoaded('service') && $booking->service)
            <div class="a2-muted">ID: {{ $booking->service->id }} • Business: {{ $booking->service->business_id }}</div>
          @endif
        </div>

        <div>
          <div class="a2-hint">البداية</div>
          <div style="font-weight:700;">{{ $booking->starts_at ? $booking->starts_at->format('Y-m-d H:i') : '—' }}</div>
          <div class="a2-muted">TZ: {{ $booking->timezone ?: '—' }}</div>
        </div>

        <div>
          <div class="a2-hint">النهاية</div>
          <div style="font-weight:700;">{{ $booking->ends_at ? $booking->ends_at->format('Y-m-d H:i') : '—' }}</div>
          <div class="a2-muted">
            @if($booking->duration_value && $booking->duration_unit)
              Duration: {{ (int)$booking->duration_value }} {{ $booking->duration_unit }}
            @else
              Duration: —
            @endif
            @if($booking->all_day) • all_day @endif
          </div>
        </div>

        <div>
          <div class="a2-hint">السعر / الحالة</div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <div style="font-weight:800;">{{ $booking->price !== null ? $fmtMoney($booking->price) : '—' }}</div>
            <span class="{{ $badgeClass($booking->status) }}">{{ $stMap[$booking->status] ?? $booking->status }}</span>
          </div>
          <div class="a2-muted">
            Created: {{ $booking->created_at ? $booking->created_at->format('Y-m-d H:i') : '—' }}
            <br>
            Updated: {{ $booking->updated_at ? $booking->updated_at->format('Y-m-d H:i') : '—' }}
          </div>
            @if(!empty($booking->meta['_execution_fee']['amount']))
              <div class="a2-alert a2-alert-warning" style="margin-top:10px;">
                رسوم بدء التنفيذ: {{ number_format((float)$booking->meta['_execution_fee']['amount'], 2) }}
                <span class="a2-hint">({{ $booking->meta['_execution_fee']['charged_at'] ?? '' }})</span>
              </div>
            @endif
        </div>

        <div>
          <div class="a2-hint">Quantity</div>
          <div>{{ $booking->quantity ?: '—' }}</div>
        </div>

        <div>
          <div class="a2-hint">Party size</div>
          <div>{{ $booking->party_size ?: '—' }}</div>
        </div>

        <div style="grid-column: span 2;">
          <div class="a2-hint">ملاحظات</div>
          <div style="white-space:pre-wrap;">{{ $booking->notes ?: '—' }}</div>
        </div>

      </div>
    </div>

    {{-- Pricing Breakdown --}}
    @if($pricing)
      <div class="a2-card" style="padding:14px;margin-top:12px;">
        <div class="a2-title" style="font-size:16px;margin-bottom:6px;">تفاصيل حساب السعر</div>
        <div class="a2-hint" style="margin-bottom:10px;">(محفوظة داخل meta._pricing)</div>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
          <div>
            <div class="a2-hint">Base price</div>
            <div style="font-weight:700;">{{ $fmtMoney($pricing['base_price'] ?? null) }}</div>
          </div>
          <div>
            <div class="a2-hint">Base minutes</div>
            <div style="font-weight:700;">{{ (int)($pricing['base_minutes'] ?? 0) ?: '—' }}</div>
          </div>
          <div>
            <div class="a2-hint">Booking minutes</div>
            <div style="font-weight:700;">{{ (int)($pricing['booking_minutes'] ?? 0) ?: '—' }}</div>
          </div>
          <div>
            <div class="a2-hint">Ratio</div>
            <div style="font-weight:700;">{{ isset($pricing['ratio']) ? number_format((float)$pricing['ratio'], 4) : '—' }}</div>
          </div>
        </div>
      </div>
    @endif

    {{-- Deposit / Wallet Hold --}}
    <div class="a2-card" style="padding:14px;margin-top:12px;">
      <div class="a2-header" style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div>
          <div class="a2-title" style="font-size:16px;">جدية الحجز (Deposit / Escrow)</div>
          <div class="a2-hint">
            تجميد من الطرفين (Client + Business). عند النزاع يتحول إلى dispute.
            فك التجميد/الإرجاع يكون بعد موافقة الطرفين (حسب منطقك في الكنترول).
          </div>
        </div>

        <div class="a2-actionsbar" style="display:flex;gap:8px;flex-wrap:wrap;">
          {{-- Confirm Start Execution --}}
        <form method="POST" action="{{ route('admin.bookings.start_confirm.client', $booking) }}" style="display:inline;">
  @csrf
  <button class="a2-btn a2-btn-primary" type="submit">تأكيد (Client)</button>
</form>

<form method="POST" action="{{ route('admin.bookings.start_confirm.business', $booking) }}" style="display:inline;">
  @csrf
  <button class="a2-btn a2-btn-ghost" type="submit">تأكيد (Business)</button>
</form>

        

          {{-- Legacy holds endpoints (إن كنت تستخدم wallet_holds) --}}
          @if($can('admin.bookings.holds.hold'))
            <form method="POST" action="{{ route('admin.bookings.holds.hold', $booking) }}" style="display:inline;">
              @csrf
              <button class="a2-btn a2-btn-ghost" type="submit">تجميد للطرفين (Legacy)</button>
            </form>
          @endif

          @if($can('admin.bookings.holds.dispute'))
            <form method="POST" action="{{ route('admin.bookings.holds.dispute', $booking) }}" style="display:inline;">
              @csrf
              <button class="a2-btn a2-btn-ghost" type="submit">نزاع (Legacy)</button>
            </form>
          @endif

          @if($can('admin.bookings.holds.release'))
            <form method="POST" action="{{ route('admin.bookings.holds.release', $booking) }}" style="display:inline;" onsubmit="return confirm('فك التجميد للطرفين؟');">
              @csrf
              <button class="a2-btn a2-btn-danger" type="submit">فك التجميد (Legacy)</button>
            </form>
          @endif
        </div>
      </div>

      @if(!$deposit)
        <div class="a2-alert a2-alert-warning">
          لا يوجد Deposit مرتبط بهذا الحجز حتى الآن.
          <div class="a2-hint">سيتم إنشاؤه تلقائيًا عند تغيير حالة الحجز إلى accepted (حسب الكنترول).</div>
        </div>
      @else
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;align-items:start;">
          <div>
            <div class="a2-hint">Deposit ID</div>
            <div style="font-weight:800;">#{{ $deposit->id }}</div>
          </div>

          <div>
            <div class="a2-hint">Status</div>
            <div style="display:flex;gap:8px;align-items:center;">
              <span class="{{ $depositBadge($deposit->status) }}">{{ $deposit->status }}</span>
            </div>
          </div>

          <div>
            <div class="a2-hint">Total</div>
            <div style="font-weight:800;">{{ $fmtMoney($deposit->total_amount) }}</div>
            <div class="a2-muted">
              Client: {{ $fmtMoney($deposit->client_amount) }} ({{ (int)$deposit->client_percent }}%)
              <br>
              Business: {{ $fmtMoney($deposit->business_amount) }} ({{ (int)$deposit->business_percent }}%)
            </div>
          </div>

          <div>
            <div class="a2-hint">Target</div>
            <div class="a2-muted" style="word-break:break-word;">
              {{ $deposit->target_type }} #{{ $deposit->target_id }}
            </div>
          </div>

          <div>
            <div class="a2-hint">Client confirmed</div>
            <div style="font-weight:700;">
              @if((int)$deposit->client_confirmed === 1)
                ✅ Yes
              @else
                ❌ No
              @endif
            </div>
          </div>

          <div>
            <div class="a2-hint">Business confirmed</div>
            <div style="font-weight:700;">
              @if((int)$deposit->business_confirmed === 1)
                ✅ Yes
              @else
                ❌ No
              @endif
            </div>
          </div>

          <div>
            <div class="a2-hint">Released at</div>
            <div class="a2-muted">{{ $deposit->released_at ? \Carbon\Carbon::parse($deposit->released_at)->format('Y-m-d H:i') : '—' }}</div>
          </div>

          <div>
            <div class="a2-hint">Refunded at</div>
            <div class="a2-muted">{{ $deposit->refunded_at ? \Carbon\Carbon::parse($deposit->refunded_at)->format('Y-m-d H:i') : '—' }}</div>
          </div>
        </div>

        {{-- Dispute Card (shows only if dispute) --}}
        @if((string)$deposit->status === 'dispute')
          <div class="a2-card" style="padding:14px;margin-top:12px;border:1px solid var(--a2-danger-bg);">
            <div class="a2-title" style="font-size:16px;margin-bottom:6px;">نزاع (Dispute)</div>
            <div class="a2-hint" style="margin-bottom:10px;">
              عند فتح النزاع يبقى المبلغ مجمّد. فك النزاع/فك التجميد يكون بعد موافقة الطرفين (طبقًا لمنطقك في الكنترول).
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="a2-card" style="padding:12px;">
                <div class="a2-hint">Client approval</div>
                <div style="font-weight:800;">{{ (int)$deposit->client_confirmed === 1 ? '✅ موافق' : '⏳ لم يؤكد بعد' }}</div>
              </div>
              <div class="a2-card" style="padding:12px;">
                <div class="a2-hint">Business approval</div>
                <div style="font-weight:800;">{{ (int)$deposit->business_confirmed === 1 ? '✅ موافق' : '⏳ لم يؤكد بعد' }}</div>
              </div>
            </div>

            <div class="a2-hint" style="margin-top:10px;">
              ملاحظة: الأزرار (تأكيد Client/Business) بالأعلى هي التي تغير أعلام التأكيد.
              بعد اكتمال تأكيد الطرفين يمكنك تنفيذ release/refund تلقائيًا من الكنترول حسب القاعدة التي تريدها.
            </div>
          </div>
        @endif

      @endif
    </div>

    {{-- Meta (debug) --}}
    @if(!empty($booking->meta))
      <div class="a2-card" style="padding:14px;margin-top:12px;">
        <div class="a2-title" style="font-size:16px;margin-bottom:6px;">Meta (JSON)</div>
        <pre style="margin:0;white-space:pre-wrap;word-break:break-word;background:var(--a2-bg);padding:12px;border-radius:12px;border:1px solid var(--a2-border);">{{ json_encode($booking->meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>
      </div>
    @endif

  </div>
</div>
@endsection