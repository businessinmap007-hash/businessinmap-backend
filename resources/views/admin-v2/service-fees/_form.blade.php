<div class="a2-card" style="padding:14px;">
    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">

        <div>
            <label class="a2-label">Code</label>
            <input class="a2-input" type="text" name="code" value="{{ old('code', $row->code) }}" required>
            <div class="a2-hint" style="margin-top:6px;">
                مثال: booking_execution_fee
            </div>
        </div>

        <div>
            <label class="a2-label">Service</label>
            <select class="a2-input" name="service_id">
                <option value="">-- بدون ربط --</option>
                @foreach($services as $s)
                    <option value="{{ $s->id }}" @selected((int)old('service_id', $row->service_id) === (int)$s->id)>
                        {{ $s->name_ar ?? $s->name_en ?? $s->key }} ({{ $s->key }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="a2-label">Amount</label>
            <input class="a2-input" type="number" step="0.01" name="amount" value="{{ old('amount', $row->amount) }}" required>
        </div>

        <div>
            <label class="a2-label">Active</label>
            <select class="a2-input" name="is_active">
                <option value="1" @selected((string)old('is_active', (int)$row->is_active) === '1')>Yes</option>
                <option value="0" @selected((string)old('is_active', (int)$row->is_active) === '0')>No</option>
            </select>
        </div>

        <div style="grid-column:1 / -1;">
            <label class="a2-label">Rules (JSON)</label>
            <textarea class="a2-input" name="rules" rows="5">{{ old('rules', is_array($row->rules ?? null) ? json_encode($row->rules, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </div>
    </div>

    <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;">
        <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'Save' }}</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.service-fees.index') }}">Back</a>
    </div>
</div>