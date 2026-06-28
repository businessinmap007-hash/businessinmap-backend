@php
    $metaJson = old('meta_json');
    if ($metaJson === null) {
        $metaJson = is_array($offer->meta) && count($offer->meta)
            ? json_encode($offer->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
@endif

<div class="a2-form-grid">
    <div class="a2-card">
        <h2 class="a2-section-title">بيانات العرض</h2>

        <div class="a2-field">
            <label class="a2-label">العنوان عربي</label>
            <input class="a2-input" type="text" name="title_ar" value="{{ old('title_ar', $offer->title_ar) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">العنوان English</label>
            <input class="a2-input" type="text" name="title_en" value="{{ old('title_en', $offer->title_en) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Offerable Type</label>
            <select class="a2-select" name="offerable_type" required>
                @foreach($offerableTypes as $type)
                    <option value="{{ $type }}" {{ old('offerable_type', $offer->offerable_type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Offerable ID</label>
            <input class="a2-input" type="number" min="0" name="offerable_id" value="{{ old('offerable_id', $offer->offerable_id) }}">
            <div class="a2-help">للمنتج أو الخدمة أو الوحدة. استخدم 0 كعرض تسويقي عام للبزنس مؤقتًا.</div>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">صاحب العرض والبائع</h2>

        <div class="a2-field">
            <label class="a2-label">Owner Business</label>
            <select class="a2-select" name="owner_business_id" required>
                <option value="">اختر صاحب الأصل</option>
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}" {{ (int) old('owner_business_id', $offer->owner_business_id) === (int) $business->id ? 'selected' : '' }}>
                        #{{ $business->id }} — {{ $business->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Seller Business</label>
            <select class="a2-select" name="seller_business_id" required>
                <option value="">اختر البائع</option>
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}" {{ (int) old('seller_business_id', $offer->seller_business_id) === (int) $business->id ? 'selected' : '' }}>
                        #{{ $business->id }} — {{ $business->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">مصدر العرض والجمهور</h2>

        <div class="a2-field">
            <label class="a2-label">Source Type</label>
            <select class="a2-select" name="source_type" required>
                @foreach($sourceTypes as $type)
                    <option value="{{ $type }}" {{ old('source_type', $offer->source_type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Audience Type</label>
            <select class="a2-select" name="audience_type" required>
                @foreach($audienceTypes as $type)
                    <option value="{{ $type }}" {{ old('audience_type', $offer->audience_type ?: 'both') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
            <div class="a2-help">b2b للبزنس فقط، b2c للعملاء فقط، both للجميع.</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">Source ID</label>
            <input class="a2-input" type="number" min="1" name="source_id" value="{{ old('source_id', $offer->source_id) }}">
            <div class="a2-help">يستخدم مع allocation أو reseller. اتركه فارغًا للعروض اليدوية.</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">Status</label>
            <select class="a2-select" name="status" required>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" {{ old('status', $offer->status) === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">السعر والخصم</h2>

        <div class="a2-field">
            <label class="a2-label">Base Price</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $offer->base_price) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Final Price</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="final_price" value="{{ old('final_price', $offer->final_price) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Currency</label>
            <input class="a2-input" type="text" name="currency" value="{{ old('currency', $offer->currency ?: 'EGP') }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Discount Type</label>
            <select class="a2-select" name="discount_type">
                <option value="">بدون</option>
                <option value="fixed" {{ old('discount_type', $offer->discount_type) === 'fixed' ? 'selected' : '' }}>fixed</option>
                <option value="percent" {{ old('discount_type', $offer->discount_type) === 'percent' ? 'selected' : '' }}>percent</option>
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Discount Value</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="discount_value" value="{{ old('discount_value', $offer->discount_value) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">التوفر والمدة</h2>

        <div class="a2-field">
            <label class="a2-label">Availability Mode</label>
            <select class="a2-select" name="availability_mode" required>
                @foreach($availabilityModes as $mode)
                    <option value="{{ $mode }}" {{ old('availability_mode', $offer->availability_mode) === $mode ? 'selected' : '' }}>{{ $mode }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Available Quantity</label>
            <input class="a2-input" type="number" min="0" name="available_quantity" value="{{ old('available_quantity', $offer->available_quantity) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Starts At</label>
            <input class="a2-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $offer->starts_at ? $offer->starts_at->format('Y-m-d\\TH:i') : '') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Ends At</label>
            <input class="a2-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $offer->ends_at ? $offer->ends_at->format('Y-m-d\\TH:i') : '') }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">شروط العرض</h2>

        <div class="a2-field">
            <label class="a2-checkline">
                <input type="checkbox" name="is_refundable" value="1" {{ old('is_refundable', $offer->is_refundable) ? 'checked' : '' }}>
                <span>قابل للاسترداد</span>
            </label>
        </div>

        <div class="a2-field">
            <label class="a2-label">Payment Model</label>
            <input class="a2-input" type="text" name="payment_model" value="{{ old('payment_model', $offer->payment_model) }}" placeholder="pay_now / pay_later / deposit">
        </div>

        <div class="a2-field">
            <label class="a2-label">Ranking Score</label>
            <input class="a2-input" type="number" step="0.0001" min="0" name="ranking_score" value="{{ old('ranking_score', $offer->ranking_score) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Meta JSON</h2>

        <div class="a2-field">
            <label class="a2-label">Meta</label>
            <textarea class="a2-textarea" name="meta_json" rows="10" placeholder='{"marketing_subscription":"offers_basic"}'>{{ $metaJson }}</textarea>
        </div>
    </div>
</div>

<div class="a2-form-actions">
    <button type="submit" class="a2-btn a2-btn-primary">حفظ العرض</button>
    <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
</div>
