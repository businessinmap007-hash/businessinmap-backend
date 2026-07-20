@extends('admin-v2.layouts.master')

@section('title', 'Menu Items')
@section('body_class', 'admin-v2-menu-items-index')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $businessIdVal = (int) ($businessId ?? 0);
    $isActiveVal = (string) ($isActive ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('عناصر المنيو') }}</h1>
            <div class="a2-page-subtitle">{{ __('إدارة قوائم الطعام الخاصة بكل بزنس') }}</div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.menu-items.create') }}" class="a2-btn a2-btn-primary">{{ __('+ إضافة عنصر') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.menu-items.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="text" name="q" value="{{ $qVal }}" placeholder="{{ __('بحث بالاسم') }}">

            <select class="a2-select a2-filter-md" name="business_id">
                <option value="0">{{ __('كل البزنسات') }}</option>
                @foreach(($businesses ?? []) as $b)
                    <option value="{{ $b->id }}" @selected($businessIdVal === (int) $b->id)>
                        {{ $b->name ?: ('#' . $b->id) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="is_active">
                <option value="" @selected($isActiveVal === '')>{{ __('الكل') }}</option>
                <option value="1" @selected($isActiveVal === '1')>Active</option>
                <option value="0" @selected($isActiveVal === '0')>Inactive</option>
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">{{ __('تطبيق') }}</button>
                <a href="{{ route('admin.menu-items.index') }}" class="a2-btn a2-btn-ghost">{{ __('تفريغ') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>Business</th>
                        <th>Name</th>
                        <th>Base Price</th>
                        <th>Sort</th>
                        <th>Status</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->business->name ?? '—' }}</td>
                        <td class="a2-fw-700">{{ $row->name_ar ?: ($row->name_en ?: '—') }}</td>
                        <td>{{ number_format((float) ($row->base_price ?? 0), 2) }}</td>
                        <td>{{ $row->sort_order }}</td>
                        <td>
                            <span class="a2-pill {{ (int) ($row->is_active ?? 0) === 1 ? 'a2-pill-active' : 'a2-pill-inactive' }}">
                                {{ (int) ($row->is_active ?? 0) === 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a href="{{ route('admin.menu-items.edit', $row) }}" class="a2-btn a2-btn-ghost a2-btn-sm">Edit</a>

                                <form method="POST" action="{{ route('admin.menu-items.destroy', $row) }}" onsubmit="return confirm('تأكيد حذف العنصر؟');" style="margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="a2-empty-cell">{{ __('لا توجد عناصر منيو') }}</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($rows, 'links'))
            <div style="margin-top:16px;">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
