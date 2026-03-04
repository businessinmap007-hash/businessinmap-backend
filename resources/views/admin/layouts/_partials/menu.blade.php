

<div class="navbar-custom">


    <div class="container">





        <div id="navigation">
            <!-- Navigation Menu-->
            <ul class="navigation-menu" style=" font-size: 14px;">
                <li>
                    <a href="{{ route('admin.home') }}">
                        <i class="zmdi zmdi-view-dashboard"></i>
                        <span> @lang('menu.home') </span>
                    </a>
                </li>

                @can('users_management')

                    <li class="has-submenu open">
                        <a href="javascript:;"><i
                                    class="zmdi zmdi-layers"></i><span> إدارة النظام </span>
                        </a>
                        <ul class="submenu open">
                            <li>
                                <a href="{{route('users.index')}}">مديري النظام</a>
                            </li>
                            @if(auth()->user()->roles()->whereName('owner')->first())
                                <li>
                                    <a href="{{ route('roles.index') }}">الصلاحيات والادوار</a>
                                </li>
                            @endif

                        </ul>

                    </li>
                @endcan


                @can('users_management')

                    <li class="has-submenu open">
                        <a href="javascript:;"><i
                                    class="zmdi zmdi-layers"></i><span> إدارة المستخدمين </span>
                        </a>
                        <ul class="submenu open">
                            <li>
                                <a href="{{route('clients.index')}}">المستخدمين</a>
                            </li>

                            <li>
                                <a href="{{route('business.index')}}">العملاء</a>
                            </li>
                        </ul>

                    </li>
                @endcan




                <li class="has-submenu open">
                    <a href="javascript:;"><i
                                class="zmdi zmdi-accounts-outline"></i><span>الأقسام </span>
                    </a>
                    <ul class="submenu open">
                        <li>
                            <a href="{{route('categories.index')}}">مشاهدة الأقسام</a>
                        </li>

                        <li>
                            <a href="{{ route('options.index')}}">خيارات التصنيف الفرعي</a>
                        </li>
                    </ul>

                </li>


{{--                @can('list_products')--}}
{{--                    <li class="has-submenu">--}}
{{--                        <a href="javascript:;"><i--}}
{{--                                    class="zmdi zmdi-layers"></i><span> المنتجات </span>--}}
{{--                        </a>--}}
{{--                        <ul class="submenu">--}}
{{--                            <li>--}}
{{--                                <a href="{{route('products.index')}}">مشاهدة المنتجات</a>--}}
{{--                            </li>--}}

{{--                            <li>--}}
{{--                                <a href="{{ route('products.create')}}">إضافة منتج</a>--}}
{{--                            </li>--}}
{{--                        </ul>--}}

{{--                    </li>--}}
{{--                @endcan--}}

{{--                @can('list_offers')--}}
{{--                    <li class="has-submenu">--}}
{{--                        <a href="javascript:;"><i--}}
{{--                                    class="zmdi zmdi-layers"></i><span> العروض </span>--}}
{{--                        </a>--}}
{{--                        <ul class="submenu">--}}
{{--                            <li>--}}
{{--                                <a href="{{route('offers.index')}}">مشاهدة العروض</a>--}}
{{--                            </li>--}}

{{--                            <li>--}}
{{--                                <a href="{{ route('offers.create')}}">إضافة عرض</a>--}}
{{--                            </li>--}}
{{--                        </ul>--}}

{{--                    </li>--}}
{{--                @endcan--}}


                <li>
                    <a href="{{ route('posts.index') }}">
                        <i class="zmdi zmdi-view-dashboard"></i>
                        <span> المنشورات </span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('jobs.index') }}">
                        <i class="zmdi zmdi-view-dashboard"></i>
                        <span> الوظائف</span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('sponsors.index') }}">
                        <i class="zmdi zmdi-view-dashboard"></i>
                        <span> الإعلانات </span>
                    </a>
                </li>

                <li>
                    <a href="{{ route('transactions.index') }}">
                        <i class="zmdi zmdi-view-dashboard"></i>
                        <span> المعاملات المالية </span>
                    </a>
                </li>


                <li>
                    <a href="{{ route('albums.index') }}">
                        <i class="zmdi zmdi-view-dashboard"></i>
                        <span> الالبومات </span>
                    </a>
                </li>




                    <li class="has-submenu open">
                        <a href="javascript:;"><i
                                    class="zmdi zmdi-accounts-outline"></i><span>إدارة أكواد الخصم </span>
                        </a>
                        <ul class="submenu open" >
                            <li>
                                <a href="{{route('coupons.index')}}">مشاهدة اكواد الخصم</a>
                            </li>

                            <li>
                                <a href="{{ route('coupons.create')}}">إضافة كود خصم</a>
                            </li>
                        </ul>

                    </li>



            @can('list_trips')
                    <li class="has-submenu open">
                        <a href="javascript:;"><i
                                    class="zmdi zmdi-accounts-outline"></i><span>إدارة الدول والمدن </span>
                        </a>
                        <ul class="submenu open">
                            <li>
                                <a href="{{route('locations.index')}}">مشاهدة الدول</a>
                            </li>

                            <li>
                                <a href="{{ route('locations.create')}}">إضافة دولة او مدينة</a>
                            </li>
                        </ul>

                    </li>
                @endcan


                @can('settings_management')
                    <li class="has-submenu open">
                        <a href="javascript:;"><i
                                    class="zmdi zmdi-accounts-outline"></i><span>إعدادات التطبيق</span>
                        </a>
                        <ul class="submenu open">

                            @can('sliders_management')
                                <li>
                                    <a href="{{route('discounts.and.gifts')}}">الخصومات والهدايا</a>
                                </li>
                            @endcan

                                @can('sliders_management')
                                    <li>
                                        <a href="{{route('settings.app.general')}}">إعدادات عامة</a>
                                    </li>
                                @endcan


                            @can('banners_management')
                                <li>
                                    <a href="{{ route('banners.index')}}">البانرات الاعلانيه</a>
                                </li>
                            @endcan


                                <li>
                                    <a href="{{ route('settings.aboutus')}}">من نحن</a>
                                </li>


{{--                            @can('home_settings')--}}
{{--                                <li>--}}
{{--                                    <a href="{{ route('home.settings')}}">إعدادات الصفحة الرئيسية</a>--}}
{{--                                </li>--}}
{{--                            @endcan--}}

                        </ul>
                    </li>

                @endcan


            </ul>
            <!-- End navigation menu  -->
        </div>
    </div>
</div>