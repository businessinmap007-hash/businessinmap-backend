@extends('layouts.master')

@section('content')
    <!-- Main Content-->
    <main class="main-content">
        <!--products -->
        <section class="products">
            <div class="container">
                <div class="main">
                    <div class="row">
                        <form action="{{ route('category.products') }}" id="filterForm">
                            <div class="col-md-4">
                                <input type="text" class="form-control inputs-filter" value="{{ request('s') }}"
                                       name="s" placeholder="ابحث بإسم المنتج">
                            </div>
                            <div class="col-md-4">
                                <select class="form-control inputs-filter" name="country">
                                    <option value="">بلد المنشأ</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" {{ request('country') == $country->id ? "selected" : "" }}>{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select class="form-control inputs-filter" name="category">
                                    <option value="all" {{ request('category') == 'all' ? 'selected' : "" }}>كل
                                        التصنيفات
                                    </option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? "selected" : "" }}>{{ $category->name }}</option>
                                    @endforeach

                                </select>
                            </div>
                            <div class="col-md-4 mt-20">
                                <select class="form-control inputs-filter" name="rate">
                                    <option value="">كل التقيمات</option>
                                    <option value="5" {{ request('rate') == '5' ? 'selected' : "" }}>5 نجوم</option>
                                    <option value="4" {{ request('rate') == '4' ? 'selected' : "" }}>4 نجوم</option>
                                    <option value="3" {{ request('rate') == '3' ? 'selected' : "" }}>3 نجوم</option>
                                    <option value="2" {{ request('rate') == '2' ? 'selected' : "" }}>2 نجوم</option>
                                    <option value="1" {{ request('rate') == '1' ? 'selected' : "" }}>1 نجوم</option>
                                </select>
                            </div>
                            <div class="col-md-4 mt-20">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control inputs-filter" name="price-from"
                                               placeholder="السعر من " value="{{ request('price-from') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control inputs-filter" name="price-to"
                                               placeholder="السعر الى " value="{{ request('price-to') }}">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mt-20">
                                <input type="submit" id="filterFormBtn" class="the-btn1 " value="بحث">
                            </div>
                        </form>
                    </div>
                    <h3 class="title">المنتجات</h3>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="side-bar">
                                <h4>تسوق باستخدام</h4>
                                <h5>التصنيف</h5>
                                <ul class="cat">
                                    <li class="active"><span class="dot">-</span><a href="#"><span>الالكترونيات </span></a>
                                    </li>
                                    <li><span class="dot">-</span><a href="#"><span>الأزياء</span></a></li>
                                    <li><span class="dot">-</span><a href="#"><span>المنزل والمطبخ</span></a></li>
                                    <li><span class="dot">-</span><a href="#"><span>الجمال والعطور</span></a></li>
                                </ul>
                                <h5>حالة الاستخدام</h5>
                                <ul class="cat">
                                    <li><span class="dot">-</span><a href="#"><span>جديد</span></a></li>
                                    <li><span class="dot">-</span><a href="#"><span>مستعمل</span></a></li>
                                </ul>
                                <h5>السعر</h5>
                                <h6 class="from">10 SR</h6>
                                <h6 class="to">10000 SR</h6>
                                <input class="span2" id="price" type="text" value="" data-slider-min="10"
                                       data-slider-max="10000"
                                       data-slider-step="5" data-slider-value="[250,2000]">
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="productItems">
                                <div class="row">

                                    @foreach($products as $product)
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="productItem"><a href="{{ route('product.details', $product) }}">
                                                    <div class="pro-wrap">
                                                        <div class="pro-hover"></div>
                                                        @if(file_exists( public_path().'/'.$product->image ))
                                                            <img src="{{ asset('public/'.$product->image)  }}"
                                                                 class=""
                                                                 alt="...">
                                                        @else
                                                            <img src="{{ request()->root() }}/public/assets/images/default.png"
                                                                 class="d-block"
                                                                 alt="...">
                                                        @endif
                                                    </div>
                                                    <div class="price"><span>{{ $product->price }} رس</span>
                                                    <ul class="hover-mnu">
                                                        <li><span>{{ $product->price }} رس</span></li>
                                                        <li>
                                                            <a href="javascript:;" class="addToWishlist"
                                                               data-id="{{ $product->id }}">
                                                                @if(auth()->check() && auth()->user()->isInFavorite($product->id))
                                                                    <i style="color: #ffffff;" class="fa fa-heart"></i>
                                                                @else
                                                                    <i class="fas fa-heart"></i>
                                                                @endif

                                                            </a>
                                                        </li>

                                                        <li>
                                                            <a href="javascript:;" class="addToCart"
                                                               data-id="{{ $product->id }}">
                                                                <i class="fas fa-shopping-cart"></i>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                    </div>
                                                    <div class="pro-det">
                                                        <h5>{{ $product->name }}</h5>
                                                        <div class="h6 section">
                                                            <img src="{{ asset('public/assets/front/imgs/home/tag.svg') }}">
                                                            <span>
                                                                {{ optional($product->category)->name }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                </a></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <ul class="paging">
                                {{ $products->appends($_GET)->links() }}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <!-- End Main Content-->
@endsection


@section('scripts')


@endsection
