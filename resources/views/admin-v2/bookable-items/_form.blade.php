@php
    $isEdit = isset($row) && $row->exists;
@endphp

<div class="a2-form-grid">

    {{-- =========================
        Basic Info
    ========================== --}}
    <div class="a2-card">
        <div class="a2-card-head">
            <h3>البيانات الأساسية</h3>
        </div>

        <div class="a2-card-body">

            {{-- Business --}}
            <div class="a2-form-group">
                <label>البزنس</label>
                <select name="business_id" class="a2-select" required>
                    <option value="">اختر البزنس</option>
                    @foreach($businesses as $b)
                        <option value="{{ $b->id }}"
                            {{ old('business_id', $row->business_id ?? '') == $b->id ? 'selected' : '' }}>
                            {{ $b->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Service --}}
            <div class="a2-form-group">
                <label>الخدمة</label>
                <select name="service_id" class="a2-select" required>
                    <option value="">اختر الخدمة</option>
                    @foreach($services as $s)
                        <option value="{{ $s->id }}"
                            {{ old('service_id', $row->service_id ?? '') == $s->id ? 'selected' : '' }}>
                            {{ $s->name_ar ?? $s->name_en }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Item Type --}}
            <div class="a2-form-group">
                <label>نوع العنصر</label>

                @if(!empty($allowedItemTypes))
                    <select name="item_type" class="a2-select" required>
                        <option value="">اختر النوع</option>
                        @foreach($allowedItemTypes as $type)
                            <option value="{{ $type }}"
                                {{ old('item_type', $row->item_type ?? '') === $type ? 'selected' : '' }}>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                @else
                    <input type="text"
                           name="item_type"
                           class="a2-input"
                           value="{{ old('item_type', $row->item_type ?? '') }}"
                           placeholder="مثال: room / car / table"
                           required>
                @endif
            </div>

            {{-- Title --}}
            <div class="a2-form-group">
                <label>العنوان</label>
                <input type="text"
                       name="title"
                       class="a2-input"
                       value="{{ old('title', $row->title ?? '') }}"
                       required>
            </div>

            {{-- Code --}}
            <div class="a2-form-group">
                <label>الكود (اختياري)</label>
                <input type="text"
                       name="code"
                       class="a2-input"
                       value="{{ old('code', $row->code ?? '') }}">
            </div>

        </div>
    </div>

    {{-- =========================
        Pricing
    ========================== --}}
    <div class="a2-card">
        <div class="a2-card-head">
            <h3>السعر والإعدادات</h3>
        </div>

        <div class="a2-card-body">

            {{-- Price --}}
            <div class="a2-form-group">
                <label>السعر</label>
                <input type="number"
                       step="0.01"
                       name="price"
                       class="a2-input"
                       value="{{ old('price', $row->price ?? 0) }}"
                       required>
            </div>

            {{-- Capacity --}}
            <div class="a2-form-group">
                <label>السعة</label>
                <input type="number"
                       name="capacity"
                       class="a2-input"
                       value="{{ old('capacity', $row->capacity ?? '') }}">
            </div>

            {{-- Quantity --}}
            <div class="a2-form-group">
                <label>الكمية</label>
                <input type="number"
                       name="quantity"
                       class="a2-input"
                       value="{{ old('quantity', $row->quantity ?? 1) }}">
            </div>

            {{-- Active --}}
            <div class="a2-form-group a2-switch">
                <label>
                    <input type="checkbox" name="is_active" value="1"
                        {{ old('is_active', $row->is_active ?? 1) ? 'checked' : '' }}>
                    مفعل
                </label>
            </div>

        </div>
    </div>

    {{-- =========================
        Deposit
    ========================== --}}
    <div class="a2-card">
        <div class="a2-card-head">
            <h3>الديبوزت</h3>
        </div>

        <div class="a2-card-body">

            {{-- Enable Deposit --}}
            <div class="a2-form-group a2-switch">
                <label>
                    <input type="checkbox"
                           name="deposit_enabled"
                           value="1"
                           id="deposit_enabled"
                           {{ old('deposit_enabled', $row->deposit_enabled ?? 0) ? 'checked' : '' }}>
                    تفعيل الديبوزت
                </label>
            </div>

            {{-- Percent --}}
            <div class="a2-form-group">
                <label>نسبة الديبوزت (%)</label>
                <input type="number"
                       name="deposit_percent"
                       id="deposit_percent"
                       class="a2-input"
                       value="{{ old('deposit_percent', $row->deposit_percent ?? 0) }}">
            </div>

        </div>
    </div>

    {{-- =========================
        Meta JSON
    ========================== --}}
    <div class="a2-card">
        <div class="a2-card-head">
            <h3>Meta (JSON)</h3>
        </div>

        <div class="a2-card-body">
            <textarea name="meta"
                      class="a2-textarea"
                      rows="6"
                      placeholder='{"key": "value"}'>{{ old('meta', isset($row->meta) ? json_encode($row->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
        </div>
    </div>

</div>

{{-- =========================
    Actions
========================= --}}
<div class="a2-actions">
    <button class="a2-btn a2-btn-primary">
        {{ $isEdit ? 'تحديث' : 'إنشاء' }}
    </button>

    <a href="{{ route('admin.bookable-items.index') }}" class="a2-btn">
        رجوع
    </a>
</div>

{{-- =========================
    JS (UX)
========================= --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    const depositCheckbox = document.getElementById('deposit_enabled');
    const depositInput = document.getElementById('deposit_percent');

    function toggleDeposit() {
        if (!depositCheckbox || !depositInput) return;
        depositInput.disabled = !depositCheckbox.checked;
    }

    if (depositCheckbox) {
        depositCheckbox.addEventListener('change', toggleDeposit);
        toggleDeposit();
    }

});
</script>