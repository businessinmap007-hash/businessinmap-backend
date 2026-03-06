@php
  $row = $serviceFee ?? null;
  $val = fn($k,$d=null) => old($k, data_get($row,$k,$d));
@endphp

<div class="a2-card" style="padding:14px;">
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
    <div>
      <div class="a2-hint">Code</div>
      <input class="a2-input" name="code" value="{{ $val('code') }}" required>
      <div class="a2-hint" style="margin-top:6px;">مثال: booking_execution_fee</div>
    </div>

    <div>
      <div class="a2-hint">Active</div>
      <select class="a2-input" name="is_active" required>
        <option value="1" @selected((string)$val('is_active','1')==='1')>Yes</option>
        <option value="0" @selected((string)$val('is_active','1')==='0')>No</option>
      </select>
    </div>

    <div>
      <div class="a2-hint">Service (اختياري)</div>
      <select class="a2-input" name="service_id">
        <option value="">ALL</option>
        @foreach($services as $s)
          <option value="{{ $s->id }}" @selected((string)$val('service_id')===(string)$s->id)>
            #{{ $s->id }} — {{ $s->name_ar ?? $s->name_en }}
          </option>
        @endforeach
      </select>
    </div>

    <div>
      <div class="a2-hint">Amount (fallback)</div>
      <input class="a2-input" name="amount" type="number" step="0.01" min="0" value="{{ $val('amount',0) }}" required>
    </div>

    <div style="grid-column:1 / -1;">
      <div class="a2-hint">Rules (JSON)</div>
      <textarea class="a2-input" name="rules" rows="6"
        placeholder='{"client_amount":1,"business_amount":1}'>{{ $val('rules') }}</textarea>
      <div class="a2-hint" style="margin-top:6px;">
        للـ booking_execution_fee استخدم amounts ثابتة لكل طرف.
      </div>
    </div>
  </div>

  <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;">
    <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'Save' }}</button>
    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.service-fees.index') }}">Back</a>
  </div>
</div>