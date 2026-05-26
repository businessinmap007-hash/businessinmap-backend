<div class="a2-card">
    <div class="a2-section-title">بيانات خدمة النظام</div>
    <div class="a2-section-subtitle">
        خدمة النظام هي تعريف عام مثل
        <span dir="ltr">booking</span> أو
        <span dir="ltr">delivery</span> أو
        <span dir="ltr">menu</span>.
        ربط الخدمة بالأقسام الفرعية يتم من شاشة التصنيفات، ورسوم العميل والبزنس تتم من شاشة Service Fees.
    </div>

    <div class="a2-grid-2 a2-mt-16" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
        <div>
            <label class="a2-label">Key</label>
            <input
                class="a2-input"
                type="text"
                name="key"
                value="{{ old('key', $row->key) }}"
                required
                placeholder="booking / menu / delivery"
                dir="ltr"
            >
            <div class="a2-hint a2-mt-8">
                يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو
                <span dir="ltr">_</span>
                أو
                <span dir="ltr">-</span>
                فقط. مثال:
                <span dir="ltr">booking</span>
            </div>
        </div>

        <div>
            <label class="a2-label">الحالة</label>

            <label class="a2-check" style="margin-top:10px;">
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

        <div>
            <label class="a2-label">الاسم عربي</label>
            <input
                class="a2-input"
                type="text"
                name="name_ar"
                value="{{ old('name_ar', $row->name_ar) }}"
                required
            >
        </div>

        <div>
            <label class="a2-label">Name EN</label>
            <input
                class="a2-input"
                type="text"
                name="name_en"
                value="{{ old('name_en', $row->name_en) }}"
            >
        </div>

        <div>
            <label class="a2-label">Deposit</label>

            <label class="a2-check" style="margin-top:10px;">
                <input
                    type="checkbox"
                    name="supports_deposit"
                    value="1"
                    @checked((bool) old('supports_deposit', (int) $row->supports_deposit))
                >
                <span>هذه الخدمة تدعم الديبوزت</span>
            </label>

            <div class="a2-hint a2-mt-8">
                إذا لم تكن مفعلة فسيتم تجاهل نسبة الديبوزت في أسعار البزنس.
            </div>
        </div>

        <div>
            <label class="a2-label">Max Deposit %</label>
            <input
                class="a2-input"
                type="number"
                min="0"
                max="100"
                name="max_deposit_percent"
                value="{{ old('max_deposit_percent', $row->max_deposit_percent ?? 0) }}"
                placeholder="0"
            >
            <div class="a2-hint a2-mt-8">
                الحد الأقصى لنسبة الديبوزت على هذه الخدمة.
            </div>
        </div>
    </div>
</div>

<div class="a2-card a2-mt-16">
    <div class="a2-section-title">Legacy Platform Fee</div>
    <div class="a2-section-subtitle">
        هذه الحقول قديمة للتوافق فقط:
        <span dir="ltr">fee_type</span>
        و
        <span dir="ltr">fee_value</span>.
        الرسوم الجديدة الفعلية على العميل والبزنس تكون من
        <span dir="ltr">category_child_service_fees</span>.
    </div>

    <div class="a2-grid-2 a2-mt-16" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
        <div>
            <label class="a2-label">Fee Type</label>
            <select class="a2-input" name="fee_type">
                <option value="">-- بدون --</option>
                <option value="fixed" @selected(old('fee_type', $row->fee_type) === 'fixed')>fixed</option>
                <option value="percent" @selected(old('fee_type', $row->fee_type) === 'percent')>percent</option>
            </select>
        </div>

        <div>
            <label class="a2-label">Fee Value</label>
            <input
                class="a2-input"
                type="number"
                step="0.01"
                min="0"
                name="fee_value"
                value="{{ old('fee_value', $row->fee_value) }}"
                placeholder="0.00"
            >
        </div>
    </div>
</div>

<div class="a2-card a2-mt-16">
    <div class="a2-section-title">Rules JSON</div>
    <div class="a2-section-subtitle">
        هذا الحقل اختياري ومخصص لأي إعدادات ديناميكية لاحقة. يجب أن يكون JSON صحيحًا إذا تم استخدامه.
    </div>

    <div class="a2-mt-16">
        <label class="a2-label">Rules</label>
        <textarea
            class="a2-textarea"
            name="rules"
            rows="8"
            dir="ltr"
            placeholder='{"example": true}'
            style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;"
        >{{ old('rules', is_array($row->rules ?? null) ? json_encode($row->rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    </div>
</div>

<div class="a2-actionsbar a2-mt-16" style="display:flex;gap:10px;flex-wrap:wrap;">
    <button class="a2-btn a2-btn-primary" type="submit">
        {{ $submitLabel ?? 'Save' }}
    </button>

    <a class="a2-btn a2-btn-ghost" href="{{ route('admin.platform-services.index') }}">
        Back
    </a>
</div>