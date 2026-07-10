@php
    $termsJson = old('terms_json');
    if ($termsJson === null) {
        $termsJson = is_array($partnership->terms) && count($partnership->terms)
            ? json_encode($partnership->terms, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }

    $metaJson = old('meta_json');
    if ($metaJson === null) {
        $metaJson = is_array($partnership->meta) && count($partnership->meta)
            ? json_encode($partnership->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
@endif

<div class="a2-form-grid">
    <div class="a2-card">
        <h2 class="a2-section-title">أطراف الشراكة</h2>

        @php
            $ownerId = (int) old('owner_business_id', $partnership->owner_business_id);
            $partnerId = (int) old('partner_business_id', $partnership->partner_business_id);
            $lookupUrl = route('admin.business-lookup', [], false);
        @endphp

        <div class="a2-field">
            <label class="a2-label">صاحب الأصل / الفندق / المورد</label>
            <select class="a2-select" name="owner_business_id" required
                    data-remote-url="{{ $lookupUrl }}" data-placeholder="اختر البزنس المالك — ابحث بالاسم أو الرقم #">
                <option value="">اختر البزنس المالك</option>
                @if($ownerId)
                    <option value="{{ $ownerId }}" selected>#{{ $ownerId }}@if($partnership->ownerBusiness) — {{ $partnership->ownerBusiness->name }}@endif</option>
                @endif
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">الشريك / شركة السياحة / الوكيل</label>
            <select class="a2-select" name="partner_business_id" required
                    data-remote-url="{{ $lookupUrl }}" data-placeholder="اختر البزنس الشريك — ابحث بالاسم أو الرقم #">
                <option value="">اختر البزنس الشريك</option>
                @if($partnerId)
                    <option value="{{ $partnerId }}" selected>#{{ $partnerId }}@if($partnership->partnerBusiness) — {{ $partnership->partnerBusiness->name }}@endif</option>
                @endif
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">نوع وقواعد الشراكة</h2>

        <div class="a2-field">
            <label class="a2-label">Relationship Type</label>
            <select class="a2-select" name="relationship_type" required>
                @foreach(\App\Models\BusinessPartnership::relationshipTypes() as $key => $label)
                    <option value="{{ $key }}" {{ old('relationship_type', $partnership->relationship_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">Status</label>
            <select class="a2-select" name="status" required>
                @foreach(\App\Models\BusinessPartnership::statuses() as $key => $label)
                    <option value="{{ $key }}" {{ old('status', $partnership->status) === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-checkline">
                <input type="checkbox" name="approval_required" value="1" {{ old('approval_required', $partnership->approval_required) ? 'checked' : '' }}>
                <span>تحتاج موافقة قبل التفعيل</span>
            </label>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">مدة الشراكة</h2>

        <div class="a2-field">
            <label class="a2-label">Starts At</label>
            <input class="a2-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $partnership->starts_at ? $partnership->starts_at->format('Y-m-d\\TH:i') : '') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">Ends At</label>
            <input class="a2-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $partnership->ends_at ? $partnership->ends_at->format('Y-m-d\\TH:i') : '') }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Terms JSON</h2>

        <div class="a2-field">
            <label class="a2-label">الشروط</label>
            <textarea class="a2-textarea" name="terms_json" rows="10" placeholder='{"commission_percent":10,"notes":"optional"}'>{{ $termsJson }}</textarea>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Meta JSON</h2>

        <div class="a2-field">
            <label class="a2-label">Meta</label>
            <textarea class="a2-textarea" name="meta_json" rows="10" placeholder='{"source":"admin"}'>{{ $metaJson }}</textarea>
        </div>
    </div>
</div>

<div class="a2-form-actions">
    <button type="submit" class="a2-btn a2-btn-primary">حفظ</button>
    <a href="{{ route('admin.business-partnerships.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
</div>
