@extends('admin-v2.layouts.master')

@section('title','Catalog Products Manager')
@section('body_class','admin-v2 admin-v2-catalog-products-index')

@section('content')
@php
    $qVal = (string)($q ?? '');
    $childIdVal = (int)($childId ?? 0);
    $brandIdVal = (int)($brandId ?? 0);
    $statusVal = (string)($status ?? '');
    $duplicateStatusVal = (string)($duplicateStatus ?? '');
    $perPageVal = (int)($perPage ?? 100);
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Catalog Products Manager</h1>
            <div class="a2-page-subtitle">مراجعة المنتجات يدويًا، ترتيب أبجدي، تحديد المكرر، وإخفاؤه من الكتالوج بدون حذف نهائي.</div>
        </div>
    </div>

    <div class="a2-stat-grid" style="margin-bottom:16px;">
        <div class="a2-stat-card"><div class="a2-stat-label">إجمالي المنتجات</div><div class="a2-stat-value">{{ number_format($stats['total'] ?? 0) }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Unique</div><div class="a2-stat-value">{{ ($stats['unique'] ?? null) === null ? '—' : number_format($stats['unique']) }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Duplicate</div><div class="a2-stat-value">{{ ($stats['duplicate'] ?? null) === null ? '—' : number_format($stats['duplicate']) }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Review</div><div class="a2-stat-value">{{ ($stats['review'] ?? null) === null ? '—' : number_format($stats['review']) }}</div></div>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.catalog-products.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" name="q" value="{{ $qVal }}" placeholder="بحث باسم المنتج / الكود / البراند / الموديل">

            <select class="a2-select a2-filter-md" name="child_id">
                <option value="0">كل الأقسام الفرعية</option>
                @foreach(($children ?? []) as $child)
                    <option value="{{ $child->id }}" @selected($childIdVal === (int)$child->id)>{{ $child->name_ar ?: $child->name_en }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="brand_id">
                <option value="0">كل البراندات</option>
                @foreach(($brands ?? []) as $brand)
                    <option value="{{ $brand->id }}" @selected($brandIdVal === (int)$brand->id)>{{ $brand->name_ar ?: $brand->name_en }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="duplicate_status">
                <option value="" @selected($duplicateStatusVal === '')>كل التكرار</option>
                <option value="unique" @selected($duplicateStatusVal === 'unique')>Unique</option>
                <option value="review" @selected($duplicateStatusVal === 'review')>Review</option>
                <option value="duplicate" @selected($duplicateStatusVal === 'duplicate')>Duplicate</option>
                <option value="master" @selected($duplicateStatusVal === 'master')>Master</option>
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="" @selected($statusVal === '')>كل الحالات</option>
                <option value="active" @selected($statusVal === 'active')>Active</option>
                <option value="inactive" @selected($statusVal === 'inactive')>Inactive</option>
                <option value="approved" @selected($statusVal === 'approved')>Approved</option>
                <option value="pending" @selected($statusVal === 'pending')>Pending</option>
                <option value="draft" @selected($statusVal === 'draft')>Draft</option>
                <option value="rejected" @selected($statusVal === 'rejected')>Rejected</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([50, 100, 200, 500] as $n)
                    <option value="{{ $n }}" @selected($perPageVal === $n)>{{ $n }} / صفحة</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a href="{{ route('admin.catalog-products.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
            </div>
        </form>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <form method="GET" action="{{ route('admin.catalog-products.index') }}" onsubmit="return confirm('تأكيد تنفيذ العملية على المنتجات المحددة؟');">
            <input type="hidden" name="q" value="{{ $qVal }}">
            <input type="hidden" name="child_id" value="{{ $childIdVal }}">
            <input type="hidden" name="brand_id" value="{{ $brandIdVal }}">
            <input type="hidden" name="status" value="{{ $statusVal }}">
            <input type="hidden" name="duplicate_status" value="{{ $duplicateStatusVal }}">
            <input type="hidden" name="per_page" value="{{ $perPageVal }}">
            <input type="hidden" name="confirm_action" value="yes">

            <div class="a2-filterbar" style="margin-bottom:12px;">
                <select class="a2-select a2-filter-md" name="manager_action" required>
                    <option value="">اختر إجراء للمنتجات المحددة</option>
                    <option value="duplicate">Mark as Duplicate / إخفاء كمكرر</option>
                    <option value="unique">Keep as Unique / إبقاء كمنتج صحيح</option>
                    <option value="review">Send to Review / يحتاج مراجعة</option>
                    <option value="inactive">Deactivate / تعطيل</option>
                    <option value="active">Activate / تفعيل</option>
                </select>
                <button class="a2-btn a2-btn-primary" type="submit">تنفيذ على المحدد</button>
                <span class="a2-muted">اختر المنتجات من الجدول. الحذف النهائي غير مفعل لحماية البيانات.</span>
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="document.querySelectorAll('.js-product-check').forEach(cb => cb.checked = this.checked)"></th>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Brand</th>
                            <th>Size</th>
                            <th>Category</th>
                            <th>Duplicate</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><input class="js-product-check" type="checkbox" name="ids[]" value="{{ $row->id }}"></td>
                            <td class="a2-fw-900">{{ $row->id }}</td>
                            <td>
                                <div class="a2-fw-900">{{ $row->name_ar ?: $row->name_en }}</div>
                                @if($row->name_en)<div class="a2-muted a2-mt-8" dir="ltr">{{ $row->name_en }}</div>@endif
                                @if($row->bim_code)<div class="a2-muted a2-mt-8" dir="ltr">Code: {{ $row->bim_code }}</div>@endif
                                @if($row->model)<div class="a2-muted a2-mt-8" dir="ltr">Model: {{ $row->model }}</div>@endif
                            </td>
                            <td>{{ $row->brand_name_ar ?: '—' }}</td>
                            <td>{{ $row->package_label_ar ?: ($row->package_label_en ?: ($row->package_value ? $row->package_value . ' ' . $row->unit_code : '—')) }}</td>
                            <td><div>{{ $row->category_name_ar ?: '—' }}</div><div class="a2-muted a2-mt-8">{{ $row->child_name_ar ?: '—' }}</div></td>
                            <td>
                                <span class="a2-pill a2-pill-gray">{{ $row->duplicate_status ?? 'unique' }}</span>
                                @if($row->duplicate_master_id)<div class="a2-muted a2-mt-8">Master: {{ $row->duplicate_master_id }}</div>@endif
                            </td>
                            <td>
                                @if((int)$row->is_active === 1)
                                    <span class="a2-pill a2-pill-success">Active</span>
                                @else
                                    <span class="a2-pill a2-pill-danger">Inactive</span>
                                @endif
                                <div class="a2-muted a2-mt-8">{{ $row->approval_status }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="a2-empty">لا توجد منتجات.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </form>

        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
