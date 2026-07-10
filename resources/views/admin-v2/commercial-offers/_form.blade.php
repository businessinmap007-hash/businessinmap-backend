@php
    $metaJson = old('meta_json');
    if ($metaJson === null) {
        $metaJson = is_array($offer->meta) && count($offer->meta)
            ? json_encode($offer->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';
    }

    // Arabic display labels for the enum dropdowns (values stay the raw keys).
    $offerableLabels = [
        'bookable_item' => 'وحدة قابلة للحجز', 'product' => 'منتج',
        'service' => 'خدمة', 'package' => 'باقة',
    ];
    $sourceLabels = [
        'direct' => 'مباشر', 'allocation' => 'تخصيص (Allotment)', 'reseller' => 'إعادة بيع',
        'promotion' => 'ترويجي', 'marketplace' => 'سوق (Marketplace)',
    ];
    $audienceLabels = [
        'b2c' => 'أفراد (B2C)', 'b2b' => 'شركات (B2B)', 'both' => 'الجميع', 'private' => 'خاص',
    ];
    $statusLabels = [
        'active' => 'نشط', 'paused' => 'موقوف', 'expired' => 'منتهٍ', 'cancelled' => 'ملغى',
    ];
    $availabilityLabels = [
        'instant' => 'فوري', 'request' => 'بالطلب', 'limited_quantity' => 'كمية محدودة',
    ];
    $lbl = fn (array $map, $key) => $map[$key] ?? $key;
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
            <label class="a2-label">العنوان (إنجليزي)</label>
            <input class="a2-input" type="text" name="title_en" value="{{ old('title_en', $offer->title_en) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">نوع المعروض</label>
            <select class="a2-select" name="offerable_type" required>
                @foreach($offerableTypes as $type)
                    <option value="{{ $type }}" {{ old('offerable_type', $offer->offerable_type) === $type ? 'selected' : '' }}>{{ $lbl($offerableLabels, $type) }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">معرّف المعروض</label>
            <input class="a2-input" type="number" min="0" name="offerable_id" value="{{ old('offerable_id', $offer->offerable_id) }}">
            <div class="a2-help">للمنتج أو الخدمة أو الوحدة. استخدم 0 كعرض تسويقي عام للبزنس مؤقتًا.</div>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">صاحب العرض والبائع</h2>

        @php
            $ownerId = (int) old('owner_business_id', $offer->owner_business_id);
            $sellerId = (int) old('seller_business_id', $offer->seller_business_id);
            $bizLookupUrl = route('admin.business-lookup', [], false);
        @endphp

        <div class="a2-field">
            <label class="a2-label">البزنس المالك</label>
            <select class="a2-select" name="owner_business_id" required
                    data-remote-url="{{ $bizLookupUrl }}" data-placeholder="اختر صاحب الأصل — ابحث بالاسم أو الرقم #">
                <option value="">اختر صاحب الأصل</option>
                @if($ownerId)
                    <option value="{{ $ownerId }}" selected>#{{ $ownerId }}@if($offer->ownerBusiness) — {{ $offer->ownerBusiness->name }}@endif</option>
                @endif
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">البزنس البائع</label>
            <select class="a2-select" name="seller_business_id" required
                    data-remote-url="{{ $bizLookupUrl }}" data-placeholder="اختر البائع — ابحث بالاسم أو الرقم #">
                <option value="">اختر البائع</option>
                @if($sellerId)
                    <option value="{{ $sellerId }}" selected>#{{ $sellerId }}@if($offer->sellerBusiness) — {{ $offer->sellerBusiness->name }}@endif</option>
                @endif
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">مصدر العرض والجمهور</h2>

        <div class="a2-field">
            <label class="a2-label">مصدر العرض</label>
            <select class="a2-select" name="source_type" required>
                @foreach($sourceTypes as $type)
                    <option value="{{ $type }}" {{ old('source_type', $offer->source_type) === $type ? 'selected' : '' }}>{{ $lbl($sourceLabels, $type) }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">الجمهور المستهدف</label>
            <select class="a2-select" name="audience_type" required>
                @foreach($audienceTypes as $type)
                    <option value="{{ $type }}" {{ old('audience_type', $offer->audience_type ?: 'both') === $type ? 'selected' : '' }}>{{ $lbl($audienceLabels, $type) }}</option>
                @endforeach
            </select>
            <div class="a2-help">شركات (B2B) للبزنس فقط، أفراد (B2C) للعملاء فقط، الجميع للكل.</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">معرّف المصدر</label>
            <input class="a2-input" type="number" min="1" name="source_id" value="{{ old('source_id', $offer->source_id) }}">
            <div class="a2-help">يستخدم مع allocation أو reseller. اتركه فارغًا للعروض اليدوية.</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">الحالة</label>
            <select class="a2-select" name="status" required>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" {{ old('status', $offer->status) === $status ? 'selected' : '' }}>{{ $lbl($statusLabels, $status) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">توجيه B2B (اختياري)</h2>
        <div class="a2-help">
            للعروض الموجَّهة للشركات: حدّد تصنيفات كاملة و/أو أقسامًا فرعية محددة
            (يمكن أن تكون من آباء مختلفين). اتركها فارغة ليظهر العرض لكل الجمهور المحدد.
        </div>

        <div class="a2-field">
            <label class="a2-label">تصنيفات كاملة</label>
            <select class="a2-select" name="target_categories[]" multiple data-placeholder="ابحث واختر تصنيفات…">
                @foreach($rootCategories as $cat)
                    <option value="{{ $cat->id }}" @selected(in_array((int) $cat->id, $selectedTargetCategories, true))>
                        #{{ $cat->id }} — {{ $cat->name_ar ?: $cat->name_en }}
                    </option>
                @endforeach
            </select>
            <div class="a2-help">العرض يُوجَّه لكل الأقسام داخل هذه التصنيفات.</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">أقسام فرعية محددة</label>
            <select class="a2-select" name="target_children[]" multiple data-placeholder="ابحث واختر أقسامًا فرعية…">
                @foreach($categoryChildren as $child)
                    <option value="{{ $child->id }}" @selected(in_array((int) $child->id, $selectedTargetChildren, true))>
                        #{{ $child->id }} — {{ $child->name_ar ?: $child->name_en }}
                    </option>
                @endforeach
            </select>
            <div class="a2-help">يمكن اختيار أقسام فرعية من تصنيفات (آباء) مختلفة.</div>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">السعر والخصم</h2>

        <div class="a2-field">
            <label class="a2-label">السعر الأساسي</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $offer->base_price) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">السعر النهائي</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="final_price" value="{{ old('final_price', $offer->final_price) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">العملة</label>
            <input class="a2-input" type="text" name="currency" value="{{ old('currency', $offer->currency ?: 'EGP') }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">نوع الخصم</label>
            <select class="a2-select" name="discount_type">
                <option value="">بدون</option>
                <option value="fixed" {{ old('discount_type', $offer->discount_type) === 'fixed' ? 'selected' : '' }}>مبلغ ثابت</option>
                <option value="percent" {{ old('discount_type', $offer->discount_type) === 'percent' ? 'selected' : '' }}>نسبة %</option>
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">قيمة الخصم</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="discount_value" value="{{ old('discount_value', $offer->discount_value) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">التوفر والمدة</h2>

        <div class="a2-field">
            <label class="a2-label">نمط التوفر</label>
            <select class="a2-select" name="availability_mode" required>
                @foreach($availabilityModes as $mode)
                    <option value="{{ $mode }}" {{ old('availability_mode', $offer->availability_mode) === $mode ? 'selected' : '' }}>{{ $lbl($availabilityLabels, $mode) }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">الكمية المتاحة</label>
            <input class="a2-input" type="number" min="0" name="available_quantity" value="{{ old('available_quantity', $offer->available_quantity) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">يبدأ في</label>
            <input class="a2-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $offer->starts_at ? $offer->starts_at->format('Y-m-d\\TH:i') : '') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">ينتهي في</label>
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
            <label class="a2-label">نموذج الدفع</label>
            <input class="a2-input" type="text" name="payment_model" value="{{ old('payment_model', $offer->payment_model) }}" placeholder="pay_now / pay_later / deposit">
        </div>

        <div class="a2-field">
            <label class="a2-label">درجة الترتيب (Ranking)</label>
            <input class="a2-input" type="number" step="0.0001" min="0" name="ranking_score" value="{{ old('ranking_score', $offer->ranking_score) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Meta JSON</h2>

        <div class="a2-field">
            <label class="a2-label">بيانات إضافية (Meta)</label>
            <textarea class="a2-textarea" name="meta_json" rows="10" placeholder='{"marketing_subscription":"offers_basic"}'>{{ $metaJson }}</textarea>
        </div>
    </div>
</div>

<div class="a2-form-actions">
    <button type="submit" class="a2-btn a2-btn-primary">حفظ العرض</button>
    <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
</div>
