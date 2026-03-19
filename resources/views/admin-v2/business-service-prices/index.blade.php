@extends('admin-v2.layouts.master')

@section('title','Business Service Prices')
@section('body_class','admin-v2-business-service-prices-index')

@section('content')
@php
    $qBusinessVal = (string)($qBusiness ?? '');
    $qServiceVal  = (string)($qService ?? '');
    $qItemTypeVal = (string)($qItemType ?? '');
    $businessIdVal = (int)($businessId ?? 0);
    $serviceIdVal  = (int)($serviceId ?? 0);
    $isActiveVal   = (string)($isActive ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">أسعار خدمات البزنس</h1>
            <div class="a2-page-subtitle">
                إدارة السعر حسب البزنس والخدمة ونوع العنصر
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.business_service_prices.create') }}" class="a2-btn a2-btn-primary">
                + إضافة سعر
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card">
        <form method="GET" action="{{ route('admin.business_service_prices.index') }}" class="a2-filterbar">
            <input
                class="a2-input a2-filter-search"
                name="q_business"
                value="{{ $qBusinessVal }}"
                placeholder="بحث باسم البزنس"
            >

            <input
                class="a2-input a2-filter-sm"
                name="q_service"
                value="{{ $qServiceVal }}"
                placeholder="بحث بالخدمة"
            >

            <input
                class="a2-input a2-filter-sm"
                name="q_item_type"
                value="{{ $qItemTypeVal }}"
                placeholder="نوع العنصر"
            >

            <select class="a2-select a2-filter-md" name="business_id">
                <option value="0">كل البزنسات</option>
                @foreach(($businesses ?? []) as $b)
                    <option value="{{ $b->id }}" @selected($businessIdVal === (int)$b->id)>
                        {{ $b->name ?: ('#'.$b->id) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="service_id">
                <option value="0">كل الخدمات</option>
                @foreach(($services ?? []) as $s)
                    <option value="{{ $s->id }}" @selected($serviceIdVal === (int)$s->id)>
                        {{ $s->name_ar ?: ($s->name_en ?: $s->key) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="is_active">
                <option value="" @selected($isActiveVal==='')>الكل</option>
                <option value="1" @selected($isActiveVal==='1')>Active</option>
                <option value="0" @selected($isActiveVal==='0')>Inactive</option>
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a href="{{ route('admin.business_service_prices.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
            </div>
        </form>
    </div>

    <div class="a2-stat-grid" style="margin-top:16px;">
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي السجلات</div>
            <div class="a2-stat-value">{{ $stats['total_rows'] ?? 0 }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">النشطة</div>
            <div class="a2-stat-value">{{ $stats['active_rows'] ?? 0 }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">بسعر ديبوزت</div>
            <div class="a2-stat-value">{{ $stats['deposit_rows'] ?? 0 }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">أنواع العناصر</div>
            <div class="a2-stat-value">{{ $stats['allowed_item_types_count'] ?? 0 }}</div>
        </div>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Business</th>
                        <th>Service</th>
                        <th>Item Type</th>
                        <th>Price</th>
                        <th>Discount</th>
                        <th>Final Service Price</th>
                        <th>Deposit Hold</th>
                        <th>Cash </th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->business->name ?? '—' }}</td>
                        <td>
                            {{ $row->service->name_ar ?? ($row->service->name_en ?? ($row->service->key ?? '—')) }}
                        </td>
                        <td dir="ltr">{{ $row->bookable_item_type ?: 'category' }}</td>
                        <td>{{ number_format((float)$row->price, 2) }} {{ $row->currency ?: 'EGP' }}</td>

                        <td>
                            @if((int)$row->discount_enabled === 1)
                                {{ (int)$row->discount_percent }}%
                                <div class="a2-muted">{{ number_format((float)$row->discount_amount, 2) }}</div>
                            @else
                                —
                            @endif
                        </td>

                        <td>{{ number_format((float)$row->final_service_price, 2) }}</td>

                        <td>
                            @if((int)$row->deposit_enabled === 1)
                                {{ (int)$row->deposit_percent }}%
                                <div class="a2-muted">{{ number_format((float)$row->deposit_hold_amount, 2) }}</div>
                            @else
                                —
                            @endif
                        </td>

                        <td>{{ number_format((float)$row->cash_due_on_execution, 2) }}</td>
                        <td>
                            <span class="a2-pill {{ (int)$row->is_active === 1 ? 'a2-pill-active' : 'a2-pill-inactive' }}">
                                {{ (int)$row->is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:8px;flex-wrap:nowrap;">
                                <a href="{{ route('admin.business_service_prices.edit', $row->id) }}" class="a2-btn a2-btn-ghost a2-btn-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.business_service_prices.destroy', $row->id) }}" onsubmit="return confirm('تأكيد حذف السجل؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="a2-empty-cell">لا توجد بيانات</td>
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