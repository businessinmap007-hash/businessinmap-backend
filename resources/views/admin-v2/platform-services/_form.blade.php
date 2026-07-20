<div class="a2-card">
    <div class="a2-section-title">{{ __('بيانات خدمة النظام') }}</div>
    <div class="a2-section-subtitle">
        {{ __('خدمة النظام هي تعريف عام فقط مثل') }}
        <span dir="ltr">booking</span> {{ __('أو') }}
        <span dir="ltr">delivery</span> {{ __('أو') }}
        <span dir="ltr">menu</span>{{ __('. لا يتم حساب أي رسوم من هذه الصفحة.') }}
    </div>

    @php
        $rawRules = old('rules_json');
        $rulesArray = [];

        if ($rawRules !== null && trim((string) $rawRules) !== '') {
            $decodedRules = json_decode((string) $rawRules, true);
            $rulesArray = is_array($decodedRules) ? $decodedRules : [];
        } else {
            $modelRules = $row->rules ?? null;
            if (is_array($modelRules)) {
                $rulesArray = $modelRules;
            } elseif (is_string($modelRules) && trim($modelRules) !== '') {
                $decodedRules = json_decode($modelRules, true);
                $rulesArray = is_array($decodedRules) ? $decodedRules : [];
            }
        }

        $notificationEnabled = old('notification_enabled');
        if ($notificationEnabled === null) {
            $notificationEnabled = (bool) data_get($rulesArray, 'notification_enabled', false);
        }

        $rulesValue = old('rules_json');
        if ($rulesValue === null) {
            $rulesValue = $rulesArray
                ? json_encode($rulesArray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                : '';
        }
    @endphp

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
                {{ __('يجب أن يحتوي على حروف إنجليزية صغيرة أو أرقام أو') }}
                <span dir="ltr">_</span>
                {{ __('أو') }}
                <span dir="ltr">-</span>
                {{ __('فقط.') }}
            </div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الحالة') }}</label>

            <label class="a2-check a2-mt-8">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    @checked((bool) old('is_active', (int) $row->is_active))
                >
                <span>{{ __('الخدمة مفعلة') }}</span>
            </label>

            <div class="a2-hint a2-mt-8">
                {{ __('تعطيل الخدمة يمنع استخدامها في الإعدادات الجديدة، لكنه لا يحذف السجلات القديمة.') }}
            </div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الاسم عربي') }}</label>
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
                <span>{{ __('هذه الخدمة تدعم نظام الديبوزت') }}</span>
            </label>

            <div class="a2-hint a2-mt-8">
                {{ __('هذا مجرد flag عام يوضح أن الخدمة يمكن أن تعمل مع الديبوزت. قيمة الديبوزت وقواعده لا تُحسب من PlatformService.') }}
            </div>
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">Notifications</label>

            <label class="a2-check a2-mt-8">
                <input
                    type="checkbox"
                    name="notification_enabled"
                    value="1"
                    @checked((bool) $notificationEnabled)
                >
                <span>{{ __('إظهار هذه الخدمة كنوع إشعار وإرسال إشعاراتها ضمن Notification Center') }}</span>
            </label>

            <div class="a2-hint a2-mt-8">
                {{ __('عند التفعيل يتم حفظ') }}
                <span dir="ltr">notification_enabled: true</span>
                {{ __('تلقائيًا داخل') }}
                <span dir="ltr">Service Rules JSON</span>.
            </div>
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label">Service Rules JSON</label>
            <textarea
                class="a2-textarea"
                name="rules_json"
                rows="8"
                dir="ltr"
                placeholder='{"notification_enabled": true, "notification_template": "default"}'
            >{{ $rulesValue }}</textarea>
            <div class="a2-hint a2-mt-8">
                {{ __('حقل متقدم لقواعد الخدمة. يمكن تركه فارغًا، والسويتش أعلاه سيضيف') }}
                <span dir="ltr">notification_enabled</span>
                {{ __('تلقائيًا عند الحفظ.') }}
            </div>
            @error('rules_json')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-card--soft a2-mt-16">
    <div class="a2-section-title">{{ __('مصادر الرسوم المعتمدة') }}</div>
    <div class="a2-section-subtitle">
        {{ __('رسوم العميل والبزنس والديبوزت يتم إدارتها من') }}
        <span dir="ltr">category_child_service_fees</span>{{ __('. وإذا وُجد عرض فعال داخل') }}
        <span dir="ltr">platform_service_fee_promotions</span>
        {{ __('تكون له الأولوية فوق رسوم القسم الفرعي.') }}
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
