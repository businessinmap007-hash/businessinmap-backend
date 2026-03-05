{{-- resources/views/admin-v2/bookings/_form.blade.php --}}

@php
  /** @var \App\Models\Booking|null $booking */
  $booking = $booking ?? null;

  $statusOptions = $statusOptions ?? (\App\Models\Booking::statusOptions());

  // safe old() helper
  $val = fn($key, $default=null) => old($key, data_get($booking, $key, $default));
@endphp

<div class="a2-card" style="padding:14px;">
  <div class="a2-header" style="margin-bottom:10px;">
    <div>
      <div class="a2-title" style="font-size:16px;">بيانات الحجز</div>
      <div class="a2-hint">الحقول الأساسية للحجز</div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
    <div>
      <label class="a2-label">Client (user_id)</label>
      <input class="a2-input" name="user_id" type="number" value="{{ $val('user_id') }}" required>
    </div>

    <div class="a2-alert a2-alert-info" style="margin-bottom:12px;">
      ملاحظة: إذا كان البزنس مفعل Deposit Hold فسيصبح إلزاميًا، وبحد أقصى <b>20%</b> من سعر الخدمة.
    </div>

    <div>
      <label class="a2-label">Business (business_id)</label>
      <input class="a2-input" name="business_id" type="number" value="{{ $val('business_id') }}" required>
    </div>

    <div style="grid-column:1 / -1;">
      <label class="a2-label">Service (service_id)</label>
      <input class="a2-input" name="service_id" type="number" value="{{ $val('service_id') }}" required>
      <div class="a2-hint" style="margin-top:6px;">
        ملاحظة: السعر يتم حسابه تلقائيًا من الخدمة داخل الكنترولر.
      </div>
    </div>

    <div>
      <label class="a2-label">Date</label>
      <input class="a2-input" name="date" type="date" value="{{ $val('date') }}" required>

    </div>

    <div>
      <label class="a2-label">Time</label>
      <input class="a2-input" name="time" type="time" value="{{ $val('time') }}" required>
    </div>

    <div>
      <label class="a2-label">Starts at</label>
      <input class="a2-input" name="starts_at" type="datetime-local"
             value="{{ $val('starts_at') ? \Carbon\Carbon::parse($val('starts_at'))->format('Y-m-d\TH:i') : '' }}">
    </div>

    <div>
      <label class="a2-label">Ends at</label>
      <input class="a2-input" name="ends_at" type="datetime-local"
             value="{{ $val('ends_at') ? \Carbon\Carbon::parse($val('ends_at'))->format('Y-m-d\TH:i') : '' }}">
    </div>

    <div>
      <label class="a2-label">Duration value</label>
      <input class="a2-input" name="duration_value" type="number" min="1" value="{{ $val('duration_value') }}">
    </div>

    <div>
      <label class="a2-label">Duration unit</label>
      <select class="a2-input" name="duration_unit">
        @php
          $units = ['minute'=>'minute','hour'=>'hour','day'=>'day','week'=>'week','month'=>'month','year'=>'year'];
          $sel = (string) $val('duration_unit', '');
        @endphp
        <option value="">—</option>
        @foreach($units as $k=>$label)
          <option value="{{ $k }}" @selected($sel===$k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div>
      <label class="a2-label">Quantity</label>
      <input class="a2-input" name="quantity" type="number" min="1" value="{{ $val('quantity', 1) }}">
    </div>

    <div>
      <label class="a2-label">Party size</label>
      <input class="a2-input" name="party_size" type="number" min="1" value="{{ $val('party_size') }}">
    </div>

    <div>
      <label class="a2-label">All day</label>
      <select class="a2-input" name="all_day">
        @php $allDay = (string)($val('all_day', 0)); @endphp
        <option value="0" @selected($allDay==='0')>No</option>
        <option value="1" @selected($allDay==='1')>Yes</option>
      </select>
    </div>

    <div>
      <label class="a2-label">Timezone</label>
      <input class="a2-input" name="timezone" type="text" value="{{ $val('timezone') }}" placeholder="Africa/Cairo">
    </div>

    <div style="grid-column:1 / -1;">
      <label class="a2-label">Status</label>
      @php $status = (string)$val('status', \App\Models\Booking::STATUS_PENDING); @endphp
      <select class="a2-input" name="status" required>
        @foreach($statusOptions as $k=>$label)
          <option value="{{ $k }}" @selected($status===$k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>

    <div style="grid-column:1 / -1;">
      <label class="a2-label">Notes</label>
      <textarea class="a2-input" name="notes" rows="4">{{ $val('notes') }}</textarea>
    </div>
  </div>

  <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;">
    <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'حفظ' }}</button>
    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">رجوع</a>
  </div>
</div>