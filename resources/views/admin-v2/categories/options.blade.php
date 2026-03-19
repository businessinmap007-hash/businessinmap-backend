@extends('admin-v2.layouts.master')

@section('title', 'Category Options')
@section('body_class', 'admin-v2-category-options')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">خيارات التصنيف</h1>
            <div class="a2-page-subtitle">
                {{ $category->name_ar ?: ($category->name_en ?: ('#' . $category->id)) }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.categories.index', ['root_id' => $category->parent_id]) }}" class="a2-btn a2-btn-ghost">
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

    <form method="POST" action="{{ route('admin.categories.options.update', $category) }}" id="categoryOptionsForm">
        @csrf
        @method('PUT')

        <div class="a2-option-dual-wrap">
            <div class="a2-card">
                <div class="a2-card-head">
                    <div>
                        <div class="a2-card-title">كل الخيارات</div>
                        <div class="a2-card-sub">دبل كليك للإضافة</div>
                    </div>
                </div>

                <div class="a2-form-group" style="padding:0 16px 16px;">
                    <input type="text" class="a2-input" id="availableSearch" placeholder="بحث في كل الخيارات">
                </div>

                <div class="a2-option-list" id="availableList">
                    @foreach($availableOptions as $option)
                        <div class="a2-option-item"
                             data-id="{{ $option->id }}"
                             data-name="{{ strtolower(trim(($option->name_ar ?? '') . ' ' . ($option->name_en ?? ''))) }}">
                            <strong>{{ $option->name_ar ?: ($option->name_en ?: ('#'.$option->id)) }}</strong>
                            <small>
                                #{{ $option->id }}
                                @if(!empty($option->name_en))
                                    — {{ $option->name_en }}
                                @endif
                            </small>
                        </div>
                    @endforeach
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
                        <div class="a2-card-sub">دبل كليك للحذف</div>
                    </div>
                </div>

                <div class="a2-form-group" style="padding:0 16px 16px;">
                    <input type="text" class="a2-input" id="selectedSearch" placeholder="بحث في المختار">
                </div>

                <div class="a2-option-list" id="selectedList">
                    @foreach($selectedOptions as $option)
                        <div class="a2-option-item"
                             data-id="{{ $option->id }}"
                             data-name="{{ strtolower(trim(($option->name_ar ?? '') . ' ' . ($option->name_en ?? ''))) }}">
                            <strong>{{ $option->name_ar ?: ($option->name_en ?: ('#'.$option->id)) }}</strong>
                            <small>
                                #{{ $option->id }}
                                @if(!empty($option->name_en))
                                    — {{ $option->name_en }}
                                @endif
                            </small>
                            <input type="hidden" name="option_ids[]" value="{{ $option->id }}">
                        </div>
                    @endforeach
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

    function moveToSelected(item) {
        item.classList.remove('is-selected');

        if (!item.querySelector('input[type="hidden"]')) {
            item.appendChild(createHiddenInput(item.dataset.id));
        }

        selectedList.appendChild(item);
        bindItem(item, 'selected');
    }

    function moveToAvailable(item) {
        item.classList.remove('is-selected');

        const hidden = item.querySelector('input[type="hidden"]');
        if (hidden) {
            hidden.remove();
        }

        availableList.appendChild(item);
        bindItem(item, 'available');
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

    attachSearch(availableSearch, availableList);
    attachSearch(selectedSearch, selectedList);
    bindAll();
})();
</script>
@endpush
@endsection