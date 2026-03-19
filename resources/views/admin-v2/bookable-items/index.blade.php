@extends('admin-v2.layouts.master')

@section('title', 'Bookable Items')
@section('body_class', 'admin-v2-bookable-items-index')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $serviceIdVal = (int) ($serviceId ?? 0);
    $businessIdVal = (int) ($businessId ?? 0);
    $isActiveVal = (string) ($isActive ?? '');
    $itemTypeVal = (string) ($itemType ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">العناصر القابلة للحجز</h1>
            <div class="a2-page-subtitle">
                إدارة الغرف والملاعب والطاولات والوحدات والعناصر القابلة للحجز
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookable-items.create') }}" class="a2-btn a2-btn-primary">
                + إضافة عنصر
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
        <form method="GET" action="{{ route('admin.bookable-items.index') }}" class="a2-filterbar">
            <input
                class="a2-input a2-filter-search"
                type="text"
                name="q"
                value="{{ $qVal }}"
                placeholder="بحث: title / code / item type"
            >

            <input
                class="a2-input a2-filter-sm"
                type="text"
                name="item_type"
                value="{{ $itemTypeVal }}"
                placeholder="نوع العنصر"
            >

            <select class="a2-select a2-filter-md" name="business_id">
                <option value="0">كل البزنسات</option>
                @foreach(($businesses ?? []) as $b)
                    <option value="{{ $b->id }}" @selected($businessIdVal === (int) $b->id)>
                        {{ $b->name ?: ('#' . $b->id) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="service_id">
                <option value="0">كل الخدمات</option>
                @foreach(($services ?? []) as $s)
                    <option value="{{ $s->id }}" @selected($serviceIdVal === (int) $s->id)>
                        {{ $s->name_ar ?: ($s->name_en ?: $s->key) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="is_active">
                <option value="" @selected($isActiveVal === '')>الكل</option>
                <option value="1" @selected($isActiveVal === '1')>Active</option>
                <option value="0" @selected($isActiveVal === '0')>Inactive</option>
            </select>

            <div class="a2-filter-actions">
                <button type="submit" class="a2-btn a2-btn-primary">تطبيق</button>
                <a href="{{ route('admin.bookable-items.index') }}" class="a2-btn a2-btn-ghost">تفريغ</a>
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
                        <th>Service</th>
                        <th>Item Type</th>
                        <th>Title</th>
                        <th>Code</th>
                        <th>Price</th>
                        <th>Capacity</th>
                        <th>Qty</th>
                        <th>Deposit</th>
                        <th>Status</th>
                        <th style="width:280px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>

                        <td>
                            {{ $row->business->name ?? '—' }}
                        </td>

                        <td>
                            {{ $row->service->name_ar ?? ($row->service->name_en ?? ($row->service->key ?? '—')) }}
                        </td>

                        <td dir="ltr">
                            {{ $row->item_type ?: '—' }}
                        </td>

                        <td class="a2-fw-700">
                            {{ $row->title ?: '—' }}
                        </td>

                        <td dir="ltr">
                            {{ $row->code ?: '—' }}
                        </td>

                        <td>
                            {{ number_format((float) ($row->price ?? 0), 2) }}
                        </td>

                        <td>
                            {{ $row->capacity ?: '—' }}
                        </td>

                        <td>
                            {{ $row->quantity ?: '—' }}
                        </td>

                        <td>
                            @if((int) ($row->deposit_enabled ?? 0) === 1)
                                {{ (int) ($row->deposit_percent ?? 0) }}%
                            @else
                                —
                            @endif
                        </td>

                        <td>
                            <span class="a2-pill {{ (int) ($row->is_active ?? 0) === 1 ? 'a2-pill-active' : 'a2-pill-inactive' }}">
                                {{ (int) ($row->is_active ?? 0) === 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <td>
                            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a href="{{ route('admin.bookable-items.edit', $row) }}" class="a2-btn a2-btn-ghost a2-btn-sm">
                                    Edit
                                </a>

                                <a href="{{ route('admin.bookable-items.calendar', $row) }}" class="a2-btn a2-btn-ghost a2-btn-sm">
                                    Calendar
                                </a>

                                <a href="{{ route('admin.bookable-items.blocked-slots.index', $row) }}" class="a2-btn a2-btn-ghost a2-btn-sm">
                                    Blocked Slots
                                </a>

                                <a href="{{ route('admin.bookable-items.price-rules.index', $row) }}" class="a2-btn a2-btn-ghost a2-btn-sm">
                                    Price Rules
                                </a>

                                <form method="POST"
                                      action="{{ route('admin.bookable-items.destroy', $row) }}"
                                      onsubmit="return confirm('تأكيد حذف العنصر؟');"
                                      style="margin:0;">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="a2-btn a2-btn-danger a2-btn-sm">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="a2-empty-cell">لا توجد عناصر قابلة للحجز</td>
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