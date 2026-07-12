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
            <div class="a2-card-title">بيانات القسم</div>
            <div class="a2-card-sub">قسم يجمع أصناف المنيو المتشابهة.</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label" for="name_ar">الاسم العربي <span class="a2-danger">*</span></label>
            <input class="a2-input" id="name_ar" name="name_ar" value="{{ old('name_ar', $row->name_ar ?? '') }}" placeholder="أطباق رئيسية" required>
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="name_en">الاسم الإنجليزي</label>
            <input class="a2-input" id="name_en" name="name_en" value="{{ old('name_en', $row->name_en ?? '') }}" dir="ltr" placeholder="Main Dishes">
        </div>

        <div class="a2-form-group">
            <label class="a2-label" for="sort_order">الترتيب</label>
            <input class="a2-input" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', (int) ($row->sort_order ?? 0)) }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>
            <label class="a2-check" style="margin-top:10px;">
                <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', (int) ($row->is_active ?? 1)))>
                <span>نشط</span>
            </label>
        </div>
    </div>
</div>

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('business.menu-sections.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    <button type="submit" class="a2-btn a2-btn-primary">{{ $isEdit ? 'تحديث' : 'حفظ' }}</button>
</div>
