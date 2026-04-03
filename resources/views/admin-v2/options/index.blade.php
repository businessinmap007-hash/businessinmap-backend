@extends('admin-v2.layouts.master')

@section('title','Options')
@section('body_class','admin-v2 admin-v2-options-index')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $groupIdVal = (string) ($groupId ?? '');
@endphp

<div class="a2-page">

    {{-- ================= HEADER ================= --}}
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الخيارات</h1>
            <div class="a2-page-subtitle">
                إدارة بيانات الخيارات وربطها بالمجموعات
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.option-groups.index') }}" class="a2-btn a2-btn-ghost">
                إدارة المجموعات
            </a>

            <a href="{{ route('admin.options.create') }}" class="a2-btn a2-btn-primary">
                + إضافة خيار
            </a>
        </div>
    </div>

    {{-- ================= ALERTS ================= --}}
    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    {{-- ================= FILTER ================= --}}
    <div class="a2-card a2-card--section a2-mb-16">
        <form method="GET" action="{{ route('admin.options.index') }}" class="a2-form-grid">

            <div class="a2-form-group">
                <label class="a2-label">بحث</label>
                <input class="a2-input"
                       type="text"
                       name="q"
                       value="{{ $qVal }}"
                       placeholder="اسم عربي أو إنجليزي">
            </div>

            <div class="a2-form-group">
                <label class="a2-label">المجموعة</label>
                <select class="a2-select" name="group_id">
                    <option value="">كل المجموعات</option>
                    <option value="ungrouped" @selected($groupIdVal === 'ungrouped')>بدون مجموعة</option>

                    @foreach($groups as $g)
                        <option value="{{ $g->id }}" @selected($groupIdVal == $g->id)>
                            #{{ $g->id }} - {{ $g->name_ar ?: $g->name_en }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-page-actions" style="align-items:flex-end;">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

                <a href="{{ route('admin.options.index') }}" class="a2-btn a2-btn-ghost">
                    تفريغ
                </a>
            </div>

        </form>
    </div>

    {{-- ================= BULK GROUP ================= --}}
    <form method="POST" action="{{ route('admin.options.bulk-assign-group') }}" class="a2-card a2-mb-16">
        @csrf

        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">تغيير المجموعة</div>
                <div class="a2-card-sub">تحديد مجموعة أو إزالة الربط</div>
            </div>

            <div class="a2-page-actions">
                <select name="target_group_id" class="a2-select">
                    <option value="">بدون مجموعة</option>

                    @foreach($groups as $g)
                        <option value="{{ $g->id }}">
                            {{ $g->name_ar ?: $g->name_en }}
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="a2-btn a2-btn-primary">
                    تطبيق على المحدد
                </button>
            </div>
        </div>

        {{-- ================= TABLE ================= --}}
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th style="width:50px;">
                        <input type="checkbox" id="checkAll">
                    </th>
                    <th style="width:80px;">ID</th>
                    <th>الاسم (AR)</th>
                    <th>الاسم (EN)</th>
                    <th style="width:120px;">Group</th>
                    <th style="width:220px;">الإجراءات</th>
                </tr>
                </thead>

                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>
                            <input type="checkbox" name="option_ids[]" value="{{ $row->id }}" class="row-checkbox">
                        </td>

                        <td>#{{ $row->id }}</td>

                        <td class="a2-fw-700">
                            {{ $row->name_ar ?: '—' }}
                        </td>

                        <td dir="ltr">
                            {{ $row->name_en ?: '—' }}
                        </td>

                        <td>
                            @if($row->group_id)
                                <span class="a2-pill a2-pill-active">
                                    #{{ $row->group_id }}
                                </span>
                            @else
                                <span class="a2-pill a2-pill-inactive">
                                    —
                                </span>
                            @endif
                        </td>

                        <td>
                            <div class="a2-actions">

                                <a href="{{ route('admin.options.edit', $row->id) }}"
                                   class="a2-btn a2-btn-ghost a2-btn-sm">
                                    تعديل
                                </a>

                                <form method="POST"
                                      action="{{ route('admin.options.destroy', $row->id) }}"
                                      onsubmit="return confirm('تأكيد الحذف؟');">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit"
                                            class="a2-btn a2-btn-danger a2-btn-sm">
                                        حذف
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="a2-empty-cell">
                            لا توجد بيانات
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- ================= PAGINATION ================= --}}
        @if(method_exists($rows, 'links'))
            <div class="a2-mt-16">
                {{ $rows->links() }}
            </div>
        @endif

    </form>
</div>

{{-- ================= JS ================= --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = checkAll.checked);
        });
    }
});
</script>
@endpush

@endsection