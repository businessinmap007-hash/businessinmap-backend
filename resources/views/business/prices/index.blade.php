@extends('business.layouts.master')

@section('title', 'أسعاري')

@section('content')
@php
    $displayName = function ($s) {
        return $s ? ($s->name_ar ?: ($s->name_en ?: $s->key)) : '—';
    };
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">أسعاري</h1>
        <div class="a2-page-subtitle">سعر كل نوع تقدّمه — يخصّك أنت فقط.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.prices.create') }}" class="a2-btn a2-btn-primary">إضافة سعر</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($childId <= 0)
    <div class="a2-alert a2-alert-warning">حسابك غير مرتبط بقسم فرعي بعد.</div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <form method="GET" action="{{ route('business.prices.index') }}" class="a2-filterbar">
        <div class="a2-filter-md">
            <label class="a2-label">الخدمة</label>
            <select class="a2-select" name="service_id">
                <option value="0">كل الخدمات</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected((int) $serviceId === (int) $service->id)>
                        {{ $displayName($service) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">تصفية</button>
            <a href="{{ route('business.prices.index') }}" class="a2-btn a2-btn-ghost">إعادة</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>الخدمة</th>
                    <th>النوع</th>
                    <th>السعر</th>
                    <th>الخصم</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $displayName($row->service) }}</td>
                        <td dir="ltr">{{ $row->bookable_item_type }}</td>
                        <td class="a2-fw-900">
                            {{ number_format((float) $row->price, 2) }} {{ $row->currency ?: 'EGP' }}
                            @php
                                $modeLabels = ['free' => 'مجانية', 'reservation_fee' => 'رسوم حجز', 'minimum_charge' => 'حد أدنى'];
                                $mode = (string) ($row->charge_mode ?? 'standard');
                            @endphp
                            @if(isset($modeLabels[$mode]))
                                <div class="a2-muted a2-mt-8">
                                    <span class="a2-pill a2-pill-sub">{{ $modeLabels[$mode] }}</span>
                                    @if((float) $row->charge_amount > 0)
                                        {{ number_format((float) $row->charge_amount, 2) }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            @if((int) $row->discount_enabled === 1)
                                <span class="a2-pill a2-pill-success">{{ (int) $row->discount_percent }}%</span>
                            @else
                                <span class="a2-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row->is_active)
                                <span class="a2-pill a2-pill-success">مفعّل</span>
                            @else
                                <span class="a2-pill a2-pill-gray">موقوف</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions">
                                <a href="{{ route('business.prices.edit', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">تعديل</a>
                                <form method="POST" action="{{ route('business.prices.destroy', $row->id) }}" onsubmit="return confirm('حذف هذا السعر؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="a2-empty">لا توجد أسعار بعد. أضف سعرًا لكل نوع تقدّمه.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($rows, 'links'))
        <div class="a2-pagination">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
