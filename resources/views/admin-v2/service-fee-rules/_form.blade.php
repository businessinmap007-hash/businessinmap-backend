@php
    use App\Models\ServiceFeeRule;

    $isEdit = isset($rule) && $rule?->exists;
    $c = $conditions ?? [];

    $currentGovs = (array) old('c_governorate_ids', $c['governorate_ids'] ?? []);
    $currentDays = (array) old('c_days_of_week', $c['days_of_week'] ?? []);
    $currentServiceKeys = (array) old('c_service_keys', $c['service_keys'] ?? []);
    $currentSubscribed = old('c_subscribed', array_key_exists('subscribed', $c) ? ($c['subscribed'] ? '1' : '0') : '');
@endphp

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">القاعدة</div>
            <div class="a2-card-sub">اتركِ النطاق فارغًا ليشمل الجميع. الأولوية الأقل تُطبَّق أولًا، والقواعد المتطابقة تتراكم.</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group" style="grid-column:1/-1;">
            <label class="a2-label" for="name">اسم القاعدة <span class="a2-danger">*</span></label>
            <input class="a2-input" id="name" name="name" value="{{ old('name', $rule->name ?? '') }}" placeholder="زيادة وقت الذروة — القاهرة" required>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="platform_service_id">الخدمة</label>
            <select class="a2-select" id="platform_service_id" name="platform_service_id">
                <option value="">كل الخدمات</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected((int) old('platform_service_id', $rule->platform_service_id ?? 0) === (int) $service->id)>
                        {{ $service->name_ar ?: $service->key }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="child_id">القسم الفرعي</label>
            <select class="a2-select" id="child_id" name="child_id">
                <option value="">كل الأقسام</option>
                @foreach($children as $child)
                    <option value="{{ $child->id }}" @selected((int) old('child_id', $rule->child_id ?? 0) === (int) $child->id)>
                        {{ $child->name_ar ?? $child->name ?? ('#' . $child->id) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="payer">الطرف الذي تُعدَّل رسومه <span class="a2-danger">*</span></label>
            <select class="a2-select" id="payer" name="payer" required>
                @foreach($payers as $key => $label)
                    <option value="{{ $key }}" @selected((string) old('payer', $rule->payer ?? ServiceFeeRule::PAYER_ANY) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="priority">الأولوية</label>
            <input class="a2-input" id="priority" name="priority" type="number" min="0" value="{{ old('priority', $rule->priority ?? 100) }}">
            <div class="a2-hint a2-mt-8">الأقل يُطبَّق أولًا.</div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">إيقاف التسلسل</label>
            <label class="a2-check" style="margin-top:10px;">
                <input type="checkbox" name="stop_on_match" value="1" @checked((bool) old('stop_on_match', (bool) ($rule->stop_on_match ?? false)))>
                <span>لا تُطبَّق أي قاعدة بعد هذه</span>
            </label>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>
            <label class="a2-check" style="margin-top:10px;">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', (bool) ($rule->is_active ?? true)))>
                <span>مفعلة</span>
            </label>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">التأثير على الرسوم</div>
            <div class="a2-card-sub">النسبة والمبلغ يقبلان قيمة سالبة للخصم. الحدود تُطبَّق بعد التأثير، والرسوم لا تنزل تحت الصفر أبدًا.</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="effect">التأثير <span class="a2-danger">*</span></label>
            <select class="a2-select js-sfr-effect" id="effect" name="effect" required>
                @foreach($effects as $key => $label)
                    <option value="{{ $key }}" @selected((string) old('effect', $rule->effect ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group js-sfr-effect-value">
            <label class="a2-label" for="effect_value">القيمة</label>
            <input class="a2-input" id="effect_value" name="effect_value" type="number" step="0.01" value="{{ old('effect_value', $rule->effect_value ?? '') }}" placeholder="50 = +50%، -10 = خصم 10">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="min_fee">حد أدنى للرسوم</label>
            <input class="a2-input" id="min_fee" name="min_fee" type="number" step="0.01" min="0" value="{{ old('min_fee', $rule->min_fee ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="max_fee">حد أقصى للرسوم</label>
            <input class="a2-input" id="max_fee" name="max_fee" type="number" step="0.01" min="0" value="{{ old('max_fee', $rule->max_fee ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="starts_at">تبدأ في</label>
            <input class="a2-input" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', optional($rule->starts_at ?? null)->format('Y-m-d\TH:i')) }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="ends_at">تنتهي في</label>
            <input class="a2-input" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', optional($rule->ends_at ?? null)->format('Y-m-d\TH:i')) }}">
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">الشروط</div>
            <div class="a2-card-sub">
                كل الشروط المملوءة يجب أن تتحقق معًا. الحقل الفارغ يعني «لا يهم» — واترك الكل فارغًا لتطبيق القاعدة على النطاق كله.
            </div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="c_min_base_amount">أقل قيمة للعملية</label>
            <input class="a2-input" id="c_min_base_amount" name="c_min_base_amount" type="number" step="0.01" min="0" value="{{ old('c_min_base_amount', $c['min_base_amount'] ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_max_base_amount">أعلى قيمة للعملية</label>
            <input class="a2-input" id="c_max_base_amount" name="c_max_base_amount" type="number" step="0.01" min="0" value="{{ old('c_max_base_amount', $c['max_base_amount'] ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_min_success_operations">أقل عدد عمليات ناجحة</label>
            <input class="a2-input" id="c_min_success_operations" name="c_min_success_operations" type="number" min="0" value="{{ old('c_min_success_operations', $c['min_success_operations'] ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_max_success_operations">أعلى عدد عمليات ناجحة</label>
            <input class="a2-input" id="c_max_success_operations" name="c_max_success_operations" type="number" min="0" value="{{ old('c_max_success_operations', $c['max_success_operations'] ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_time_from">من الساعة</label>
            <input class="a2-input" id="c_time_from" name="c_time_from" type="time" value="{{ old('c_time_from', $c['time_from'] ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_time_to">إلى الساعة</label>
            <input class="a2-input" id="c_time_to" name="c_time_to" type="time" value="{{ old('c_time_to', $c['time_to'] ?? '') }}">
            <div class="a2-hint a2-mt-8">نافذة تعبر منتصف الليل مسموحة (22:00 → 02:00).</div>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_subscribed">اشتراك البزنس</label>
            <select class="a2-select" id="c_subscribed" name="c_subscribed">
                <option value="" @selected($currentSubscribed === '')>لا يهم</option>
                <option value="1" @selected($currentSubscribed === '1')>مشترك فقط</option>
                <option value="0" @selected($currentSubscribed === '0')>غير مشترك فقط</option>
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_days_of_week">أيام الأسبوع</label>
            <select class="a2-select" id="c_days_of_week" name="c_days_of_week[]" multiple size="4">
                @foreach($days as $value => $label)
                    <option value="{{ $value }}" @selected(in_array((string) $value, array_map('strval', $currentDays), true))>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_governorate_ids">المحافظات</label>
            <select class="a2-select" id="c_governorate_ids" name="c_governorate_ids[]" multiple size="6">
                @foreach($governorates as $gov)
                    <option value="{{ $gov->id }}" @selected(in_array((string) $gov->id, array_map('strval', $currentGovs), true))>{{ $gov->name_ar }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="c_service_keys">أنواع الخدمة</label>
            <select class="a2-select" id="c_service_keys" name="c_service_keys[]" multiple size="6">
                @foreach($services as $service)
                    <option value="{{ $service->key }}" @selected(in_array((string) $service->key, array_map('strval', $currentServiceKeys), true))>{{ $service->name_ar ?: $service->key }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group" style="grid-column:1/-1;">
            <label class="a2-label" for="notes">ملاحظات</label>
            <textarea class="a2-input" id="notes" name="notes" rows="3">{{ old('notes', $rule->notes ?? '') }}</textarea>
        </div>
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('admin.service-fee-rules.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    <button type="submit" class="a2-btn a2-btn-primary">{{ $isEdit ? 'تحديث' : 'حفظ' }}</button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var effect = document.querySelector('.js-sfr-effect');
    var valueField = document.querySelector('.js-sfr-effect-value');
    if (!effect || !valueField) return;

    // A waive has nothing to configure — hiding the box beats explaining it.
    function sync() {
        valueField.style.display = effect.value === 'waive' ? 'none' : '';
    }

    effect.addEventListener('change', sync);
    sync();
});
</script>
@endpush
