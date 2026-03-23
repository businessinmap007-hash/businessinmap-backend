@extends('admin-v2.layouts.master')

@section('title', 'Options')
@section('body_class', 'admin-v2 admin-v2-options-index')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $groupFilterVal = (string) ($groupFilter ?? '');

    $allGroupsSafe = collect($allGroups ?? []);

    /*
    |--------------------------------------------------------------------------
    | Unified rows source
    |--------------------------------------------------------------------------
    | يدعم حالتين:
    | 1) لو الكنترول يرجع rows مباشرة
    | 2) لو الكنترول يرجع groups + ungroupedOptions
    */
    if (isset($rows)) {
        $rowsSafe = collect($rows);
    } else {
        $ungroupedSafe = collect($ungroupedOptions ?? [])
            ->map(function ($option) {
                $option->resolved_group_name = null;
                return $option;
            });

        $groupedRows = collect($groups ?? [])->flatMap(function ($group) {
            return collect($group->options ?? [])->map(function ($option) use ($group) {
                $option->resolved_group_name = $group->name_ar ?: ($group->name_en ?: ('#' . $group->id));
                return $option;
            });
        });

        $rowsSafe = $ungroupedSafe
            ->concat($groupedRows)
            ->sortBy('id')
            ->values();
    }

    $totalCountVal = (int) ($totalCount ?? $rowsSafe->count());

    $groupedCountVal = (int) (
        $groupedCount
        ?? $rowsSafe->filter(function ($row) {
            return !empty($row->group_id) || !empty($row->resolved_group_name) || !empty($row->group ?? null);
        })->count()
    );

    $ungroupedCountVal = (int) (
        $ungroupedCount
        ?? $rowsSafe->filter(function ($row) {
            return empty($row->group_id) && empty($row->resolved_group_name) && empty($row->group ?? null);
        })->count()
    );
@endphp

<div class="a2-page">

    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إدارة الخيارات</h1>
            <div class="a2-page-subtitle">
                تنظيم الخيارات داخل مجموعات عامة بدون التأثير على الربط الأساسي
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.option-groups.index') }}" class="a2-btn a2-btn-ghost">
                إدارة المجموعات
            </a>

            <a href="{{ route('admin.option-groups.create') }}" class="a2-btn a2-btn-ghost">
                + إضافة Group
            </a>

            <a href="{{ route('admin.options.create') }}" class="a2-btn a2-btn-primary">
                + إضافة Option
            </a>
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

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي الخيارات</div>
            <div class="a2-stat-value">{{ $totalCountVal }}</div>
            <div class="a2-stat-note">داخل الجدول الأساسي</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">داخل Groups</div>
            <div class="a2-stat-value">{{ $groupedCountVal }}</div>
            <div class="a2-stat-note">تم تصنيفها</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">بدون Group</div>
            <div class="a2-stat-value">{{ $ungroupedCountVal }}</div>
            <div class="a2-stat-note">تحتاج تصنيف</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد المجموعات</div>
            <div class="a2-stat-value">{{ $allGroupsSafe->count() }}</div>
            <div class="a2-stat-note">المتاحة حاليًا</div>
        </div>
    </div>

    <div class="a2-card a2-mb-16">
        <form method="GET" action="{{ route('admin.options.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search"
                   type="text"
                   name="q"
                   value="{{ $qVal }}"
                   placeholder="بحث في الخيارات">

            <select class="a2-select a2-filter-md" name="group_id">
                <option value="" @selected($groupFilterVal === '')>كل المجموعات</option>
                <option value="ungrouped" @selected($groupFilterVal === 'ungrouped')>بدون Group</option>

                @foreach($allGroupsSafe as $g)
                    <option value="{{ $g->id }}" @selected($groupFilterVal === (string) $g->id)>
                        {{ $g->name_ar ?: ($g->name_en ?: ('#' . $g->id)) }}
                    </option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a href="{{ route('admin.options.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
            </div>
        </form>
    </div>

    <form method="POST" action="{{ route('admin.options.bulk-assign-group') }}" id="optionsBulkAssignForm">
        @csrf
        <input type="hidden" name="target_group_id" id="targetGroupIdHidden" value="">

        <div class="a2-card a2-mb-16">
            <div class="a2-page-actions" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">

                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button type="button"
                            class="a2-btn a2-btn-primary"
                            onclick="submitBulkAssign()">
                        حفظ على Group
                    </button>

                    <button type="button"
                            class="a2-btn a2-btn-danger"
                            onclick="deleteSelectedOptions()">
                        حذف المحدد
                    </button>

                    <button type="button"
                            class="a2-btn a2-btn-ghost"
                            onclick="editSelectedOption()">
                        تعديل المحدد
                    </button>
                </div>

                <div style="display:flex;gap:8px;align-items:center;">
                    <span class="a2-muted">Group:</span>

                    <select class="a2-select js-target-group-select" style="min-width:220px;">
                        <option value="">بدون Group</option>
                        @foreach($allGroupsSafe as $g)
                            <option value="{{ $g->id }}">
                                {{ $g->name_ar ?: ($g->name_en ?: ('#' . $g->id)) }}
                            </option>
                        @endforeach
                    </select>
                </div>

            </div>
        </div>

        <div class="a2-card">
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th style="width:42px;">
                                <input type="checkbox" id="checkAll">
                            </th>
                            <th style="width:90px;">ID</th>
                            <th>الاسم عربي</th>
                            <th>الاسم إنجليزي</th>
                            <th style="width:180px;">Group</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($rowsSafe as $row)
                            @php
                                $resolvedGroupName = null;

                                if (!empty($row->group) && is_object($row->group)) {
                                    $resolvedGroupName = $row->group->name_ar ?: ($row->group->name_en ?: ('#' . $row->group->id));
                                } elseif (!empty($row->resolved_group_name)) {
                                    $resolvedGroupName = $row->resolved_group_name;
                                }
                            @endphp

                            <tr>
                                <td>
                                    <input type="checkbox"
                                           name="option_ids[]"
                                           value="{{ $row->id }}"
                                           class="row-checkbox">
                                </td>

                                <td>#{{ $row->id }}</td>

                                <td class="a2-fw-700">
                                    {{ $row->name_ar ?: '—' }}
                                </td>

                                <td dir="ltr">
                                    {{ $row->name_en ?: '—' }}
                                </td>

                                <td>
                                    @if($resolvedGroupName)
                                        <span class="a2-pill a2-pill-success">
                                            {{ $resolvedGroupName }}
                                        </span>
                                    @else
                                        <span class="a2-pill a2-pill-gray">
                                            بدون Group
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="a2-empty-cell">
                                    لا توجد خيارات
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="a2-page-actions a2-mt-16" style="justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button"
                        class="a2-btn a2-btn-primary"
                        onclick="submitBulkAssign()">
                    حفظ على Group
                </button>

                <button type="button"
                        class="a2-btn a2-btn-danger"
                        onclick="deleteSelectedOptions()">
                    حذف المحدد
                </button>

                <button type="button"
                        class="a2-btn a2-btn-ghost"
                        onclick="editSelectedOption()">
                    تعديل المحدد
                </button>
            </div>

            <div style="display:flex;gap:8px;align-items:center;">
                <span class="a2-muted">Group:</span>

                <select class="a2-select js-target-group-select" style="min-width:220px;">
                    <option value="">بدون Group</option>
                    @foreach($allGroupsSafe as $g)
                        <option value="{{ $g->id }}">
                            {{ $g->name_ar ?: ($g->name_en ?: ('#' . $g->id)) }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </form>
</div>

<form id="bulkDeleteOptionsForm" method="POST" action="{{ route('admin.options.bulk-delete') }}" style="display:none;">
    @csrf
    @method('DELETE')
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAll = document.getElementById('checkAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rowCheckboxes.forEach(function (cb) {
                cb.checked = checkAll.checked;
            });
        });
    }

    const hidden = document.getElementById('targetGroupIdHidden');
    const selects = document.querySelectorAll('.js-target-group-select');

    selects.forEach(function (select) {
        select.addEventListener('change', function () {
            const val = select.value || '';

            if (hidden) {
                hidden.value = val;
            }

            selects.forEach(function (other) {
                if (other !== select) {
                    other.value = val;
                }
            });
        });
    });
});

function getSelectedOptionIds() {
    return Array.from(document.querySelectorAll('.row-checkbox:checked'))
        .map(function (el) { return el.value; })
        .filter(Boolean);
}

function submitBulkAssign() {
    const form = document.getElementById('optionsBulkAssignForm');
    const ids = getSelectedOptionIds();

    if (ids.length === 0) {
        alert('حدد Option واحدة على الأقل.');
        return;
    }

    form.action = "{{ route('admin.options.bulk-assign-group') }}";
    form.submit();
}

function editSelectedOption() {
    const ids = getSelectedOptionIds();

    if (ids.length === 0) {
        alert('حدد Option واحدة أولًا.');
        return;
    }

    if (ids.length > 1) {
        alert('يمكن تعديل Option واحدة فقط في كل مرة.');
        return;
    }

    window.location.href = "{{ url('/admin/options') }}/" + ids[0] + "/edit";
}

function deleteSelectedOptions() {
    const ids = getSelectedOptionIds();

    if (ids.length === 0) {
        alert('حدد Option واحدة على الأقل.');
        return;
    }

    if (!confirm('تأكيد حذف الخيارات المحددة؟')) {
        return;
    }

    const form = document.getElementById('bulkDeleteOptionsForm');
    form.querySelectorAll('input[name="option_ids[]"]').forEach(function (el) {
        el.remove();
    });

    ids.forEach(function (id) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'option_ids[]';
        input.value = id;
        form.appendChild(input);
    });

    form.submit();
}
</script>
@endpush
@endsection