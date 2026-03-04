@extends('layouts.master')



@section('styles')
    <style>
        #sidebar .block .checkbox {
            display: initial !important;
        }

        #cats-rows li {
            margin-top: 10px;
        }
    </style>
@endsection
@section('content')


    <!-- Main Content-->
    <main class="main-content">
        <!--products -->
        <section class="products">
            <div class="container">
                <div class="main">
                    <form action="{{ route('category.products') }}" id="filterForm">
                        <div class="row">
                            <div class="col-xs-12">
                                <h3 class="title-2">المنتجات</h3>
                                <div class="show-mode">
                                    <spa> عرض:</spa>
                                    <ul>
                                        <li class="{{ request('show') == ""  || request('show') =='grid' ? "active" : ""}}">
                                            <a href="{{ preg_replace('~(\?|&)'.'show'.'=[^&]*~', '$1', request()->fullUrl() ) }}{{isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !="" ? "&" : "?"}}show=grid">

                                                <i class="fa fa-th-large"></i>
                                            </a>
                                        </li>
                                        <li class="{{ request('show') =='list' ? "active" : ""}}">
                                            <a href="{{ preg_replace('~(\?|&)'.'show'.'=[^&]*~', '$1', request()->fullUrl() ) }}{{isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !="" ? "&" : "?"}}show=list">

                                                <i class="fa fa-bars"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="sort-by">
                                    <spa> ترتيب حسب :</spa>
                                    <ul>
                                        {{--<li>--}}
                                        {{--<a href="#"> الأكثر تطابقا </a>--}}
                                        {{--</li>--}}
                                        <li class="{{ request('order') == 'desc' ? "active" : ""}}">
                                            <a href="{{ preg_replace('~(\?|&)'.'order'.'=[^&]*~', '$1', request()->fullUrl() )}}{{isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !="" ? "&" : "?"}}order=desc">
                                                الأحدث </a>
                                        </li>
                                        <li class="{{ request('order') == 'asc' ? "active" : ""}}">
                                            <a href="{{ preg_replace('~(\?|&)'.'order'.'=[^&]*~', '$1', request()->fullUrl() ) }}{{isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !="" ? "&" : "?"}}order=asc">
                                                الأقدم </a>
                                        </li>
                                        <li class="{{ request('price') == 'asc' ? "active" : ""}}">
                                            <a href="{{ preg_replace('~(\?|&)'.'price'.'=[^&]*~', '$1', request()->fullUrl() ) }}{{isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !="" ? "&" : "?"}}price=asc">
                                                السعر الأقل </a>


                                        </li>
                                        <li class="{{ request('price') == 'desc' ? "active" : ""}}">
                                            <a href="{{ preg_replace('~(\?|&)'.'price'.'=[^&]*~', '$1', request()->fullUrl() ) }}{{isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !="" ? "&" : "?"}}price=desc">
                                                السعر الأكبر </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="row">


                            <div class="col-md-4 col-lg-3">
                                <div class="col-12" id="sidebar">

                                    <div class="block">
                                        <h3 class="block-title bl-open">
                                            بحث بالاسم
                                            <i class="fa fa-chevron-down"></i>
                                        </h3>
                                        <div class="block-body bl-open">

                                            <input type="text" class="form-control inputs-filter"
                                                   style="margin-bottom: 20px;"
                                                   value="{{ request('s') }}"
                                                   name="s" placeholder="ابحث بإسم المنتج">

                                        </div>
                                    </div>


                                    <div class="block">
                                        <h3 class="block-title bl-close">
                                            بلد المنشأ
                                            <i class="fa fa-chevron-down"></i>
                                        </h3>
                                        <div class="block-body bl-close">
                                            <select class="form-control inputs-filter" name="country" style="margin-bottom: 20px;">
                                                @foreach($countries as $country)
                                                    <option value="{{ $country->id }}" {{ request('country') == $country->id ? "selected" : "" }}>{{ $country->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <!-- sub categories block -->
                                    <div class="block">
                                        <h3 class="block-title bl-close">
                                            قسم
                                            <i class="fa fa-chevron-down"></i>
                                        </h3>
                                        <div class="block-body bl-close">
                                            <input class="form-control" id="cats" type="text"
                                                   placeholder="القسم"/>


                                            <ul id="cats-rows">


                                                @foreach($categories as $key=>$category)
                                                    <li>
                                                        <input id="cat{{ $key }}" type="checkbox"
                                                               {{ collect(request('category'))->contains($category->id) ? 'checked' : "" }}
                                                               value="{{ $category->id }}" name="category[]"
                                                               class="inputs-filter"/>
                                                        <label class="checkbox"
                                                               for="cat{{ $key }}"> {{ $category->name }}</label>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                    <!-- Brands block -->
                                {{--<div class="block">--}}
                                {{--<h3 class="block-title bl-close">--}}
                                {{--العلامة التجارية--}}
                                {{--<i class="fa fa-chevron-down"></i>--}}
                                {{--</h3>--}}
                                {{--<div class="block-body bl-close">--}}
                                {{--<input class="form-control" id="brand" type="text"--}}
                                {{--placeholder="العالمة التجارية"/>--}}
                                {{--<ul id="brand-rows">--}}
                                {{--<li>--}}
                                {{--<input id="bra1" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="bra1"> إسم العلامة التجارية </label>--}}

                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="bra2" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="bra2"> إسم العلامة التجارية </label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="bra3" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="bra3"> إسم العلامة التجارية </label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="bra4" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="bra4"> إسم العلامة التجارية </label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="bra5" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="bra5"> إسم العلامة التجارية </label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="bra6" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="bra6"> إسم العلامة التجارية </label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="bra7" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="bra7"> إسم العلامة التجارية </label>--}}
                                {{--</li>--}}
                                {{--</ul>--}}
                                {{--</div>--}}
                                {{--</div>--}}
                                <!-- Offers block -->
                                {{--<div class="block">--}}
                                {{--<h3 class="block-title bl-close">--}}
                                {{--العروض--}}
                                {{--<i class="fa fa-chevron-down"></i>--}}
                                {{--</h3>--}}
                                {{--<div class="block-body bl-close">--}}
                                {{--<ul>--}}
                                {{--<li>--}}
                                {{--<input id="off1" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="off1">--}}
                                {{--العرض الأول--}}

                                {{--</label>--}}

                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="off2" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="off2">--}}
                                {{--العرض الثانى--}}

                                {{--</label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="off3" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="off3">--}}
                                {{--العرض الثالث--}}

                                {{--</label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="off4" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="off4">--}}
                                {{--العرض الرابع--}}

                                {{--</label>--}}
                                {{--</li>--}}
                                {{--<li>--}}
                                {{--<input id="off5" type="checkbox" name="cat"/>--}}
                                {{--<label class="checkbox" for="off5">--}}
                                {{--العرض الخامس--}}

                                {{--</label>--}}
                                {{--</li>--}}
                                {{--</ul>--}}
                                {{--</div>--}}
                                {{--</div>--}}
                                <!-- Offers block -->
                                    <div class="block price">
                                        <h3 class="block-title bl-close">
                                            السعر
                                            <i class="fa fa-chevron-down"></i>
                                        </h3>
                                        <div class="block-body bl-close">
                                            <label class="col-xs-6">من ( ريال )<input class="form-control"
                                                                                      id="min_price"
                                                                                      type="number"
                                                                                      name="price-from"
                                                                                      placeholder="10"
                                                                                      value="{{ request('price-from') }}"
                                                                                      min="{{ request('price-from') != "" ? request('price-from') : 10}}"/></label>
                                            <label class="col-xs-6">الى ( ريال )<input class="form-control"
                                                                                       id="max_price"
                                                                                       value="{{ request('price-to') }}"
                                                                                       type="number"
                                                                                       name="price-to"
                                                                                       placeholder="100" min="1"
                                                                                       max="{{ request('price-to') != "" ? request('price-to') : 100}}"/></label>
                                            <div class="col-xs-12">
                                                <div class="price-slider">
                                                    <input id="slider-range" type="text" value=""
                                                           data-slider-min="10"
                                                           data-slider-max="100"
                                                           data-slider-step="1" data-slider-value="[10,100]"/>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    {{--<a class="the-btn1 col-sm-12" href="#">إعرض</a>--}}

                                    <input type="submit" id="filterFormBtn" class="the-btn1 " value="بحث">
                                </div>
                            </div>

                            <!-- products -->
                            <div class="col-md-9">
                                <div class="productItems">
                                    <div class="row">


                                        @forelse($products as $product)
                                            <div class="{{ request('show') == 'list' ? 'col-xs-12' : "col-xs-6 col-md-4" }}">
                                                <div class="productItem {{ request('show') == 'list' ? 'list col-xs-12 ' : "col-xs-12" }}  ">
                                                    <a class="row" href="{{ route('product.details', $product) }}">
                                                        <div class="pro-wrap {{ request('show') == 'list' ? 'col-md-3 ' : "" }} col-xs-12 ">
                                                            @if(file_exists(public_path().'/'.$product->image ))
                                                                <img src="{{ asset('public/'.$product->image)  }}"
                                                                     class=""
                                                                     alt="...">
                                                            @else
                                                                <img src="{{ request()->root() }}/public/assets/images/default.png"
                                                                     class="d-block"
                                                                     alt="...">
                                                            @endif
                                                        </div>
                                                        <div class="pro-det  {{ request('show') == 'list' ? 'col-md-9' : "" }} col-xs-12 ">
                                                            <h5>{{ $product->name }}</h5>
                                                            <div class="price">
                                                                @if($product->price_sale != "")
                                                                    {{ $product->price_sale }}  رس
                                                                    <span class="discount">{{ $product->price }}
                                                                        رس </span>
                                                                @else
                                                                    {{ $product->price }}  رس
                                                                @endif
                                                            </div>
                                                            <div class="h6 section">
                                                                <img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>{{ optional($product->category)->name }}</span>
                                                            </div>
                                                        </div>
                                                        <ul class="action">
                                                            <li>
                                                                <a href="javascript:;" class="addToCart"
                                                                   data-id="{{ $product->id }}">
                                                                    <i class="fas fa-shopping-cart"></i>
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a href="javascript:;" class="addToWishlist"
                                                                   data-id="{{ $product->id }}">
                                                                    @if(auth()->check() && auth()->user()->isInFavorite($product->id))
                                                                        <i style="color: #ffffff;"
                                                                           class="fa fa-heart"></i>
                                                                    @else
                                                                        <i class="fas fa-heart"></i>
                                                                    @endif
                                                                </a>
                                                            </li>
                                                        </ul>
                                                    </a>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-center">
                                                <p class="text-center mt-30">
                                                    <strong>لا توجد نتائج</strong>
                                                </p>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                                <ul class="paging">
                                    {{ $products->appends($_GET)->links() }}
                                </ul>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
    <!-- End Main Content-->

@endsection



@section('scripts')
    <!-- Price Slider JS-->
    <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/front/js/bootstrap-slider.min.js"></script>

    <script>
        //slideToggle block ...
        var window_width = $(window).width();
        if (window_width < 768) {
            $(".block-title").removeClass('bl-open');
            $(".block-title .block-body").removeClass('bl-open');
        }
        $(".block-title").click(function () {
            if ($(this).is('.bl-open')) {
                $(this).parent().find(".block-body").slideUp();
                $(this).removeClass('bl-open');
                $(this).parent().find(".block-body").removeClass('bl-open');
                $(this).addClass('bl-close');
                $(this).parent().find(".block-body").addClass('bl-close');
            } else {
                $(this).parent().find(".block-body").slideDown();
                $(this).removeClass('bl-close');
                $(this).parent().find(".block-body").removeClass('bl-close');
                $(this).addClass('bl-open');
                $(this).parent().find(".block-body").addClass('bl-open');
            }
        });
        // search realtime ...
        $('.block-body input[type=text]').keyup(function () {
            var $rows = $(this).closest('.block-body').find('li');
            var val = $.trim($(this).val()).replace(/ +/g, ' ').toLowerCase();
            $rows.show().filter(function () {
                var text = $(this).text().replace(/\s+/g, ' ').toLowerCase();
                return !~text.indexOf(val);
            }).hide();
        });
        // price slider ....
        $("#slider-range").slider({
            tooltip_position: 'top'
        });
        $("#slider-range").on("slideStart", function (slideEvt) {
            $("#min_price").val(slideEvt.value[0]);
            $("#max_price").val(slideEvt.value[1]);
        });
        $("#slider-range").on("slide", function (slideEvt) {
            $("#min_price").val(slideEvt.value[0]);
            $("#max_price").val(slideEvt.value[1]);
        });


    </script>
@endsection