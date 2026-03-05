@php
  $row = $row ?? null;
  $val = fn($k,$d=null) => old($k, data_get($row,$k,$d));
@endphp

<div class="a2-card" style="padding:14px;">
  <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
    <div>
      <div class="a2-hint">Business</div>
      <select class="a2-input" name="business_id" required>
        @foreach($businesses as $b)
          <option value="{{ $b->id }}" @selected((string)$val('business_id')===(string)$b->id)>
            #{{ $b->id }} — {{ $b->name }}
          </option>
        @endforeach
      </select>
    </div>

    <div>
      <div class="a2-hint">Service</div>
      <select class="a2-input" name="service_id" required>
        @foreach($services as $s)
          <option value="{{ $s->id }}" @selected((string)$val('service_id')===(string)$s->id)>
            #{{ $s->id }} — {{ $s->name_ar ?? $s->name_en }}
          </option>
        @endforeach
      </select>
    </div>

    <div>
      <div class="a2-hint">Price</div>
      <input class="a2-input" type="number" step="0.01" min="0" name="price" value="{{ $val('price',0) }}" required>
    </div>

    <div>
      <div class="a2-hint">Active</div>
      <select class="a2-input" name="is_active" required>
        <option value="1" @selected((string)$val('is_active','1')==='1')>Yes</option>
        <option value="0" @selected((string)$val('is_active','1')==='0')>No</option>
      </select>
    </div>
  </div>

  <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;">
    <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'Save' }}</button>
    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.business_service_prices.index') }}">Back</a>
  </div>
</div>