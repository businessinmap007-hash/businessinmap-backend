@php
    $selectedServiceId = (int) old('service_id', $row->service_id);
    $selectedService = collect($services)->firstWhere('id', $selectedServiceId);
    $supportsDeposit = (bool) data_get($selectedService, 'supports_deposit', false);
    $maxDepositPercent = (int) data_get($selectedService, 'max_deposit_percent', 0);
@endphp

<div class="a2-card" style="padding:14px;">
    <div class="a2-alert a2-alert-info" style="margin-bottom:12px;">
        هذا الجدول يُستخدم لتعريف العنصر الحقيقي القابل للحجز:
        غرفة / جناح / شقة / ملعب / قاعة ...
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">

        <div>
            <label class="a2-label">Business</label>
            <select class="a2-input" name="business_id" required>
                <option value="">-- اختر --</option>
                @foreach($businesses as $b)
                    <option value="{{ $b->id }}" @selected((int)old('business_id', $row->business_id) === (int)$b->id)>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="a2-label">Service</label>
            <select class="a2-input" name="service_id" id="service_id" required>
                <option value="">-- اختر --</option>
                @foreach($services as $s)
                    <option
                        value="{{ $s->id }}"
                        data-supports-deposit="{{ (int)$s->supports_deposit }}"
                        data-max-deposit-percent="{{ (int)$s->max_deposit_percent }}"
                        @selected((int)old('service_id', $row->service_id) === (int)$s->id)
                    >
                        {{ $s->name_ar ?? $s->name_en ?? $s->key }} ({{ $s->key }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="a2-label">Item Type</label>
            <input class="a2-input" type="text" name="item_type" value="{{ old('item_type', $row->item_type) }}" placeholder="room / suite / apartment / court">
        </div>

        <div>
            <label class="a2-label">Title</label>
            <input class="a2-input" type="text" name="title" value="{{ old('title', $row->title) }}" required placeholder="Room 101 / Apartment 3A / Court 1">
        </div>

        <div>
            <label class="a2-label">Code</label>
            <input class="a2-input" type="text" name="code" value="{{ old('code', $row->code) }}" placeholder="101 / A3 / C1">
        </div>

        <div>
            <label class="a2-label">Price</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="price" value="{{ old('price', $row->price) }}" required>
        </div>

        <div>
            <label class="a2-label">Capacity</label>
            <input class="a2-input" type="number" min="1" name="capacity" value="{{ old('capacity', $row->capacity) }}">
        </div>

        <div>
            <label class="a2-label">Quantity</label>
            <input class="a2-input" type="number" min="1" name="quantity" value="{{ old('quantity', $row->quantity ?? 1) }}">
        </div>

        <div>
            <label class="a2-label">Active</label>
            <select class="a2-input" name="is_active">
                <option value="1" @selected((string)old('is_active', (int)$row->is_active) === '1')>Yes</option>
                <option value="0" @selected((string)old('is_active', (int)$row->is_active) === '0')>No</option>
            </select>
        </div>

        <div>
            <label class="a2-label">Deposit Enabled</label>
            <select class="a2-input" name="deposit_enabled" id="deposit_enabled">
                <option value="1" @selected((string)old('deposit_enabled', (int)$row->deposit_enabled) === '1')>Yes</option>
                <option value="0" @selected((string)old('deposit_enabled', (int)$row->deposit_enabled) === '0')>No</option>
            </select>
        </div>

        <div>
            <label class="a2-label">Deposit Percent</label>
            <input class="a2-input" type="number" min="0" max="100" name="deposit_percent" id="deposit_percent"
                   value="{{ old('deposit_percent', $row->deposit_percent) }}">
            <div class="a2-hint" id="deposit_help" style="margin-top:6px;">
                @if($supportsDeposit)
                    الحد الأقصى المسموح: {{ $maxDepositPercent }}%
                @else
                    الخدمة المختارة لا تدعم الديبوزت.
                @endif
            </div>
        </div>

        <div style="grid-column:1 / -1;">
            <label class="a2-label">Meta (JSON)</label>
            <textarea class="a2-input" name="meta" rows="5">{{ old('meta', is_array($row->meta ?? null) ? json_encode($row->meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </div>
    </div>

    <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;">
        <button class="a2-btn a2-btn-primary" type="submit">{{ $submitLabel ?? 'Save' }}</button>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.index') }}">Back</a>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const serviceSelect = document.getElementById('service_id');
    const depositEnabled = document.getElementById('deposit_enabled');
    const depositPercent = document.getElementById('deposit_percent');
    const depositHelp = document.getElementById('deposit_help');

    function refreshDepositUi() {
        if (!serviceSelect) return;

        const option = serviceSelect.options[serviceSelect.selectedIndex];
        if (!option) return;

        const supports = Number(option.dataset.supportsDeposit || 0) === 1;
        const maxPercent = Number(option.dataset.maxDepositPercent || 0);

        if (!supports) {
            depositEnabled.value = '0';
            depositEnabled.setAttribute('disabled', 'disabled');
            depositPercent.value = '0';
            depositPercent.setAttribute('readonly', 'readonly');
            depositHelp.textContent = 'الخدمة المختارة لا تدعم الديبوزت.';
            return;
        }

        depositEnabled.removeAttribute('disabled');

        if (depositEnabled.value === '1') {
            depositPercent.removeAttribute('readonly');
        } else {
            depositPercent.value = '0';
            depositPercent.setAttribute('readonly', 'readonly');
        }

        depositPercent.setAttribute('max', String(maxPercent));
        depositHelp.textContent = 'الحد الأقصى المسموح: ' + maxPercent + '%';
    }

    serviceSelect?.addEventListener('change', refreshDepositUi);
    depositEnabled?.addEventListener('change', refreshDepositUi);
    refreshDepositUi();
})();
</script>
@endpush