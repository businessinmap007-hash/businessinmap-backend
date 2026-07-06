@extends('business.layouts.master')

@section('title', 'حجز #' . $booking->id)

@section('content')
@php
    $serviceName = $booking->service ? ($booking->service->name_ar ?: ($booking->service->name_en ?: $booking->service->key)) : '—';
    $unitCode = $booking->bookable?->code ?: data_get($booking->bookableMeta(), 'code', '—');
    $unitType = $booking->bookable?->item_type ?: data_get($booking->bookableMeta(), 'item_type', '');
    $cur = $invoice['currency'] ?? 'EGP';
    $modeLabels = ['free' => 'مجانية', 'reservation_fee' => 'رسوم حجز', 'minimum_charge' => 'حد أدنى', 'standard' => 'سعر عادي'];
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">حجز #{{ $booking->id }}</h1>
        <div class="a2-page-subtitle">{{ $serviceName }} — {{ $unitCode }} <span dir="ltr">({{ $unitType }})</span></div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.bookings.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
@endif

<div class="a2-form-grid">
    <div>
        <div class="a2-card a2-card--section">
            <div class="a2-card-head"><div><div class="a2-card-title">أصناف الأكل (dine-in)</div>
                <div class="a2-card-sub">أضف من منيوك ما طلبه العميل على الطاولة.</div></div></div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr><th>الصنف</th><th>السعر</th><th>الكمية</th><th>الإجمالي</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($lines as $line)
                            <tr>
                                <td>{{ $line->menuItem?->name_ar ?: ('#' . $line->menu_id) }}</td>
                                <td>{{ number_format((float) $line->price, 2) }}</td>
                                <td>{{ (int) $line->qty }}</td>
                                <td class="a2-fw-900">{{ number_format((float) $line->total_price, 2) }}</td>
                                <td class="a2-text-right">
                                    <form method="POST" action="{{ route('business.bookings.food.remove', $booking->id) }}" onsubmit="return confirm('حذف الصنف؟');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="item_id" value="{{ $line->id }}">
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="a2-empty">لا يوجد أكل مضاف لهذا الحجز.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($menuItems->isEmpty())
                <div class="a2-alert a2-alert-warning a2-mt-16">لا توجد أصناف في منيوك. أضف أصنافًا من شاشة المنيو أولًا.</div>
            @else
                <form method="POST" action="{{ route('business.bookings.food.add', $booking->id) }}" class="a2-filterbar" style="margin-top:16px;">
                    @csrf
                    <div class="a2-filter-md">
                        <label class="a2-label" for="menu_id">الصنف</label>
                        <select class="a2-select" id="menu_id" name="menu_id" required>
                            @foreach($menuItems as $mi)
                                <option value="{{ $mi->id }}">{{ $mi->name_ar ?: $mi->name_en }} — {{ number_format((float) $mi->base_price, 2) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="a2-filter-sm">
                        <label class="a2-label" for="qty">الكمية</label>
                        <input class="a2-input" id="qty" name="qty" type="number" min="1" value="1" required>
                    </div>
                    <div class="a2-filter-actions">
                        <button class="a2-btn a2-btn-primary" type="submit">إضافة</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div>
        <div class="a2-card a2-card--section">
            <div class="a2-card-head"><div><div class="a2-card-title">الفاتورة الموحّدة</div></div></div>

            <table class="a2-table">
                <tbody>
                    <tr>
                        <td>احتساب الوحدة</td>
                        <td class="a2-text-right">
                            <span class="a2-pill a2-pill-sub">{{ $modeLabels[$invoice['charge_mode']] ?? $invoice['charge_mode'] }}</span>
                        </td>
                    </tr>
                    <tr><td>رسوم الوحدة / الطاولة</td><td class="a2-text-right a2-fw-900">{{ number_format((float) $invoice['table_charge'], 2) }} {{ $cur }}</td></tr>
                    <tr><td>إجمالي الأكل</td><td class="a2-text-right a2-fw-900">{{ number_format((float) $invoice['food_total'], 2) }} {{ $cur }}</td></tr>
                    <tr><td class="a2-fw-900">الإجمالي</td><td class="a2-text-right a2-fw-900">{{ number_format((float) $invoice['total'], 2) }} {{ $cur }}</td></tr>
                    <tr><td>التأمين (ضمان)</td><td class="a2-text-right">{{ number_format((float) $invoice['deposit_amount'], 2) }} {{ $cur }}</td></tr>
                </tbody>
            </table>

            <div class="a2-hint a2-mt-8">
                الإجمالي يتحدّث تلقائيًا مع كل إضافة/حذف. التأمين ضمان يُخصم عند التنفيذ.
            </div>
        </div>
    </div>
</div>
@endsection
