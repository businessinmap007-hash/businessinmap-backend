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

    $matchesAnyRoute = function (string $value, array $routes): bool {
        foreach ($routes as $route) {
            if ($value === (string) $route) {
                return true;
            }
        }

        return false;
    };

    $isActive = function (array $item) use ($currentRoute, $startsWithAny, $matchesAnyRoute) {
        if (! empty($item['active_routes']) && is_array($item['active_routes'])) {
            return $matchesAnyRoute($currentRoute, $item['active_routes']);
        }

        if (! empty($item['active']) && is_array($item['active'])) {
            return $startsWithAny($currentRoute, $item['active']);
        }

        if (! empty($item['active']) && is_string($item['active'])) {
            return str_starts_with($currentRoute, (string) $item['active']);
        }

        $route = $item['route'] ?? null;

        if (! $route) {
            return false;
        }

        return $currentRoute === $route || str_starts_with($currentRoute, rtrim((string) $route, '.') . '.');
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
            'shield' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M12 2 20 6v6c0 5-3.4 9.7-8 10-4.6-.3-8-5-8-10V6l8-4Z"/></svg>',
            'dot' => '<span class="a2-nav-dot"></span>',
        ];

        return $svgs[$key] ?? $svgs['dot'];
    };

    $menu = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'dashboard', 'active_routes' => ['admin.dashboard']],
        [
            'label' => 'Users',
            'route' => 'admin.users.index',
            'icon' => 'users',
            'active' => 'admin.users.',
            'children' => [
                ['label' => 'All Users', 'route' => 'admin.users.index', 'active' => 'admin.users.'],
            ],
        ],
        [
            'label' => 'Categories',
            'route' => 'admin.categories.index',
            'icon' => 'folder',
            'active' => [
                'admin.categories.',
                'admin.category-children.',
                'admin.category-child-options.',
                'admin.category-child-service-fees.',
                'admin.options.',
                'admin.option-groups.',
            ],
            'children' => [
                ['label' => 'Root Categories', 'route' => 'admin.categories.index', 'active' => 'admin.categories.'],
                ['label' => 'Category Children', 'route' => 'admin.category-children.index', 'active' => 'admin.category-children.'],
                ['label' => 'Category Services Bulk', 'route' => 'admin.categories.services-bulk.index', 'active' => 'admin.categories.services-bulk.'],
                ['label' => 'Category Child Options', 'route' => 'admin.category-child-options.bulk.edit', 'active' => 'admin.category-child-options.'],
                ['label' => 'Options', 'route' => 'admin.options.index', 'active' => 'admin.options.'],
                ['label' => 'Option Groups', 'route' => 'admin.option-groups.index', 'active' => 'admin.option-groups.'],
            ],
        ],
        [
            'label' => 'Services',
            'route' => 'admin.platform-services.index',
            'icon' => 'settings',
            'active' => [
                'admin.platform-services.',
                'admin.platform-service-fee-promotions.',
                'admin.business_service_prices.',
                'admin.platform-service-item-types.',
                'admin.business-partnerships.',
                'admin.bookable-allocations.',
                'admin.commercial-offers.',
                'admin.business-offers-subscriptions.',
                'admin.offer-performance.',
            ],
            'children' => [
                ['label' => 'Platform Services', 'route' => 'admin.platform-services.index', 'active' => 'admin.platform-services.'],
                ['label' => 'Business Partnerships', 'route' => 'admin.business-partnerships.index', 'active' => 'admin.business-partnerships.'],
                ['label' => 'Bookable Allocations', 'route' => 'admin.bookable-allocations.index', 'active' => 'admin.bookable-allocations.'],
                ['label' => 'Commercial Offers', 'route' => 'admin.commercial-offers.index', 'active' => 'admin.commercial-offers.'],
                ['label' => 'Offer Performance', 'route' => 'admin.offer-performance.index', 'active' => 'admin.offer-performance.'],
                ['label' => 'Offers Subscription', 'route' => 'admin.business-offers-subscriptions.form', 'active' => 'admin.business-offers-subscriptions.'],
                ['label' => 'Create Offer', 'route' => 'admin.commercial-offers.create', 'active_routes' => ['admin.commercial-offers.create']],
                ['label' => 'Create Partnership', 'route' => 'admin.business-partnerships.create', 'active_routes' => ['admin.business-partnerships.create']],
                ['label' => 'Create Allocation', 'route' => 'admin.bookable-allocations.create', 'active_routes' => ['admin.bookable-allocations.create']],
                ['label' => 'Create Platform Service', 'route' => 'admin.platform-services.create', 'active_routes' => ['admin.platform-services.create']],
                ['label' => 'Fee Promotions', 'route' => 'admin.platform-service-fee-promotions.index', 'active' => 'admin.platform-service-fee-promotions.'],
                ['label' => 'Business Service Prices', 'route' => 'admin.business_service_prices.index', 'active' => 'admin.business_service_prices.'],
                ['label' => 'Platform Service Item Types', 'route' => 'admin.platform-service-item-types.index', 'active' => 'admin.platform-service-item-types.'],
                ['label' => 'Create Business Price', 'route' => 'admin.business_service_prices.create', 'active_routes' => ['admin.business_service_prices.create']],
            ],
        ],
        [
            'label' => 'Bookings',
            'route' => 'admin.bookings.index',
            'icon' => 'ticket',
            'active' => ['admin.bookings.', 'admin.bookable-items.', 'admin.disputes.'],
            'children' => [
                ['label' => 'All Bookings', 'route' => 'admin.bookings.index', 'active' => 'admin.bookings.'],
                ['label' => 'Create Booking', 'route' => 'admin.bookings.create', 'active_routes' => ['admin.bookings.create']],
                ['label' => 'Bookable Items', 'route' => 'admin.bookable-items.index', 'active' => 'admin.bookable-items.'],
                ['label' => 'Create Bookable Item', 'route' => 'admin.bookable-items.create', 'active_routes' => ['admin.bookable-items.create']],
                ['label' => 'Bookable Bulk Operations', 'route' => 'admin.bookable-items.bulk.index', 'active' => 'admin.bookable-items.bulk.'],
                ['label' => 'Disputes', 'route' => 'admin.disputes.index', 'active' => 'admin.disputes.'],
            ],
        ],
        [
            'label' => 'Wallet & Finance',
            'route' => 'admin.wallet-transactions.index',
            'icon' => 'wallet',
            'active' => [
                'admin.wallet-transactions.',
                'admin.wallet-ops.',
                'admin.wallet-notes.',
                'admin.payments.',
                'admin.subscriptions.',
                'admin.guarantees.',
                'admin.guarantee-levels.',
            ],
            'children' => [
                ['label' => 'Wallet Transactions', 'route' => 'admin.wallet-transactions.index', 'active' => 'admin.wallet-transactions.'],
                ['label' => 'Wallet Recharge', 'route' => 'admin.wallet-ops.recharge.form', 'active' => 'admin.wallet-ops.'],
                ['label' => 'Guarantees', 'route' => 'admin.guarantees.index', 'active' => 'admin.guarantees.'],
                ['label' => 'Guarantee Levels', 'route' => 'admin.guarantee-levels.index', 'active' => 'admin.guarantee-levels.'],
                ['label' => 'Wallet Notes', 'route' => 'admin.wallet-notes.index', 'active' => 'admin.wallet-notes.'],
                ['label' => 'Payments', 'route' => 'admin.payments.index', 'active' => 'admin.payments.'],
                ['label' => 'Subscriptions', 'route' => 'admin.subscriptions.index', 'active' => 'admin.subscriptions.'],
            ],
        ],
        [
            'label' => 'Content',
            'route' => 'admin.posts.index',
            'icon' => 'file',
            'active' => ['admin.posts.', 'admin.jobs.', 'admin.sponsors.', 'admin.albums.'],
            'children' => [
                ['label' => 'Posts', 'route' => 'admin.posts.index', 'active' => 'admin.posts.'],
                ['label' => 'Jobs', 'route' => 'admin.jobs.index', 'active' => 'admin.jobs.'],
                ['label' => 'Sponsors', 'route' => 'admin.sponsors.index', 'active' => 'admin.sponsors.'],
                ['label' => 'Albums', 'route' => 'admin.albums.index', 'active' => 'admin.albums.'],
            ],
        ],
    ];
@endphp

<ul class="a2-nav-list">
    @foreach($menu as $item)
        @php
            $type = $item['type'] ?? 'link';
            $label = $item['label'] ?? '—';
        @endphp

        @if($type === 'section')
            <li class="a2-nav-section">{{ $label }}</li>
            @continue
        @endif

        @php
            $routeName = $item['route'] ?? null;
            $exists = $routeName && Route::has($routeName);
            $href = $exists ? route($routeName) : '#';
            $children = $item['children'] ?? [];
            $hasChildren = is_array($children) && count($children) > 0;
            $active = $exists && $isActive($item);
            $open = false;

            if ($hasChildren) {
                foreach ($children as $child) {
                    $childRoute = $child['route'] ?? null;

                    if ($childRoute && Route::has($childRoute) && $isActive($child)) {
                        $open = true;
                        break;
                    }
                }

                $open = $open || $active;
            }

            $iconKey = $item['icon'] ?? 'dot';
        @endphp

        <li class="a2-nav-item">
            @if(! $hasChildren)
                <a
                    class="a2-nav-link {{ $active ? 'is-active' : '' }} {{ ! $exists ? 'is-disabled' : '' }}"
                    href="{{ $href }}"
                    data-tip="{{ $label }}"
                    aria-current="{{ $active ? 'page' : 'false' }}"
                    aria-disabled="{{ $exists ? 'false' : 'true' }}"
                >
                    {!! $ico($iconKey) !!}
                    <span class="a2-nav-text">{{ $label }}</span>
                </a>
            @else
                <details class="a2-nav-group" {{ $open ? 'open' : '' }}>
                    <summary class="a2-nav-parent {{ ($active || $open) ? 'is-active' : '' }}" data-tip="{{ $label }}">
                        {!! $ico($iconKey) !!}
                        <span class="a2-nav-text">{{ $label }}</span>
                        <span class="a2-nav-caret">▾</span>
                    </summary>

                    <ul class="a2-nav-children">
                        @foreach($children as $child)
                            @php
                                $childRoute = $child['route'] ?? null;
                                $childExists = $childRoute && Route::has($childRoute);
                                $childHref = $childExists ? route($childRoute) : '#';
                                $childActive = $childExists && $isActive($child);
                            @endphp

                            <li>
                                <a
                                    class="a2-nav-child-link {{ $childActive ? 'is-active' : '' }} {{ ! $childExists ? 'is-disabled' : '' }}"
                                    href="{{ $childHref }}"
                                    aria-current="{{ $childActive ? 'page' : 'false' }}"
                                    aria-disabled="{{ $childExists ? 'false' : 'true' }}"
                                >
                                    <span class="a2-nav-bullet"></span>
                                    <span class="a2-nav-text">{{ $child['label'] ?? '—' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </li>
    @endforeach
</ul>
