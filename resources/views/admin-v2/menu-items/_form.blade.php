@php
    $row = $row ?? null;
    $businesses = $businesses ?? [];
    $submitLabel = $submitLabel ?? 'حفظ';
@endphp

<div class="a2-card">
    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">{{ __('البزنس') }}</label>
            @php $bizId = (int) old('business_id', $row->business_id ?? 0); @endphp
            <select class="a2-select" name="business_id" required
                    data-remote-url="{{ route('admin.business-lookup', [], false) }}"
                    data-placeholder="{{ __('اختر البزنس — ابحث بالاسم أو الرقم #') }}">
                <option value="">{{ __('اختر البزنس') }}</option>
                @if($bizId)
                    <option value="{{ $bizId }}" selected>#{{ $bizId }}@if($row->business) — {{ $row->business->name }}@endif</option>
                @endif
            </select>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('قسم المنيو (اختياري)') }}</label>
            <input class="a2-input" type="number" name="category_id" value="{{ old('category_id', $row->category_id ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الاسم بالعربي') }}</label>
            <input class="a2-input" type="text" name="name_ar" value="{{ old('name_ar', $row->name_ar ?? '') }}" required>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الاسم بالإنجليزي') }}</label>
            <input class="a2-input" type="text" name="name_en" value="{{ old('name_en', $row->name_en ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('السعر الأساسي') }}</label>
            <input class="a2-input" type="number" step="0.01" min="0" name="base_price" value="{{ old('base_price', $row->base_price ?? 0) }}" required>
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الترتيب') }}</label>
            <input class="a2-input" type="number" name="sort_order" value="{{ old('sort_order', $row->sort_order ?? 0) }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">{{ __('الصورة (رابط)') }}</label>
            <input class="a2-input" type="text" name="image" value="{{ old('image', $row->image ?? '') }}">
        </div>

        <div class="a2-form-group a2-form-group-full">
            <label class="a2-label">{{ __('الوصف بالعربي') }}</label>
            <textarea class="a2-textarea" name="description_ar">{{ old('description_ar', $row->description_ar ?? '') }}</textarea>
        </div>

        <div class="a2-form-group a2-form-group-full">
            <label class="a2-label">{{ __('الوصف بالإنجليزي') }}</label>
            <textarea class="a2-textarea" name="description_en">{{ old('description_en', $row->description_en ?? '') }}</textarea>
        </div>

        <div class="a2-form-group">
            <label class="a2-checkbox-label">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $row->is_active ?? true))>
                {{ __('مفعّل') }}
            </label>
        </div>
    </div>

    <div class="a2-form-actions">
        <button type="submit" class="a2-btn a2-btn-primary">{{ $submitLabel }}</button>
    </div>
</div>
