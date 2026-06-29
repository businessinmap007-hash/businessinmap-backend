<div class="a2-card">
    <div class="a2-section-title">بيانات خدمة النظام</div>
    <div class="a2-section-subtitle">
        خدمة النظام هي تعريف عام فقط مثل
        <span dir="ltr">booking</span> أو
        <span dir="ltr">delivery</span> أو
        <span dir="ltr">menu</span>.
        لا يتم حساب أي رسوم من هذه الصفحة.
    </div>

    <div class="a2-form-grid a2-mt-16">
        <div class="a2-form-group">
            <label class="a2-label">Key</label>
            <input
                class="a2-input"
                type="text"
                name="key"
                value="{{ old('key', $row->key) }}"
                required
                placeholder="booking / menu / delivery / business_offers"
                dir="ltr"
            >
            <div class="a2-hint a2-mt-8">
                يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو
                <span dir="ltr">_</span>
                أو
                <span dir="ltr">-</span>
                فقط.
            </div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>

            <label class="a2-check a2-mt-8">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked((bool) old('is_active', (int) $row->is_active))
                >
                <span>الخدمة مفعلة</span>
            </label>

            <div class="a2-hint a2-mt-8">
                تعطيل الخدمة يمنع استخدامها في الإعدادات الجديدة، لكنه لا يحذف السجلات القديمة.
            </div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الاسم عربي</label>
            <input
                class="a2-input"
                type="text"
                name="name_ar"
                value="{{ old('name_ar', $row->name_ar) }}"
                required
            >
        </div>

        <div class="a2-form-group">
            <label class="a2-label">Name EN</label>
            <input
                class="a2-input"
                type="text"
                name="name_en"
                value="{{ old('name_en', $row->name_en) }}"
            >
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">Deposit Support</label>

            <label class="a2-check a2-mt-8">
                <input
                    type="checkbox"
                    name="supports_deposit"
                    value="1"
                    @checked((bool) old('supports_deposit', (int) $row->supports_deposit))
                >
                <span>هذه الخدمة تدعم نظام الديبوزت</span>
            </label>

            <div class="a2-hint a2-mt-8">
                هذا مجرد flag عام يوضح أن الخدمة يمكن أن تعمل مع الديبوزت.
                قيمة الديبوزت وقواعده لا تُحسب من PlatformService.
            </div>
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">Service Rules JSON</label>
            @php
                $rulesValue = old('rules_json');
                if ($rulesValue === null) {
                    $rawRules = $row->rules ?? null;
                    $rulesValue = is_array($rawRules)
                        ? json_encode($rawRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        : (is_string($rawRules) ? $rawRules : '');
                }
            @endphp
            <textarea
                class="a2-textarea"
                name="rules_json"
                rows="8"
                dir="ltr"
                placeholder='{"max_active_offers":5,"duration_days":30,"fixed_fee":20}'
            >{{ $rulesValue }}</textarea>
            <div class="a2-hint a2-mt-8">
                تستخدم للخدمات التي لها قواعد تشغيل مثل
                <span dir="ltr">business_offers</span>.
                اتركها فارغة إذا لم تكن الخدمة تحتاج rules.
            </div>
            @error('rules_json')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--soft a2-mt-16">
    <div class="a2-section-title">مصادر الرسوم المعتمدة</div>
    <div class="a2-section-subtitle">
        رسوم العميل والبزنس والديبوزت يتم إدارتها من
        <span dir="ltr">category_child_service_fees</span>.
        وإذا وُجد عرض فعال داخل
        <span dir="ltr">platform_service_fee_promotions</span>
        تكون له الأولوية فوق رسوم القسم الفرعي.
    </div>
</div>

<div class="a2-actionsbar a2-mt-16">
    <button class="a2-btn a2-btn-primary" type="submit">
        {{ $submitLabel ?? 'Save' }}
    </button>

    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.platform-services.index') }}">
        Back
    </a>
</div>
