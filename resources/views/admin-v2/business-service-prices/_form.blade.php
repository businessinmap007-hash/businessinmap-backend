<div class="a2-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
  <div>
    <label class="a2-label">Service</label>
    <select class="a2-input" name="platform_service_id" required>
      <option value="">-- اختر --</option>
      @foreach($services as $s)
        <option value="{{ $s->id }}" @selected(old('platform_service_id', (int)$row->platform_service_id) === (int)$s->id)>
          {{ $s->name_ar }} ({{ $s->key }})
        </option>
      @endforeach
    </select>
    @error('platform_service_id')<div class="a2-error">{{ $message }}</div>@enderror
  </div>

  <div>
    <label class="a2-label">Business</label>
    <select class="a2-input" name="business_id" required>
      <option value="">-- اختر --</option>
      @foreach($businesses as $b)
        <option value="{{ $b->id }}" @selected(old('business_id', (int)$row->business_id) === (int)$b->id)>
          {{ $b->name }}
        </option>
      @endforeach
    </select>
    @error('business_id')<div class="a2-error">{{ $message }}</div>@enderror
  </div>

  <div>
    <label class="a2-label">Active</label>
    <select class="a2-input" name="is_active">
      <option value="1" @selected(old('is_active', (int)$row->is_active)===1)>Yes</option>
      <option value="0" @selected(old('is_active', (int)$row->is_active)===0)>No</option>
    </select>
  </div>

  <div>
    <label class="a2-label">Price</label>
    <input class="a2-input" type="number" step="0.01" name="price" value="{{ old('price', $row->price) }}" required>
    @error('price')<div class="a2-error">{{ $message }}</div>@enderror
  </div>

  <div>
    <label class="a2-label">Fee Type (override)</label>
    <select class="a2-input" name="fee_type">
      <option value="" @selected(!old('fee_type', $row->fee_type))>—</option>
      <option value="fixed" @selected(old('fee_type', $row->fee_type)==='fixed')>fixed</option>
      <option value="percent" @selected(old('fee_type', $row->fee_type)==='percent')>percent</option>
    </select>
  </div>

  <div>
    <label class="a2-label">Fee Value (override)</label>
    <input class="a2-input" type="number" step="0.01" name="fee_value" value="{{ old('fee_value', $row->fee_value) }}">
  </div>
</div>

<div style="margin-top:12px; display:flex; gap:8px;">
  <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'Save' }}</button>
  <a class="a2-btn" href="{{ route('admin.business-service-prices.index') }}">Back</a>
</div>