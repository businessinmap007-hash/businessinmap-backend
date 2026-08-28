@extends('business.layouts.master')

@section('title', __('إعدادات المنيو'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('إعدادات المنيو') }}</h1>
        <div class="a2-page-subtitle">{{ __('هل أسعار أصنافك شاملة رسوم الخدمة والضريبة؟') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">{{ __(\App\Support\BusinessPanelNav::catalogLabel()) }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('business.menu-settings.update') }}">
    @csrf
    @method('PUT')

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('شمول السعر') }}</div>
                <div class="a2-card-sub">{{ __('إذا كان السعر شاملاً لن يُضاف فوقه؛ وإلا يُضاف على الطلب.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-check">
                    <input type="checkbox" name="prices_include_service" value="1" @checked((bool) old('prices_include_service', $row->prices_include_service))>
                    <span>{{ __('الأسعار شاملة رسوم الخدمة (لا تُضاف فوق السعر)') }}</span>
                </label>
            </div>

            <div class="a2-form-group a2-field-full">
                <label class="a2-check">
                    <input type="checkbox" name="prices_include_tax" value="1" @checked((bool) old('prices_include_tax', $row->prices_include_tax))>
                    <span>{{ __('الأسعار شاملة الضريبة (لا تُضاف فوق السعر)') }}</span>
                </label>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('نسبة الضريبة') }}</div>
                <div class="a2-card-sub">{{ __('اترك الحقل فارغاً لاستخدام النسبة الافتراضية للمنصة (:rate%).', ['rate' => rtrim(rtrim(number_format($defaultTaxRate, 2), '0'), '.')]) }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="tax_rate_percent">{{ __('نسبة الضريبة على المنيو (%)') }}</label>
                <input type="number" step="0.01" min="0" max="100" id="tax_rate_percent" name="tax_rate_percent"
                    class="a2-input @error('tax_rate_percent') a2-input-error @enderror"
                    value="{{ old('tax_rate_percent', $row->tax_rate_percent) }}"
                    placeholder="{{ rtrim(rtrim(number_format($defaultTaxRate, 2), '0'), '.') }}">
                @error('tax_rate_percent')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('حدٌّ أدنى للطلب') }}</div>
                <div class="a2-card-sub">{{ __('اترك الحقل فارغاً لعدم اشتراط حدٍّ أدنى. لا يُحسب فيه سوى أصناف المنيو نفسها.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="min_order_amount">{{ __('الحد الأدنى لقيمة الطلب (جنيه)') }}</label>
                <input type="number" step="0.01" min="0" id="min_order_amount" name="min_order_amount"
                    class="a2-input @error('min_order_amount') a2-input-error @enderror"
                    value="{{ old('min_order_amount', $row->min_order_amount) }}"
                    placeholder="{{ __('بلا حدٍّ أدنى') }}">
                @error('min_order_amount')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('هامش الربح الافتراضي') }}</div>
                <div class="a2-card-sub">{{ __('عند تعبئة الرفوف: إذا أدخلت سعر التوريد وتركت سعر البيع فارغاً، يُحسَب سعر البيع تلقائياً بإضافة هذا الهامش فوق سعر التوريد.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="default_margin_percent">{{ __('هامش الربح الافتراضي فوق سعر التوريد (%)') }}</label>
                <input type="number" step="0.01" min="0" max="1000" id="default_margin_percent" name="default_margin_percent"
                    class="a2-input @error('default_margin_percent') a2-input-error @enderror"
                    value="{{ old('default_margin_percent', $row->default_margin_percent) }}"
                    placeholder="{{ __('بلا هامش افتراضي') }}">
                @error('default_margin_percent')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">{{ __('حدٌّ يستوجب ضمانًا') }}</div>
                <div class="a2-card-sub">{{ __('طلب بقيمة أعلى من هذا الحد ولا يملك عميله ضمانًا أو رصيد محفظة يكفيه، يوصلك معلَّم عليه "بلا ضمان" — وأنت تقرر وقت القبول: تكمله على مسؤوليتك أو ترفضه. اترك الحقل فارغاً لعدم اشتراط ضمان أبداً.') }}</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group a2-field-full">
                <label class="a2-label" for="deposit_required_above">{{ __('قيمة الطلب التي تستوجب ضماناً (جنيه)') }}</label>
                <input type="number" step="0.01" min="0" id="deposit_required_above" name="deposit_required_above"
                    class="a2-input @error('deposit_required_above') a2-input-error @enderror"
                    value="{{ old('deposit_required_above', $row->deposit_required_above) }}"
                    placeholder="{{ __('بلا اشتراط') }}">
                @error('deposit_required_above')<div class="a2-field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
        <button type="submit" class="a2-btn a2-btn-primary">{{ __('حفظ') }}</button>
    </div>
</form>
@endsection
