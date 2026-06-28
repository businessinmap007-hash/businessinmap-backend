@php
    $metaJson = old('meta_json');
    if ($metaJson === null) {
        $metaJson = is_array($allocation->meta) && count($allocation->meta)
            ? json_encode($allocation->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
@endif

<div class="a2-form-grid">
    <div class="a2-card">
        <h2 class="a2-section-title">الشراكة والوحدة</h2>

        <div class="a2-field">
            <label class="a2-label">Partnership</label>
            <select class="a2-select" name="partnership_id" required>
                <option value="">اختر الشراكة</option>
                @foreach($partnerships as $partnership)
                    <option value="{{ $partnership->id }}" {{ (int) old('partnership_id', $allocation->partnership_id) === (int) $partnership->id ? 'selected' : '' }}>
                        #{{ $partnership->id }} — {{ optional($partnership->ownerBusiness)->name ?: 'Owner' }} ↔ {{ optional($partnership->partnerBusiness)->name ?: 'Partner' }}
                    </option>
                @endforeach
            </select>
            <div class="a2-help">الوحدة المختارة يجب أن تكون مملوكة لصاحب الأصل في الشراكة.</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">Bookable Item</label>
            <select class="a2-select" name="bookable_item_id" required>
                <option value="">اختر الوحدة / الغرفة / العنصر</option>
                @foreach($bookables as $bookable)
                    <option value="{{ $bookable->id }}" {{ (int) old('bookable_item_id', $allocation->bookable_item_id) === (int) $bookable->id ? 'selected' : '' }}>
                        #{{ $bookable->id }} — {{ $bookable->display_name }} — {{ optional($bookable->business)->name ?: '—' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Platform Service</label>
            <select class="a2-select" name="platform_service_id">
                <option value="">تلقائي من الوحدة</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" {{ (int) old('platform_service_id', $allocation->platform_service_id) === (int) $service->id ? 'selected' : '' }}>
                        #{{ $service->id }} — {{ $service->name_ar ?: $service->name_en ?: $service->key }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">نوع الحصة والحالة</h2>

        <div class="a2-field">
            <label class="a2-label">Allocation Type</label>
            <select class="a2-select" name="allocation_type" required>
                @foreach(\App\Models\BookableAllocation::allocationTypes() as $key => $label)
                    <option value="{{ $key }}" {{ old('allocation_type', $allocation->allocation_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Status</label>
            <select class="a2-select" name="status" required>
                @foreach(\App\Models\BookableAllocation::statuses() as $key => $label)
                    <option value="{{ $key }}" {{ old('status', $allocation->status) === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">المدة والـ Release</h2>

        <div class="a2-field">
            <label class="a2-label">Starts At</label>
            <input class="a2-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $allocation->starts_at ? $allocation->starts_at->format('Y-m-d\\TH:i') : '') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Ends At</label>
            <input class="a2-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $allocation->ends_at ? $allocation->ends_at->format('Y-m-d\\TH:i') : '') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Release Days Before</label>
            <input class="a2-input" type="number" min="0" name="release_days_before" value="{{ old('release_days_before', $allocation->release_days_before) }}">
            <div class="a2-help">مثال: 3 يعني أن الحصة تعود قبل الوصول بثلاثة أيام.</div>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">الكميات</h2>

        <div class="a2-field">
            <label class="a2-label">Quantity Total</label>
            <input class="a2-input" type="number" min="0" name="quantity_total" value="{{ old('quantity_total', $allocation->quantity_total) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Quantity Sold</label>
            <input class="a2-input" type="number" min="0" name="quantity_sold" value="{{ old('quantity_sold', $allocation->quantity_sold) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Quantity Reserved</label>
            <input class="a2-input" type="number" min="0" name="quantity_reserved" value="{{ old('quantity_reserved', $allocation->quantity_reserved) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Quantity Released</label>
            <input class="a2-input" type="number" min="0" name="quantity_released" value="{{ old('quantity_released', $allocation->quantity_released) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">السعر والعرض التجاري</h2>

        <div class="a2-field">
            <label class="a2-label">Contract Price</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="contract_price" value="{{ old('contract_price', $allocation->contract_price) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Currency</label>
            <input class="a2-input" type="text" name="currency" value="{{ old('currency', $allocation->currency ?: 'EGP') }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Markup Type</label>
            <select class="a2-select" name="markup_type">
                <option value="none" {{ old('markup_type', $allocation->markup_type) === 'none' ? 'selected' : '' }}>None</option>
                <option value="fixed" {{ old('markup_type', $allocation->markup_type) === 'fixed' ? 'selected' : '' }}>Fixed</option>
                <option value="percent" {{ old('markup_type', $allocation->markup_type) === 'percent' ? 'selected' : '' }}>Percent</option>
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Markup Value</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="markup_value" value="{{ old('markup_value', $allocation->markup_value) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Min Nights</label>
            <input class="a2-input" type="number" min="1" name="min_nights" value="{{ old('min_nights', $allocation->min_nights) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Max Nights</label>
            <input class="a2-input" type="number" min="1" name="max_nights" value="{{ old('max_nights', $allocation->max_nights) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Meta JSON</h2>

        <div class="a2-field">
            <label class="a2-label">Meta</label>
            <textarea class="a2-textarea" name="meta_json" rows="10" placeholder='{"is_refundable":true,"payment_model":"pay_now"}'>{{ $metaJson }}</textarea>
        </div>
    </div>
</div>

<div class="a2-form-actions">
    <button type="submit" class="a2-btn a2-btn-primary">حفظ وتحديث العرض</button>
    <a href="{{ route('admin.bookable-allocations.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
</div>
