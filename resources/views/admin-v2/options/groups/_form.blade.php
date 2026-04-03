@php
    $groupSafe = $group ?? null;
    $isEdit = !empty($groupSafe?->id);

    $selectedIds = collect($selectedOptionIds ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->values();

    $allOptions = collect($availableOptions ?? []);

    $selectedOptions = $isEdit
        ? $allOptions->whereIn('id', $selectedIds)->values()
        : collect();

    $ungroupedOptions = $allOptions
        ->whereNull('group_id')
        ->when($isEdit, fn ($c) => $c->whereNotIn('id', $selectedIds))
        ->values();

    $otherGroups = $isEdit
        ? $allOptions
            ->whereNotIn('id', $selectedIds)
            ->whereNotNull('group_id')
            ->groupBy('group_id')
        : collect();

    $allSelectableCards = $isEdit
        ? $selectedOptions->count() + $ungroupedOptions->count() + $otherGroups->flatten(1)->count()
        : $allOptions->count();
@endphp

<div class="a2-card a2-card--section a2-mb-16">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">بيانات المجموعة</div>
            <div class="a2-card-sub">الاسم والترتيب والحالة</div>
        </div>
    </div>

    <div class="a2-form-grid">
        <div class="a2-form-group">
            <label class="a2-label">الاسم عربي</label>
            <input class="a2-input" name="name_ar" value="{{ old('name_ar', $groupSafe->name_ar ?? '') }}">
            @error('name_ar')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الاسم إنجليزي</label>
            <input class="a2-input" name="name_en" value="{{ old('name_en', $groupSafe->name_en ?? '') }}" dir="ltr">
            @error('name_en')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الترتيب</label>
            <input class="a2-input" type="number" name="reorder" value="{{ old('reorder', $groupSafe->reorder ?? 0) }}">
            @error('reorder')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="a2-form-group">
            <label class="a2-label">الحالة</label>
            <select class="a2-select" name="is_active">
                <option value="1" @selected((string) old('is_active', $groupSafe->is_active ?? 1) === '1')>Active</option>
                <option value="0" @selected((string) old('is_active', $groupSafe->is_active ?? 1) === '0')>Inactive</option>
            </select>
            @error('is_active')
                <div class="a2-error">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="a2-card a2-mb-16">
    <div class="a2-filterbar">
        <input type="text"
               id="optionSearchInput"
               class="a2-input a2-filter-search"
               placeholder="بحث داخل الخيارات">

        <div class="a2-filter-actions">
            <button type="button" class="a2-btn a2-btn-ghost" id="selectVisibleBtn">تحديد الظاهر</button>
            <button type="button" class="a2-btn a2-btn-ghost" id="clearVisibleBtn">إلغاء الظاهر</button>
        </div>
    </div>
</div>

@if($isEdit)
    <div class="a2-card a2-card--section a2-mb-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">الخيارات داخل هذه المجموعة</div>
                <div class="a2-card-sub">إلغاء التحديد = إزالة الخيار من هذه المجموعة</div>
            </div>
        </div>

        @if($selectedOptions->count())
            <div class="a2-check-grid">
                @foreach($selectedOptions as $option)
                    @php
                        $name = $option->name_ar ?: ($option->name_en ?: '—');
                    @endphp
                    <label class="a2-check-card js-option-card"
                           data-name="{{ Str::lower(trim($name . ' ' . ($option->name_en ?? '') . ' selected current')) }}">
                        <input type="checkbox"
                               class="js-option-checkbox"
                               name="option_ids[]"
                               value="{{ $option->id }}"
                               checked>

                        <span>
                            <strong>#{{ $option->id }} — {{ $name }}</strong>
                            <small dir="ltr">{{ $option->name_en }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        @else
            <div class="a2-alert a2-alert-warning" style="margin:16px;">
                لا توجد خيارات مرتبطة بهذه المجموعة حاليًا.
            </div>
        @endif
    </div>

    @foreach($otherGroups as $groupId => $options)
        @php
            $firstOption = collect($options)->first();
            $otherGroupName = $firstOption?->group?->name_ar ?: ($firstOption?->group?->name_en ?: ('#' . $groupId));
        @endphp

        <div class="a2-card a2-card--section a2-mb-16">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">{{ $otherGroupName }}</div>
                    <div class="a2-card-sub">
                        تحديد خيار هنا سينقله من هذه المجموعة إلى المجموعة الحالية
                    </div>
                </div>
            </div>

            <div class="a2-check-grid">
                @foreach($options as $option)
                    @php
                        $name = $option->name_ar ?: ($option->name_en ?: '—');
                    @endphp
                    <label class="a2-check-card js-option-card"
                           data-name="{{ Str::lower(trim($name . ' ' . ($option->name_en ?? '') . ' ' . $otherGroupName)) }}">
                        <input type="checkbox"
                               class="js-option-checkbox"
                               name="option_ids[]"
                               value="{{ $option->id }}">

                        <span>
                            <strong>#{{ $option->id }} — {{ $name }}</strong>
                            <small dir="ltr">{{ $option->name_en }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="a2-card a2-card--section a2-mb-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">خيارات بدون Group</div>
                <div class="a2-card-sub">يمكن إضافتها مباشرة إلى هذه المجموعة</div>
            </div>
        </div>

        @if($ungroupedOptions->count())
            <div class="a2-check-grid">
                @foreach($ungroupedOptions as $option)
                    @php
                        $name = $option->name_ar ?: ($option->name_en ?: '—');
                    @endphp
                    <label class="a2-check-card js-option-card"
                           data-name="{{ Str::lower(trim($name . ' ' . ($option->name_en ?? '') . ' ungrouped بدون group')) }}">
                        <input type="checkbox"
                               class="js-option-checkbox"
                               name="option_ids[]"
                               value="{{ $option->id }}">

                        <span>
                            <strong>#{{ $option->id }} — {{ $name }}</strong>
                            <small dir="ltr">{{ $option->name_en }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        @else
            <div class="a2-alert a2-alert-warning" style="margin:16px;">
                لا توجد خيارات غير منضمة لأي مجموعة.
            </div>
        @endif
    </div>
@else
    <div class="a2-card">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">اختيار الخيارات</div>
                <div class="a2-card-sub">يمكنك ربط الخيارات مباشرة أثناء إنشاء المجموعة</div>
            </div>
        </div>

        @if($allOptions->count())
            <div class="a2-check-grid">
                @foreach($allOptions as $option)
                    @php
                        $name = $option->name_ar ?: ($option->name_en ?: '—');
                        $groupName = $option->group?->name_ar ?: ($option->group?->name_en ?: '');
                    @endphp
                    <label class="a2-check-card js-option-card"
                           data-name="{{ Str::lower(trim($name . ' ' . ($option->name_en ?? '') . ' ' . $groupName)) }}">
                        <input type="checkbox"
                               class="js-option-checkbox"
                               name="option_ids[]"
                               value="{{ $option->id }}">

                        <span>
                            <strong>#{{ $option->id }} — {{ $name }}</strong>
                            <small dir="ltr">{{ $option->name_en }}</small>
                        </span>
                    </label>
                @endforeach
            </div>
        @else
            <div class="a2-alert a2-alert-warning" style="margin:16px;">
                لا توجد خيارات متاحة.
            </div>
        @endif
    </div>
@endif

<div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
    <a href="{{ route('admin.option-groups.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    <button type="submit" class="a2-btn a2-btn-primary">
        {{ $isEdit ? 'تحديث' : 'حفظ' }}
    </button>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.getElementById('optionSearchInput');
    const cards = Array.from(document.querySelectorAll('.js-option-card'));

    function filterCards() {
        const val = (search?.value || '').toLowerCase();

        cards.forEach(card => {
            const haystack = (card.dataset.name || '').toLowerCase();
            card.style.display = haystack.includes(val) ? '' : 'none';
        });
    }

    search?.addEventListener('input', filterCards);

    document.getElementById('selectVisibleBtn')?.addEventListener('click', () => {
        cards.forEach(card => {
            if (card.style.display !== 'none') {
                const checkbox = card.querySelector('.js-option-checkbox');
                if (checkbox) checkbox.checked = true;
            }
        });
    });

    document.getElementById('clearVisibleBtn')?.addEventListener('click', () => {
        cards.forEach(card => {
            if (card.style.display !== 'none') {
                const checkbox = card.querySelector('.js-option-checkbox');
                if (checkbox) checkbox.checked = false;
            }
        });
    });

    filterCards();
});
</script>
@endpush