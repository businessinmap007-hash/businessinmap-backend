@extends('admin-v2.layouts.master')

@section('title','Catalog Brands')
@section('body_class','admin-v2 admin-v2-catalog-brands-index')

@section('content')
@php
    $qVal = (string)($q ?? '');
    $verifiedVal = (string)($verified ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Catalog Brands</h1>
            <div class="a2-page-subtitle">{{ __('براندات المنتجات وربطها بعدد المنتجات داخل الكتالوج.') }}</div>
        </div>
    </div>

    <div class="a2-stat-grid" style="margin-bottom:16px;">
        <div class="a2-stat-card"><div class="a2-stat-label">Brands</div><div class="a2-stat-value">{{ $stats['total'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Active</div><div class="a2-stat-value">{{ $stats['active'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Verified</div><div class="a2-stat-value">{{ $stats['verified'] ?? 0 }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Products</div><div class="a2-stat-value">{{ $stats['products'] ?? 0 }}</div></div>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.catalog-brands.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" name="q" value="{{ $qVal }}" placeholder="{{ __('بحث باسم البراند أو slug') }}">
            <select class="a2-select a2-filter-sm" name="verified">
                <option value="" @selected($verifiedVal === '')>{{ __('كل البراندات') }}</option>
                <option value="1" @selected($verifiedVal === '1')>Verified</option>
                <option value="0" @selected($verifiedVal === '0')>Not Verified</option>
            </select>
            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">{{ __('بحث') }}</button>
                <a href="{{ route('admin.catalog-brands.index') }}" class="a2-btn a2-btn-ghost">{{ __('تفريغ') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Arabic</th><th>English</th><th>Slug</th><th>Country</th><th>Products</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="a2-fw-900">{{ $row->id }}</td>
                        <td>{{ $row->name_ar }}</td>
                        <td>{{ $row->name_en }}</td>
                        <td dir="ltr">{{ $row->slug }}</td>
                        <td>{{ $row->country_code ?: '—' }}</td>
                        <td>{{ $productCounts[$row->id] ?? 0 }}</td>
                        <td>
                            @if((int)$row->is_active === 1)
                                <span class="a2-pill a2-pill-success">Active</span>
                            @else
                                <span class="a2-pill a2-pill-danger">Inactive</span>
                            @endif
                            @if((int)$row->is_verified === 1)
                                <div class="a2-pill a2-pill-success a2-mt-8">Verified</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="a2-empty">{{ __('لا توجد براندات.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
