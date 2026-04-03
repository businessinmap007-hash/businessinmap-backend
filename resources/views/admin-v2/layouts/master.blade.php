<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin V2')</title>

    <link rel="stylesheet" href="{{ asset('admin-v2/css/admin.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    @stack('styles')
</head>

<body class="admin-v2 @yield('body_class')">

    <div class="a2-shell">

        {{-- Overlay --}}
        <div class="a2-overlay" id="a2Overlay"></div>

        {{-- Sidebar --}}
        <aside class="a2-sidebar" id="a2Sidebar">
            <div class="a2-side-top">
                <a class="a2-brand" href="{{ route('admin.dashboard') }}">
                    <span class="a2-brand-badge">BIM</span>
                    <span class="a2-brand-text">Admin V2</span>
                </a>

                <button
                    class="a2-burger"
                    type="button"
                    id="a2CloseSidebar"
                    aria-label="Close Menu"
                >
                    ✕
                </button>
            </div>

            <nav class="a2-nav">
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
                        aria-label="Open Menu"
                    >
                        ☰
                    </button>

                    <div class="a2-topbar-title">
                        @yield('topbar_title', 'BIM Admin V2')
                    </div>
                </div>

                <div class="a2-topbar-right">
                    @include('admin-v2.layouts._partials.userbar')
                </div>
            </header>

            {{-- Content --}}
            <main class="a2-content">
                @yield('content')
                @include('admin-v2.layouts._partials.resultsbar-auto')
            </main>

        </div>
    </div>

    {{-- Toggle Active --}}
    <script>
    (function () {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        async function toggle(btn) {
            const url = btn.dataset.url;
            if (!url || btn.dataset.loading === '1') return;

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
            if (!btn) return;

            e.preventDefault();
            toggle(btn);
        });
    })();
    </script>

    {{-- Sidebar / Mobile --}}
    <script>
    (function () {
        const body = document.body;
        const overlay = document.getElementById('a2Overlay');
        const sidebar = document.getElementById('a2Sidebar');
        const btnOpen = document.getElementById('a2OpenSidebar');
        const btnClose = document.getElementById('a2CloseSidebar');

        const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

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
            if (!link) return;

            if (link.getAttribute('aria-disabled') === 'true') return;

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

 @include('admin-v2.layouts._partials.date-range-script')
 <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    @stack('scripts')
   
</body>
</html>
