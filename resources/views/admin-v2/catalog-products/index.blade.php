@extends('admin-v2.layouts.master')

@section('title','Catalog Products')
@section('body_class','admin-v2 admin-v2-catalog-products-index')

@section('content')
@php
    $qVal = (string)($q ?? '');
    $childIdVal = (int)($childId ?? 0);
    $brandIdVal = (int)($brandId ?? 0);
    $statusVal = (string)($status ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Catalog Products</h1>
            <div class="a2-page-subtitle">إدارة ومراجعة منتجات الكتالوج الأساسية: الاسم، البراند، القسم، السعة أو الوزن.</div>
        </div>
    </div>

    <div class="a2-stat-grid" style="margin-bottom:16px;">
        <div class="a2-stat-card"><div class="a2-stat-label">إجمالي المنتجات</div><div class="a2-stat-value">{{ $stats['total'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Active</div><div class="a2-stat-value">{{ $stats['active'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Approved</div><div class="a2-stat-value">{{ $stats['approved'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">الأقسام الفرعية</div><div class="a2-stat-value">{{ $stats['children'] ?? 0 }}</div></div>
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

            <select class="a2-select a2-filter-sm" name="status">
                <option value="" @selected($statusVal === '')>كل الحالات</option>
                <option value="active" @selected($statusVal === 'active')>Active</option>
                <option value="inactive" @selected($statusVal === 'inactive')>Inactive</option>
                <option value="approved" @selected($statusVal === 'approved')>Approved</option>
                <option value="pending" @selected($statusVal === 'pending')>Pending</option>
                <option value="draft" @selected($statusVal === 'draft')>Draft</option>
                <option value="rejected" @selected($statusVal === 'rejected')>Rejected</option>
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a href="{{ route('admin.catalog-products.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
            </div>
        </form>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Brand</th>
                        <th>Size</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="a2-fw-900">{{ $row->id }}</td>
                        <td dir="ltr">{{ $row->bim_code }}</td>
                        <td>
                            <div class="a2-fw-900">{{ $row->name_ar ?: $row->name_en }}</div>
                            @if($row->name_en)
                                <div class="a2-muted a2-mt-8">{{ $row->name_en }}</div>
                            @endif
                            @if($row->model)
                                <div class="a2-muted a2-mt-8">Model: {{ $row->model }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $row->category_name_ar ?: '—' }}</div>
                            <div class="a2-muted a2-mt-8">{{ $row->child_name_ar ?: '—' }}</div>
                        </td>
                        <td>{{ $row->brand_name_ar ?: '—' }}</td>
                        <td>{{ $row->package_label_ar ?: ($row->package_label_en ?: ($row->package_value ? $row->package_value . ' ' . $row->unit_code : '—')) }}</td>
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
                    <tr><td colspan="7" class="a2-empty">لا توجد منتجات.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
