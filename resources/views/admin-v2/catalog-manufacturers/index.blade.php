@extends('admin-v2.layouts.master')

@section('title', 'Catalog Manufacturers')
@section('body_class', 'admin-v2-catalog-manufacturers')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $statusVal = (string) ($status ?? '');
    $perPageVal = (int) ($perPage ?? 50);
    $perPageOptions = [10, 20, 50, 100];
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Catalog Manufacturers</h1>
            <div class="a2-page-subtitle">إدارة الشركات المصنعة داخل الكتالوج الموحد.</div>
        </div>
    </div>

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">Total Manufacturers</div>
            <div class="a2-stat-value">{{ number_format($stats['total'] ?? 0) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Active</div>
            <div class="a2-stat-value">{{ ($stats['active'] ?? null) === null ? '—' : number_format($stats['active']) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Inactive</div>
            <div class="a2-stat-value">{{ ($stats['inactive'] ?? null) === null ? '—' : number_format($stats['inactive']) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Linked Products</div>
            <div class="a2-stat-value">{{ number_format($stats['products'] ?? 0) }}</div>
        </div>
    </div>

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.catalog-manufacturers.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" name="q" value="{{ $qVal }}" placeholder="Search: Arabic / English name / code">

            <select class="a2-select a2-filter-sm" name="status">
                <option value="">All Status</option>
                <option value="1" @selected($statusVal === '1')>Active</option>
                <option value="0" @selected($statusVal === '0')>Inactive</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach($perPageOptions as $n)
                    <option value="{{ $n }}" @selected((int) $perPageVal === (int) $n)>{{ $n }} / صفحة</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.catalog-manufacturers.index') }}">تفريغ</a>
            </div>
        </form>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Arabic Name</th>
                        <th>English Name</th>
                        <th>Code</th>
                        <th>Products</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td class="a2-text-right"><span class="a2-clip" style="max-width:220px">{{ $row->name_ar ?? $row->name ?? '—' }}</span></td>
                            <td class="a2-text-left" dir="ltr"><span class="a2-clip" style="max-width:220px">{{ $row->name_en ?? $row->name ?? '—' }}</span></td>
                            <td dir="ltr">{{ $row->code ?? '—' }}</td>
                            <td><span class="a2-pill a2-pill-gray">{{ number_format($row->products_count ?? 0) }}</span></td>
                            <td>
                                @if(property_exists($row, 'is_active'))
                                    <span class="a2-pill {{ (int) $row->is_active === 1 ? 'a2-pill-active' : 'a2-pill-inactive' }}">
                                        {{ (int) $row->is_active === 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                @elseif(property_exists($row, 'status'))
                                    <span class="a2-pill a2-pill-gray">{{ $row->status ?: '—' }}</span>
                                @else
                                    <span class="a2-muted">—</span>
                                @endif
                            </td>
                            <td dir="ltr">{{ !empty($row->created_at) ? \Illuminate\Support\Carbon::parse($row->created_at)->format('Y-m-d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="a2-empty-cell">لا يوجد بيانات</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($items, 'links'))
            <div class="a2-paginate">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
