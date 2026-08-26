@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-form-grid">
        <div class="a2-form-group a2-field-full">
            <label class="a2-label" for="name_ar">{{ __('الاسم') }} <span class="a2-danger">*</span></label>
            <input class="a2-input" id="name_ar" name="name_ar" value="{{ old('name_ar', $row->name_ar ?? '') }}" required>
        </div>

        <div class="a2-form-group">
            <label class="a2-check">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $row->is_active ?? true))>
                <span>{{ __('مفعّلة') }}</span>
            </label>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="currency">{{ __('العملة') }}</label>
            <input class="a2-input" id="currency" name="currency" maxlength="3" dir="ltr" style="max-width:100px;text-transform:uppercase;"
                   value="{{ old('currency', $row->currency ?? 'EGP') }}">
        </div>
    </div>
</div>

<div class="a2-card-grid-2 a2-mt-16">
    <div class="a2-card a2-card--section">
        <div class="a2-card-title">{{ __('على التاجر') }}</div>

        <label class="a2-check a2-mt-8">
            <input type="checkbox" name="business_fee_enabled" value="1" @checked((bool) old('business_fee_enabled', $row->business_fee_enabled ?? true))>
            <span>{{ __('مفعّل') }}</span>
        </label>

        <div class="a2-form-grid a2-mt-12">
            <div class="a2-form-group">
                <label class="a2-label">{{ __('نوع الرسم') }}</label>
                <select class="a2-select" name="business_fee_type">
                    <option value="fixed" @selected(old('business_fee_type', $row->business_fee_type ?? 'fixed') === 'fixed')>{{ __('مبلغ ثابت') }}</option>
                    <option value="percent" @selected(old('business_fee_type', $row->business_fee_type ?? '') === 'percent')>{{ __('نسبة %') }}</option>
                </select>
            </div>

            <div class="a2-form-group">
                <label class="a2-label">{{ __('القيمة') }}</label>
                <input class="a2-input" type="number" step="0.01" min="0" dir="ltr" name="business_fee_amount"
                       value="{{ old('business_fee_amount', $row->business_fee_amount ?? 5) }}">
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-title">{{ __('على العميل') }}</div>

        <label class="a2-check a2-mt-8">
            <input type="checkbox" name="client_fee_enabled" value="1" @checked((bool) old('client_fee_enabled', $row->client_fee_enabled ?? true))>
            <span>{{ __('مفعّل') }}</span>
        </label>

        <div class="a2-form-grid a2-mt-12">
            <div class="a2-form-group">
                <label class="a2-label">{{ __('نوع الرسم') }}</label>
                <select class="a2-select" name="client_fee_type">
                    <option value="fixed" @selected(old('client_fee_type', $row->client_fee_type ?? 'fixed') === 'fixed')>{{ __('مبلغ ثابت') }}</option>
                    <option value="percent" @selected(old('client_fee_type', $row->client_fee_type ?? '') === 'percent')>{{ __('نسبة %') }}</option>
                </select>
            </div>

            <div class="a2-form-group">
                <label class="a2-label">{{ __('القيمة') }}</label>
                <input class="a2-input" type="number" step="0.01" min="0" dir="ltr" name="client_fee_amount"
                       value="{{ old('client_fee_amount', $row->client_fee_amount ?? 1) }}">
            </div>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section a2-mt-16">
    <div class="a2-form-group a2-field-full">
        <label class="a2-label" for="notes">{{ __('ملاحظات') }}</label>
        <textarea class="a2-textarea" id="notes" name="notes" rows="2">{{ old('notes', $row->notes ?? '') }}</textarea>
    </div>
</div>

<div class="a2-page-actions a2-mt-16">
    <button type="submit" class="a2-btn a2-btn-primary">{{ isset($row) && $row->exists ? __('حفظ') : __('إنشاء') }}</button>
    <a href="{{ route('admin.fee-groups.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
</div>
