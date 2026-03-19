<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">بيانات الخيار</div>
            <div class="a2-card-sub">الاسم العربي والإنجليزي</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">الاسم عربي <span class="a2-danger">*</span></label>
            <input class="a2-input" name="name_ar" value="{{ old('name_ar', $row->name_ar ?? '') }}">
            @error('name_ar')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الاسم إنجليزي</label>
            <input class="a2-input" name="name_en" value="{{ old('name_en', $row->name_en ?? '') }}" dir="ltr">
            @error('name_en')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        @if(!empty($hasSortOrder))
            <div class="a2-form-group">
                <label class="a2-label">الترتيب</label>
                <input class="a2-input" type="number" name="sort_order" value="{{ old('sort_order', $row->sort_order ?? 0) }}">
                @error('sort_order')
                    <div class="a2-error">{{ $message }}</div>
                @enderror
            </div>
        @endif

        @if(!empty($hasIsActive))
            <div class="a2-form-group">
                <label class="a2-label">الحالة</label>
                <select class="a2-select" name="is_active">
                    <option value="1" @selected((string) old('is_active', $row->is_active ?? 1) === '1')>Active</option>
                    <option value="0" @selected((string) old('is_active', $row->is_active ?? 1) === '0')>Inactive</option>
                </select>
            </div>
        @endif
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('admin.options.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    <button type="submit" class="a2-btn a2-btn-primary">
        {{ !empty($row->id) ? 'تحديث' : 'حفظ' }}
    </button>
</div>