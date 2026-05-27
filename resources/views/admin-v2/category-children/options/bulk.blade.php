@extends('admin-v2.layouts.master')

@section('title', 'Bulk Category Child Options')
@section('body_class', 'admin-v2 admin-v2-category-child-options-bulk')

@section('content')
@php
    $rootsSafe = collect($roots ?? []);
    $optionGroupsSafe = collect($optionGroups ?? []);
    $ungroupedSafe = collect($ungroupedOptions ?? []);
    $parentIdInt = (int) ($parentId ?? 0);

    $hasUngrouped = $ungroupedSafe->isNotEmpty();

    $nameOf = function ($item) {
        $ar = (string) ($item->name_ar ?? '');
        $en = (string) ($item->name_en ?? '');
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل خيارات الأقسام الفرعية دفعة واحدة</h1>
            <div class="a2-page-subtitle">
                اختر التصنيف الرئيسي، ثم الأقسام الفرعية، ثم اختر الخيارات من داخل الجروبات
            </div>
        </div>

        <div class="a2-page-actions">
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
        <div class="a2-alert a2-alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.category-child-options.bulk.update') }}" id="bulkOptionsForm">
        @csrf

        <input type="hidden" name="parent_id" id="bulk_parent_id" value="{{ $parentIdInt }}">

        {{-- Root Categories --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">التصنيفات الرئيسية</h2>
                    <div class="a2-section-subtitle">اضغط على التصنيف لعرض الأقسام الفرعية الخاصة به فقط</div>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">لا توجد تصنيفات رئيسية بها أقسام فرعية.</div>
            @else
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    @foreach($rootsSafe as $root)
                        @php
                            $rootId = (int) $root->id;
                            $isActive = $rootId === $parentIdInt || ($parentIdInt === 0 && $loop->first);
                            $childrenCount = collect($root->children ?? [])->count();
                        @endphp

                        <button
                            type="button"
                            class="a2-btn {{ $isActive ? 'a2-btn-primary' : 'a2-btn-ghost' }} js-root-tab"
                            data-root-id="{{ $rootId }}"
                        >
                            {{ $nameOf($root) }}
                            <span class="a2-badge" style="margin-inline-start:6px;">{{ $childrenCount }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Children --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">الأقسام الفرعية</h2>
                    <div class="a2-section-subtitle">سيتم تطبيق التعديل على الأقسام المحددة فقط</div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleChildren">
                        تحديد الظاهر
                    </button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleChildren">
                        إلغاء تحديد الظاهر
                    </button>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">لا توجد أقسام فرعية متاحة.</div>
            @else
                @foreach($rootsSafe as $root)
                    @php
                        $rootId = (int) $root->id;
                        $isActive = $rootId === $parentIdInt || ($parentIdInt === 0 && $loop->first);
                        $children = collect($root->children ?? []);
                    @endphp

                    <div class="js-root-panel"
                         data-root-id="{{ $rootId }}"
                         style="{{ $isActive ? '' : 'display:none;' }}">

                        @if($children->isEmpty())
                            <div class="a2-muted">لا توجد أقسام فرعية داخل هذا التصنيف.</div>
                        @else
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                                @foreach($children as $child)
                                    <label class="a2-check-card">
                                        <input
                                            type="checkbox"
                                            name="child_ids[]"
                                            value="{{ $child->id }}"
                                            class="js-child-checkbox"
                                            {{ $isActive ? 'checked' : '' }}
                                            {{ $isActive ? '' : 'disabled' }}
                                        >
                                        <span>{{ $nameOf($child) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
        </div>

        {{-- Mode --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <h2 class="a2-section-title">طريقة التطبيق</h2>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <label class="a2-check-card">
                    <input type="radio" name="mode" value="append" checked>
                    <span>إضافة فقط</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="replace">
                    <span>استبدال بالكامل</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="remove">
                    <span>حذف المحدد</span>
                </label>
            </div>
        </div>

        {{-- Option Groups --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">جروبات الخيارات</h2>
                    <div class="a2-section-subtitle">اضغط على الجروب لعرض الخيارات الخاصة به فقط</div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleOptions">
                        تحديد خيارات الجروب
                    </button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleOptions">
                        إلغاء تحديد الجروب
                    </button>
                </div>
            </div>

            @if($optionGroupsSafe->isEmpty() && !$hasUngrouped)
                <div class="a2-muted">لا توجد خيارات متاحة.</div>
            @else
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
                    @foreach($optionGroupsSafe as $group)
                        @php
                            $groupId = (int) $group->id;
                            $isActiveGroup = $loop->first;
                            $optionsCount = collect($group->options ?? [])->count();
                        @endphp

                        <button
                            type="button"
                            class="a2-btn {{ $isActiveGroup ? 'a2-btn-primary' : 'a2-btn-ghost' }} js-option-group-tab"
                            data-group-id="group-{{ $groupId }}"
                        >
                            {{ $nameOf($group) }}
                            <span class="a2-badge" style="margin-inline-start:6px;">{{ $optionsCount }}</span>
                        </button>
                    @endforeach

                    @if($hasUngrouped)
                        <button
                            type="button"
                            class="a2-btn {{ $optionGroupsSafe->isEmpty() ? 'a2-btn-primary' : 'a2-btn-ghost' }} js-option-group-tab"
                            data-group-id="ungrouped"
                        >
                            بدون جروب
                            <span class="a2-badge" style="margin-inline-start:6px;">{{ $ungroupedSafe->count() }}</span>
                        </button>
                    @endif
                </div>

                @foreach($optionGroupsSafe as $group)
                    @php
                        $groupId = (int) $group->id;
                        $isActiveGroup = $loop->first;
                        $groupOptions = collect($group->options ?? []);
                    @endphp

                    <div
                        class="js-option-group-panel"
                        data-group-id="group-{{ $groupId }}"
                        style="{{ $isActiveGroup ? '' : 'display:none;' }}"
                    >
                        @if($groupOptions->isEmpty())
                            <div class="a2-muted">لا توجد خيارات داخل هذا الجروب.</div>
                        @else
                            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                                @foreach($groupOptions as $option)
                                    <label class="a2-check-card">
                                        <input
                                            type="checkbox"
                                            name="option_ids[]"
                                            value="{{ $option->id }}"
                                            class="js-option-checkbox"
                                        >
                                        <span>{{ $nameOf($option) }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach

                @if($hasUngrouped)
                    <div
                        class="js-option-group-panel"
                        data-group-id="ungrouped"
                        style="{{ $optionGroupsSafe->isEmpty() ? '' : 'display:none;' }}"
                    >
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
                            @foreach($ungroupedSafe as $option)
                                <label class="a2-check-card">
                                    <input
                                        type="checkbox"
                                        name="option_ids[]"
                                        value="{{ $option->id }}"
                                        class="js-option-checkbox"
                                    >
                                    <span>{{ $nameOf($option) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>

        <div class="a2-card">
            <button type="submit" class="a2-btn a2-btn-primary">
                تطبيق التعديل الجماعي
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    /*
    |--------------------------------------------------------------------------
    | Root Tabs
    |--------------------------------------------------------------------------
    */
    const rootTabs = document.querySelectorAll('.js-root-tab');
    const rootPanels = document.querySelectorAll('.js-root-panel');
    const parentInput = document.getElementById('bulk_parent_id');

    function activateRoot(rootId) {
        rootTabs.forEach(function (tab) {
            const active = tab.dataset.rootId === rootId;
            tab.classList.toggle('a2-btn-primary', active);
            tab.classList.toggle('a2-btn-ghost', !active);
        });

        rootPanels.forEach(function (panel) {
            const active = panel.dataset.rootId === rootId;
            panel.style.display = active ? '' : 'none';

            panel.querySelectorAll('.js-child-checkbox').forEach(function (input) {
                input.disabled = !active;
                input.checked = active;
            });
        });

        if (parentInput) {
            parentInput.value = rootId;
        }
    }

    rootTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateRoot(tab.dataset.rootId);
        });
    });

    const checkVisibleChildren = document.getElementById('checkVisibleChildren');
    const uncheckVisibleChildren = document.getElementById('uncheckVisibleChildren');

    function visibleChildren() {
        const activePanel = Array.from(rootPanels).find(function (panel) {
            return panel.style.display !== 'none';
        });

        if (!activePanel) {
            return [];
        }

        return activePanel.querySelectorAll('.js-child-checkbox:not(:disabled)');
    }

    if (checkVisibleChildren) {
        checkVisibleChildren.addEventListener('click', function () {
            visibleChildren().forEach(function (input) {
                input.checked = true;
            });
        });
    }

    if (uncheckVisibleChildren) {
        uncheckVisibleChildren.addEventListener('click', function () {
            visibleChildren().forEach(function (input) {
                input.checked = false;
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Option Group Tabs
    |--------------------------------------------------------------------------
    */
    const optionTabs = document.querySelectorAll('.js-option-group-tab');
    const optionPanels = document.querySelectorAll('.js-option-group-panel');

    function activateOptionGroup(groupId) {
        optionTabs.forEach(function (tab) {
            const active = tab.dataset.groupId === groupId;
            tab.classList.toggle('a2-btn-primary', active);
            tab.classList.toggle('a2-btn-ghost', !active);
        });

        optionPanels.forEach(function (panel) {
            panel.style.display = panel.dataset.groupId === groupId ? '' : 'none';
        });
    }

    optionTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateOptionGroup(tab.dataset.groupId);
        });
    });

    const checkVisibleOptions = document.getElementById('checkVisibleOptions');
    const uncheckVisibleOptions = document.getElementById('uncheckVisibleOptions');

    function visibleOptions() {
        const activePanel = Array.from(optionPanels).find(function (panel) {
            return panel.style.display !== 'none';
        });

        if (!activePanel) {
            return [];
        }

        return activePanel.querySelectorAll('.js-option-checkbox');
    }

    if (checkVisibleOptions) {
        checkVisibleOptions.addEventListener('click', function () {
            visibleOptions().forEach(function (input) {
                input.checked = true;
            });
        });
    }

    if (uncheckVisibleOptions) {
        uncheckVisibleOptions.addEventListener('click', function () {
            visibleOptions().forEach(function (input) {
                input.checked = false;
            });
        });
    }
});
</script>
@endsection