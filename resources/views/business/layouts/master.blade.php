<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة النشاط التجاري')</title>
    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
</head>
<body class="admin-v2 @yield('body_class')">
    <div class="a2-shell" style="display:block;">
        <header class="a2-topbar" style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 20px;border-bottom:1px solid var(--a2-border-2);background:#fff;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span class="a2-fw-900">لوحة النشاط التجاري</span>
                @auth
                    <span class="a2-pill a2-pill-sub">{{ auth()->user()->name }}</span>
                @endauth
            </div>

            <nav style="display:flex;align-items:center;gap:8px;">
                <a href="{{ route('business.dashboard') }}" class="a2-btn a2-btn-ghost a2-btn-sm">الرئيسية</a>
                <a href="{{ route('business.offerings.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">عروضي</a>
                <a href="{{ route('business.bookable-items.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">وحداتي</a>
                <a href="{{ route('business.prices.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">أسعاري</a>
                <a href="{{ route('business.menu.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">المنيو</a>
                <a href="{{ route('business.products.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">منتجاتي</a>
                <a href="{{ route('business.bookings.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">حجوزاتي</a>
                <a href="{{ route('business.orders.index') }}" class="a2-btn a2-btn-ghost a2-btn-sm">الطلبات</a>
                @auth
                    <form method="POST" action="{{ route('business.logout') }}">
                        @csrf
                        <button type="submit" class="a2-btn a2-btn-ghost a2-btn-sm">خروج</button>
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
