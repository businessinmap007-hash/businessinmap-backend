@extends('admin-v2.layouts.master')

@section('title','Options')
@section('body_class','admin-v2-options-index')

@section('content')
<div class="a2-page">
    <div class="a2-page-actions" style="margin-bottom:16px;">
        <a href="{{ route('admin.options.create') }}" class="a2-btn a2-btn-primary">+ إضافة Option</a>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card" style="margin-bottom:16px;">
        <form method="GET" action="{{ route('admin.options.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="text" name="q" value="{{ $q ?? '' }}" placeholder="بحث">

            @if(!empty($hasIsActive))
                <select class="a2-select a2-filter-sm" name="active">
                    <option value="" @selected(($active ?? '') === '')>الكل</option>
                    <option value="1" @selected((string)($active ?? '') === '1')>Active</option>
                    <option value="0" @selected((string)($active ?? '') === '0')>Inactive</option>
                </select>
            @endif

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a href="{{ route('admin.options.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
            </div>
        </form>
    </div>

    <div class="a2-card">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">كل الخيارات</div>
                <div class="a2-card-sub">إدارة الخيارات المتاحة للتصنيفات</div>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th style="width:90px;">ID</th>
                        <th>الاسم عربي</th>
                        <th>الاسم إنجليزي</th>
                        @if(!empty($hasSortOrder))
                            <th style="width:120px;">Order</th>
                        @endif
                        @if(!empty($hasIsActive))
                            <th style="width:120px;">Status</th>
                        @endif
                        <th style="width:220px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->name_ar ?: '—' }}</td>
                            <td dir="ltr">{{ $row->name_en ?: '—' }}</td>
                            @if(!empty($hasSortOrder))
                                <td>{{ $row->sort_order ?? 0 }}</td>
                            @endif
                            @if(!empty($hasIsActive))
                                <td>{{ !empty($row->is_active) ? 'Active' : 'Inactive' }}</td>
                            @endif
                            <td>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <a href="{{ route('admin.options.edit', $row) }}" class="a2-btn a2-btn-ghost a2-btn-sm">Edit</a>

                                    <form method="POST" action="{{ route('admin.options.destroy', $row) }}" onsubmit="return confirm('تأكيد الحذف؟');" style="margin:0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 3 + (!empty($hasSortOrder) ? 1 : 0) + (!empty($hasIsActive) ? 1 : 0) + 1 }}" class="a2-empty-cell">
                                لا توجد بيانات
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px;">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection