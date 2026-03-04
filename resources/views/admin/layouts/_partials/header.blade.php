<!-- Navigation Bar-->
<header id="topnav">

    <div class="topbar-main">
        <div class="container">

            <!-- LOGO -->
            <div class="topbar-left">
                <a href="{{ route('admin.home') }}" class="logo" style="width: 200px;">
                    BIM
                </a>
            </div>
            <!-- End Logo container-->
            <div class="menu-extras">
                <ul class="nav navbar-nav navbar-right pull-right">

                    @can('notifications_management')
                        {{--<li>--}}
                            {{--<div class="notification-box">--}}
                                {{--<ul class="list-inline m-b-0">--}}
                                    {{--<li>--}}
                                        {{--<a href="{{ route('notifications.index') }}" class="right-bar-toggle">--}}
                                            {{--<i class="zmdi zmdi-notifications-none"></i>--}}


                                            {{--<div class="noti-dot"--}}
                                                 {{--@if(\App\Models\Notification::whereIsRead(0)->count() > 0)--}}
                                                 {{--style="display: block;"--}}
                                                 {{--@else--}}
                                                 {{--style="display: none;"--}}
                                                    {{--@endif>--}}
                                                {{--<span class="dot"></span>--}}
                                                {{--<span class="pulse"></span>--}}
                                            {{--</div>--}}

                                        {{--</a>--}}

                                    {{--</li>--}}
                                {{--</ul>--}}
                            {{--</div>--}}
                        {{--</li>--}}
                    @endcan


                    {{--<li class="dropdown">--}}
                    {{--<a href="" class="dropdown-toggle waves-effect waves-light profile " data-toggle="dropdown"--}}
                    {{--aria-expanded="true">--}}
                    {{--<img src="{{ request()->root() }}/public/assets/admin/images/saudi-arabia.png"--}}
                    {{--alt="user-img"--}}
                    {{--class="img-circle user-img">--}}
                    {{--</a>--}}

                    {{--<ul class="dropdown-menu">--}}

                    {{--<a href="#" class="dropdown-toggle" data-toggle="dropdown">--}}
                    {{--{{ app()->getLocale() }} <i class="fa fa-caret-down"></i>--}}
                    {{--</a>--}}

                    {{--@foreach (config('translatable.locales') as $lang => $language)--}}
                    {{--@if ($lang != app()->getLocale())--}}
                    {{--<li>--}}
                    {{--<a href="{{ route('lang.switch', $lang) }}">--}}
                    {{--{{ $language }}--}}
                    {{--</a>--}}
                    {{--</li>--}}
                    {{--@endif--}}
                    {{--@endforeach--}}


                    {{--</ul>--}}
                    {{--</li>--}}

                    <li class="dropdown user-box">
                        <a href="" class="dropdown-toggle waves-effect waves-light profile " data-toggle="dropdown"
                           aria-expanded="true">
                            <img src="{{ $helper->getDefaultImage(auth()->user()->image, request()->root().'/public/assets/admin/images/default.png') }}"
                                 alt="user-img" class="img-circle user-img">
                        </a>

                        <ul class="dropdown-menu">
                            <li><a href="{{ route('user.profile') }}?profileId={{ auth()->id() }}"><i
                                            class="ti-user m-r-5"></i>@lang('maincp.personal_page')</a></li>
                            <li><a href="{{ route('users.edit', auth()->id()) }}"><i class="ti-settings m-r-5"></i>
                                    @lang('global.settings')
                                </a>
                            </li>


                            <li><a href="{{ route('logout') }}"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="ti-power-off m-r-5"></i>@lang('maincp.log_out')
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
                <div class="menu-item">
                    <!-- Mobile menu toggle-->
                    <a class="navbar-toggle">
                        <div class="lines">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </a>
                    <!-- End mobile menu toggle-->
                </div>
            </div>

        </div>
    </div>

    <form id="logout-form" action="{{ route('administrator.logout') }}" method="POST"
          style="display: none;">
        {{ csrf_field() }}
    </form>
    @include('admin.layouts._partials.menu')
</header>
<!-- End Navigation Bar-->

<div class="wrapper">
    <div class="container">