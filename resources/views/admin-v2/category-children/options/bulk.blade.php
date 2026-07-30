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
            <h1 class="a2-page-title">{{ __('تعديل خيارات الأقسام الفرعية دفعة واحدة') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('اختر التصنيف الرئيسي، ثم الأقسام الفرعية، ثم اختر الخيارات من داخل الجروبات') }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.category-children.index', $parentIdInt > 0 ? ['parent_id' => $parentIdInt] : []) }}"
               class="a2-btn a2-btn-ghost">
                {{ __('رجوع') }}
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
                    <h2 class="a2-section-title">{{ __('التصنيفات الرئيسية') }}</h2>
                    <div class="a2-section-subtitle">{{ __('اضغط على التصنيف لعرض الأقسام الفرعية الخاصة به فقط') }}</div>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">{{ __('لا توجد تصنيفات رئيسية بها أقسام فرعية.') }}</div>
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
                    <h2 class="a2-section-title">{{ __('الأقسام الفرعية') }}</h2>
                    <div class="a2-section-subtitle">{{ __('سيتم تطبيق التعديل على الأقسام المحددة فقط') }}</div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleChildren">
                        {{ __('تحديد الظاهر') }}
                    </button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleChildren">
                        {{ __('إلغاء تحديد الظاهر') }}
                    </button>
                </div>
            </div>

            @if($rootsSafe->isEmpty())
                <div class="a2-muted">{{ __('لا توجد أقسام فرعية متاحة.') }}</div>
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
                            <div class="a2-muted">{{ __('لا توجد أقسام فرعية داخل هذا التصنيف.') }}</div>
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
            <h2 class="a2-section-title">{{ __('طريقة التطبيق') }}</h2>

            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <label class="a2-check-card">
                    <input type="radio" name="mode" value="append" checked>
                    <span>{{ __('إضافة فقط') }}</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="replace">
                    <span>{{ __('استبدال بالكامل') }}</span>
                </label>

                <label class="a2-check-card">
                    <input type="radio" name="mode" value="remove">
                    <span>{{ __('حذف المحدد') }}</span>
                </label>
            </div>
        </div>

        {{-- Option Groups --}}
        <div class="a2-card" style="margin-bottom:16px;">
            <div class="a2-section-head">
                <div>
                    <h2 class="a2-section-title">{{ __('جروبات الخيارات') }}</h2>
                    <div class="a2-section-subtitle">{{ __('الجروبات مغلقة — اضغط أي جروب لفتحه وعرض خياراته') }}</div>
                </div>

                <div class="a2-page-actions">
                    <button type="button" class="a2-btn a2-btn-ghost" id="checkVisibleOptions">
                        {{ __('تحديد خيارات الجروب المفتوح') }}
                    </button>
                    <button type="button" class="a2-btn a2-btn-ghost" id="uncheckVisibleOptions">
                        {{ __('إلغاء تحديد الجروب المفتوح') }}
                    </button>
                </div>
            </div>

            @if($optionGroupsSafe->isEmpty() && !$hasUngrouped)
                <div class="a2-muted">{{ __('لا توجد خيارات متاحة.') }}</div>
            @else
                @foreach($optionGroupsSafe as $group)
                    @php
                        $groupOptions = collect($group->options ?? []);
                    @endphp

                    <details class="a2-card a2-card--section js-option-group-panel" style="margin-bottom:10px;">
                        <summary class="a2-card-head" style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;">
                            <span class="a2-section-title a2-mb-0">{{ $nameOf($group) }}</span>
                            <span class="a2-badge">{{ $groupOptions->count() }}</span>
                        </summary>

                        @if($groupOptions->isEmpty())
                            <div class="a2-muted a2-mt-12">{{ __('لا توجد خيارات داخل هذا الجروب.') }}</div>
                        @else
                            <div class="a2-mt-12" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
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
                    </details>
                @endforeach

                @if($hasUngrouped)
                    <details class="a2-card a2-card--section js-option-group-panel" style="margin-bottom:10px;">
                        <summary class="a2-card-head" style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;">
                            <span class="a2-section-title a2-mb-0">{{ __('بدون جروب') }}</span>
                            <span class="a2-badge">{{ $ungroupedSafe->count() }}</span>
                        </summary>
                        <div class="a2-mt-12" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">
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
                    </details>
                @endif
            @endif
        </div>

        <div class="a2-card">
            <button type="submit" class="a2-btn a2-btn-primary">
                {{ __('تطبيق التعديل الجماعي') }}
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
    // Option groups are collapsible <details> (start closed). The group
    // check/uncheck buttons act on the OPEN groups' options.
    const checkVisibleOptions = document.getElementById('checkVisibleOptions');
    const uncheckVisibleOptions = document.getElementById('uncheckVisibleOptions');

    function openGroupOptions() {
        const inputs = [];
        document.querySelectorAll('.js-option-group-panel[open]').forEach(function (panel) {
            panel.querySelectorAll('.js-option-checkbox').forEach(function (input) {
                inputs.push(input);
            });
        });
        return inputs;
    }

    if (checkVisibleOptions) {
        checkVisibleOptions.addEventListener('click', function () {
            openGroupOptions().forEach(function (input) { input.checked = true; });
        });
    }

    if (uncheckVisibleOptions) {
        uncheckVisibleOptions.addEventListener('click', function () {
            openGroupOptions().forEach(function (input) { input.checked = false; });
        });
    }
});
</script>
@endsection