<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin V2')</title>

    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
    {{-- Global admin hot-fixes (selects/dropdowns/form grids). Loaded after
         tom-select so its .ts-* overrides win. The data-admin-fixes marker tells
         booking-protection-preview.js not to inject a duplicate copy. --}}
    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin-fixes.css') }}" data-admin-fixes="1">

    @yield('head')
    @stack('styles')
</head>

<body class="admin-v2 @yield('body_class')">

    <a href="#a2MainContent" class="a2-skip-link">تخطي إلى المحتوى الرئيسي</a>

    <div class="a2-shell">

        {{-- Mobile Overlay --}}
        <div class="a2-overlay" id="a2Overlay" aria-hidden="true"></div>

        {{-- Sidebar --}}
        <aside class="a2-sidebar" id="a2Sidebar" aria-label="Admin navigation">
            <div class="a2-side-top">
                <a class="a2-brand" href="{{ route('admin.dashboard') }}">
                    <span class="a2-brand-badge">BIM</span>
                    <span class="a2-brand-text">Admin V2</span>
                </a>

                <button
                    class="a2-burger"
                    type="button"
                    id="a2CloseSidebar"
                    aria-label="Close menu"
                >
                    <svg class="a2-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M18.3 5.71 12 12.01l-6.29-6.3-1.42 1.42 6.3 6.29-6.3 6.29 1.42 1.42 6.29-6.3 6.29 6.3 1.42-1.42-6.3-6.29 6.3-6.29z"/></svg>
                </button>
            </div>

            <nav class="a2-nav" aria-label="Admin menu">
                @include('admin-v2.layouts._partials.menu')
            </nav>
        </aside>

        {{-- Main --}}
        <div class="a2-main">

            {{-- Topbar --}}
            <header class="a2-topbar">
                <div class="a2-topbar-left">
                    <button
                        class="a2-burger a2-burger--mobile"
                        type="button"
                        id="a2OpenSidebar"
                        aria-label="Open menu"
                    >
                        <svg class="a2-ico" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M3 6h18v2H3V6Zm0 5h18v2H3v-2Zm0 5h18v2H3v-2Z"/></svg>
                    </button>

                    <div class="a2-topbar-title">
                        @yield('topbar_title', 'BIM Admin V2')
                    </div>
                </div>

                <div class="a2-topbar-right">
                    @includeIf('admin-v2.layouts._partials.userbar')
                </div>
            </header>

            {{-- Content --}}
            <main class="a2-content" id="a2MainContent" tabindex="-1">
                @yield('before_content')

                @yield('content')

                @yield('after_content')

                @includeIf('admin-v2.layouts._partials.resultsbar-auto')
            </main>

        </div>
    </div>

    {{-- Global: Toggle Active --}}
    <script>
    (function () {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        async function toggle(btn) {
            const url = btn.dataset.url;

            if (!url || btn.dataset.loading === '1') {
                return;
            }

            btn.dataset.loading = '1';
            btn.style.opacity = '.65';

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await res.json().catch(() => null);

                if (!res.ok || !data || data.ok !== true) {
                    throw new Error('Bad response');
                }

                const isActive = Number(data.is_active) === 1;

                btn.dataset.state = isActive ? '1' : '0';
                btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                btn.textContent = data.label || (isActive ? 'Active' : 'Inactive');

                btn.classList.toggle('a2-pill-active', isActive);
                btn.classList.toggle('a2-pill-inactive', !isActive);

                btn.classList.toggle('a2-pill-success', isActive);
                btn.classList.toggle('a2-pill-gray', !isActive);
            } catch (e) {
                console.error(e);
                alert('حدث خطأ أثناء تغيير الحالة');
            } finally {
                btn.dataset.loading = '0';
                btn.style.opacity = '1';
            }
        }

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-toggle-active');

            if (!btn) {
                return;
            }

            e.preventDefault();
            toggle(btn);
        });
    })();
    </script>

    {{-- Global: Sidebar / Mobile --}}
    <script>
    (function () {
        const body = document.body;
        const overlay = document.getElementById('a2Overlay');
        const sidebar = document.getElementById('a2Sidebar');
        const btnOpen = document.getElementById('a2OpenSidebar');
        const btnClose = document.getElementById('a2CloseSidebar');

        const isMobile = function () {
            return window.matchMedia('(max-width: 768px)').matches;
        };

        function openMobile() {
            body.classList.add('a2-sidebar-open');
        }

        function closeMobile() {
            body.classList.remove('a2-sidebar-open');
        }

        function toggleMini() {
            body.classList.toggle('a2-sidebar-mini');
        }

        function enableMini() {
            body.classList.add('a2-sidebar-mini');
        }

        btnOpen?.addEventListener('click', function () {
            if (isMobile()) {
                openMobile();
            } else {
                toggleMini();
            }
        });

        btnClose?.addEventListener('click', function () {
            if (isMobile()) {
                closeMobile();
            } else {
                enableMini();
            }
        });

        overlay?.addEventListener('click', closeMobile);

        sidebar?.addEventListener('click', function (e) {
            const link = e.target.closest('a');

            if (!link) {
                return;
            }

            if (link.getAttribute('aria-disabled') === 'true') {
                e.preventDefault();
                return;
            }

            if (isMobile()) {
                closeMobile();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeMobile();
            }
        });

        window.addEventListener('resize', function () {
            if (!isMobile()) {
                closeMobile();
            }
        });
    })();
    </script>

    {{-- Optional shared scripts --}}
    @includeIf('admin-v2.layouts._partials.date-range-script')

    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

    @if(Route::has('admin.bookings.protectionPreview'))
        <script>
            window.BIM_BOOKING_PROTECTION_PREVIEW_URL = @json(route('admin.bookings.protectionPreview'));
        </script>
        <script src="{{ asset('admin-v2/js/booking-protection-preview.js') }}"></script>
    @endif

    @stack('scripts')
</body>
</html>
