@extends('admin-v2.layouts.master')

@section('title', 'Options')
@section('body_class', 'admin-v2 admin-v2-options-index')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $groupFilterVal = (string) ($groupFilter ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">كل الخيارات</h1>
            <div class="a2-page-subtitle">
                تنظيم الـ Options داخل مجموعات عامة. كل Option يمكن أن ينتمي إلى مجموعة واحدة فقط.
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
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي الخيارات</div>
            <div class="a2-stat-value">{{ (int) ($totalCount ?? 0) }}</div>
            <div class="a2-stat-note">داخل الجدول الأساسي</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">خيارات داخل Groups</div>
            <div class="a2-stat-value">{{ (int) ($groupedCount ?? 0) }}</div>
            <div class="a2-stat-note">مصنفة بالفعل</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">خيارات بدون Group</div>
            <div class="a2-stat-value">{{ (int) ($ungroupedCount ?? 0) }}</div>
            <div class="a2-stat-note">تحتاج تصنيف</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد الـ Groups</div>
            <div class="a2-stat-value">{{ collect($allGroups ?? [])->count() }}</div>
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
                @foreach(($allGroups ?? []) as $g)
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

        <div class="a2-card a2-card--section a2-mb-16">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">Bulk Assign</div>
                    <div class="a2-card-sub">
                        حدد Option واحدة أو أكثر ثم انقلها إلى Group واحدة. عند النقل، تختفي من أي Group أخرى تلقائيًا.
                    </div>
                </div>

                <div class="a2-page-actions">
                    <select class="a2-select" name="target_group_id" style="min-width:220px;">
                        <option value="">بدون Group</option>
                        @foreach(($allGroups ?? []) as $g)
                            <option value="{{ $g->id }}">
                                {{ $g->name_ar ?: ($g->name_en ?: ('#' . $g->id)) }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="a2-btn a2-btn-primary">
                        نقل المحدد
                    </button>
                </div>
            </div>

            <div class="a2-page-subtitle" style="padding:0 16px 16px;">
                كل Option يظهر في مكان واحد فقط لأن الربط يعتمد على `group_id` واحد داخل جدول `options`.
            </div>
        </div>

        @if($groupFilterVal === '' || $groupFilterVal === 'ungrouped')
            <div class="a2-card a2-card--section a2-mb-16">
                <div class="a2-card-head">
                    <div>
                        <div class="a2-card-title">بدون Group</div>
                        <div class="a2-card-sub">
                            {{ collect($ungroupedOptions ?? [])->count() }} خيار
                        </div>
                    </div>
                </div>

                @if(collect($ungroupedOptions ?? [])->count())
                    <div class="a2-check-grid">
                        @foreach($ungroupedOptions as $option)
                            <label class="a2-check-card">
                                <input type="checkbox" name="option_ids[]" value="{{ $option->id }}">
                                <span>
                                    <strong>
                                        #{{ $option->id }} — {{ $option->name_ar ?: ($option->name_en ?: '—') }}
                                    </strong>
                                    <small dir="ltr">
                                        {{ $option->name_en ?: '—' }}
                                    </small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="a2-alert a2-alert-warning" style="margin:16px;">
                        لا توجد Options غير مصنفة.
                    </div>
                @endif
            </div>
        @endif

        @foreach(($groups ?? []) as $group)
            @php
                $groupOptions = $group->options ?? collect();
            @endphp

            @if($groupFilterVal === '' || $groupFilterVal === (string) $group->id)
                <div class="a2-card a2-card--section a2-mb-16">
                    <div class="a2-card-head">
                        <div>
                            <div class="a2-card-title">
                                {{ $group->name_ar ?: ($group->name_en ?: ('#' . $group->id)) }}
                            </div>
                            <div class="a2-card-sub">
                                Group #{{ $group->id }} — {{ $groupOptions->count() }} خيار
                            </div>
                        </div>

                        <div class="a2-page-actions">
                            <a href="{{ route('admin.option-groups.edit', $group->id) }}"
                               class="a2-btn a2-btn-ghost a2-btn-sm">
                                تعديل المجموعة
                            </a>
                        </div>
                    </div>

                    @if($groupOptions->count())
                        <div class="a2-check-grid">
                            @foreach($groupOptions as $option)
                                <label class="a2-check-card">
                                    <input type="checkbox" name="option_ids[]" value="{{ $option->id }}">
                                    <span>
                                        <strong>
                                            #{{ $option->id }} — {{ $option->name_ar ?: ($option->name_en ?: '—') }}
                                        </strong>
                                        <small dir="ltr">
                                            {{ $option->name_en ?: '—' }}
                                        </small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="a2-alert a2-alert-warning" style="margin:16px;">
                            لا توجد Options داخل هذه المجموعة.
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bulkForm = document.getElementById('optionsBulkAssignForm');

    if (!bulkForm) return;

    bulkForm.addEventListener('submit', function (e) {
        const checked = bulkForm.querySelectorAll('input[name="option_ids[]"]:checked').length;

        if (checked === 0) {
            e.preventDefault();
            alert('حدد Option واحدة على الأقل.');
        }
    });
});
</script>
@endpush
@endsection