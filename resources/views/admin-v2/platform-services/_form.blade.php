<div class="a2-card" style="padding:14px;">
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">

        <div>
            <label class="a2-label">Key</label>
            <input class="a2-input" type="text" name="key" value="{{ old('key', $row->key) }}" required>
            <div class="a2-hint" style="margin-top:6px;">مثال: booking / menu / delivery</div>
        </div>

        <div>
            <label class="a2-label">Active</label>
            <select class="a2-input" name="is_active">
                <option value="1" @selected((string)old('is_active', (int)$row->is_active) === '1')>Yes</option>
                <option value="0" @selected((string)old('is_active', (int)$row->is_active) === '0')>No</option>
            </select>
        </div>

        <div>
            <label class="a2-label">الاسم عربي</label>
            <input class="a2-input" type="text" name="name_ar" value="{{ old('name_ar', $row->name_ar) }}" required>
        </div>

        <div>
            <label class="a2-label">Name EN</label>
            <input class="a2-input" type="text" name="name_en" value="{{ old('name_en', $row->name_en) }}">
        </div>

        <div>
            <label class="a2-label">Supports Deposit</label>
            <select class="a2-input" name="supports_deposit">
                <option value="1" @selected((string)old('supports_deposit', (int)$row->supports_deposit) === '1')>Yes</option>
                <option value="0" @selected((string)old('supports_deposit', (int)$row->supports_deposit) === '0')>No</option>
            </select>
        </div>

        <div>
            <label class="a2-label">Max Deposit %</label>
            <input class="a2-input" type="number" min="0" max="100" name="max_deposit_percent" value="{{ old('max_deposit_percent', $row->max_deposit_percent) }}">
        </div>

        <div>
            <label class="a2-label">Fee Type</label>
            <select class="a2-input" name="fee_type">
                <option value="">-- بدون --</option>
                <option value="fixed" @selected(old('fee_type', $row->fee_type) === 'fixed')>fixed</option>
                <option value="percent" @selected(old('fee_type', $row->fee_type) === 'percent')>percent</option>
            </select>
        </div>

        <div>
            <label class="a2-label">Fee Value</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="fee_value" value="{{ old('fee_value', $row->fee_value) }}">
        </div>

        <div style="grid-column:1 / -1;">
            <label class="a2-label">Rules (JSON)</label>
            <textarea class="a2-input" name="rules" rows="5">{{ old('rules', is_array($row->rules ?? null) ? json_encode($row->rules, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </div>
    </div>

    <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;">
        <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'Save' }}</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.platform-services.index') }}">Back</a>
    </div>
</div>