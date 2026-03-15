@php
    $selectedServiceId = (int) old('service_id', $row->service_id);
    $selectedService = collect($services)->firstWhere('id', $selectedServiceId);

    $supportsDeposit = (bool) data_get($selectedService, 'supports_deposit', false);
    $maxDepositPercent = (int) data_get($selectedService, 'max_deposit_percent', 0);

    $metaValue = old(
        'meta',
        is_array($row->meta ?? null)
            ? json_encode($row->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : ''
    );
@endphp

<div class="a2-card a2-mb-12">
    <div class="a2-card-head">
        <div>
            <div class="a2-title">Item Information</div>
            <div class="a2-hint">تعريف العنصر القابل للحجز</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">Business</label>
            <select class="a2-select" name="business_id" required>
                <option value="">-- اختر --</option>
                @foreach($businesses as $b)
                    <option value="{{ $b->id }}" @selected((int) old('business_id', $row->business_id) === (int) $b->id)>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>
            @error('business_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Service</label>
            <select class="a2-select" name="service_id" id="service_id" required>
                <option value="">-- اختر --</option>
                @foreach($services as $s)
                    <option
                        value="{{ $s->id }}"
                        data-supports-deposit="{{ (int) $s->supports_deposit }}"
                        data-max-deposit-percent="{{ (int) $s->max_deposit_percent }}"
                        @selected((int) old('service_id', $row->service_id) === (int) $s->id)
                    >
                        {{ $s->name_ar ?? $s->name_en ?? $s->key }} ({{ $s->key }})
                    </option>
                @endforeach
            </select>
            @error('service_id')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Item Type</label>
            <input
                class="a2-input"
                name="item_type"
                value="{{ old('item_type', $row->item_type) }}"
                placeholder="room / suite / apartment / court"
            >
            @error('item_type')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Title</label>
            <input
                class="a2-input"
                name="title"
                value="{{ old('title', $row->title) }}"
                required
                placeholder="Room 101 / Court 1"
            >
            @error('title')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Code</label>
            <input
                class="a2-input"
                name="code"
                value="{{ old('code', $row->code) }}"
                placeholder="101 / A3"
            >
            @error('code')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Active</label>
            <select class="a2-select" name="is_active">
                <option value="1" @selected((string) old('is_active', (int) $row->is_active) === '1')>Yes</option>
                <option value="0" @selected((string) old('is_active', (int) $row->is_active) === '0')>No</option>
            </select>
            @error('is_active')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-mb-12">
    <div class="a2-card-head">
        <div>
            <div class="a2-title">Pricing & Capacity</div>
            <div class="a2-hint">السعر والسعة</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">Price</label>
            <input
                class="a2-input"
                type="number"
                step="0.01"
                min="0"
                name="price"
                value="{{ old('price', $row->price) }}"
                required
            >
            @error('price')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Capacity</label>
            <input
                class="a2-input"
                type="number"
                min="1"
                name="capacity"
                value="{{ old('capacity', $row->capacity) }}"
            >
            @error('capacity')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Quantity</label>
            <input
                class="a2-input"
                type="number"
                min="1"
                name="quantity"
                value="{{ old('quantity', $row->quantity ?? 1) }}"
            >
            @error('quantity')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card">
    <div class="a2-card-head">
        <div>
            <div class="a2-title">Deposit Settings</div>
            <div class="a2-hint">العربون المطلوب للحجز</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">Deposit Enabled</label>
            <select class="a2-select" name="deposit_enabled" id="deposit_enabled">
                <option value="1" @selected((string) old('deposit_enabled', (int) $row->deposit_enabled) === '1')>Yes</option>
                <option value="0" @selected((string) old('deposit_enabled', (int) $row->deposit_enabled) === '0')>No</option>
            </select>
            @error('deposit_enabled')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Deposit Percent</label>
            <input
                class="a2-input"
                type="number"
                min="0"
                max="100"
                name="deposit_percent"
                id="deposit_percent"
                value="{{ old('deposit_percent', $row->deposit_percent) }}"
            >

            <div class="a2-hint a2-mt-8" id="deposit_help">
                @if($supportsDeposit)
                    الحد الأقصى المسموح: {{ $maxDepositPercent }}%
                @else
                    الخدمة المختارة لا تدعم الديبوزت
                @endif
            </div>

            @error('deposit_percent')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group a2-col-span-full">
            <label class="a2-label">Meta (JSON)</label>
            <textarea
                class="a2-textarea"
                name="meta"
                rows="5"
                placeholder='{"floor":1,"view":"sea"}'
            >{{ $metaValue }}</textarea>
            @error('meta')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="a2-actionsbar a2-mt-16">
        <button class="a2-btn a2-btn-primary" type="submit">
            {{ $submitLabel ?? 'Save' }}
        </button>

        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.index') }}">
            Back
        </a>
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
        if (!serviceSelect || !depositEnabled || !depositPercent || !depositHelp) {
            return;
        }

        const option = serviceSelect.options[serviceSelect.selectedIndex];
        if (!option) {
            return;
        }

        const supports = Number(option.dataset.supportsDeposit || 0) === 1;
        const maxPercent = Number(option.dataset.maxDepositPercent || 0);

        if (!supports) {
            depositEnabled.value = '0';
            depositEnabled.setAttribute('disabled', 'disabled');
            depositPercent.value = '0';
            depositPercent.setAttribute('readonly', 'readonly');
            depositPercent.setAttribute('max', '0');
            depositHelp.textContent = 'الخدمة المختارة لا تدعم الديبوزت';
            return;
        }

        depositEnabled.removeAttribute('disabled');
        depositPercent.setAttribute('max', String(maxPercent));

        if (depositEnabled.value === '1') {
            depositPercent.removeAttribute('readonly');
        } else {
            depositPercent.value = '0';
            depositPercent.setAttribute('readonly', 'readonly');
        }

        depositHelp.textContent = 'الحد الأقصى المسموح: ' + maxPercent + '%';
    }

    serviceSelect?.addEventListener('change', refreshDepositUi);
    depositEnabled?.addEventListener('change', refreshDepositUi);

    refreshDepositUi();
})();
</script>
@endpush
