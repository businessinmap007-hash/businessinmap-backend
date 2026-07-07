@extends('admin-v2.layouts.master')

@section('title', 'Platform Service Item Types')
@section('body_class', 'admin-v2 admin-v2-platform-service-item-types-index')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $serviceIdVal = (int) ($serviceId ?? 0);
    $groupIdVal = (int) ($groupId ?? 0);
    $activeVal = (string) ($active ?? '');

    $displayName = function ($item) {
        $ar = trim((string) ($item->name_ar ?? ''));
        $en = trim((string) ($item->name_en ?? ''));
        return $ar !== '' ? $ar : ($en !== '' ? $en : ('#' . ($item->id ?? '')));
    };
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">أنواع عناصر خدمات المنصة</h1>
            <div class="a2-page-subtitle">
                إدارة أنواع العناصر لكل خدمة مثل غرف الحجز، التوصيل، المنيو، وغيرها.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-services.index') }}" class="a2-btn a2-btn-ghost">
                خدمات المنصة
            </a>

            <a href="{{ route('admin.platform-service-item-groups.index', request()->only('service_id')) }}" class="a2-btn a2-btn-ghost">
                الفروع
            </a>

            <a href="{{ route('admin.platform-service-item-types.create', request()->only('service_id')) }}" class="a2-btn a2-btn-primary">
                إضافة نوع
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="a2-card a2-card--soft a2-mb-16">
        <form method="GET" action="{{ route('admin.platform-service-item-types.index') }}" class="a2-filterbar">
            <div class="a2-filter-search">
                <label class="a2-label">بحث</label>
                <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="key / عربي / English">
            </div>

            <div class="a2-filter-md">
                <label class="a2-label">الخدمة</label>
                <select class="a2-select" name="service_id">
                    <option value="0">كل الخدمات</option>
                    @foreach(($services ?? []) as $service)
                        <option value="{{ $service->id }}" @selected($serviceIdVal === (int) $service->id)>
                            {{ $displayName($service) }} — {{ $service->key }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-md">
                <label class="a2-label">الفرع</label>
                <select class="a2-select" name="group_id">
                    <option value="0">كل الفروع</option>
                    @foreach(($groups ?? []) as $group)
                        <option value="{{ $group->id }}" @selected($groupIdVal === (int) $group->id)>
                            {{ $displayName($group) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">الحالة</label>
                <select class="a2-select" name="active">
                    <option value="">الكل</option>
                    <option value="1" @selected($activeVal === '1')>مفعل</option>
                    <option value="0" @selected($activeVal === '0')>غير مفعل</option>
                </select>
            </div>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تصفية</button>
                <a href="{{ route('admin.platform-service-item-types.index') }}" class="a2-btn a2-btn-ghost">إعادة</a>
            </div>
        </form>
    </div>

    <div class="a2-card">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>النوع</th>
                        <th>الخدمة</th>
                        <th>الفرع</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>الحالة</th>
                        <th>الترتيب</th>
                        <th class="a2-text-right">إجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse(($rows ?? []) as $row)
                        <tr>
                            <td>{{ $row->id }}</td>

                            <td>
                                <div class="a2-fw-900">{{ $displayName($row) }}</div>
                                <div class="a2-muted" dir="ltr">{{ $row->name_en ?: '—' }}</div>
                            </td>

                            <td>
                                @if($row->service)
                                    <div class="a2-fw-800">{{ $displayName($row->service) }}</div>
                                    <div class="a2-muted" dir="ltr">{{ $row->service->key }}</div>
                                @else
                                    —
                                @endif
                            </td>

                            <td>
                                @forelse($row->groups as $g)
                                    <span class="a2-pill a2-pill-sub">{{ $displayName($g) }}</span>
                                @empty
                                    <span class="a2-muted">بدون فرع</span>
                                @endforelse
                            </td>

                            <td dir="ltr">{{ $row->key }}</td>

                            <td>
                                @if($row->is_default)
                                    <span class="a2-pill a2-pill-success">Default</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">—</span>
                                @endif
                            </td>

                            <td>
                                @if($row->is_active)
                                    <span class="a2-pill a2-pill-success">Active</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">Inactive</span>
                                @endif
                            </td>

                            <td>{{ (int) $row->sort_order }}</td>

                            <td class="a2-text-right">
                                <div class="a2-inline-actions">
                                    <a href="{{ route('admin.platform-service-item-types.edit', $row) }}" class="a2-btn a2-btn-sm a2-btn-ghost">
                                        تعديل
                                    </a>

                                    <form method="POST" action="{{ route('admin.platform-service-item-types.destroy', $row) }}" onsubmit="return confirm('حذف هذا النوع؟');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="a2-empty">لا توجد أنواع عناصر حتى الآن.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($rows, 'links'))
            <div class="a2-pagination">
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection