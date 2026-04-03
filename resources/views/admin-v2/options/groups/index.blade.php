@extends('admin-v2.layouts.master')

@section('title','Option Groups')
@section('body_class','admin-v2 admin-v2-option-groups-index')

@section('content')
@php
    $qVal = (string) ($q ?? '');
@endphp

<div class="a2-page">

    {{-- ================= HEADER ================= --}}
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">مجموعات الخيارات</h1>
            <div class="a2-page-subtitle">
                إدارة مجموعات تنظيم الخيارات
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.options.index') }}" class="a2-btn a2-btn-ghost">
                إدارة الخيارات
            </a>

            <a href="{{ route('admin.option-groups.create') }}" class="a2-btn a2-btn-primary">
                + إضافة Group
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
        <form method="GET" action="{{ route('admin.option-groups.index') }}" class="a2-form-grid">

            <div class="a2-form-group">
                <label class="a2-label">بحث</label>
                <input class="a2-input"
                       type="text"
                       name="q"
                       value="{{ $qVal }}"
                       placeholder="اسم المجموعة">
            </div>

            <div class="a2-page-actions" style="align-items:flex-end;">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>

                <a href="{{ route('admin.option-groups.index') }}" class="a2-btn a2-btn-ghost">
                    تفريغ
                </a>
            </div>

        </form>
    </div>

    {{-- ================= TABLE ================= --}}
    <div class="a2-card">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                <tr>
                    <th style="width:80px;">ID</th>
                    <th>الاسم (AR)</th>
                    <th>الاسم (EN)</th>
                    <th style="width:120px;">الترتيب</th>
                    <th style="width:120px;">الحالة</th>
                    <th style="width:140px;">عدد الخيارات</th>
                    <th style="width:260px;">الإجراءات</th>
                </tr>
                </thead>

                <tbody>
                @forelse($rows as $row)
                    @php
                        $isActive = (int) ($row->is_active ?? 0) === 1;
                    @endphp

                    <tr>
                        <td>#{{ $row->id }}</td>

                        <td class="a2-fw-700">
                            {{ $row->name_ar ?: '—' }}
                        </td>

                        <td dir="ltr">
                            {{ $row->name_en ?: '—' }}
                        </td>

                        <td>
                            {{ (int) ($row->reorder ?? 0) }}
                        </td>

                        <td>
                            <span class="a2-pill {{ $isActive ? 'a2-pill-active' : 'a2-pill-inactive' }}">
                                {{ $isActive ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <td>
                            <span class="a2-pill a2-pill-success">
                                {{ (int) ($row->options_count ?? 0) }}
                            </span>
                        </td>

                        <td>
                            <div class="a2-actions">

                                <a href="{{ route('admin.option-groups.edit', $row->id) }}"
                                   class="a2-btn a2-btn-primary a2-btn-sm">
                                    إدارة الخيارات
                                </a>

                                <a href="{{ route('admin.option-groups.edit', $row->id) }}"
                                   class="a2-btn a2-btn-ghost a2-btn-sm">
                                    تعديل
                                </a>

                                <form method="POST"
                                      action="{{ route('admin.option-groups.destroy', $row->id) }}"
                                      onsubmit="return confirm('تأكيد حذف المجموعة؟');">
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
                        <td colspan="7" class="a2-empty-cell">
                            لا توجد مجموعات
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
    </div>
</div>
@endsection