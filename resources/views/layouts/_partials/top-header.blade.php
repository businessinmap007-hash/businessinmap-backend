<!-- start header1 -->
<div class="header1">
    <div class="container">
        <nav class="navbar navbar-default">
            <div class="container-fluid">
                <div class="row">

                    {{-- LOGO --}}
                    <div class="col-xs-12 col-md-3">
                        <div class="navbar-header navbar-right">
                            <a class="navbar-brand"
                               href="{{ Route::has('user.home') ? route('user.home') : '#' }}">
                                <img src="{{ request()->root() }}/public/assets/front/img/logo.png">
                            </a>
                        </div>
                    </div>

                    {{-- SEARCH (DISABLED SAFELY) --}}
                    <div class="col-xs-12 col-md-6">
                        <div class="top-search">
                            {{--
                            @if(Route::has('category.products'))
                                <form class="navbar-form navbar-left"
                                      action="{{ route('category.products') }}"
                                      method="GET">
                                    <div class="input-group">
                                        <input
                                            type="text"
                                            class="form-control"
                                            name="s"
                                            value="{{ request('s') }}"
                                            placeholder="ابحث عن اسم المنتج">
                                        <div class="input-group-btn">
                                            <button class="submit" type="submit">
                                                <i class="fa fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            @endif
                            --}}
                        </div>
                    </div>

                    {{-- ICONS --}}
                    <div class="col-xs-12 col-md-3">
                        <ul class="nav navbar-nav">

                            {{-- CART --}}
                            <li>
                                <a href="{{ Route::has('cart') ? route('cart') : '#' }}">
                                    <img src="{{ request()->root() }}/public/assets/front/img/icon2.png">
                                    <span>مشترياتى</span>
                                </a>
                            </li>

                            {{-- PROFILE --}}
                            <li>
                                <a href="{{ Route::has('profile') ? route('profile') : '#' }}">
                                    <img src="{{ request()->root() }}/public/assets/front/img/icon3.png">
                                    <span>حسابى</span>
                                </a>
                            </li>

                            {{-- LANGUAGE --}}
                            <li>
                                <a href="{{ Route::has('lang.switch') ? route('lang.switch', 'en') : '#' }}">
                                    <img src="{{ request()->root() }}/public/assets/front/img/icon1.png">
                                    <span>English</span>
                                </a>
                            </li>

                        </ul>
                    </div>

                </div>
            </div>
        </nav>
    </div>
</div>
<!-- end header1 -->
