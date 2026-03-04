@extends('layouts.app')

@section('content')

<!-- start wrapper -->
<div class="wrapper">
    <!-- start page bg -->
    <div class="page-bg">
        <div class="page-container">
            @include('layouts._partials.top-header')
            @include('layouts._partials.menu')
            @include('layouts._partials.slider')
            <!-- start section -->
            <div class="block-section">
                <div class="container">
                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <a href="{{ $setting->getBody('wonder_hand_url') }}">
                                <div class="block">
                                    <div class="block-img">
                                        <img src="{{ request()->root() }}/public/assets/front/img/pic1.png">
                                    </div>
                                    <div class="block-details">
                                        <h3>{{ $setting->getBody('wonder_hand_title_'.app()->getLocale()) }}</h3>
                                        <p>{{ $setting->getBody('wonder_hand_description_'.app()->getLocale()) }}</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <a href="{{ $setting->getBody('wonder_sec3_url') }}">
                                <div class="block">
                                    <div class="block-img"><img src="{{ request()->root() }}/public/assets/front/img/pic3.png">
                                    </div>
                                    <div class="block-details">
                                        <h3>{{ $setting->getBody('wonder_sec3_title_'.app()->getLocale()) }}</h3>
                                        <p>{{ $setting->getBody('wonder_sec3_description_'.app()->getLocale()) }}</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <a href="{{ $setting->getBody('wonder_man_url') }}">
                                <div class="block">
                                    <div class="block-img"><img src="{{ request()->root() }}/public/assets/front/img/pic2.png">
                                    </div>
                                    <div class="block-details">
                                        <h3>{{ $setting->getBody('wonder_man_title_'.app()->getLocale()) }}</h3>
                                        <p>{{ $setting->getBody('wonder_man_description_'.app()->getLocale()) }}</p>
                                    </div>
                                </div>
                            </a>
                        </div>

                    </div>
                </div>
            </div>
            <!-- end section -->
        </div>
    </div>
    <!-- end page bg -->

    <!-- start work -->
    <div class="work">
        <div class="container">
            <div class="row">
                <div class="title-section">

                    <h2 class="text-center">{{ $setting->getBody('section_cat_title_'.app()->getLocale()) }}</h2>
                    <span>{{ $setting->getBody('section_cat_description_'.app()->getLocale()) }}</span>
                </div>
                <div class="slider-3d">
                    <div id="example">
                        <carousel-3d :autoplay="true" :autoplay-timeout="2000" :display="5" :controls-visible="true" :controls-width="30" :controls-height="60" :clickable="false" :width="(mobile?316:395)">

                            @foreach($featuredProducts as $key => $featuredProduct)
                            <slide :index="{{ $key}}">
                                <div style="cursor: pointer;" class="img-3d" onclick="window.location.href=' {{ route('product.details',$featuredProduct->id) }}'">
                                    <img src="{{ asset('public/'.$featuredProduct->image) }}">
                                </div>
                            </slide>
                            @endforeach

                        </carousel-3d>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- end work -->

    <!-- end rotate-slider -->
    <!-- start offers -->
    <div class="offers">
        <div class="offers-shape"><img src="{{ request()->root() }}/public/assets/front/img/shape1.png"></div>
        <div class="container">
            <div class="row">
                <div class="more-div">
                    <h2 class="new-title"> جديد العروض </h2>
                    <a href="#" class="the-btn1 btn-default">شاهد المزيد<span class="glyphicon glyphicon-chevron-left"></span></a>
                </div>
                <div class="offers-block">
                    <!--<div class="col-md-3">
                            <div class="product-a">
                                <img src="{{ request()->root() }}/public/assets/front/img/product1.png">
                                <div class="product-a-data">
                                    <h3>مفارش يدوية</h3>
                                    <a href="#" class="the-btn1">المزيد<span
                                                class="glyphicon glyphicon-chevron-left"></span></a>
                                </div>
                            </div>
                        </div>-->
                    <div class="col-md-12">

                        @foreach($offers as $offer)
                        <div class="col-lg-4 col-sm-6">
                            <div class="product-b">
                                <div class="product-b-img">
                                    @if($offer->image != '')
                                    <img src="{{ asset('public/'.$offer->image) }}">
                                    @else
                                    <img src="{{ asset('public/'.optional($offer->product)->image) }}">
                                    @endif
                                </div>
                                <div class="product-b-data">
                                    <h3>
                                        <a href="{{ route('product.details', optional($offer->product)->id) }}">
                                            {{ optional($offer->product)->name }}
                                        </a>
                                    </h3>
                                    <p>{{ $offer->description != "" ?  strip_tags(substr($offer->description,0, 20)) : strip_tags(substr(optional($offer->product)->description, 0, 20)) }}</p>
                                    <div><img src="{{ request()->root() }}/public/assets/front/img/price.png">
                                        {{ $offer->price }} ريال سعودى
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- end offers -->
    <!-- start adv -->
    <div class="adv-section">
        <div class="container">
            <div class="row">
                {{--<div class="col-md-3 col-sm-4">--}}
                {{--<div id="myCarousel-a" class="carousel slide" data-ride="carousel">--}}

                {{--<!-- Wrapper for slides -->--}}
                {{--<div class="carousel-inner">--}}
                {{--<div class="item">--}}
                {{--<div class="adv">--}}
                {{--<img src="{{ request()->root() }}/public/assets/front/img/adv2.png" alt="">--}}
                {{--<a href="#" class="the-btn1">شاهد المزيد<span--}}
                {{--class="glyphicon glyphicon-chevron-left"></span></a>--}}
                {{--<a href="#" class="link"></a>--}}
                {{--</div>--}}
                {{--</div>--}}
                {{--<div class="item">--}}
                {{--<div class="adv">--}}
                {{--<img src="{{ request()->root() }}/public/assets/front/img/adv2.png" alt="">--}}
                {{--<a href="#" class="the-btn1">شاهد المزيد<span--}}
                {{--class="glyphicon glyphicon-chevron-left"></span></a>--}}
                {{--<a href="#" class="link"></a>--}}
                {{--</div>--}}
                {{--</div>--}}
                {{--<div class="item active">--}}
                {{--<div class="adv">--}}
                {{--<img src="{{ request()->root() }}/public/assets/front/img/adv2.png" alt="">--}}
                {{--<a href="#" class="the-btn1">شاهد المزيد<span--}}
                {{--class="glyphicon glyphicon-chevron-left"></span></a>--}}
                {{--<a href="#" class="link"></a>--}}
                {{--</div>--}}
                {{--</div>--}}

                {{--</div>--}}

                {{--</div>--}}
                {{--</div>--}}
                <div class="col-md-12 col-sm-12">
                    <div id="myCarousel-b" class="carousel slide" data-ride="carousel">

                        <!-- Wrapper for slides -->
                        <div class="carousel-inner">
                            @foreach($banners as $key => $banner)
                            <div class="item {{ $key == 0  ? "active" : ""}}">
                                <div class="adv" style="max-height: 200px;">
                                    <img src="{{ asset('public/'.$banner->image) }}" alt="">
                                    <a target="_blank" href="{{ $banner->link }}" class="the-btn1 btn-default">شاهد
                                        المزيد<span class="glyphicon glyphicon-chevron-left"></span></a>
                                    <a target="_blank" href="{{ $banner->link }}" class="link"></a>
                                </div>
                            </div>
                            @endforeach
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- end adv -->
    <!-- start section -->
    <div class="offers latest-offers">

        <div class="container">
            <div class="row">
                <div class="more-div">
                    <h2 class="new-title"> المنتجات الجديد </h2>
                    <a href="#" class="the-btn1 btn-default">شاهد المزيد<span class="glyphicon glyphicon-chevron-left"></span></a>
                </div>
                <div class="offers-block">

                    @if($products->count() > 1)
                    <div class="col-md-12">
                        @foreach($products as $product)
                        <div class="col-lg-4 col-md-6">
                            <div class="product-b" style="min-height: 106px;">
                                <div class="product-b-img">
                                    <img src="{{ asset('/public/'.$product->image) }}">
                                </div>
                                <div class="product-b-data">
                                    <h3>
                                        <a href="{{ route('product.details', $product->id) }}">{{ $product->name }}</a>
                                    </h3>
                                    <p>{{ strip_tags(substr($product->description,0, 25)) }}</p>
                                    <div>
                                        <img src="{{ request()->root() }}/public/assets/front/img/price.png">
                                        {{ $product->price }}
                                        ريال سعودى
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach

                    </div>

                    @endif
                </div>
            </div>
        </div>
        <div class="offers-shape offers-shape2"><img src="{{ request()->root() }}/public/assets/front/img/shape2.png">
        </div>
    </div>
    <!-- end section -->

    <!-- end footer -->
</div>
<!-- end wrapper -->


@endsection


@section('scripts')


<script>
    //product slider
    $('.cards').slick({
        autoplay: false,
        dots: false,
        autoplaySpeed: 1000,
        centerMode: true,
        slidesToShow: 4,
        slidesToScroll: 1,
        responsive: [{
                breakpoint: 1260,
                settings: {
                    arrows: false,
                    slidesToShow: 3
                }
            },
            {
                breakpoint: 992,
                settings: {
                    arrows: false,
                    slidesToShow: 2
                }
            },
            {
                breakpoint: 576,
                settings: {
                    arrows: false,
                    slidesToShow: 1
                }
            }
        ]
    });
    //main slider
    $('.main-slider').slick({
        autoplay: false,
        dots: false,
        autoplaySpeed: 1000,
        slidesToShow: 3,
        slidesToScroll: 1,
        responsive: [{
                breakpoint: 1260,
                settings: {
                    arrows: false,
                    slidesToShow: 3
                }
            },
            {
                breakpoint: 992,
                settings: {
                    arrows: false,
                    slidesToShow: 2
                }
            },
            {
                breakpoint: 576,
                settings: {
                    arrows: false,
                    slidesToShow: 1
                }
            }
        ]
    });
</script>
@endsection