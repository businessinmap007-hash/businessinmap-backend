@php $isEdit = isset($row) && $row?->exists; @endphp

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
            <div class="a2-card-title">{{ __('بيانات الصنف') }}</div>
            <div class="a2-card-sub">{{ __('صنف من منيو نشاطك يمكن للعميل طلبه.') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="name_ar">{{ __('الاسم العربي') }} <span class="a2-danger">*</span></label>
            <input class="a2-input" id="name_ar" name="name_ar" value="{{ old('name_ar', $row->name_ar ?? '') }}" placeholder="{{ __('برجر لحم') }}" required>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="name_en">{{ __('الاسم الإنجليزي') }}</label>
            <input class="a2-input" id="name_en" name="name_en" value="{{ old('name_en', $row->name_en ?? '') }}" dir="ltr" placeholder="Beef Burger">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="menu_section_id">{{ __('القسم') }}</label>
            <select class="a2-input" id="menu_section_id" name="menu_section_id">
                <option value="">{{ __('— بدون قسم —') }}</option>
                @foreach(($sections ?? []) as $section)
                    <option value="{{ $section->id }}" @selected((int) old('menu_section_id', $row->menu_section_id ?? 0) === (int) $section->id)>{{ $section->name_ar }}</option>
                @endforeach
            </select>
            @if(($sections ?? collect())->isEmpty())
                <small class="a2-help"><a href="{{ route('business.menu-sections.create') }}">{{ __('أضف أقساماً') }}</a> {{ __('لتنظيم المنيو (مقبلات، رئيسي، حلويات…).') }}</small>
            @endif
        </div>

        @if(! empty($itemTypes ?? []))
            {{-- The heading, straight from the taxonomy: the merchant picks
                 «مشويات» and every grill he adds files itself under it. No
                 typing, and it cannot drift from what his activity may sell. --}}
            <div class="a2-form-group">
                <label class="a2-label" for="item_type">{{ __('النوع') }}</label>
                <select class="a2-select" id="item_type" name="item_type">
                    <option value="">{{ __('— بدون نوع —') }}</option>
                    @foreach($itemTypes as $type)
                        <option value="{{ $type['key'] }}" @selected((string) old('item_type', $row->item_type ?? '') === (string) $type['key'])>{{ $type['label'] }}</option>
                    @endforeach
                </select>
                <small class="a2-help">{{ __('يجمع أصنافك تحت بند واحد في المنيو — كل المشويات تحت «مشويات».') }}</small>
            </div>
        @endif

        <div class="a2-form-group">
            <label class="a2-label" for="base_price">{{ __('السعر') }} <span class="a2-danger">*</span></label>
            <input class="a2-input" id="base_price" name="base_price" value="{{ old('base_price', $row->base_price ?? 0) }}" inputmode="decimal" placeholder="0.00" required>
        </div>

        {{-- «الطماطم ٤٥» هو أربعون وخمسة للكيلو، أو للصندوق، أو لحبةٍ واحدة —
             والزبونُ يعرف عند الميزان. الفراغُ يعني «بالقطعة»، وهو ما عليه
             أغلبُ المنيوهات؛ ومن يبيع بالوزن يقولها. --}}
        <div class="a2-form-group">
            <label class="a2-label" for="sale_unit">{{ __('وحدة البيع') }}</label>
            <select class="a2-select" id="sale_unit" name="sale_unit">
                <option value="">{{ __('— بالقطعة —') }}</option>
                @foreach($saleUnits as $code => $label)
                    <option value="{{ $code }}" @selected((string) old('sale_unit', $row->sale_unit ?? '') === (string) $code)>{{ $label }}</option>
                @endforeach
            </select>
            <small class="a2-help">{{ __('السعر لكل ماذا — كجم للخضار، لتر للعصير. اتركها فارغة إن كان السعر للقطعة.') }}</small>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="sort_order">{{ __('الترتيب') }}</label>
            <input class="a2-input" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', (int) ($row->sort_order ?? 0)) }}">
        </div>

        <div class="a2-form-group a2-field-full">
            <label class="a2-label" for="description_ar">{{ __('الوصف') }}</label>
            <textarea class="a2-textarea" id="description_ar" name="description_ar" placeholder="{{ __('وصف مختصر للصنف') }}">{{ old('description_ar', $row->description_ar ?? '') }}</textarea>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الحالة') }}</label>
            <label class="a2-check" style="margin-top:10px;">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', (int) ($row->is_active ?? 1)))>
                <span>{{ __('متاح للطلب') }}</span>
            </label>
        </div>
    </div>
</div>

@include('business._partials.offering-vocabulary')

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    <button type="submit" class="a2-btn a2-btn-primary">{{ $isEdit ? __('تحديث') : __('حفظ') }}</button>
</div>
