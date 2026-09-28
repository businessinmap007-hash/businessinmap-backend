<?php
    $row = $row ?? null;
    $specs = $specs ?? collect();
    $blankSpecRows = $blankSpecRows ?? 6;
    $selectedCategoryId = (int) old('product_category_id', $row->product_category_id ?? 0);
    $selectedChildId = (int) old('product_category_child_id', $row->product_category_child_id ?? 0);
?>
<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('بيانات المنتج') }}</div>
            <div class="a2-card-sub">{{ __('القسم، الاسم، البراند والوحدة') }}</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('القسم الرئيسي') }} <span class="a2-danger">*</span></label>
            {{-- no-ts: this select DRIVES the cascade below, and tom-select's
                 selection doesn't reliably reach a plain `change` listener on
                 the original element — plain native select keeps both ends
                 of the cascade on the one event model. --}}
            <select class="a2-select no-ts" name="product_category_id" id="js-category-select" required>
                <option value="">{{ __('اختر القسم') }}</option>
                @foreach($productCategories as $cat)
                    <option value="{{ $cat->id }}" @selected($selectedCategoryId === (int) $cat->id)>{{ $cat->name_ar ?: $cat->name_en }}</option>
                @endforeach
            </select>
            @error('product_category_id')<div class="a2-error">{{ $message }}</div>@enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('القسم الفرعي') }} <span class="a2-danger">*</span></label>
            {{-- no-ts: this select's options are filtered natively by the
                 cascade script below — tom-select would keep rendering its
                 own captured copy of the option list and never notice the
                 `hidden` attribute the filter sets. See admin-v2 layout's
                 initTomSelects() comment. --}}
            <select class="a2-select no-ts" name="product_category_child_id" id="js-child-select" required>
                <option value="">{{ __('اختر القسم الفرعي') }}</option>
                @foreach($children as $child)
                    <option value="{{ $child->id }}" data-category="{{ $child->product_category_id }}" @selected($selectedChildId === (int) $child->id)>{{ $child->name_ar ?: $child->name_en }}</option>
                @endforeach
            </select>
            @error('product_category_child_id')<div class="a2-error">{{ $message }}</div>@enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الاسم عربي') }} <span class="a2-danger">*</span></label>
            <input class="a2-input" name="name_ar" value="{{ old('name_ar', $row->name_ar ?? '') }}">
            @error('name_ar')<div class="a2-error">{{ $message }}</div>@enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الاسم إنجليزي') }}</label>
            <input class="a2-input" name="name_en" value="{{ old('name_en', $row->name_en ?? '') }}" dir="ltr">
            @error('name_en')<div class="a2-error">{{ $message }}</div>@enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الموديل') }}</label>
            <input class="a2-input" name="model" value="{{ old('model', $row->model ?? '') }}" dir="ltr">
            @error('model')<div class="a2-error">{{ $message }}</div>@enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('البراند') }}</label>
            <select class="a2-select" name="brand_id">
                <option value="">{{ __('بدون براند') }}</option>
                @foreach($brandOptions as $brand)
                    <option value="{{ $brand->id }}" @selected((int) old('brand_id', $row->brand_id ?? 0) === (int) $brand->id)>{{ $brand->name_ar ?: $brand->name_en }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('صورة المنتج (رابط)') }}</label>
            <input class="a2-input" name="main_image" value="{{ old('main_image', $row->main_image ?? '') }}" dir="ltr">
            @error('main_image')<div class="a2-error">{{ $message }}</div>@enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الوحدة') }}</label>
            <select class="a2-select" name="unit_id">
                <option value="">{{ __('بدون وحدة') }}</option>
                @foreach($unitOptions as $unit)
                    <option value="{{ $unit->id }}" @selected((int) old('unit_id', $row->unit_id ?? 0) === (int) $unit->id)>{{ $unit->name_ar ?: $unit->code }}</option>
                @endforeach
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('قيمة العبوة/الحجم') }}</label>
            <input class="a2-input" type="number" step="0.001" name="package_value" value="{{ old('package_value', $row->package_value ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('وصف العبوة عربي') }}</label>
            <input class="a2-input" name="package_label_ar" value="{{ old('package_label_ar', $row->package_label_ar ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الحالة') }}</label>
            <select class="a2-select" name="is_active">
                <option value="1" @selected((string) old('is_active', $row->is_active ?? 1) === '1')>Active</option>
                <option value="0" @selected((string) old('is_active', $row->is_active ?? 1) === '0')>Inactive</option>
            </select>
        </div>
    </div>
</div>

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('جدول المواصفات') }}</div>
            <div class="a2-card-sub">{{ __('البروسيسور، الرام، المساحة… كل صف مواصفة واحدة. القيمة النصية أو الرقمية، وليس الاثنين معًا.') }}</div>
        </div>
        <a href="{{ route('admin.catalog-attributes.create') }}" class="a2-btn a2-btn-ghost">{{ __('+ نوع مواصفة جديد') }}</a>
    </div>

    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('المواصفة') }}</th>
                    <th>{{ __('قيمة نصية') }}</th>
                    <th>{{ __('قيمة رقمية') }}</th>
                    @if($specs->isNotEmpty())<th>{{ __('حذف') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach($specs as $i => $spec)
                    <tr>
                        <td>
                            <select class="a2-select" name="specs[{{ $i }}][attribute_id]">
                                <option value="">{{ __('— بدون —') }}</option>
                                @foreach($attributeOptions as $attr)
                                    <option value="{{ $attr->id }}" @selected((int) $spec->attribute_id === (int) $attr->id)>{{ $attr->name_ar ?: $attr->name_en }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="a2-input" name="specs[{{ $i }}][value_text]" value="{{ $spec->value_text_ar }}"></td>
                        <td><input class="a2-input" type="number" step="any" name="specs[{{ $i }}][value_number]" value="{{ $spec->value_number }}"></td>
                        <td><label class="a2-checkbox"><input type="checkbox" name="remove_spec_ids[]" value="{{ $spec->id }}"> {{ __('حذف') }}</label></td>
                    </tr>
                @endforeach
                @for($i = $specs->count(); $i < $specs->count() + $blankSpecRows; $i++)
                    <tr>
                        <td>
                            <select class="a2-select" name="specs[{{ $i }}][attribute_id]">
                                <option value="">{{ __('— بدون —') }}</option>
                                @foreach($attributeOptions as $attr)
                                    <option value="{{ $attr->id }}">{{ $attr->name_ar ?: $attr->name_en }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="a2-input" name="specs[{{ $i }}][value_text]" value=""></td>
                        <td><input class="a2-input" type="number" step="any" name="specs[{{ $i }}][value_number]" value=""></td>
                        @if($specs->isNotEmpty())<td></td>@endif
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('admin.catalog-products.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
    <button type="submit" class="a2-btn a2-btn-primary">{{ !empty($row->id) ? __('تحديث') : __('حفظ') }}</button>
</div>

<script>
(function () {
    var categorySelect = document.getElementById('js-category-select');
    var childSelect = document.getElementById('js-child-select');
    if (!categorySelect || !childSelect) return;

    function filterChildren() {
        var categoryId = categorySelect.value;
        var options = childSelect.querySelectorAll('option[data-category]');
        options.forEach(function (opt) {
            opt.hidden = categoryId !== '' && opt.getAttribute('data-category') !== categoryId;
        });
        if (childSelect.selectedOptions[0] && childSelect.selectedOptions[0].hidden) {
            childSelect.value = '';
        }
    }

    categorySelect.addEventListener('change', filterChildren);
    filterChildren();
})();
</script>
