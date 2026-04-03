@extends('admin-v2.layouts.master')

@section('title', 'Category Child Options')
@section('body_class', 'admin-v2 admin-v2-category-child-options-edit')

@section('content')
@php
    $parentIdInt = (int) ($parentId ?? 0);

    $childSafe = $categoryChild ?? null;
    $groupsSafe = collect($groups ?? []);
    $selectedOptionIdsSafe = collect($selectedOptionIds ?? [])
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $id > 0)
        ->unique()
        ->values();

    $ungroupedOptionsSafe = collect($ungroupedOptions ?? []);
    $childName = $childSafe?->name_ar ?: ($childSafe?->name_en ?: ('#' . ($childSafe->id ?? 0)));
    $parentName = $parent?->name_ar ?: ($parent?->name_en ?: null);

    $totalGroups = $groupsSafe->count();
    $totalGroupedOptions = $groupsSafe->sum(function ($group) {
        return collect($group->options ?? [])->count();
    });
    $totalUngroupedOptions = $ungroupedOptionsSafe->count();
    $totalSelected = $selectedOptionIdsSafe->count();
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">خيارات القسم الفرعي</h1>
            <div class="a2-page-subtitle">
                {{ $childName }}
                @if(!empty($childSafe?->id))
                    <span class="a2-muted">#{{ $childSafe->id }}</span>
                @endif

                @if($parentName)
                    — تحت {{ $parentName }}
                @endif
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.option-groups.index') }}" class="a2-btn a2-btn-ghost">
                إدارة المجموعات
            </a>

            <a href="{{ route('admin.options.index') }}" class="a2-btn a2-btn-ghost">
                إدارة الخيارات
            </a>

            <a href="{{ route('admin.category-children.edit', ['categoryChild' => $childSafe->id, 'parent_id' => $parentIdInt]) }}"
               class="a2-btn a2-btn-ghost">
                تعديل القسم
            </a>

            <a href="{{ route('admin.category-children.index', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}"
               class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">المحدد لهذا القسم</div>
            <div class="a2-stat-value" id="selectedCountText">{{ $totalSelected }}</div>
            <div class="a2-stat-note">سيتم حفظه فعليًا على القسم الفرعي</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد المجموعات</div>
            <div class="a2-stat-value">{{ $totalGroups }}</div>
            <div class="a2-stat-note">مصدر الاختيار</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">خيارات داخل المجموعات</div>
            <div class="a2-stat-value">{{ $totalGroupedOptions }}</div>
            <div class="a2-stat-note">مجمعة داخل Option Groups</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">خيارات بدون Group</div>
            <div class="a2-stat-value">{{ $totalUngroupedOptions }}</div>
            <div class="a2-stat-note">اختياري عرضها واختيارها</div>
        </div>
    </div>

    <div class="a2-card a2-card--section a2-mb-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">آلية العمل</div>
                <div class="a2-section-subtitle">
                    يمكنك اختيار كل خيارات Group كاملة، أو بعض الخيارات فقط من أي Group، أو الدمج بين أكثر من Group لنفس القسم الفرعي.
                </div>
            </div>
        </div>

        <div class="a2-filterbar a2-mt-12">
            <input type="text"
                   id="optionSearchInput"
                   class="a2-input a2-filter-search"
                   placeholder="بحث داخل الخيارات والمجموعات">

            <div class="a2-filter-actions">
                <button type="button" class="a2-btn a2-btn-ghost" id="expandAllGroupsBtn">
                    فتح الكل
                </button>

                <button type="button" class="a2-btn a2-btn-ghost" id="collapseAllGroupsBtn">
                    طي الكل
                </button>

                <button type="button" class="a2-btn a2-btn-ghost" id="selectVisibleBtn">
                    تحديد الظاهر
                </button>

                <button type="button" class="a2-btn a2-btn-ghost" id="clearAllBtn">
                    إلغاء الكل
                </button>
            </div>
        </div>
    </div>

    <form method="POST"
          action="{{ route('admin.category-child-options.update', ['categoryChild' => $childSafe->id]) }}"
          id="categoryChildOptionsForm">
        @csrf
        @method('PUT')

        <input type="hidden" name="parent_id" value="{{ $parentIdInt }}">
        <div id="rowsContainer"></div>

        @forelse($groupsSafe as $group)
            @php
                $groupOptions = collect($group->options ?? []);
                $groupName = $group->name_ar ?: ($group->name_en ?: ('#' . $group->id));
                $groupSelectedCount = $groupOptions
                    ->filter(fn ($option) => $selectedOptionIdsSafe->contains((int) $option->id))
                    ->count();
            @endphp

            <details class="a2-card a2-card--section a2-mb-16 js-group-card"
                     open
                     data-group-name="{{ Str::lower($groupName) }}">
                <summary class="a2-card-head" style="cursor:pointer;list-style:none;">
                    <div>
                        <div class="a2-section-title a2-mb-0">{{ $groupName }}</div>
                        <div class="a2-section-subtitle a2-mb-0">
                            Group #{{ $group->id }} —
                            <span class="js-group-total">{{ $groupOptions->count() }}</span> خيار —
                            المحدد حاليًا:
                            <span class="js-group-selected-count">{{ $groupSelectedCount }}</span>
                        </div>
                    </div>

                    <div class="a2-page-actions" onclick="event.preventDefault(); event.stopPropagation();">
                        <button type="button"
                                class="a2-btn a2-btn-sm a2-btn-ghost js-select-group-btn">
                            تحديد الكل
                        </button>

                        <button type="button"
                                class="a2-btn a2-btn-sm a2-btn-ghost js-clear-group-btn">
                            إلغاء المجموعة
                        </button>

                        <a href="{{ route('admin.option-groups.edit', $group->id) }}"
                           class="a2-btn a2-btn-sm a2-btn-ghost">
                            تعديل Group
                        </a>
                    </div>
                </summary>

                @if($groupOptions->isNotEmpty())
                    <div class="a2-check-grid a2-mt-12">
                        @foreach($groupOptions as $option)
                            @php
                                $optionId = (int) $option->id;
                                $isChecked = $selectedOptionIdsSafe->contains($optionId);
                                $optionNameAr = $option->name_ar ?: '—';
                                $optionNameEn = $option->name_en ?: '—';
                            @endphp

                            <label class="a2-check-card js-option-card"
                                   data-option-name="{{ Str::lower(trim($optionNameAr . ' ' . $optionNameEn . ' ' . $groupName)) }}">
                                <input type="checkbox"
                                       class="js-option-checkbox"
                                       value="{{ $optionId }}"
                                       @checked($isChecked)>

                                <span>
                                    <strong>#{{ $optionId }} — {{ $optionNameAr }}</strong>
                                    <small dir="ltr">{{ $optionNameEn }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="a2-alert a2-alert-warning a2-mt-12">
                        لا توجد خيارات داخل هذه المجموعة.
                    </div>
                @endif
            </details>
        @empty
            <div class="a2-alert a2-alert-warning a2-mb-16">
                لا توجد مجموعات خيارات متاحة حاليًا.
            </div>
        @endforelse

        @if($ungroupedOptionsSafe->isNotEmpty())
            <details class="a2-card a2-card--section a2-mb-16 js-group-card"
                     open
                     data-group-name="ungrouped بدون group">
                <summary class="a2-card-head" style="cursor:pointer;list-style:none;">
                    <div>
                        <div class="a2-section-title a2-mb-0">خيارات بدون Group</div>
                        <div class="a2-section-subtitle a2-mb-0">
                            {{ $ungroupedOptionsSafe->count() }} خيار
                        </div>
                    </div>

                    <div class="a2-page-actions" onclick="event.preventDefault(); event.stopPropagation();">
                        <button type="button"
                                class="a2-btn a2-btn-sm a2-btn-ghost js-select-group-btn">
                            تحديد الكل
                        </button>

                        <button type="button"
                                class="a2-btn a2-btn-sm a2-btn-ghost js-clear-group-btn">
                            إلغاء المجموعة
                        </button>
                    </div>
                </summary>

                <div class="a2-check-grid a2-mt-12">
                    @foreach($ungroupedOptionsSafe as $option)
                        @php
                            $optionId = (int) $option->id;
                            $isChecked = $selectedOptionIdsSafe->contains($optionId);
                            $optionNameAr = $option->name_ar ?: '—';
                            $optionNameEn = $option->name_en ?: '—';
                        @endphp

                        <label class="a2-check-card js-option-card"
                               data-option-name="{{ Str::lower(trim($optionNameAr . ' ' . $optionNameEn . ' ungrouped بدون group')) }}">
                            <input type="checkbox"
                                   class="js-option-checkbox"
                                   value="{{ $optionId }}"
                                   @checked($isChecked)>

                            <span>
                                <strong>#{{ $optionId }} — {{ $optionNameAr }}</strong>
                                <small dir="ltr">{{ $optionNameEn }}</small>
                            </span>
                        </label>
                    @endforeach
                </div>
            </details>
        @endif

        <div class="a2-card a2-card--soft a2-mb-16">
            <div class="a2-section-title">الخيارات المحددة الآن</div>
            <div class="a2-section-subtitle">
                هذه القائمة للمعاينة فقط قبل الحفظ
            </div>

            <div id="selectedPreview" class="a2-option-chip-grid a2-mt-12"></div>

            <div id="selectedPreviewEmpty" class="a2-alert a2-alert-warning a2-mt-12" style="display:none;">
                لا توجد خيارات محددة حاليًا.
            </div>
        </div>

        <div class="a2-page-actions" style="justify-content:flex-end;">
            <a href="{{ route('admin.category-children.edit', ['categoryChild' => $childSafe->id, 'parent_id' => $parentIdInt]) }}"
               class="a2-btn a2-btn-ghost">
                رجوع
            </a>

            <button type="button" class="a2-btn a2-btn-ghost" id="reviewSelectionBtn">
                مراجعة التحديد
            </button>

            <button type="submit" class="a2-btn a2-btn-primary">
                حفظ الخيارات
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('categoryChildOptionsForm');
    const rowsContainer = document.getElementById('rowsContainer');
    const searchInput = document.getElementById('optionSearchInput');
    const selectedCountText = document.getElementById('selectedCountText');
    const selectedPreview = document.getElementById('selectedPreview');
    const selectedPreviewEmpty = document.getElementById('selectedPreviewEmpty');

    const allCheckboxes = () => Array.from(document.querySelectorAll('.js-option-checkbox'));
    const allCards = () => Array.from(document.querySelectorAll('.js-option-card'));
    const allGroupCards = () => Array.from(document.querySelectorAll('.js-group-card'));

    function checkedBoxes() {
        return allCheckboxes().filter(cb => cb.checked);
    }

    function normalizeText(text) {
        return (text || '').toString().trim().toLowerCase();
    }

    function syncRowsContainer() {
        rowsContainer.innerHTML = '';

        checkedBoxes().forEach(function (checkbox, index) {
            const optionId = checkbox.value;

            const optionInput = document.createElement('input');
            optionInput.type = 'hidden';
            optionInput.name = `rows[${index}][option_id]`;
            optionInput.value = optionId;
            rowsContainer.appendChild(optionInput);

            const reorderInput = document.createElement('input');
            reorderInput.type = 'hidden';
            reorderInput.name = `rows[${index}][reorder]`;
            reorderInput.value = index;
            rowsContainer.appendChild(reorderInput);
        });
    }

    function updateSelectedCount() {
        if (selectedCountText) {
            selectedCountText.textContent = checkedBoxes().length;
        }
    }

    function updateGroupCounters() {
        allGroupCards().forEach(function (groupCard) {
            const boxes = Array.from(groupCard.querySelectorAll('.js-option-checkbox'));
            const selectedCount = boxes.filter(cb => cb.checked).length;
            const counter = groupCard.querySelector('.js-group-selected-count');

            if (counter) {
                counter.textContent = selectedCount;
            }
        });
    }

    function updatePreview() {
        selectedPreview.innerHTML = '';

        const selected = checkedBoxes();

        if (selected.length === 0) {
            selectedPreviewEmpty.style.display = '';
            return;
        }

        selectedPreviewEmpty.style.display = 'none';

        selected.forEach(function (checkbox) {
            const card = checkbox.closest('.js-option-card');
            const strong = card ? card.querySelector('strong') : null;
            const small = card ? card.querySelector('small') : null;

            const chip = document.createElement('div');
            chip.className = 'a2-option-chip-card';
            chip.innerHTML = `
                <div class="a2-option-chip-title">${strong ? strong.innerHTML : ('#' + checkbox.value)}</div>
                <div class="a2-option-chip-sub">${small ? small.innerHTML : ''}</div>
            `;
            selectedPreview.appendChild(chip);
        });
    }

    function refreshAll() {
        syncRowsContainer();
        updateSelectedCount();
        updateGroupCounters();
        updatePreview();
    }

    function filterCards() {
        const keyword = normalizeText(searchInput ? searchInput.value : '');

        allGroupCards().forEach(function (groupCard) {
            const groupName = normalizeText(groupCard.getAttribute('data-group-name'));
            let visibleCount = 0;

            Array.from(groupCard.querySelectorAll('.js-option-card')).forEach(function (card) {
                const optionText = normalizeText(card.getAttribute('data-option-name'));
                const matched = keyword === '' || groupName.includes(keyword) || optionText.includes(keyword);

                card.style.display = matched ? '' : 'none';

                if (matched) {
                    visibleCount++;
                }
            });

            groupCard.style.display = visibleCount > 0 || keyword === '' ? '' : 'none';
        });
    }

    allCheckboxes().forEach(function (checkbox) {
        checkbox.addEventListener('change', refreshAll);
    });

    allGroupCards().forEach(function (groupCard) {
        const selectBtn = groupCard.querySelector('.js-select-group-btn');
        const clearBtn = groupCard.querySelector('.js-clear-group-btn');

        if (selectBtn) {
            selectBtn.addEventListener('click', function () {
                Array.from(groupCard.querySelectorAll('.js-option-card')).forEach(function (card) {
                    if (card.style.display === 'none') return;

                    const checkbox = card.querySelector('.js-option-checkbox');
                    if (checkbox) checkbox.checked = true;
                });

                refreshAll();
            });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                Array.from(groupCard.querySelectorAll('.js-option-checkbox')).forEach(function (checkbox) {
                    checkbox.checked = false;
                });

                refreshAll();
            });
        }
    });

    if (searchInput) {
        searchInput.addEventListener('input', filterCards);
    }

    const expandAllGroupsBtn = document.getElementById('expandAllGroupsBtn');
    const collapseAllGroupsBtn = document.getElementById('collapseAllGroupsBtn');
    const selectVisibleBtn = document.getElementById('selectVisibleBtn');
    const clearAllBtn = document.getElementById('clearAllBtn');
    const reviewSelectionBtn = document.getElementById('reviewSelectionBtn');

    if (expandAllGroupsBtn) {
        expandAllGroupsBtn.addEventListener('click', function () {
            allGroupCards().forEach(function (groupCard) {
                groupCard.open = true;
            });
        });
    }

    if (collapseAllGroupsBtn) {
        collapseAllGroupsBtn.addEventListener('click', function () {
            allGroupCards().forEach(function (groupCard) {
                groupCard.open = false;
            });
        });
    }

    if (selectVisibleBtn) {
        selectVisibleBtn.addEventListener('click', function () {
            allCards().forEach(function (card) {
                if (card.style.display === 'none') return;

                const checkbox = card.querySelector('.js-option-checkbox');
                if (checkbox) checkbox.checked = true;
            });

            refreshAll();
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function () {
            allCheckboxes().forEach(function (checkbox) {
                checkbox.checked = false;
            });

            refreshAll();
        });
    }

    if (reviewSelectionBtn) {
        reviewSelectionBtn.addEventListener('click', function () {
            document.getElementById('selectedPreview')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    }

    form.addEventListener('submit', function (e) {
        syncRowsContainer();

        if (checkedBoxes().length === 0) {
            e.preventDefault();
            alert('حدد Option واحدة على الأقل قبل الحفظ.');
            return;
        }
    });

    refreshAll();
    filterCards();
});
</script>
@endpush
@endsection