@extends('admin-v2.layouts.master')

@section('title','Product Categories')
@section('body_class','admin-v2 admin-v2-product-categories-index')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Product Categories</h1>
            <div class="a2-page-subtitle">تصنيفات المنتجات منفصلة عن تصنيفات البزنس.</div>
        </div>
    </div>

    <div class="a2-stat-grid" style="margin-bottom:16px;">
        <div class="a2-stat-card"><div class="a2-stat-label">Categories</div><div class="a2-stat-value">{{ $stats['total'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Active</div><div class="a2-stat-value">{{ $stats['active'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Children</div><div class="a2-stat-value">{{ $stats['children'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Products</div><div class="a2-stat-value">{{ $stats['products'] ?? 0 }}</div></div>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.product-categories.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" name="q" value="{{ $q ?? '' }}" placeholder="بحث باسم التصنيف أو slug">
            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">بحث</button>
                <a href="{{ route('admin.product-categories.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
            </div>
        </form>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Arabic</th><th>English</th><th>Slug</th><th>Children</th><th>Products</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="a2-fw-900">{{ $row->id }}</td>
                        <td>{{ $row->name_ar }}</td>
                        <td>{{ $row->name_en }}</td>
                        <td dir="ltr">{{ $row->slug }}</td>
                        <td>{{ $childrenCounts[$row->id] ?? 0 }}</td>
                        <td>{{ $productCounts[$row->id] ?? 0 }}</td>
                        <td>
                            @if((int)$row->is_active === 1)
                                <span class="a2-pill a2-pill-success">Active</span>
                            @else
                                <span class="a2-pill a2-pill-danger">Inactive</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="a2-empty">لا توجد تصنيفات منتجات.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
