@extends('admin-v2.layouts.master')

@section('title', 'عروض رسوم المنصة')
@section('body_class', 'admin-v2 admin-v2-platform-service-fee-promotions')

@section('content')
@php
    $f = $filters ?? [];

    $fmtDate = function ($date) {
        return $date ? $date->format('Y-m-d H:i') : '—';
    };

    $nameOfService = function ($service) {
        if (!$service) return 'كل الخدمات';
        return $service->name_ar ?? $service->name_en ?? $service->name ?? $service->key ?? ('#' . $service->id);
    };

    $nameOfChild = function ($child) {
        if (!$child) return '—';
        return $child->name_ar ?? $child->name_en ?? $child->name ?? ('#' . $child->id);
    };

    $now = now();
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">عروض رسوم المنصة</h1>
            <div class="a2-page-subtitle">
                إدارة العروض المؤقتة لتعديل أو إيقاف رسوم المنصة مع الاحتفاظ بالقيم الأصلية.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-fee-promotions.create') }}" class="a2-btn a2-btn-primary">
                إضافة عرض جديد
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="a2-card a2-card--section">
        <form method="GET" action="{{ route('admin.platform-service-fee-promotions.index') }}" class="a2-filterbar">
            <input
                type="text"
                name="q"
                class="a2-input a2-filter-search"
                value="{{ $f['q'] ?? '' }}"
                placeholder="بحث باسم العرض أو الملاحظات"
            >

            <select name="service_id" class="a2-select a2-filter-md">
                <option value="0">كل الخدمات</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected((int)($f['service_id'] ?? 0) === (int)$service->id)>
                        {{ $service->name_ar ?? $service->name_en ?? $service->name ?? $service->key ?? ('#' . $service->id) }}
                    </option>
                @endforeach
            </select>

            <select name="scope_type" class="a2-select a2-filter-md">
                <option value="">كل النطاقات</option>
                @foreach($scopeTypes as $key => $label)
                    <option value="{{ $key }}" @selected(($f['scope_type'] ?? '') === $key)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <select name="target_party" class="a2-select a2-filter-md">
                <option value="">كل الأطراف</option>
                @foreach($targetParties as $key => $label)
                    <option value="{{ $key }}" @selected(($f['target_party'] ?? '') === $key)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <select name="discount_type" class="a2-select a2-filter-md">
                <option value="">كل أنواع العروض</option>
                @foreach($discountTypes as $key => $label)
                    <option value="{{ $key }}" @selected(($f['discount_type'] ?? '') === $key)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            <select name="active" class="a2-select a2-filter-sm">
                <option value="">كل الحالات</option>
                <option value="1" @selected(($f['active'] ?? '') === '1')>مفعل</option>
                <option value="0" @selected(($f['active'] ?? '') === '0')>موقوف</option>
            </select>

            <select name="running" class="a2-select a2-filter-sm">
                <option value="">كل الفترات</option>
                <option value="1" @selected(($f['running'] ?? '') === '1')>فعال الآن</option>
            </select>

            <select name="per_page" class="a2-select a2-filter-sm">
                @foreach([10,25,50,100] as $pp)
                    <option value="{{ $pp }}" @selected((int)($f['per_page'] ?? 25) === $pp)>
                        {{ $pp }}
                    </option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">
                    فلترة
                </button>

                <a href="{{ route('admin.platform-service-fee-promotions.index') }}" class="a2-btn a2-btn-ghost">
                    تصفية
                </a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--section">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العرض</th>
                        <th>النطاق</th>
                        <th>الخدمة</th>
                        <th>القسم الفرعي</th>
                        <th>الطرف</th>
                        <th>نوع العرض</th>
                        <th>القيمة</th>
                        <th>الفترة</th>
                        <th>الأولوية</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($promotions as $promotion)
                        @php
                            $isRunning =
                                (bool) $promotion->is_active
                                && (!$promotion->starts_at || $promotion->starts_at <= $now)
                                && (!$promotion->ends_at || $promotion->ends_at >= $now);
                        @endphp

                        <tr>
                            <td>{{ $promotion->id }}</td>

                            <td class="a2-text-right">
                                <strong>{{ $promotion->name }}</strong>
                                @if($promotion->notes)
                                    <div class="a2-muted a2-mt-8">
                                        {{ \Illuminate\Support\Str::limit($promotion->notes, 80) }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                {{ $scopeTypes[$promotion->scope_type] ?? $promotion->scope_type }}
                            </td>

                            <td>
                                {{ $nameOfService($promotion->service) }}
                            </td>

                            <td>
                                {{ $nameOfChild($promotion->child) }}
                            </td>

                            <td>
                                {{ $targetParties[$promotion->target_party] ?? $promotion->target_party }}
                            </td>

                            <td>
                                {{ $discountTypes[$promotion->discount_type] ?? $promotion->discount_type }}
                            </td>

                            <td>
                                @if($promotion->discount_type === 'waive')
                                    —
                                @else
                                    {{ number_format((float) $promotion->discount_value, 2) }}
                                @endif
                            </td>

                            <td>
                                <div class="a2-stack-sm">
                                    <div>من: {{ $fmtDate($promotion->starts_at) }}</div>
                                    <div>إلى: {{ $fmtDate($promotion->ends_at) }}</div>
                                </div>
                            </td>

                            <td>{{ $promotion->priority }}</td>

                            <td>
                                <div class="a2-stack-sm">
                                    @if($promotion->is_active)
                                        <span class="a2-pill a2-pill-success">مفعل</span>
                                    @else
                                        <span class="a2-pill a2-pill-danger">موقوف</span>
                                    @endif

                                    @if($isRunning)
                                        <span class="a2-pill a2-pill-warning">فعال الآن</span>
                                    @else
                                        <span class="a2-pill a2-pill-gray">غير فعال الآن</span>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <div class="a2-table-actions">
                                    <a href="{{ route('admin.platform-service-fee-promotions.edit', $promotion) }}" class="a2-btn a2-btn-sm a2-btn-ghost">
                                        تعديل
                                    </a>

                                    <form method="POST" action="{{ route('admin.platform-service-fee-promotions.toggle', $promotion) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="a2-btn a2-btn-sm a2-btn-dark">
                                            {{ $promotion->is_active ? 'إيقاف' : 'تفعيل' }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.platform-service-fee-promotions.destroy', $promotion) }}" onsubmit="return confirm('هل تريد حذف هذا العرض؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="a2-btn a2-btn-sm a2-btn-danger">
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="a2-empty-cell">
                                لا توجد عروض رسوم منصة حتى الآن.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($promotions->hasPages())
            <div class="a2-mt-16">
                {{ $promotions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection