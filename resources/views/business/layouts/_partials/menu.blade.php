@php
    use App\Support\BusinessPanelNav;
    use Illuminate\Support\Facades\Route;

    /**
     * قائمةُ لوحة النشاط — شجرةٌ لا شريطًا.
     *
     * كانت سبعةَ عشرَ زرًّا فى صفٍّ أفقىٍّ واحد: كلُّ شاشةٍ فى المنصّة بنفس
     * الوزن، ولا شىء يقول أىُّ زرٍّ يخصّ أىَّ خدمة. الآن فرعٌ لكل خدمةٍ يبيعها
     * صاحبُ المحل، وتحته كلُّ ما يخصّها — إعدادُها ومخزونُها وعملياتُها.
     *
     * ── والحَجب هو نفسه ─────────────────────────────────────────────────────
     *
     * `BusinessPanelNav::shows()` هو الحكَم كما كان: فرعٌ خلت كلُّ روابطه لا
     * يُرسَم أصلًا، فوكالةُ التسويق لا ترى فرعَ «المنيو» فارغًا — لا تراه.
     */
    $currentRoute = (string) Route::currentRouteName();

    $shows = fn (?string $gate) => $gate === null || BusinessPanelNav::shows($gate);

    $isActive = function (array $item) use ($currentRoute): bool {
        foreach ((array) ($item['active'] ?? [$item['route'] ?? null]) as $prefix) {
            if ($prefix && str_starts_with($currentRoute, (string) $prefix)) {
                return true;
            }
        }

        return false;
    };

    $ico = function (string $key) {
        $svgs = [
            'home' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M12 3 2 12h3v9h6v-6h2v6h6v-9h3L12 3Z"/></svg>',
            'booking' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M7 2v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7Zm12 8v10H5V10h14Z"/></svg>',
            'menu' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M4 4h16v3H4V4Zm0 6h16v3H4v-3Zm0 6h10v3H4v-3Z"/></svg>',
            'box' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M12 2 3 6.5V18l9 4 9-4V6.5L12 2Zm0 2.2 6.5 3.3L12 10.8 5.5 7.5 12 4.2Z"/></svg>',
            'route' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M5 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm14 12a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM7 6h6a5 5 0 0 1 0 10h-2a3 3 0 0 0 0 6h6v-2h-6a1 1 0 0 1 0-2h2a7 7 0 0 0 0-14H7v2Z"/></svg>',
            'heart' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M12 21s-8-5.4-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 5.6-8 11-8 11Z"/></svg>',
            'tag' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M2 12 12 2h8a2 2 0 0 1 2 2v8L12 22 2 12Zm15-7a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/></svg>',
            'users' => '<svg class="a2-ico" viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm-4 6c-4.4 0-8 2-8 4v1h16v-1c0-2-3.6-4-8-4Z"/></svg>',
        ];

        return $svgs[$key] ?? '<span class="a2-nav-dot"></span>';
    };

    /*
     * الشجرة. `gate` هو مفتاحُ الحَجب فى BusinessPanelNav، و`null` يعنى
     * «إدارةُ الحساب» — لا خدمةَ تُباع، فتظهر للجميع.
     */
    $tree = [
        ['label' => 'الرئيسية', 'icon' => 'home', 'route' => 'business.dashboard', 'active' => ['business.dashboard']],

        ['label' => 'الحجز', 'icon' => 'booking', 'gate' => 'bookings', 'children' => [
            ['label' => 'إعدادات الخدمات', 'route' => 'business.booking-settings.edit', 'gate' => 'booking-settings', 'active' => ['business.booking-settings.']],
            ['label' => 'وحداتي', 'route' => 'business.bookable-items.index', 'gate' => 'bookable-items', 'active' => ['business.bookable-items.']],
            ['label' => 'الإضافات', 'route' => 'business.booking-add-ons.index', 'gate' => 'bookable-items', 'active' => ['business.booking-add-ons.']],
            ['label' => 'حجوزاتي', 'route' => 'business.bookings.index', 'gate' => 'bookings', 'active' => ['business.bookings.']],
        ]],

        ['label' => BusinessPanelNav::catalogLabel(), 'icon' => 'menu', 'gate' => 'menu', 'children' => [
            ['label' => 'الأصناف', 'route' => 'business.menu.index', 'gate' => 'menu', 'active' => ['business.menu.index']],
            ['label' => 'مراجعة القائمة', 'route' => 'business.menu.review', 'gate' => 'menu', 'active' => ['business.menu.review']],
            ['label' => 'تعبئة الرفوف', 'route' => 'business.menu.catalog.index', 'gate' => 'menu-catalog', 'active' => ['business.menu.catalog.']],
            ['label' => 'الأقسام', 'route' => 'business.menu-sections.index', 'gate' => 'menu', 'active' => ['business.menu-sections.']],
            // «الإعدادات» لا «إعدادات المنيو»: الفرعُ فوقها يحمل الاسم الصحيح
            // للتاجر — «الكتالوج» عند معرض الأثاث — فتكرارُه هنا يناقضه.
            ['label' => 'الإعدادات', 'route' => 'business.menu-settings.edit', 'gate' => 'menu', 'active' => ['business.menu-settings.']],
            ['label' => 'الطاولات', 'route' => 'business.tables.index', 'gate' => 'tables', 'active' => ['business.tables.']],
            ['label' => 'نداءات الطاولات', 'route' => 'business.table-calls.index', 'gate' => 'table-calls', 'active' => ['business.table-calls.']],
        ]],

        ['label' => 'التجزئة', 'icon' => 'box', 'gate' => 'products', 'children' => [
            ['label' => 'منتجاتي', 'route' => 'business.products.index', 'gate' => 'products', 'active' => ['business.products.']],
        ]],

        ['label' => 'خطوط التشغيل', 'icon' => 'route', 'gate' => 'schedules', 'children' => [
            ['label' => 'الخطوط', 'route' => 'business.schedules.index', 'gate' => 'schedules', 'active' => ['business.schedules.']],
        ]],

        ['label' => 'التدريب', 'icon' => 'heart', 'gate' => 'training-plans', 'children' => [
            ['label' => 'الخطط التدريبية', 'route' => 'business.training-plans.index', 'gate' => 'training-plans', 'active' => ['business.training-plans.']],
        ]],

        // ما يشترك فيه كل نشاطٍ مهما باع.
        ['label' => 'العروض والأسعار', 'icon' => 'tag', 'children' => [
            ['label' => 'عروضي', 'route' => 'business.offerings.index', 'active' => ['business.offerings.']],
            ['label' => 'أسعاري', 'route' => 'business.prices.index', 'active' => ['business.prices.']],
        ]],

        ['label' => 'الحساب', 'icon' => 'users', 'children' => [
            // ملفُّ النشاط أوّلًا: هو الشاشةُ التى يفتحها صاحبُ محلٍّ جديد قبل
            // أن يكون له طلبٌ أو موظّف.
            ['label' => 'ملف النشاط', 'route' => 'business.profile.edit', 'active' => ['business.profile.']],
            ['label' => 'الطلبات', 'route' => 'business.orders.index', 'gate' => 'orders', 'active' => ['business.orders.']],
            ['label' => 'الموظفون', 'route' => 'business.staff.index', 'active' => ['business.staff.']],
            ['label' => 'شارك متجرك', 'route' => 'business.share-store', 'active' => ['business.share-store']],
        ]],
    ];
@endphp

<ul class="a2-nav-list">
    @foreach($tree as $item)
        @php
            $children = collect($item['children'] ?? [])
                ->filter(fn ($c) => Route::has($c['route'] ?? '') && $shows($c['gate'] ?? null))
                ->values();

            // فرعٌ خلت كلُّ روابطه لا يُرسَم: عنوانٌ بلا شىءٍ تحته وعدٌ مكسور.
            $hasChildren = $children->isNotEmpty();
            $selfRoute = $item['route'] ?? null;
            $selfShown = $selfRoute && Route::has($selfRoute) && $shows($item['gate'] ?? null);
        @endphp

        @continue(! $hasChildren && ! $selfShown)

        <li class="a2-nav-item">
            @if(! $hasChildren)
                <a class="a2-nav-link {{ $isActive($item) ? 'is-active' : '' }}" href="{{ route($selfRoute) }}">
                    {!! $ico($item['icon'] ?? 'dot') !!}
                    <span class="a2-nav-text">{{ __($item['label']) }}</span>
                </a>
            @else
                @php $open = $children->contains(fn ($c) => $isActive($c)); @endphp
                <details class="a2-nav-group" {{ $open ? 'open' : '' }}>
                    <summary class="a2-nav-parent {{ $open ? 'is-active' : '' }}">
                        {!! $ico($item['icon'] ?? 'dot') !!}
                        <span class="a2-nav-text">{{ __($item['label']) }}</span>
                        <span class="a2-nav-caret">▾</span>
                    </summary>
                    <ul class="a2-nav-children">
                        @foreach($children as $child)
                            <li>
                                <a class="a2-nav-child-link {{ $isActive($child) ? 'is-active' : '' }}" href="{{ route($child['route']) }}">
                                    <span class="a2-nav-bullet"></span>
                                    <span class="a2-nav-text">{{ __($child['label']) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </li>
    @endforeach
</ul>
