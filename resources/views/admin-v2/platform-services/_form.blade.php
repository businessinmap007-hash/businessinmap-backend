@csrf
<div class="a2-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
  <div>
    <label class="a2-label">Key</label>
    <input class="a2-input" name="key" value="{{ old('key', $row->key) }}" placeholder="booking / menu / delivery" required>
    @error('key')<div class="a2-error">{{ $message }}</div>@enderror
  </div>

  <div>
    <label class="a2-label">Active</label>
    <select class="a2-input" name="is_active">
      <option value="1" @selected(old('is_active', (int)$row->is_active)===1)>Yes</option>
      <option value="0" @selected(old('is_active', (int)$row->is_active)===0)>No</option>
    </select>
  </div>

  <div>
    <label class="a2-label">الاسم عربي</label>
    <input class="a2-input" name="name_ar" value="{{ old('name_ar', $row->name_ar) }}" required>
    @error('name_ar')<div class="a2-error">{{ $message }}</div>@enderror
  </div>

  <div>
    <label class="a2-label">Name EN</label>
    <input class="a2-input" name="name_en" value="{{ old('name_en', $row->name_en) }}">
  </div>

  <div style="grid-column:1/-1;">
    <div style="display:flex; align-items:center; gap:10px;">
      <label class="a2-label" style="margin:0;">Supports Deposit</label>
      <input type="checkbox" name="supports_deposit" value="1" @checked(old('supports_deposit', (bool)$row->supports_deposit))>
      <span class="a2-hint">تفعيل إمكانية تحديد Deposit لهذه الخدمة</span>
    </div>
  </div>

  <div>
    <label class="a2-label">Max Deposit %</label>
    <input class="a2-input" type="number" name="max_deposit_percent" min="0" max="100"
           value="{{ old('max_deposit_percent', (int)$row->max_deposit_percent) }}">
    @error('max_deposit_percent')<div class="a2-error">{{ $message }}</div>@enderror
  </div>

  <div>
    <label class="a2-label">Default Fee Type</label>
    <select class="a2-input" name="fee_type">
      <option value="" @selected(!old('fee_type', $row->fee_type))>—</option>
      <option value="fixed" @selected(old('fee_type', $row->fee_type)==='fixed')>fixed</option>
      <option value="percent" @selected(old('fee_type', $row->fee_type)==='percent')>percent</option>
    </select>
  </div>

  <div>
    <label class="a2-label">Default Fee Value</label>
    <input class="a2-input" type="number" step="0.01" name="fee_value" value="{{ old('fee_value', $row->fee_value) }}">
  </div>

  <div style="grid-column:1/-1;">
    <label class="a2-label">Rules (JSON)</label>
    <textarea class="a2-input" name="rules" rows="5" placeholder='{"example":true}'>{{ old('rules', is_array($row->rules) ? json_encode($row->rules, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
  </div>
</div>

<div style="margin-top:12px; display:flex; gap:8px;">
  <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'Save' }}</button>
  <a class="a2-btn" href="{{ route('admin.platform-services.index') }}">Back</a>
</div>