@php
    $rulesJson = old('rules_json');
    if ($rulesJson === null) {
        $rulesJson = is_array($package->rules) && count($package->rules)
            ? json_encode($package->rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }

    $metaJson = old('meta_json');
    if ($metaJson === null) {
        $metaJson = is_array($package->meta) && count($package->meta)
            ? json_encode($package->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
@endif

<div class="a2-form-grid">
    <div class="a2-card">
        <h2 class="a2-section-title">بيانات الباقة</h2>

        <div class="a2-field">
            <label class="a2-label">Key</label>
            <input class="a2-input" type="text" name="key" value="{{ old('key', $package->key) }}" placeholder="boost_7_days">
            <div class="a2-help">اتركه فارغًا لتوليده تلقائيًا من الاسم.</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">الاسم عربي</label>
            <input class="a2-input" type="text" name="name_ar" value="{{ old('name_ar', $package->name_ar) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Name English</label>
            <input class="a2-input" type="text" name="name_en" value="{{ old('name_en', $package->name_en) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">السعر والمدة</h2>

        <div class="a2-field">
            <label class="a2-label">Price</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="price" value="{{ old('price', $package->price ?? 0) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Currency</label>
            <input class="a2-input" type="text" name="currency" value="{{ old('currency', $package->currency ?: 'EGP') }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Duration Days</label>
            <input class="a2-input" type="number" min="1" max="365" name="duration_days" value="{{ old('duration_days', $package->duration_days ?: 7) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">Boost Score</label>
            <input class="a2-input" type="number" step="0.0001" min="0" name="boost_score" value="{{ old('boost_score', $package->boost_score ?: 10) }}" required>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">الحالة</h2>

        <label class="a2-checkline">
            <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $package->is_featured ?? true) ? 'checked' : '' }}>
            <span>Featured Offer</span>
        </label>

        <label class="a2-checkline">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $package->is_active ?? true) ? 'checked' : '' }}>
            <span>Active Package</span>
        </label>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Rules JSON</h2>
        <textarea class="a2-textarea" name="rules_json" rows="8" placeholder='{"placement":"search_and_compare"}'>{{ $rulesJson }}</textarea>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Meta JSON</h2>
        <textarea class="a2-textarea" name="meta_json" rows="8">{{ $metaJson }}</textarea>
    </div>
</div>

<div class="a2-form-actions">
    <button class="a2-btn a2-btn-primary" type="submit">حفظ الباقة</button>
    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.offer-boost-packages.index') }}">رجوع</a>
</div>
