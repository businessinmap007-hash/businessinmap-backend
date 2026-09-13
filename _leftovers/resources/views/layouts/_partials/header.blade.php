<!-- top -->
<div class="top1">
    <div id="top" class="col-xs-12 top2">
        <div id="header" class="header2">
            <div class="container">
                <div class="row">
                    <div class="col-lg-4">
                        <ul class="actions">
                            @if(auth()->check())
                                <li>
                                    <a href="{{ route('user.auth.logout') }}">
                                        <i class="fa fa-sign-out-alt"></i>
                                        <span class="title">@lang('trans.logout')</span>
                                    </a>
                                </li>
                                @else
                                <li>
                                    <a href="{{ route('get.user.login') }}">
                                        <i class="fa fa-user"></i>
                                        <span class="title">دخول</span>
                                    </a>
                                </li>

                            @endif

                            <li>
                                <a class="active" href="cart.html"><i class="fa fa-shopping-basket"></i>
                                    <span class="title">السلة</span>
                                    <span class="bubble">3</span>
                                </a>
                            </li>
                            <li>
                                <a class="" href="wishlist.html"><i class="fa fa-heart"></i>
                                    <span class="title">المفضلة</span>
                                    <!-- <span class="bubble">3</span> -->
                                </a>
                            </li>
                        </ul>
                    </div>
                    <!-- Begin Logo -->
                    <div class="logo col-lg-4">
                        <a href="{{ url('/') }}">
                            <img src="{{ request()->root() }}/public/assets/front/images/HLogo.png"/>
                        </a>
                    </div>
                    <!-- End Logo -->

                    <div class="col-lg-4">
                        <a class="lang" href="index_en.html">ENGLISH</a>
                        <ul class="social">
                            <li><a href="#" title="facebook"><i class="fab fa-facebook-square"></i></a></li>
                            <li><a href="#" title="twitter"><i class="fab fa-twitter"></i></a></li>
                            <li><a href="#" title="instagram"><i class="fab fa-instagram"></i></a></li>
                            <li><a href="#" title="youtube"><i class="fab fa-google-play"></i></a></li>
                            <li><a href="#" title="instagram"><i class="fab fa-apple"></i></a></li>
                        </ul>
                    </div>

                    <!-- Begin Logo -->
                    <div class="logo col-lg-4 logo2"><a href="{{ url('/') }}"><img
                                    src="{{ request()->root() }}/public/assets/front/images/HLogo.png"/></a></div>
                    <!-- End Logo -->

                </div>
            </div>
        </div>
    </div>
</div>
<!-- Begin Header -->
<div class="header1">
    <div id="header">
        <div class="container">
            <div class="row">
                <!-- Begin menu -->
                <div class="menu col-12 col-lg-12">
                    <nav class="navbar navbar-expand-lg navbar-light">
                        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarmenu"
                                aria-controls="navbarSupportedContent" aria-expanded="false"
                                aria-label="Toggle navigation">
                            <i class="fa fa-bars"></i>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarmenu">
                            <ul class="navbar-nav mr-auto">
                                <li class="nav-item active">
                                    <a class="nav-link" href="index.html">الرئيسية </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="index.html">الخدمات</a>
                                </li>
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" data-toggle="dropdown">المنتجات</a>
                                    <ul class="dropdown-menu">
                                        <li class="nav-item dropdown">
                                            <a class="nav-link dropdown-toggle" data-toggle="dropdown">العناية
                                                الشخصية</a>
                                            <ul class="dropdown-menu" aria-labelledby="dropdown03">
                                                <li><a class="dropdown-item" href="products.html">العناية بالمرأة</a>
                                                </li>
                                                <li><a class="dropdown-item" href="products.html">سائل الإستحمام</a>
                                                </li>
                                                <li><a class="dropdown-item" href="products.html">مزيلات العرق</a></li>
                                                <li><a class="dropdown-item" href="products.html">شامبو الشعر</a></li>
                                            </ul>
                                        </li>
                                        <li><a class="dropdown-item" href="products.html">الأم والطفل</a></li>
                                        <li><a class="dropdown-item" href="products.html">العناية بالجمال</a></li>
                                        <li><a class="dropdown-item" href="products.html">العناية بالرجال</a></li>
                                        <li><a class="dropdown-item" href="products.html">العناية بالسكرى</a></li>
                                        <li><a class="dropdown-item" href="products.html">الحمل والرضاعة</a></li>
                                        <li><a class="dropdown-item" href="products.html">مستلزمات طبية</a></li>
                                    </ul>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="products.html">العروض</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="page.html">من نحن</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="blog.html">المقالات</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="contact-us.html">إتصل بنا</a>
                                </li>

                            </ul>
                            <div class="search-div">
                                <form action="{{ route('category.products') }}" id="filterForm">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="s" value="{{ request('s') }}"
                                               placeholder="إبحث فى المنتجات">
                                        <div class="input-group-prepend">
                                                        <span class="input-group-text">
                                                            <input type="submit"/>
                                                            <label class="active"><i class="fa fa-search"></i></label>
                                                        </span>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </nav>
                </div>
                <!-- End menu -->

            </div>
        </div>
    </div>
</div>
<!-- End Header -->