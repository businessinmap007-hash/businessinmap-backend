@php
    $isEdit = isset($group) && $group->exists;
@endphp

<div class="a2-card a2-card--section">

    <div class="a2-form-grid">

        <div class="a2-form-group">
            <label class="a2-label">الاسم العربي</label>
            <input class="a2-input"
                   type="text"
                   name="name_ar"
                   value="{{ old('name_ar', $group->name_ar ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الاسم الإنجليزي</label>
            <input class="a2-input"
                   type="text"
                   name="name_en"
                   dir="ltr"
                   value="{{ old('name_en', $group->name_en ?? '') }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الترتيب</label>
            <input class="a2-input"
                   type="number"
                   name="reorder"
                   value="{{ old('reorder', $group->reorder ?? 0) }}">
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>
            <select class="a2-select" name="is_active">
                <option value="1" @selected(old('is_active', $group->is_active ?? 1)==1)>نشط</option>
                <option value="0" @selected(old('is_active', $group->is_active ?? 1)==0)>غير نشط</option>
            </select>
        </div>

    </div>

</div>