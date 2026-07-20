@php
    use Illuminate\Support\Facades\Route;

    $currentRoute = (string) Route::currentRouteName();

    $startsWithAny = function (string $value, array $prefixes): bool {
        foreach ($prefixes as $prefix) {
            if ((string) $prefix !== '' && str_starts_with($value, (string) $prefix)) {
                return true;
            }
        }
        return false;
    };

    $isActive = function (array $item) use ($currentRoute, $startsWithAny) {
        if (! empty($item['active_routes']) && is_array($item['active_routes'])) return in_array($currentRoute, $item['active_routes'], true);
        if (! empty($item['active']) && is_array($item['active'])) return $startsWithAny($currentRoute, $item['active']);
        if (! empty($item['active']) && is_string($item['active'])) return str_starts_with($currentRoute, $item['active']);
        $route = $item['route'] ?? null;
        return $route && ($currentRoute === $route || str_starts_with($currentRoute, rtrim((string) $route, '.') . '.'));
    };

    $ico = function (?string $key) {
        $key = (string) ($key ?? 'dot');
        $svgs = [
            'dashboard' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8V15H3v6Zm10-18v6h8V3h-8Z"/></svg>',
            'users' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm-4 6c-4.4 0-8 2-8 4v1h16v-1c0-2-3.6-4-8-4Z"/></svg>',
            'folder' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M10 4 12 6h8a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6Z"/></svg>',
            'file' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M7 2h7l5 5v15a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm7 1v5h5"/></svg>',
            'ticket' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M21 10V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 1 1 0 4v4a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4a2 2 0 1 1 0-4Zm-8 9h-2v-2h2v2Zm0-4h-2V9h2v6Z"/></svg>',
            'settings' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.31.06-.63.06-.94s-.02-.63-.06-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.6-.22l-2.39.96a7.1 7.1 0 0 0-1.63-.94l-.36-2.54A.5.5 0 0 0 13.9 1h-3.8a.5.5 0 0 0-.49.42l-.36 2.54c-.58.23-1.12.54-1.63.94l-2.39-.96a.5.5 0 0 0-.6.22L2.71 7.48a.5.5 0 0 0 .12.64l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94L2.83 14.52a.5.5 0 0 0-.12.64l1.92 3.32c.13.22.39.3.6.22l2.39-.96c.51.4 1.05.71 1.63.94l.36 2.54c.04.24.25.42.49.42h3.8c.24 0 .45-.18.49-.42l.36-2.54c.58-.23 1.12-.54 1.63-.94l2.39.96c.21.08.47 0 .6-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"/></svg>',
            'wallet' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M3 6a3 3 0 0 1 3-3h13a2 2 0 0 1 2 2v3h-2V5H6a1 1 0 0 0 0 2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a3 3 0 0 1-3-3V6Zm15 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
            'dot' => '<span class="a2-nav-dot"></span>',
        ];
        return $svgs[$key] ?? $svgs['dot'];
    };

    $menu = [
        ['label' => 'لوحة التحكم', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'active_routes' => ['admin.dashboard']],
        ['label' => 'المستخدمون', 'route' => 'admin.users.index', 'icon' => 'users', 'active' => 'admin.users.'],
        ['label' => 'التصنيفات والكتالوج', 'route' => 'admin.categories.index', 'icon' => 'folder', 'active' => ['admin.categories.', 'admin.category-children.', 'admin.category-child-options.', 'admin.options.', 'admin.option-groups.', 'admin.catalog-products.', 'admin.product-categories.', 'admin.product-category-children.', 'admin.catalog-brands.', 'admin.catalog-manufacturers.', 'admin.catalog-units.', 'admin.catalog-attributes.'], 'children' => [
            ['label' => 'التصنيفات', 'type' => 'section', 'children' => [
                ['label' => 'التصنيفات الرئيسية', 'route' => 'admin.categories.index', 'active' => 'admin.categories.'],
                ['label' => 'التصنيفات الفرعية', 'route' => 'admin.category-children.index', 'active' => 'admin.category-children.'],
                ['label' => 'خيارات التصنيفات الفرعية', 'route' => 'admin.category-child-options.bulk.edit', 'active' => 'admin.category-child-options.'],
                ['label' => 'الخيارات', 'route' => 'admin.options.index', 'active' => 'admin.options.'],
                ['label' => 'مجموعات الخيارات', 'route' => 'admin.option-groups.index', 'active' => 'admin.option-groups.'],
            ]],
            ['label' => 'كتالوج المنتجات', 'type' => 'section', 'children' => [
                ['label' => 'منتجات الكتالوج', 'route' => 'admin.catalog-products.index', 'active' => 'admin.catalog-products.'],
                ['label' => 'تصنيفات المنتجات', 'route' => 'admin.product-categories.index', 'active' => 'admin.product-categories.'],
                ['label' => 'التصنيفات الفرعية للمنتجات', 'route' => 'admin.product-category-children.index', 'active' => 'admin.product-category-children.'],
                ['label' => 'العلامات التجارية', 'route' => 'admin.catalog-brands.index', 'active' => 'admin.catalog-brands.'],
                ['label' => 'المصنّعون', 'route' => 'admin.catalog-manufacturers.index', 'active' => 'admin.catalog-manufacturers.'],
                ['label' => 'الوحدات', 'route' => 'admin.catalog-units.index', 'active' => 'admin.catalog-units.'],
                ['label' => 'الخصائص', 'route' => 'admin.catalog-attributes.index', 'active' => 'admin.catalog-attributes.'],
            ]],
        ]],
        ['label' => 'الخدمات والتسعير', 'route' => 'admin.platform-services.index', 'icon' => 'settings', 'active' => ['admin.platform-services.', 'admin.platform-service-fee-promotions.', 'admin.service-fee-rules.', 'admin.business_service_prices.', 'admin.platform-service-item-types.', 'admin.platform-service-item-groups.', 'admin.service-branches.', 'admin.service-catalog-matrix.', 'admin.categories.services-bulk.', 'admin.business-partnerships.', 'admin.bookable-allocations.', 'admin.commercial-offers.', 'admin.business-offers-subscriptions.', 'admin.offer-performance.', 'admin.offer-boost-packages.', 'admin.offer-follows.', 'admin.notification-center.'], 'children' => [
            ['label' => 'إعداد الخدمات', 'type' => 'section', 'children' => [
                ['label' => 'خدمات المنصّة', 'route' => 'admin.platform-services.index', 'active' => 'admin.platform-services.'],
                ['label' => 'أنواع عناصر الخدمات', 'route' => 'admin.platform-service-item-types.index', 'active' => 'admin.platform-service-item-types.'],
                ['label' => 'فروع أنواع العناصر', 'route' => 'admin.platform-service-item-groups.index', 'active' => 'admin.platform-service-item-groups.'],
                ['label' => 'لوحة فروع الخدمات', 'route' => 'admin.service-branches.index', 'active' => 'admin.service-branches.'],
                ['label' => 'مصفوفة كتالوج الخدمات', 'route' => 'admin.service-catalog-matrix.index', 'active' => 'admin.service-catalog-matrix.'],
            ]],
            ['label' => 'الربط والتسعير', 'type' => 'section', 'children' => [
                ['label' => 'ربط الخدمات بالتصنيفات (جماعي)', 'route' => 'admin.categories.services-bulk.index', 'active' => 'admin.categories.services-bulk.'],
                ['label' => 'أسعار خدمات الأعمال', 'route' => 'admin.business_service_prices.index', 'active' => 'admin.business_service_prices.'],
                ['label' => 'قواعد الرسوم الديناميكية', 'route' => 'admin.service-fee-rules.index', 'active' => 'admin.service-fee-rules.'],
                ['label' => 'عروض الرسوم', 'route' => 'admin.platform-service-fee-promotions.index', 'active' => 'admin.platform-service-fee-promotions.'],
            ]],
            ['label' => 'العمليات التجارية', 'type' => 'section', 'children' => [
                ['label' => 'شراكات الأعمال', 'route' => 'admin.business-partnerships.index', 'active' => 'admin.business-partnerships.'],
                ['label' => 'مخصصات الحجز', 'route' => 'admin.bookable-allocations.index', 'active' => 'admin.bookable-allocations.'],
                ['label' => 'العروض التجارية', 'route' => 'admin.commercial-offers.index', 'active' => 'admin.commercial-offers.'],
            ]],
            ['label' => 'العروض والتسويق', 'type' => 'section', 'children' => [
                ['label' => 'أداء العروض', 'route' => 'admin.offer-performance.index', 'active' => 'admin.offer-performance.'],
                ['label' => 'باقات تعزيز العروض', 'route' => 'admin.offer-boost-packages.index', 'active' => 'admin.offer-boost-packages.'],
                ['label' => 'متابعات العروض', 'route' => 'admin.offer-follows.index', 'active' => 'admin.offer-follows.'],
                ['label' => 'اشتراكات عروض الأعمال', 'route' => 'admin.business-offers-subscriptions.form', 'active' => 'admin.business-offers-subscriptions.'],
                ['label' => 'مركز الإشعارات', 'route' => 'admin.notification-center.index', 'active' => 'admin.notification-center.'],
            ]],
        ]],
        ['label' => 'العمليات', 'route' => 'admin.bookings.index', 'icon' => 'ticket', 'active' => ['admin.bookings.', 'admin.bookable-items.', 'admin.disputes.', 'admin.menu-items.'], 'children' => [
            ['label' => 'الحجوزات', 'type' => 'section', 'children' => [
                ['label' => 'كل الحجوزات', 'route' => 'admin.bookings.index', 'active' => 'admin.bookings.'],
                ['label' => 'إنشاء حجز', 'route' => 'admin.bookings.create', 'active_routes' => ['admin.bookings.create']],
                ['label' => 'عناصر الحجز', 'route' => 'admin.bookable-items.index', 'active' => 'admin.bookable-items.'],
                ['label' => 'إنشاء عنصر حجز', 'route' => 'admin.bookable-items.create', 'active_routes' => ['admin.bookable-items.create']],
                ['label' => 'عمليات الحجز الجماعية', 'route' => 'admin.bookable-items.bulk.index', 'active' => 'admin.bookable-items.bulk.'],
                ['label' => 'النزاعات', 'route' => 'admin.disputes.index', 'active' => 'admin.disputes.'],
            ]],
            ['label' => 'المنيو', 'type' => 'section', 'children' => [
                ['label' => 'عناصر المنيو', 'route' => 'admin.menu-items.index', 'active' => 'admin.menu-items.'],
                ['label' => 'إنشاء عنصر منيو', 'route' => 'admin.menu-items.create', 'active_routes' => ['admin.menu-items.create']],
            ]],
        ]],
        ['label' => 'الجدولة والخطوط', 'route' => 'admin.trip-schedules.index', 'icon' => 'ticket', 'active' => ['admin.trip-schedules.'], 'children' => [
            ['label' => 'خطوط التشغيل', 'route' => 'admin.trip-schedules.index', 'active_routes' => ['admin.trip-schedules.index']],
            ['label' => 'حجوزات الرحلات', 'route' => 'admin.trip-schedules.reservations', 'active_routes' => ['admin.trip-schedules.reservations']],
        ]],
        ['label' => 'المحفظة والمالية', 'route' => 'admin.wallet-transactions.index', 'icon' => 'wallet', 'active' => ['admin.wallet-overview.', 'admin.wallet-transactions.', 'admin.wallet-ops.', 'admin.wallet-notes.', 'admin.payments.', 'admin.subscriptions.', 'admin.guarantees.', 'admin.guarantee-levels.', 'admin.held-deletions.'], 'children' => [
            ['label' => 'نظرة عامة على المحفظة', 'route' => 'admin.wallet-overview.index', 'active' => 'admin.wallet-overview.'],
            ['label' => 'معاملات المحفظة', 'route' => 'admin.wallet-transactions.index', 'active' => 'admin.wallet-transactions.'],
            ['label' => 'شحن المحفظة', 'route' => 'admin.wallet-ops.recharge.form', 'active' => 'admin.wallet-ops.'],
            ['label' => 'الضمانات', 'route' => 'admin.guarantees.index', 'active' => 'admin.guarantees.'],
            ['label' => 'مستويات الضمان', 'route' => 'admin.guarantee-levels.index', 'active' => 'admin.guarantee-levels.'],
            ['label' => 'ملاحظات المحفظة', 'route' => 'admin.wallet-notes.index', 'active' => 'admin.wallet-notes.'],
            ['label' => 'المدفوعات', 'route' => 'admin.payments.index', 'active' => 'admin.payments.'],
            ['label' => 'الاشتراكات', 'route' => 'admin.subscriptions.index', 'active' => 'admin.subscriptions.'],
            ['label' => 'طلبات حذف موقوفة', 'route' => 'admin.held-deletions.index', 'active' => 'admin.held-deletions.'],
        ]],
        ['label' => 'التوصيل والطاولات', 'route' => 'admin.delivery.drivers.index', 'icon' => 'ticket', 'active' => ['admin.delivery.', 'admin.business-tables.', 'admin.wallet-topups.'], 'children' => [
            ['label' => 'سائقو التوصيل', 'route' => 'admin.delivery.drivers.index', 'active' => 'admin.delivery.drivers.'],
            ['label' => 'عمليات التوصيل المكتملة', 'route' => 'admin.delivery.completions.index', 'active' => 'admin.delivery.completions.'],
            ['label' => 'طاولات المطاعم', 'route' => 'admin.business-tables.index', 'active' => 'admin.business-tables.'],
            ['label' => 'شحن الأرصدة', 'route' => 'admin.wallet-topups.index', 'active' => 'admin.wallet-topups.'],
        ]],
        ['label' => 'المحتوى', 'route' => 'admin.posts.index', 'icon' => 'file', 'active' => ['admin.posts.', 'admin.jobs.', 'admin.job-follows.', 'admin.sponsors.', 'admin.albums.'], 'children' => [
            ['label' => 'المنشورات', 'route' => 'admin.posts.index', 'active' => 'admin.posts.'],
            ['label' => 'الوظائف', 'route' => 'admin.jobs.index', 'active' => 'admin.jobs.'],
            ['label' => 'متابعات الوظائف', 'route' => 'admin.job-follows.index', 'active' => 'admin.job-follows.'],
            ['label' => 'الرعاة', 'route' => 'admin.sponsors.index', 'active' => 'admin.sponsors.'],
            ['label' => 'الألبومات', 'route' => 'admin.albums.index', 'active' => 'admin.albums.'],
        ]],
        // App-level integration credentials (paste-and-go, no redeploy).
        ['label' => 'إعدادات التطبيق', 'route' => 'admin.payment-settings.edit', 'icon' => 'settings', 'active' => ['admin.payment-settings.', 'admin.push-settings.', 'admin.admin-roles.', 'admin.arbitrators.', 'admin.dispute-rules.', 'admin.dispute-fees.'], 'children' => [
            ['label' => 'بوابة الدفع (فوري)', 'route' => 'admin.payment-settings.edit', 'active' => 'admin.payment-settings.'],
            ['label' => 'الإشعارات (Firebase)', 'route' => 'admin.push-settings.edit', 'active' => 'admin.push-settings.'],
            ['label' => 'صلاحيات المشرفين', 'route' => 'admin.admin-roles.index', 'active' => 'admin.admin-roles.'],
            ['label' => 'الحُكّام', 'route' => 'admin.arbitrators.index', 'active' => 'admin.arbitrators.'],
            ['label' => 'قواعد النزاع', 'route' => 'admin.dispute-rules.index', 'active' => 'admin.dispute-rules.'],
            ['label' => 'رسوم جلسات التحكيم', 'route' => 'admin.dispute-fees.index', 'active' => 'admin.dispute-fees.'],
        ]],
    ];

    // BIM-14.1 — hide what this admin cannot open. The required ability is read
    // off the route's own `can:` middleware rather than repeated here: a second
    // copy of the mapping would drift, and a menu full of links that only 403
    // is worse than no menu at all.
    $canReach = function (?string $routeName) use (&$canReach): bool {
        if (! $routeName || ! Route::has($routeName)) return false;

        $user = auth()->user();
        if (! $user) return false;

        foreach (Route::getRoutes()->getByName($routeName)->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'can:')) {
                if (! $user->can(explode(',', substr($middleware, 4))[0])) return false;
            }
        }

        return true;
    };

    $firstReachable = function (array $items) use (&$firstReachable, $canReach): ?string {
        foreach ($items as $item) {
            if (! empty($item['children']) && is_array($item['children'])) {
                if ($found = $firstReachable($item['children'])) return $found;
                continue;
            }
            if ($canReach($item['route'] ?? null)) return $item['route'];
        }
        return null;
    };

    $filterMenu = function (array $items) use (&$filterMenu, $canReach, $firstReachable): array {
        $visible = [];

        foreach ($items as $item) {
            $children = $item['children'] ?? [];

            if (is_array($children) && $children !== []) {
                $keptChildren = $filterMenu($children);

                if ($keptChildren === []) continue; // nothing left under it

                $item['children'] = $keptChildren;

                // A group's own link often points at its first child, which this
                // admin may not be able to open — send them to one they can.
                if (! $canReach($item['route'] ?? null)) {
                    $item['route'] = $firstReachable($keptChildren) ?? ($item['route'] ?? null);
                }

                $visible[] = $item;
                continue;
            }

            if (($item['type'] ?? null) === 'section') continue; // an empty heading

            if ($canReach($item['route'] ?? null)) $visible[] = $item;
        }

        return $visible;
    };

    $menu = $filterMenu($menu);
@endphp

<ul class="a2-nav-list">
    @foreach($menu as $item)
        @php
            $label = $item['label'] ?? '—';
            $routeName = $item['route'] ?? null;
            $exists = $routeName && Route::has($routeName);
            $href = $exists ? route($routeName) : '#';
            $children = $item['children'] ?? [];
            $hasChildren = is_array($children) && count($children) > 0;
            $active = $exists && $isActive($item);
            $open = false;

            if ($hasChildren) {
                foreach ($children as $child) {
                    if (($child['type'] ?? null) === 'section') {
                        foreach (($child['children'] ?? []) as $sectionChild) {
                            $sectionChildRoute = $sectionChild['route'] ?? null;
                            if ($sectionChildRoute && Route::has($sectionChildRoute) && $isActive($sectionChild)) { $open = true; break 2; }
                        }
                        continue;
                    }

                    $childRoute = $child['route'] ?? null;
                    if ($childRoute && Route::has($childRoute) && $isActive($child)) { $open = true; break; }
                }
                $open = $open || $active;
            }
        @endphp

        <li class="a2-nav-item">
            @if(! $hasChildren)
                <a class="a2-nav-link {{ $active ? 'is-active' : '' }} {{ ! $exists ? 'is-disabled' : '' }}" href="{{ $href }}" data-tip="{{ __($label) }}" aria-current="{{ $active ? 'page' : 'false' }}" aria-disabled="{{ $exists ? 'false' : 'true' }}">
                    {!! $ico($item['icon'] ?? 'dot') !!}
                    <span class="a2-nav-text">{{ __($label) }}</span>
                </a>
            @else
                <details class="a2-nav-group" {{ $open ? 'open' : '' }}>
                    <summary class="a2-nav-parent {{ ($active || $open) ? 'is-active' : '' }}" data-tip="{{ __($label) }}">
                        {!! $ico($item['icon'] ?? 'dot') !!}
                        <span class="a2-nav-text">{{ __($label) }}</span>
                        <span class="a2-nav-caret">▾</span>
                    </summary>
                    <ul class="a2-nav-children">
                        @foreach($children as $child)
                            @if(($child['type'] ?? null) === 'section')
                                <li class="a2-nav-section">{{ __($child['label'] ?? '—') }}</li>
                                @foreach(($child['children'] ?? []) as $sectionChild)
                                    @php
                                        $sectionChildRoute = $sectionChild['route'] ?? null;
                                        $sectionChildExists = $sectionChildRoute && Route::has($sectionChildRoute);
                                        $sectionChildHref = $sectionChildExists ? route($sectionChildRoute) : '#';
                                        $sectionChildActive = $sectionChildExists && $isActive($sectionChild);
                                    @endphp
                                    <li>
                                        <a class="a2-nav-child-link {{ $sectionChildActive ? 'is-active' : '' }} {{ ! $sectionChildExists ? 'is-disabled' : '' }}" href="{{ $sectionChildHref }}" aria-current="{{ $sectionChildActive ? 'page' : 'false' }}" aria-disabled="{{ $sectionChildExists ? 'false' : 'true' }}">
                                            <span class="a2-nav-bullet"></span>
                                            <span class="a2-nav-text">{{ __($sectionChild['label'] ?? '—') }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            @else
                                @php
                                    $childRoute = $child['route'] ?? null;
                                    $childExists = $childRoute && Route::has($childRoute);
                                    $childHref = $childExists ? route($childRoute) : '#';
                                    $childActive = $childExists && $isActive($child);
                                @endphp
                                <li>
                                    <a class="a2-nav-child-link {{ $childActive ? 'is-active' : '' }} {{ ! $childExists ? 'is-disabled' : '' }}" href="{{ $childHref }}" aria-current="{{ $childActive ? 'page' : 'false' }}" aria-disabled="{{ $childExists ? 'false' : 'true' }}">
                                        <span class="a2-nav-bullet"></span>
                                        <span class="a2-nav-text">{{ __($child['label'] ?? '—') }}</span>
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </details>
            @endif
        </li>
    @endforeach
</ul>
