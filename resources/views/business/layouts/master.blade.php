<!DOCTYPE html>
@php $__locale = app()->getLocale(); $__other = $__locale === 'ar' ? 'en' : 'ar'; @endphp
<html lang="{{ $__locale }}" dir="{{ $__locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('لوحة النشاط التجاري'))</title>
    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
</head>
<body class="admin-v2 @yield('body_class')">
    <div class="a2-shell" style="display:block;">
        <header class="a2-topbar" style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 20px;border-bottom:1px solid var(--a2-border-2);background:#fff;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="a2-fw-900">{{ __('لوحة النشاط التجاري') }}</span>
                @auth
                    <span class="a2-pill a2-pill-sub">{{ auth()->user()->name }}</span>
                @endauth
            </div>

            {{-- «فتحت حساب تسويق واعطانى منيو وحجوزات والخطط التدريبية» —
                 المالك 2026-08-19. كان الشريط سبعةَ عشرَ رابطًا بلا شرطٍ
                 واحد: لم يسأل يومًا عمّا يبيعه صاحبُ المحل. البيانات كانت
                 صحيحة — «تسويق» #177 لا يفعّل إلا الحجز — والشاشة وحدها هى
                 التى لم تقرأها. --}}
            <nav style="display:flex;align-items:center;gap:8px;">
                <a href="{{ route('business.dashboard') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('الرئيسية') }}</a>
                <a href="{{ route('business.offerings.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('عروضي') }}</a>
                @if(\App\Support\BusinessPanelNav::shows('bookable-items'))
                <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('وحداتي') }}</a>
                @endif
                <a href="{{ route('business.prices.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('أسعاري') }}</a>
                @if(\App\Support\BusinessPanelNav::shows('menu'))
                <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __(\App\Support\BusinessPanelNav::catalogLabel()) }}</a>
                @endif
                @if(\App\Support\BusinessPanelNav::shows('tables'))
                <a href="{{ route('business.tables.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('الطاولات') }}</a>
                @endif
                @if(\App\Support\BusinessPanelNav::shows('table-calls'))
                <a href="{{ route('business.table-calls.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('نداءات الطاولات') }}</a>
                @endif
                <a href="{{ route('business.share-store') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('شارك متجرك') }}</a>
                @if(\App\Support\BusinessPanelNav::shows('products'))
                <a href="{{ route('business.products.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('منتجاتي') }}</a>
                @endif
                @if(\App\Support\BusinessPanelNav::shows('schedules'))
                <a href="{{ route('business.schedules.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('خطوط التشغيل') }}</a>
                @endif
                @if(\App\Support\BusinessPanelNav::shows('training-plans'))
                <a href="{{ route('business.training-plans.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('الخطط التدريبية') }}</a>
                @endif
                @if(\App\Support\BusinessPanelNav::shows('bookings'))
                <a href="{{ route('business.bookings.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('حجوزاتي') }}</a>
                @endif
                @if(\App\Support\BusinessPanelNav::shows('booking-settings'))
                <a href="{{ route('business.booking-settings.edit') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('إعدادات الحجز') }}</a>
                @endif
                @if(\App\Support\BusinessPanelNav::shows('orders'))
                <a href="{{ route('business.orders.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('الطلبات') }}</a>
                @endif
                <a href="{{ route('business.staff.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('الموظفون') }}</a>
                <a href="{{ route('business.locale.switch', $__other) }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ $__locale === 'ar' ? 'EN' : 'ع' }}</a>
                @auth
                    <form method="POST" action="{{ route('business.logout') }}">
                        @csrf
                        <button type="submit" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('خروج') }}</button>
                    </form>
                @endauth
            </nav>
        </header>

        <main style="padding:20px;max-width:1100px;margin:0 auto;">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    @stack('scripts')
</body>
</html>
