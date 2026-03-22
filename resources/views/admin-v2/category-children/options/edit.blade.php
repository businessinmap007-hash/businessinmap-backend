@extends('admin-v2.layouts.master')

@section('title', 'Category Child Options')
@section('body_class', 'admin-v2 admin-v2-category-child-options-edit')

@section('content')
@php
    $childName = $categoryChild->name_ar ?: ($categoryChild->name_en ?: ('#' . $categoryChild->id));
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">خيارات القسم الفرعي</h1>
            <div class="a2-page-subtitle">
                {{ $childName }}
            </div>
        </div>

        <div class="a2-page-actions">
            @if(!empty($parentId))
                <a href="{{ route('admin.category-children.index', ['parent_id' => $parentId]) }}"
                   class="a2-btn a2-btn-ghost">
                    رجوع
                </a>
            @else
                <a href="{{ route('admin.category-children.index') }}"
                   class="a2-btn a2-btn-ghost">
                    رجوع
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="a2-card a2-card--section a2-mb-16">
        <div class="a2-card-head">
            <div>
                <div class="a2-section-title a2-mb-0">بيانات القسم الفرعي</div>
                <div class="a2-section-subtitle">
                    عرض مختصر للعنصر الجاري تعديل خياراته
                </div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div class="a2-card a2-card--soft">
                <div class="a2-stat-label">الاسم العربي</div>
                <div class="a2-stat-value">{{ $categoryChild->name_ar ?: '—' }}</div>
            </div>

            <div class="a2-card a2-card--soft">
                <div class="a2-stat-label">الاسم الإنجليزي</div>
                <div class="a2-stat-value" dir="ltr">{{ $categoryChild->name_en ?: '—' }}</div>
            </div>

            <div class="a2-card a2-card--soft">
                <div class="a2-stat-label">ID</div>
                <div class="a2-stat-value">#{{ $categoryChild->id }}</div>
            </div>

            <div class="a2-card a2-card--soft">
                <div class="a2-stat-label">عدد الأقسام الرئيسية</div>
                <div class="a2-stat-value">
                    {{ $categoryChild->relationLoaded('parents') ? $categoryChild->parents->count() : 0 }}
                </div>
            </div>
        </div>

        @if($categoryChild->relationLoaded('parents') && $categoryChild->parents->count())
            <div class="a2-option-chip-grid a2-mt-16">
                @foreach($categoryChild->parents as $parent)
                    <div class="a2-option-chip-card">
                        <div class="a2-option-chip-title">
                            {{ $parent->name_ar ?: ($parent->name_en ?: ('#' . $parent->id)) }}
                        </div>
                        <div class="a2-option-chip-sub" dir="ltr">
                            #{{ $parent->id }}
                            @if(!empty($parent->name_en))
                                — {{ $parent->name_en }}
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <form method="POST"
          action="{{ route('admin.category-child-options.update', ['categoryChild' => $categoryChild->id]) }}"
          id="categoryChildOptionsForm">
        @csrf
        @method('PUT')

        @if(!empty($parentId))
            <input type="hidden" name="parent_id" value="{{ (int) $parentId }}">
        @endif

        <div class="a2-option-dual-wrap">
            <div class="a2-card">
                <div class="a2-card-head">
                    <div>
                        <div class="a2-card-title">كل الخيارات المتاحة</div>
                        <div class="a2-card-sub">دبل كليك للإضافة أو استخدم الزر الأوسط</div>
                    </div>
                </div>

                <div class="a2-form-group" style="padding:0 16px 16px;">
                    <input type="text"
                           class="a2-input"
                           id="availableSearch"
                           placeholder="بحث في كل الخيارات">
                </div>

                <div class="a2-option-list" id="availableList">
                    @forelse($availableOptions as $option)
                        <div class="a2-option-item"
                             data-id="{{ $option->id }}"
                             data-name="{{ strtolower(trim(($option->name_ar ?? '') . ' ' . ($option->name_en ?? ''))) }}">
                            <strong>{{ $option->name_ar ?: ($option->name_en ?: ('#' . $option->id)) }}</strong>
                            <small dir="ltr">
                                #{{ $option->id }}
                                @if(!empty($option->name_en))
                                    — {{ $option->name_en }}
                                @endif
                            </small>
                        </div>
                    @empty
                        <div class="a2-empty-cell">لا توجد خيارات متاحة</div>
                    @endforelse
                </div>
            </div>

            <div class="a2-option-dual-actions">
                <button type="button" class="a2-btn a2-btn-ghost" id="addSelectedBtn">إضافة ←</button>
                <button type="button" class="a2-btn a2-btn-ghost" id="removeSelectedBtn">→ حذف</button>
            </div>

            <div class="a2-card">
                <div class="a2-card-head">
                    <div>
                        <div class="a2-card-title">الخيارات المختارة</div>
                        <div class="a2-card-sub">دبل كليك للحذف أو استخدم الزر الأوسط</div>
                    </div>
                </div>

                <div class="a2-form-group" style="padding:0 16px 16px;">
                    <input type="text"
                           class="a2-input"
                           id="selectedSearch"
                           placeholder="بحث في المختار">
                </div>

                <div class="a2-option-list" id="selectedList">
                    @forelse($selectedOptions as $option)
                        <div class="a2-option-item"
                             data-id="{{ $option->id }}"
                             data-name="{{ strtolower(trim(($option->name_ar ?? '') . ' ' . ($option->name_en ?? ''))) }}">
                            <strong>{{ $option->name_ar ?: ($option->name_en ?: ('#' . $option->id)) }}</strong>
                            <small dir="ltr">
                                #{{ $option->id }}
                                @if(!empty($option->name_en))
                                    — {{ $option->name_en }}
                                @endif
                            </small>
                            <input type="hidden" name="option_ids[]" value="{{ $option->id }}">
                        </div>
                    @empty
                        <div class="a2-empty-cell">لا توجد خيارات مختارة</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="a2-page-actions" style="justify-content:flex-end;margin-top:16px;">
            <button type="submit" class="a2-btn a2-btn-primary">حفظ التعديلات</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const availableList = document.getElementById('availableList');
    const selectedList = document.getElementById('selectedList');
    const availableSearch = document.getElementById('availableSearch');
    const selectedSearch = document.getElementById('selectedSearch');
    const addBtn = document.getElementById('addSelectedBtn');
    const removeBtn = document.getElementById('removeSelectedBtn');

    function createHiddenInput(id) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'option_ids[]';
        hidden.value = id;
        return hidden;
    }

    function bindItem(item, type) {
        item.onclick = function () {
            item.classList.toggle('is-selected');
        };

        item.ondblclick = function () {
            if (type === 'available') {
                moveToSelected(item);
            } else {
                moveToAvailable(item);
            }
        };
    }

    function removeEmptyCells(list) {
        list.querySelectorAll('.a2-empty-cell').forEach(function (el) {
            el.remove();
        });
    }

    function ensureEmptyCell(list, message) {
        const items = list.querySelectorAll('.a2-option-item');
        const empty = list.querySelector('.a2-empty-cell');

        if (items.length === 0 && !empty) {
            const div = document.createElement('div');
            div.className = 'a2-empty-cell';
            div.textContent = message;
            list.appendChild(div);
        }

        if (items.length > 0 && empty) {
            empty.remove();
        }
    }

    function moveToSelected(item) {
        item.classList.remove('is-selected');

        if (!item.querySelector('input[type="hidden"]')) {
            item.appendChild(createHiddenInput(item.dataset.id));
        }

        removeEmptyCells(selectedList);
        selectedList.appendChild(item);
        bindItem(item, 'selected');

        ensureEmptyCell(availableList, 'لا توجد خيارات متاحة');
        ensureEmptyCell(selectedList, 'لا توجد خيارات مختارة');
    }

    function moveToAvailable(item) {
        item.classList.remove('is-selected');

        const hidden = item.querySelector('input[type="hidden"]');
        if (hidden) {
            hidden.remove();
        }

        removeEmptyCells(availableList);
        availableList.appendChild(item);
        bindItem(item, 'available');

        ensureEmptyCell(availableList, 'لا توجد خيارات متاحة');
        ensureEmptyCell(selectedList, 'لا توجد خيارات مختارة');
    }

    function moveSelected(fromList, toSelected) {
        fromList.querySelectorAll('.a2-option-item.is-selected').forEach(function (item) {
            if (toSelected) {
                moveToSelected(item);
            } else {
                moveToAvailable(item);
            }
        });
    }

    function bindAll() {
        availableList.querySelectorAll('.a2-option-item').forEach(function (item) {
            bindItem(item, 'available');
        });

        selectedList.querySelectorAll('.a2-option-item').forEach(function (item) {
            bindItem(item, 'selected');
        });
    }

    function attachSearch(input, list) {
        input.addEventListener('input', function () {
            const q = input.value.trim().toLowerCase();

            list.querySelectorAll('.a2-option-item').forEach(function (item) {
                const name = item.dataset.name || '';
                item.style.display = (!q || name.includes(q)) ? '' : 'none';
            });
        });
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            moveSelected(availableList, true);
        });
    }

    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            moveSelected(selectedList, false);
        });
    }

    if (availableSearch) {
        attachSearch(availableSearch, availableList);
    }

    if (selectedSearch) {
        attachSearch(selectedSearch, selectedList);
    }

    bindAll();
    ensureEmptyCell(availableList, 'لا توجد خيارات متاحة');
    ensureEmptyCell(selectedList, 'لا توجد خيارات مختارة');
})();
</script>
@endpush
@endsection