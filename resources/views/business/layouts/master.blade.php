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

    {{-- خمسُ شاشاتٍ تدفع CSS إلى هنا ولم يكن هنا شىء يستقبله.

         `@push('styles')` بلا `@stack` يُلقى بصمت: لا خطأ، ولا صفحةٌ مكسورة —
         فقط تنسيقٌ كُتب ولم يصل. شاشةُ «الإضافات والمميزات» وشاشتا الوحدات
         وصفحةُ العروض كلُّها كانت تُعرض بلا الذى كُتب لها، ولوحةُ الإدارة
         تستقبله منذ البداية. --}}
    @stack('styles')
</head>
<body class="admin-v2 @yield('body_class')">

    {{--
        قائمةٌ جانبية بدل شريطٍ من سبعةَ عشرَ زرًّا.

        كان كلُّ شاشةٍ فى المنصّة بنفس الوزن فى صفٍّ أفقىٍّ واحد، ولا شىء يقول
        أىُّ زرٍّ يخصّ أىَّ خدمة. الآن فرعٌ لكل خدمةٍ يبيعها صاحبُ المحل وتحته
        كلُّ ما يخصّها.

        وتستعمل هيكلَ لوحة الإدارة وأصنافَها كما هى — الملفُّ نفسه محمَّلٌ هنا
        منذ البداية — فلا ورقةَ أنماطٍ ثانية تفترق عن الأولى عند أول تعديل.
    --}}
    <div class="a2-shell">

        <div class="a2-overlay" id="a2Overlay" aria-hidden="true"></div>

        <aside class="a2-sidebar" id="a2Sidebar" aria-label="{{ __('قائمة النشاط') }}">
            <div class="a2-side-top">
                <a class="a2-brand" href="{{ route('business.dashboard') }}">
                    <span class="a2-brand-badge">BIM</span>
                    <span class="a2-brand-text">{{ __('لوحة النشاط') }}</span>
                </a>

                <button class="a2-burger" type="button" id="a2CloseSidebar" aria-label="{{ __('إغلاق القائمة') }}">
                    <svg class="a2-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M18.3 5.71 12 12.01l-6.29-6.3-1.42 1.42 6.3 6.29-6.3 6.29 1.42 1.42 6.29-6.3 6.29 6.3 1.42-1.42-6.3-6.29 6.3-6.29z"/></svg>
                </button>
            </div>

            <nav class="a2-nav" aria-label="{{ __('قائمة النشاط') }}">
                @include('business.layouts._partials.menu')
            </nav>
        </aside>

        <div class="a2-main">

            <header class="a2-topbar">
                <div class="a2-topbar-left">
                    <button class="a2-burger a2-burger--mobile" type="button" id="a2OpenSidebar" aria-label="{{ __('فتح القائمة') }}">
                        <svg class="a2-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M3 6h18v2H3V6Zm0 5h18v2H3v-2Zm0 5h18v2H3v-2Z"/></svg>
                    </button>

                    <div class="a2-topbar-title">@yield('title', __('لوحة النشاط التجاري'))</div>
                </div>

                <div class="a2-topbar-right" style="display:flex;align-items:center;gap:8px;">
                    @auth
                        <span class="a2-pill a2-pill-sub">{{ auth()->user()->name }}</span>
                    @endauth

                    <a href="{{ route('business.locale.switch', $__other) }}" class="a2-btn a2-btn-ghost a2-btn-sm">{{ $__locale === 'ar' ? 'EN' : 'ع' }}</a>

                    @auth
                        <form method="POST" action="{{ route('business.logout') }}">
                            @csrf
                            <button type="submit" class="a2-btn a2-btn-ghost a2-btn-sm">{{ __('خروج') }}</button>
                        </form>
                    @endauth
                </div>
            </header>

            <main class="a2-content" id="a2MainContent" tabindex="-1">
                @yield('content')
            </main>

        </div>
    </div>

    <script>
        (function () {
            var body = document.body;
            var open = document.getElementById('a2OpenSidebar');
            var close = document.getElementById('a2CloseSidebar');
            var overlay = document.getElementById('a2Overlay');

            var toggle = function (on) { body.classList.toggle('a2-sidebar-open', on); };

            open && open.addEventListener('click', function () { toggle(true); });
            close && close.addEventListener('click', function () { toggle(false); });
            overlay && overlay.addEventListener('click', function () { toggle(false); });
        })();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    @stack('scripts')
</body>
</html>
