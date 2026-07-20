@extends('admin-v2.layouts.master')

@section('title', 'Platform Service Item Groups')
@section('body_class', 'admin-v2 admin-v2-platform-service-item-groups-index')

@section('content')
@php
    $qVal = (string) ($q ?? '');
    $serviceIdVal = (int) ($serviceId ?? 0);
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
            <h1 class="a2-page-title">{{ __('فروع أنواع العناصر') }}</h1>
            <div class="a2-page-subtitle">
                {{ __('تقسيم أنواع العناصر داخل كل خدمة إلى فروع (مثال: فنادق / عيادات / ملاعب داخل الحجز).') }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-item-types.index', request()->only('service_id')) }}" class="a2-btn a2-btn-ghost">
                {{ __('أنواع العناصر') }}
            </a>

            <a href="{{ route('admin.platform-service-item-groups.create', request()->only('service_id')) }}" class="a2-btn a2-btn-primary">
                {{ __('إضافة فرع') }}
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
        <form method="GET" action="{{ route('admin.platform-service-item-groups.index') }}" class="a2-filterbar">
            <div class="a2-filter-search">
                <label class="a2-label">{{ __('بحث') }}</label>
                <input class="a2-input" name="q" value="{{ $qVal }}" placeholder="{{ __('key / عربي / English') }}">
            </div>

            <div class="a2-filter-md">
                <label class="a2-label">{{ __('الخدمة') }}</label>
                <select class="a2-select" name="service_id">
                    <option value="0">{{ __('كل الخدمات') }}</option>
                    @foreach(($services ?? []) as $service)
                        <option value="{{ $service->id }}" @selected($serviceIdVal === (int) $service->id)>
                            {{ $displayName($service) }} — {{ $service->key }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">{{ __('الحالة') }}</label>
                <select class="a2-select" name="active">
                    <option value="">{{ __('الكل') }}</option>
                    <option value="1" @selected($activeVal === '1')>{{ __('مفعل') }}</option>
                    <option value="0" @selected($activeVal === '0')>{{ __('غير مفعل') }}</option>
                </select>
            </div>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تصفية') }}</button>
                <a href="{{ route('admin.platform-service-item-groups.index') }}" class="a2-btn a2-btn-ghost">{{ __('إعادة') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('الفرع') }}</th>
                        <th>{{ __('الخدمة') }}</th>
                        <th>Key</th>
                        <th>{{ __('الأنواع') }}</th>
                        <th>{{ __('الحالة') }}</th>
                        <th>{{ __('الترتيب') }}</th>
                        <th class="a2-text-right">{{ __('إجراءات') }}</th>
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

                            <td dir="ltr">{{ $row->key }}</td>

                            <td>
                                <a href="{{ route('admin.platform-service-item-types.index', ['group_id' => $row->id, 'service_id' => $row->platform_service_id]) }}" class="a2-pill a2-pill-sub">
                                    {{ (int) ($row->item_types_count ?? 0) }} {{ __('نوع') }}
                                </a>
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
                                    <a href="{{ route('admin.platform-service-item-groups.edit', $row) }}" class="a2-btn a2-btn-sm a2-btn-ghost">
                                        {{ __('تعديل') }}
                                    </a>

                                    <form method="POST" action="{{ route('admin.platform-service-item-groups.toggle-active', $row) }}">
                                        @csrf
                                        <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">
                                            {{ $row->is_active ? 'تعطيل' : 'تفعيل' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.platform-service-item-groups.destroy', $row) }}" onsubmit="return confirm('حذف هذا الفرع؟ ستصبح أنواعه بدون فرع.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">
                                            {{ __('حذف') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="a2-empty">{{ __('لا توجد فروع حتى الآن.') }}</td>
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
