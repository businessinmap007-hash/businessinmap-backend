@php
    $parentIdInt = (int) ($parentId ?? 0);
    $childName = $categoryChild->name_ar ?: ($categoryChild->name_en ?: ('#' . $categoryChild->id));
@endphp

<input type="hidden" name="parent_id" value="{{ $parentIdInt }}">

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-section-title a2-mb-0">بيانات المجموعة</div>
            <div class="a2-section-subtitle">
                {{ $childName }}
            </div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div>
            <label class="a2-label">الاسم العربي</label>
            <input type="text"
                   name="name_ar"
                   class="a2-input @error('name_ar') is-invalid @enderror"
                   value="{{ old('name_ar', $group->name_ar) }}"
                   placeholder="مثال: المرافق">
            @error('name_ar')
                <div class="a2-text-danger a2-mt-6">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="a2-label">الاسم الإنجليزي</label>
            <input type="text"
                   name="name_en"
                   class="a2-input @error('name_en') is-invalid @enderror"
                   value="{{ old('name_en', $group->name_en) }}"
                   placeholder="Example: Amenities">
            @error('name_en')
                <div class="a2-text-danger a2-mt-6">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="a2-label">الترتيب</label>
            <input type="number"
                   name="reorder"
                   class="a2-input @error('reorder') is-invalid @enderror"
                   value="{{ old('reorder', $group->reorder ?? 0) }}"
                   min="0"
                   step="1">
            @error('reorder')
                <div class="a2-text-danger a2-mt-6">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label class="a2-label">الحالة</label>
            <select name="is_active" class="a2-select @error('is_active') is-invalid @enderror">
                <option value="1" @selected((string) old('is_active', (int) ($group->is_active ?? 1)) === '1')>نشط</option>
                <option value="0" @selected((string) old('is_active', (int) ($group->is_active ?? 1)) === '0')>غير نشط</option>
            </select>
            @error('is_active')
                <div class="a2-text-danger a2-mt-6">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-page-actions a2-mt-16" style="justify-content:flex-end;">
    <a href="{{ route('admin.category-child-option-groups.index', ['categoryChild' => $categoryChild->id, 'parent_id' => $parentIdInt]) }}"
       class="a2-btn a2-btn-ghost">
        إلغاء
    </a>

    <button type="submit" class="a2-btn a2-btn-primary">
        حفظ
    </button>
</div>