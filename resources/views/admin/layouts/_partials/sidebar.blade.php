<nav class="admin-sidebar" id="adminSidebar">
    <ul class="admin-sidebar-menu">

        <li class="admin-sidebar-item">
            <a href="{{ route('admin.home') }}" class="admin-sidebar-link">
                <i class="zmdi zmdi-view-dashboard"></i>
                <span>الرئيسية</span>
            </a>
        </li>

        @can('users_management')
        <li class="admin-sidebar-item has-sub">
            <a href="#" class="admin-sidebar-link">
                <div>
                    <i class="zmdi zmdi-layers"></i>
                    <span>إدارة النظام</span>
                </div>
                <i class="zmdi zmdi-chevron-down admin-sidebar-arrow"></i>
            </a>
            <ul class="admin-sidebar-submenu">
                <li><a href="{{ route('users.index') }}">مديري النظام</a></li>
                @if(auth()->user()->roles()->whereName('owner')->first())
                    <li><a href="{{ route('roles.index') }}">الصلاحيات والأدوار</a></li>
                @endif
            </ul>
        </li>
        @endcan

        @can('users_management')
        <li class="admin-sidebar-item has-sub">
            <a href="#" class="admin-sidebar-link">
                <div>
                    <i class="zmdi zmdi-accounts"></i>
                    <span>إدارة المستخدمين</span>
                </div>
                <i class="zmdi zmdi-chevron-down admin-sidebar-arrow"></i>
            </a>
            <ul class="admin-sidebar-submenu">
                <li><a href="{{ route('clients.index') }}">المستخدمين</a></li>
                <li><a href="{{ route('business.index') }}">العملاء</a></li>
            </ul>
        </li>
        @endcan

        <li class="admin-sidebar-item">
            <a href="{{ route('categories.index') }}" class="admin-sidebar-link">
                <i class="zmdi zmdi-accounts-outline"></i>
                <span>الأقسام</span>
            </a>
        </li>

        <li class="admin-sidebar-item">
            <a href="{{ route('posts.index') }}" class="admin-sidebar-link">
                <i class="zmdi zmdi-collection-text"></i>
                <span>المنشورات</span>
            </a>
        </li>

        <li class="admin-sidebar-item">
            <a href="{{ route('jobs.index') }}" class="admin-sidebar-link">
                <i class="zmdi zmdi-case"></i>
                <span>الوظائف</span>
            </a>
        </li>

        <li class="admin-sidebar-item">
            <a href="{{ route('sponsors.index') }}" class="admin-sidebar-link">
                <i class="zmdi zmdi-flag"></i>
                <span>الإعلانات</span>
            </a>
        </li>

        <li class="admin-sidebar-item">
            <a href="{{ route('transactions.index') }}" class="admin-sidebar-link">
                <i class="zmdi zmdi-balance-wallet"></i>
                <span>المعاملات المالية</span>
            </a>
        </li>

        <li class="admin-sidebar-item">
            <a href="{{ route('albums.index') }}" class="admin-sidebar-link">
                <i class="zmdi zmdi-collection-image"></i>
                <span>الألبومات</span>
            </a>
        </li>

        <li class="admin-sidebar-item has-sub">
            <a href="#" class="admin-sidebar-link">
                <div>
                    <i class="zmdi zmdi-ticket-star"></i>
                    <span>أكواد الخصم</span>
                </div>
                <i class="zmdi zmdi-chevron-down admin-sidebar-arrow"></i>
            </a>
            <ul class="admin-sidebar-submenu">
                <li><a href="{{ route('coupons.index') }}">مشاهدة الأكواد</a></li>
                <li><a href="{{ route('coupons.create') }}">إضافة كود خصم</a></li>
            </ul>
        </li>

        @can('list_trips')
        <li class="admin-sidebar-item has-sub">
            <a href="#" class="admin-sidebar-link">
                <div>
                    <i class="zmdi zmdi-globe"></i>
                    <span>إدارة الدول والمدن</span>
                </div>
                <i class="zmdi zmdi-chevron-down admin-sidebar-arrow"></i>
            </a>
            <ul class="admin-sidebar-submenu">
                <li><a href="{{ route('locations.index') }}">مشاهدة الدول</a></li>
                <li><a href="{{ route('locations.create') }}">إضافة دولة أو مدينة</a></li>
            </ul>
        </li>
        @endcan

        @can('settings_management')
        <li class="admin-sidebar-item has-sub">
            <a href="#" class="admin-sidebar-link">
                <div>
                    <i class="zmdi zmdi-settings"></i>
                    <span>إعدادات التطبيق</span>
                </div>
                <i class="zmdi zmdi-chevron-down admin-sidebar-arrow"></i>
            </a>
            <ul class="admin-sidebar-submenu">
                @can('sliders_management')
                    <li><a href="{{ route('discounts.and.gifts') }}">الخصومات والهدايا</a></li>
                @endcan

                @can('sliders_management')
                    <li><a href="{{ route('settings.app.general') }}">إعدادات عامة</a></li>
                @endcan

                @can('banners_management')
                    <li><a href="{{ route('banners.index') }}">البانرات الإعلانية</a></li>
                @endcan

                <li><a href="{{ route('settings.aboutus') }}">من نحن</a></li>
            </ul>
        </li>
        @endcan

    </ul>
</nav>
