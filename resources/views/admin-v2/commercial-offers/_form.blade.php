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
        <h2 class="a2-section-title">{{ __('بيانات العرض') }}</h2>

        <div class="a2-field">
            <label class="a2-label">{{ __('العنوان عربي') }}</label>
            <input class="a2-input" type="text" name="title_ar" value="{{ old('title_ar', $offer->title_ar) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('العنوان (إنجليزي)') }}</label>
            <input class="a2-input" type="text" name="title_en" value="{{ old('title_en', $offer->title_en) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('نوع المعروض') }}</label>
            <select class="a2-select" name="offerable_type" required>
                @foreach($offerableTypes as $type)
                    <option value="{{ $type }}" {{ old('offerable_type', $offer->offerable_type) === $type ? 'selected' : '' }}>{{ $lbl($offerableLabels, $type) }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('معرّف المعروض') }}</label>
            <input class="a2-input" type="number" min="0" name="offerable_id" value="{{ old('offerable_id', $offer->offerable_id) }}">
            <div class="a2-help">{{ __('للمنتج أو الخدمة أو الوحدة. استخدم 0 كعرض تسويقي عام للبزنس مؤقتًا.') }}</div>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('صاحب العرض والبائع') }}</h2>

        @php
            $ownerId = (int) old('owner_business_id', $offer->owner_business_id);
            $sellerId = (int) old('seller_business_id', $offer->seller_business_id);
            $bizLookupUrl = route('admin.business-lookup', [], false);
        @endphp

        <div class="a2-field">
            <label class="a2-label">{{ __('البزنس المالك') }}</label>
            <select class="a2-select" name="owner_business_id" required
                    data-remote-url="{{ $bizLookupUrl }}" data-placeholder="{{ __('اختر صاحب الأصل — ابحث بالاسم أو الرقم #') }}">
                <option value="">{{ __('اختر صاحب الأصل') }}</option>
                @if($ownerId)
                    <option value="{{ $ownerId }}" selected>#{{ $ownerId }}@if($offer->ownerBusiness) — {{ $offer->ownerBusiness->name }}@endif</option>
                @endif
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('البزنس البائع') }}</label>
            <select class="a2-select" name="seller_business_id" required
                    data-remote-url="{{ $bizLookupUrl }}" data-placeholder="{{ __('اختر البائع — ابحث بالاسم أو الرقم #') }}">
                <option value="">{{ __('اختر البائع') }}</option>
                @if($sellerId)
                    <option value="{{ $sellerId }}" selected>#{{ $sellerId }}@if($offer->sellerBusiness) — {{ $offer->sellerBusiness->name }}@endif</option>
                @endif
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('مصدر العرض والجمهور') }}</h2>

        <div class="a2-field">
            <label class="a2-label">{{ __('مصدر العرض') }}</label>
            <select class="a2-select" name="source_type" required>
                @foreach($sourceTypes as $type)
                    <option value="{{ $type }}" {{ old('source_type', $offer->source_type) === $type ? 'selected' : '' }}>{{ $lbl($sourceLabels, $type) }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('الجمهور المستهدف') }}</label>
            <select class="a2-select" name="audience_type" required>
                @foreach($audienceTypes as $type)
                    <option value="{{ $type }}" {{ old('audience_type', $offer->audience_type ?: 'both') === $type ? 'selected' : '' }}>{{ $lbl($audienceLabels, $type) }}</option>
                @endforeach
            </select>
            <div class="a2-help">{{ __('شركات (B2B) للبزنس فقط، أفراد (B2C) للعملاء فقط، الجميع للكل.') }}</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('معرّف المصدر') }}</label>
            <input class="a2-input" type="number" min="1" name="source_id" value="{{ old('source_id', $offer->source_id) }}">
            <div class="a2-help">{{ __('يستخدم مع allocation أو reseller. اتركه فارغًا للعروض اليدوية.') }}</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('الحالة') }}</label>
            <select class="a2-select" name="status" required>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" {{ old('status', $offer->status) === $status ? 'selected' : '' }}>{{ $lbl($statusLabels, $status) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('توجيه B2B (اختياري)') }}</h2>
        <div class="a2-help">
            {{ __('استهدف') }} <strong>{{ __('الأب كاملًا') }}</strong> {{ __('ليصل العرض لكل أقسامه، أو اختر') }}
            <strong>{{ __('أقسامًا فرعية محددة') }}</strong>{{ __('. اختر التصنيف بالأسفل لعرض أقسامه فقط، أو ابحث باسم القسم مباشرة (اسم الأب يظهر بجواره لتعرف إن كان من شركات أو محلات). اتركها فارغة ليظهر العرض لكل الجمهور المحدد.') }}
        </div>

        @php
            $childName = fn ($c) => $c->name_ar ?: $c->name_en ?: ('#' . $c->id);
            $parentLabel = function ($c) {
                $names = $c->parents
                    ->map(fn ($p) => $p->name_ar ?: $p->name_en ?: ('#' . $p->id))
                    ->filter()
                    ->values();

                return $names->isEmpty() ? 'بدون تصنيف' : $names->join(' / ');
            };

            // Data the cascade script needs: each child's label + its parent ids.
            $childOptionData = $categoryChildren->map(fn ($c) => [
                'id' => (int) $c->id,
                'label' => $childName($c) . ' — ' . $parentLabel($c),
                'parents' => $c->parents->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ])->values();
        @endphp

        <div class="a2-field">
            <label class="a2-label">{{ __('تصنيفات كاملة (استهداف الأب مباشرة)') }}</label>
            <select class="a2-select" name="target_categories[]" multiple data-placeholder="{{ __('ابحث واختر تصنيفات…') }}">
                @foreach($rootCategories as $cat)
                    <option value="{{ $cat->id }}" @selected(in_array((int) $cat->id, $selectedTargetCategories, true))>
                        #{{ $cat->id }} — {{ $cat->name_ar ?: $cat->name_en }}
                    </option>
                @endforeach
            </select>
            <div class="a2-help">{{ __('العرض يُوجَّه لكل الأقسام داخل هذه التصنيفات.') }}</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('تصفية حسب التصنيف (لعرض أقسامه فقط)') }}</label>
            <select id="b2b-parent-filter" class="a2-select" multiple data-placeholder="{{ __('اختر تصنيفًا لعرض أقسامه…') }}">
                @foreach($rootCategories as $cat)
                    <option value="{{ $cat->id }}">#{{ $cat->id }} — {{ $cat->name_ar ?: $cat->name_en }}</option>
                @endforeach
            </select>
            <div class="a2-help">{{ __('اختياري — لا يُحفَظ، يضيّق قائمة الأقسام بالأسفل فقط. اتركه فارغًا للبحث في كل الأقسام.') }}</div>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('أقسام فرعية محددة') }}</label>
            <select id="b2b-target-children" class="a2-select" name="target_children[]" multiple data-placeholder="{{ __('ابحث باسم القسم…') }}">
                @foreach($categoryChildren as $child)
                    <option value="{{ $child->id }}" @selected(in_array((int) $child->id, $selectedTargetChildren, true))>
                        {{ $childName($child) }} — {{ $parentLabel($child) }}
                    </option>
                @endforeach
            </select>
            <div class="a2-help">{{ __('كل قسم مكتوب بجواره اسم الأب. يمكن اختيار أقسام من تصنيفات مختلفة.') }}</div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        var CHILD_OPTIONS = @json($childOptionData);

        function setup() {
            var filterEl = document.getElementById('b2b-parent-filter');
            var childEl = document.getElementById('b2b-target-children');
            var filterTS = filterEl && filterEl.tomselect;
            var childTS = childEl && childEl.tomselect;

            if (!filterTS || !childTS) {
                // tom-select not ready yet (global init runs on window.load).
                return false;
            }

            function refreshChildren() {
                var selectedParents = new Set(filterTS.items.map(String));
                var keepSelected = new Set(childTS.items.map(String));

                childTS.clearOptions();
                CHILD_OPTIONS.forEach(function (c) {
                    var inParent = selectedParents.size === 0
                        || c.parents.some(function (p) { return selectedParents.has(String(p)); });

                    if (inParent || keepSelected.has(String(c.id))) {
                        childTS.addOption({ value: String(c.id), text: c.label });
                    }
                });
                childTS.refreshOptions(false);
            }

            filterTS.on('change', refreshChildren);
            return true;
        }

        window.addEventListener('load', function () {
            // The global tom-select initializer also runs on `load`; retry a few
            // frames in case our listener fires before instances are attached.
            if (setup()) return;
            var tries = 0;
            var timer = setInterval(function () {
                if (setup() || ++tries > 20) clearInterval(timer);
            }, 50);
        });
    })();
    </script>
    @endpush

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('السعر والخصم') }}</h2>

        <div class="a2-field">
            <label class="a2-label">{{ __('السعر الأساسي') }}</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $offer->base_price) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('السعر النهائي') }}</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="final_price" value="{{ old('final_price', $offer->final_price) }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('العملة') }}</label>
            <input class="a2-input" type="text" name="currency" value="{{ old('currency', $offer->currency ?: 'EGP') }}" required>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('نوع الخصم') }}</label>
            <select class="a2-select" name="discount_type">
                <option value="">{{ __('بدون') }}</option>
                <option value="fixed" {{ old('discount_type', $offer->discount_type) === 'fixed' ? 'selected' : '' }}>{{ __('مبلغ ثابت') }}</option>
                <option value="percent" {{ old('discount_type', $offer->discount_type) === 'percent' ? 'selected' : '' }}>{{ __('نسبة %') }}</option>
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('قيمة الخصم') }}</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="discount_value" value="{{ old('discount_value', $offer->discount_value) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('التوفر والمدة') }}</h2>

        <div class="a2-field">
            <label class="a2-label">{{ __('نمط التوفر') }}</label>
            <select class="a2-select" name="availability_mode" required>
                @foreach($availabilityModes as $mode)
                    <option value="{{ $mode }}" {{ old('availability_mode', $offer->availability_mode) === $mode ? 'selected' : '' }}>{{ $lbl($availabilityLabels, $mode) }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('الكمية المتاحة') }}</label>
            <input class="a2-input" type="number" min="0" name="available_quantity" value="{{ old('available_quantity', $offer->available_quantity) }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('يبدأ في') }}</label>
            <input class="a2-input" type="datetime-local" name="starts_at" value="{{ old('starts_at', $offer->starts_at ? $offer->starts_at->format('Y-m-d\\TH:i') : '') }}">
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('ينتهي في') }}</label>
            <input class="a2-input" type="datetime-local" name="ends_at" value="{{ old('ends_at', $offer->ends_at ? $offer->ends_at->format('Y-m-d\\TH:i') : '') }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">{{ __('شروط العرض') }}</h2>

        <div class="a2-field">
            <label class="a2-checkline">
                <input type="checkbox" name="is_refundable" value="1" {{ old('is_refundable', $offer->is_refundable) ? 'checked' : '' }}>
                <span>{{ __('قابل للاسترداد') }}</span>
            </label>
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('نموذج الدفع') }}</label>
            <input class="a2-input" type="text" name="payment_model" value="{{ old('payment_model', $offer->payment_model) }}" placeholder="pay_now / pay_later / deposit">
        </div>

        <div class="a2-field">
            <label class="a2-label">{{ __('درجة الترتيب (Ranking)') }}</label>
            <input class="a2-input" type="number" step="0.0001" min="0" name="ranking_score" value="{{ old('ranking_score', $offer->ranking_score) }}">
        </div>
    </div>

    <div class="a2-card">
        <h2 class="a2-section-title">Meta JSON</h2>

        <div class="a2-field">
            <label class="a2-label">{{ __('بيانات إضافية (Meta)') }}</label>
            <textarea class="a2-textarea" name="meta_json" rows="10" placeholder='{"marketing_subscription":"offers_basic"}'>{{ $metaJson }}</textarea>
        </div>
    </div>
</div>

<div class="a2-form-actions">
    <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ العرض') }}</button>
    <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
</div>
