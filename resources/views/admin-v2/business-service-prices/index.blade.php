@extends('admin-v2.layouts.master')

@section('title','Business Service Prices')
@section('body_class','admin-v2 admin-v2-business-service-prices-index')

@section('content')
@php
    $qBusinessVal = (string)($qBusiness ?? '');
    $qServiceVal  = (string)($qService ?? '');
    $qChildVal    = (string)($qChild ?? '');
    $qItemTypeVal = (string)($qItemType ?? '');

    $businessIdVal = (int)($businessId ?? 0);
    $serviceIdVal  = (int)($serviceId ?? 0);
    $childIdVal    = (int)($childId ?? 0);
    $isActiveVal   = (string)($isActive ?? '');
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">أسعار خدمات البزنس</h1>
            <div class="a2-page-subtitle">
                إدارة سعر الخدمة الذي يحدده البزنس حسب القسم الفرعي والخدمة ونوع العنصر.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.business_service_prices.create') }}" class="a2-btn a2-btn-primary">
                + إضافة سعر
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء:</div>
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <div class="a2-section-title">ملاحظة مهمة</div>
        <div class="a2-section-subtitle">
            هذه الصفحة خاصة بسعر الخدمة الذي يحدده البزنس.
            أما رسوم المنصة على العميل أو البزنس فتتم من شاشة
            <span dir="ltr">Service Fees</span>
            ولا يتم خلطها مع السعر هنا.
        </div>
    </div>

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
                name="q_child"
                value="{{ $qChildVal }}"
                placeholder="بحث بالقسم الفرعي"
            >

            <input
                class="a2-input a2-filter-sm"
                name="q_item_type"
                value="{{ $qItemTypeVal }}"
                placeholder="نوع العنصر"
            >

            <select
                class="a2-select a2-filter-md js-bsp-business-filter"
                name="business_id"
                data-remote-url="{{ route('admin.business_service_prices.business-lookup') }}"
                data-placeholder="كل البزنسات"
            >
                <option value="0">كل البزنسات</option>
                @if($selectedBusiness ?? null)
                    <option value="{{ $selectedBusiness->id }}" selected>
                        {{ $selectedBusiness->name ?: ('#'.$selectedBusiness->id) }}
                    </option>
                @endif
            </select>

            <select class="a2-select a2-filter-md" name="service_id">
                <option value="0">كل الخدمات</option>
                @foreach(($services ?? []) as $s)
                    <option value="{{ $s->id }}" @selected($serviceIdVal === (int)$s->id)>
                        {{ $s->name_ar ?: ($s->name_en ?: $s->key) }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="child_id">
                <option value="0">كل الأقسام الفرعية</option>
                @foreach(($children ?? []) as $child)
                    <option value="{{ $child->id }}" @selected($childIdVal === (int)$child->id)>
                        {{ $child->name_ar ?: ($child->name_en ?: ('#'.$child->id)) }}
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
            <div class="a2-stat-label">البزنسات</div>
            <div class="a2-stat-value">{{ $stats['business_count'] ?? 0 }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">الأقسام الفرعية</div>
            <div class="a2-stat-value">{{ $stats['children_count'] ?? 0 }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">الخدمات</div>
            <div class="a2-stat-value">{{ $stats['services_count'] ?? 0 }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">أنواع العناصر</div>
            <div class="a2-stat-value">{{ $stats['item_types_count'] ?? 0 }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">متوسط السعر</div>
            <div class="a2-stat-value">
                {{ number_format((float)($stats['avg_price'] ?? 0), 2) }}
            </div>
        </div>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th style="min-width:70px;">ID</th>
                        <th style="min-width:180px;">Business</th>
                        <th style="min-width:170px;">Category Child</th>
                        <th style="min-width:150px;">Service</th>
                        <th style="min-width:110px;">Item Type</th>
                        <th style="min-width:120px;">Price</th>
                        <th style="min-width:120px;">Discount</th>
                        <th style="min-width:150px;">Final Service Price</th>
                        <th style="min-width:120px;">Cash</th>
                        <th style="min-width:100px;">Status</th>
                        <th style="min-width:160px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($rows as $row)
                    @php
                        $businessName = $row->business->name ?? '—';
                        $childName = $row->child
                            ? ($row->child->name_ar ?: ($row->child->name_en ?: ('#'.$row->child->id)))
                            : '—';

                        $serviceName = $row->service
                            ? ($row->service->name_ar ?: ($row->service->name_en ?: ($row->service->key ?? '—')))
                            : '—';

                        $currency = $row->currency ?: 'EGP';
                    @endphp

                    <tr>
                        <td>
                            <div class="a2-fw-900">{{ $row->id }}</div>
                        </td>

                        <td class="a2-text-right">
                            <div class="a2-fw-900">{{ $businessName }}</div>
                            <div class="a2-muted a2-mt-8">ID: {{ (int) $row->business_id }}</div>
                        </td>

                        <td class="a2-text-right">
                            <div>{{ $childName }}</div>
                            <div class="a2-muted a2-mt-8">ID: {{ (int) $row->child_id }}</div>
                        </td>

                        <td class="a2-text-right">
                            <div>{{ $serviceName }}</div>
                            <div class="a2-muted a2-mt-8" dir="ltr">
                                {{ $row->service->key ?? '' }}
                            </div>
                        </td>

                        <td dir="ltr">{{ $row->bookable_item_type ?: 'category' }}</td>

                        <td>
                            <div class="a2-fw-900">
                                {{ number_format((float)$row->price, 2) }}
                            </div>
                            <div class="a2-muted a2-mt-8">{{ $currency }}</div>
                        </td>

                        <td>
                            @if((int)$row->discount_enabled === 1)
                                <span class="a2-pill a2-pill-success">
                                    {{ (int)$row->discount_percent }}%
                                </span>
                                <div class="a2-muted a2-mt-8">
                                    {{ number_format((float)$row->discount_amount, 2) }}
                                </div>
                            @else
                                <span class="a2-muted">—</span>
                            @endif
                        </td>

                        <td>
                            <div class="a2-fw-900">
                                {{ number_format((float)$row->final_service_price, 2) }}
                            </div>
                            <div class="a2-muted a2-mt-8">{{ $currency }}</div>
                        </td>


                        <td>
                            {{ number_format((float)$row->cash_due_on_execution, 2) }}
                        </td>

                        <td>
                            <span class="a2-pill {{ (int)$row->is_active === 1 ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                {{ (int)$row->is_active === 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>

                        <td>
                            <div style="display:flex;gap:8px;flex-wrap:nowrap;">
                                <a
                                    href="{{ route('admin.business_service_prices.edit', $row->id) }}"
                                    class="a2-btn a2-btn-ghost a2-btn-sm"
                                >
                                    Edit
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('admin.business_service_prices.destroy', $row->id) }}"
                                    onsubmit="return confirm('تأكيد حذف السجل؟');"
                                >
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
                        <td colspan="11" class="a2-empty-cell">
                            لا توجد بيانات
                        </td>
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Business filter: search-as-you-type instead of embedding ~1,750 rows.
    const el = document.querySelector('.js-bsp-business-filter');
    if (!el || !window.TomSelect || el.tomselect) return;

    const remoteUrl = el.dataset.remoteUrl;

    new TomSelect(el, {
        valueField: 'value',
        labelField: 'text',
        searchField: 'text',
        create: false,
        maxOptions: 30,
        allowEmptyOption: true,
        placeholder: el.dataset.placeholder || 'ابحث',
        dropdownParent: 'body',
        shouldLoad: function (query) { return query.length >= 1; },
        load: function (query, callback) {
            const url = new URL(remoteUrl, window.location.origin);
            url.searchParams.set('q', query);
            fetch(url.toString(), {headers: {'Accept': 'application/json'}})
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const rows = (data && data.ok && Array.isArray(data.businesses)) ? data.businesses : [];
                    callback(rows.map(function (b) { return {value: String(b.id), text: b.name}; }));
                })
                .catch(function () { callback(); });
        },
    });
});
</script>
@endpush
@endsection