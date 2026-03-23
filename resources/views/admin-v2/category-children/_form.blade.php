@php
    /** @var \App\Models\CategoryChild|null $row */
    $child = $row ?? null;
    $isEdit = isset($child) && $child?->exists;

    $parentIdInt = (int) ($parentId ?? request()->get('parent_id', 0));

    $selectedParentIds = collect(old(
        'parent_ids',
        $selectedParentIds ?? []
    ))->map(fn ($id) => (int) $id)->unique()->values()->all();

    $parents = $parents ?? collect();
@endphp

@if($parentIdInt > 0)
    <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">
@endif

<div class="a2-page">
    

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">البيانات الأساسية</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-form-group">
                <label class="a2-label">
                    الاسم العربي <span style="color:var(--a2-danger)">*</span>
                </label>
                <input class="a2-input"
                       type="text"
                       name="name_ar"
                       value="{{ old('name_ar', $child->name_ar ?? '') }}"
                       placeholder="مثال: شقق مفروشة">
                @error('name_ar')
                    <div class="a2-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="a2-form-group">
                <label class="a2-label">الاسم الإنجليزي</label>
                <input class="a2-input"
                       type="text"
                       name="name_en"
                       value="{{ old('name_en', $child->name_en ?? '') }}"
                       dir="ltr"
                       placeholder="Example: Furnished Apartments">
                @error('name_en')
                    <div class="a2-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="a2-form-group">
                <label class="a2-label">الترتيب (reorder)</label>
                <input class="a2-input"
                       type="number"
                       name="reorder"
                       value="{{ old('reorder', $child->reorder ?? 0) }}"
                       min="0"
                       step="1"
                       placeholder="0">
                <div class="a2-section-subtitle a2-mb-0 a2-mt-8">
                    اكتب ترتيب العرض الصحيح للقسم الفرعي.
                </div>
                @error('reorder')
                    <div class="a2-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="a2-form-group">
                <label class="a2-label">نوع السجل</label>
                <input class="a2-input" type="text" value="قسم فرعي موحّد / Category Child" disabled>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">الأقسام الرئيسية المرتبطة</div>
            </div>
        </div>

        @if($parents->count())
            <div class="a2-check-grid">
                @foreach($parents as $parent)
                    <label class="a2-check-card">
                        <input type="checkbox"
                               name="parent_ids[]"
                               value="{{ $parent->id }}"
                               @checked(in_array((int) $parent->id, $selectedParentIds, true))>

                        <span>
                            <strong>
                                {{ $parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id)) }}
                            </strong>
                            <small dir="ltr">
                                #{{ $parent->id }}
                                @if(!empty($parent->name_en))
                                    — {{ $parent->name_en }}
                                @endif
                            </small>
                        </span>
                    </label>
                @endforeach
            </div>
        @else
            <div class="a2-alert a2-alert-warning">
                لا توجد أقسام رئيسية متاحة للربط حاليًا.
            </div>
        @endif

        @error('parent_ids')
            <div class="a2-error a2-mt-8">{{ $message }}</div>
        @enderror

        @error('parent_ids.*')
            <div class="a2-error a2-mt-8">{{ $message }}</div>
        @enderror
    </div>

    @if($isEdit && $child && $child->relationLoaded('options'))
        <div class="a2-card a2-card--section">
            <div class="a2-card-head">
                <div>
                    <div class="a2-section-title a2-mb-0">ملخص المجموعات والخيارات</div>
                </div>
            </div>

            @if($child->options->count())
                <div class="a2-option-chip-grid">
                    @foreach($child->options as $option)
                        <div class="a2-option-chip-card">
                            <div class="a2-option-chip-title">
                                {{ $option->name_ar ?: ($option->name_en ?: ('#' . $option->id)) }}
                            </div>
                            <div class="a2-option-chip-sub" dir="ltr">
                                #{{ $option->id }}
                                @if(!empty($option->name_en))
                                    — {{ $option->name_en }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="a2-alert a2-alert-warning">
                    لا توجد خيارات مرتبطة بهذا القسم الفرعي حتى الآن.
                </div>
            @endif
        </div>
    @endif

    <div class="a2-page-actions" style="justify-content:flex-end;">
        <a href="{{ route('admin.category-children.index', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}"
           class="a2-btn a2-btn-ghost">
            رجوع
        </a>

        <button type="submit" class="a2-btn a2-btn-primary">
            {{ $isEdit ? 'تحديث' : 'حفظ' }}
        </button>
    </div>
</div>