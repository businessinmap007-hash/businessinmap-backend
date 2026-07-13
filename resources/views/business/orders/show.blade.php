@extends('business.layouts.master')

@section('title', 'طلب #' . $order->id)

@section('content')
@php
    $typeLabels = ['delivery' => 'توصيل', 'pickup' => 'استلام'];
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">طلب #{{ $order->id }}</h1>
        <div class="a2-page-subtitle">
            <span class="a2-pill a2-pill-sub">{{ $typeLabels[$order->fulfillment_type] ?? $order->fulfillment_type }}</span>
            @if($order->fulfillment_type === 'delivery' && $order->address)
                — {{ $order->address }}
            @endif
        </div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.orders.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
@endif

@if($handoverToken)
    <div class="a2-card a2-card--section" style="text-align:center;">
        <div class="a2-card-title">تأكيد التسليم</div>
        <div class="a2-card-sub" style="margin-bottom:12px;">اعرض هذا الرمز للعميل ليمسحه ويؤكد استلام الطلب.</div>
        <img src="{{ route('handover.qr', $handoverToken, false) }}" alt="رمز تأكيد التسليم" width="200" height="200"
             style="border:1px solid var(--a2-line,#e6e9ef);border-radius:12px;background:#fff;">
    </div>
@elseif($order->handover_confirmed_at)
    <div class="a2-alert a2-alert-success">تم تأكيد تسليم هذا الطلب في {{ $order->handover_confirmed_at->format('Y-m-d H:i') }}.</div>
@endif

<div class="a2-form-grid">
    <div>
        <div class="a2-card a2-card--section">
            <div class="a2-card-head"><div><div class="a2-card-title">أصناف الطلب</div></div></div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead><tr><th>الصنف</th><th>السعر</th><th>الكمية</th><th>الإجمالي</th><th></th></tr></thead>
                    <tbody>
                        @forelse($lines as $line)
                            <tr>
                                <td>{{ $line->display_name }}</td>
                                <td>{{ number_format((float) $line->price, 2) }}</td>
                                <td>{{ (int) $line->qty }}</td>
                                <td class="a2-fw-900">{{ number_format((float) $line->total_price, 2) }}</td>
                                <td class="a2-text-right">
                                    <form method="POST" action="{{ route('business.orders.food.remove', $order->id) }}" onsubmit="return confirm('حذف الصنف؟');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="item_id" value="{{ $line->id }}">
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="a2-empty">لا توجد أصناف بعد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($menuItems->isEmpty())
                <div class="a2-alert a2-alert-warning a2-mt-16">لا توجد أصناف في منيوك. أضف من شاشة المنيو أولًا.</div>
            @else
                <form method="POST" action="{{ route('business.orders.food.add', $order->id) }}" class="a2-filterbar" style="margin-top:16px;">
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
                        <button class="a2-btn a2-btn-primary" type="submit">إضافة صنف</button>
                    </div>
                </form>
            @endif

            @if($listings->isNotEmpty())
                <form method="POST" action="{{ route('business.orders.product.add', $order->id) }}" class="a2-filterbar" style="margin-top:12px;">
                    @csrf
                    <div class="a2-filter-md">
                        <label class="a2-label" for="listing_id">منتج تجزئة</label>
                        <select class="a2-select" id="listing_id" name="listing_id" required>
                            @foreach($listings as $lst)
                                <option value="{{ $lst->id }}">{{ $lst->product_name }} — {{ number_format((float) $lst->price, 2) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="a2-filter-sm">
                        <label class="a2-label" for="product_qty">الكمية</label>
                        <input class="a2-input" id="product_qty" name="qty" type="number" min="1" value="1" required>
                    </div>
                    <div class="a2-filter-actions">
                        <button class="a2-btn a2-btn-primary" type="submit">إضافة منتج</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div>
        <div class="a2-card a2-card--section">
            <div class="a2-card-head"><div><div class="a2-card-title">الفاتورة</div></div></div>
            <table class="a2-table">
                <tbody>
                    <tr><td>إجمالي الأصناف</td><td class="a2-text-right a2-fw-900">{{ number_format((float) $order->total, 2) }}</td></tr>
                    @if($order->fulfillment_type === 'delivery')
                        <tr><td>رسوم التوصيل</td><td class="a2-text-right">{{ number_format((float) $order->delivery_fee, 2) }}</td></tr>
                    @endif
                    <tr><td class="a2-fw-900">الإجمالي النهائي</td><td class="a2-text-right a2-fw-900">{{ number_format((float) $order->final_total, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
