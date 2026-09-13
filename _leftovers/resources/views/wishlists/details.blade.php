@extends('layouts.master')

@section('content')

    <!-- Main Content-->
    <main class="main-content">
        <!--product-->
        <section class="product">
            <div class="container">
                <div class="main">
                    <div class="row">
                        <div class="col-md-4">
                            <ul id="lightSlider">
                                @foreach($product->images as $image)
                                    <li data-thumb="{{ asset('public/'.$image->image) }}">
                                        <img src="{{ asset('public/'.$image->image) }}">
                                    </li>
                                @endforeach

                            </ul>
                        </div>
                        <div class="col-md-8">
                            <h4><a href="javascript:;">{{ $product->name }}</a></h4>
                            <div dir="ltr">
                                <div class="rating">
                                    <input class="kv-rtl-theme-fas-star rating-loading" dir="rtl" value="1"
                                           data-size="xs">
                                    <div class="share">مشاركة</div>
                                </div>
                            </div>
                            <div class="h5"><span>البائع :</span>
                                <p><a href="#"
                                      class="the-market">
                                        {{ optional($product->user)->first_name .' '. optional($product->user)->last_name }}
                                    </a>
                                </p>
                            </div>
                            <div class="h5"><span>القسم :</span>
                                <p><a href="#" class="the-market">{{ optional($product->category)->name }}</a></p>
                            </div>

                            @if($product->materials != '')
                                <div class="h5"><span>مادة الصنع :</span>
                                    <p>{{ $product->materials }}</p>
                                </div>
                            @endif
                            <div class="h5"><span>بلد المنشأ :</span>
                                <p>{{ optional($product->country)->name }}</p>
                            </div>
                            <div class="h5"><span>الوصف :</span>
                                <p>{!! htmlspecialchars_decode($product->description) !!}</p>
                            </div>
                            <div class="h5"><span>السعر :</span>
                                <p>{{ $product->price }} SR</p>
                            </div>
                            <div class="h5"><span>الكميه :</span>
                                <input type="number" class="qty" min="1" value="1"/>
                            </div>
                            @if(count($product->sizes) > 0)
                                <div class="h5"><span>المقاس :</span>
                                    <ul class="size">
                                        @foreach($product->sizes as $size)
                                            <li>{{ $size }}</li>
                                        @endforeach

                                    </ul>
                                </div>
                            @endif
                            @if(count($product->colors) > 0)
                                <div class="h5"><span>اللون:</span>
                                    <ul class="color">
                                        @foreach($product->colors as $color)
                                            <li>{{ $color }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <div class="cart-btns">
                                <button class="btn-more addToCart" href="javascript:;" data-id="{{ $product->id }}">

                                    <i class="fas fa-shopping-cart"></i>

                                    اضافه إلى السله
                                </button>



                                <button class="btn-more addToWishlist" data-id="{{ $product->id }}">
                                    @if(auth()->check() && auth()->user()->isInFavorite($product->id))
                                        <i style="color: #ffffff;" class="fa fa-heart"></i>
                                    @else
                                        <i class="fas fa-heart"></i>
                                    @endif
                                    اضافه إلى المفضله
                                </button>


                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="reviews">
                                <h4 class="title">التعليقات</h4>
                                <div class="review">
                                    <div class="time"><i class="far fa-calendar-alt"></i><span>12 days ago</span></div>
                                    <div class="row">
                                        <div class="block-img"><img src="assets/imgs/home/store4.png"></div>
                                        <div class="block-details">
                                            <h5>احمد موسى</h5>
                                            <div class="rating-stars"><i class="fas fa-star"></i><i
                                                        class="fas fa-star"></i><i
                                                        class="far fa-star"></i><i class="far fa-star"></i><i
                                                        class="far fa-star"></i></div>
                                        </div>
                                    </div>

                                    <p class="rate">This product has average quality or below average This product has
                                        average quality or
                                        below average This product has average quality or below average This product has
                                        average quality or
                                        below average .</p>
                                </div>
                                <div class="review">
                                    <div class="time"><i class="far fa-calendar-alt"></i><span>12 days ago</span></div>
                                    <div class="row">
                                        <div class="block-img"><img src="assets/imgs/home/store4.png"></div>
                                        <div class="block-details">
                                            <h5>احمد موسى</h5>
                                            <div class="rating-stars"><i class="fas fa-star"></i><i
                                                        class="fas fa-star"></i><i
                                                        class="far fa-star"></i><i class="far fa-star"></i><i
                                                        class="far fa-star"></i></div>
                                        </div>
                                    </div>

                                    <p class="rate">This product has average quality or below average This product has
                                        average quality or
                                        below average This product has average quality or below average This product has
                                        average quality or
                                        below average .</p>
                                </div>
                            </div>
                            <a href="login.html" class="btn-review">سجل دخولك لتستطيع التعليق</a>
                        </div>
                        <div class="col-md-12">
                            <div class="related">
                                <h4 class="title">منتجات مشابهه</h4>
                                <div class="productItems">
                                    <div class="owl-carousel">

                                        @foreach($relatedProducts as $relatedProduct)
                                            <div class="productItem">
                                                <a href="{{ route('product.details',$relatedProduct->id) }}">
                                                    <div class="pro-wrap">
                                                        <div class="pro-hover"></div>

                                                        <img src="{{ $helper->getDefaultImage(asset('public/'.$relatedProduct->image) , asset('public/assets/front/img/logo.png')) }}"
                                                             alt="{{ $relatedProduct->name }}">
                                                    </div>
                                                    <div class="price"><span>{{ $relatedProduct->price }} رس</span>
                                                    </div>
                                                    <div class="pro-det">
                                                        <h5>{{ $relatedProduct->name }}</h5>
                                                        <div class="h6 section">
                                                            <img src="{{ asset('public/assets/front/imgs/home/tag.svg') }}">
                                                            <span>{{ optional($relatedProduct->category)->name }}</span>
                                                        </div>
                                                    </div>
                                                    <ul class="hover-mnu">
                                                        <li><span>{{ $relatedProduct->price }} رس</span></li>

                                                        <li>
                                                            <a href="javascript:;" class="addToWishlist"
                                                               data-id="{{ $relatedProduct->id }}">
                                                                @if(auth()->check() && auth()->user()->isInFavorite($relatedProduct->id))
                                                                    <i style="color: #ffffff;" class="fa fa-heart"></i>
                                                                @else
                                                                    <i class="fas fa-heart"></i>
                                                                @endif
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a href="javascript:;" class="addToCart"
                                                               data-id="{{ $relatedProduct->id }}">
                                                                <i class="fas fa-shopping-cart"></i>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </a>

                                            </div>

                                        @endforeach


                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <!-- End Main Content-->

@endsection


@section('scripts')

    <script>
        if ($('.kv-rtl-theme-fas-star').length) {
            $('.kv-rtl-theme-fas-star').rating({
                hoverOnClear: false,
                theme: 'krajee-fas',
                showCaption: 'false',
                containerClass: 'is-star'
            });
            $('.kv-rtl-theme-fas-star').on('rating:change', function (event, value, caption) {
                console.log(`${value}`);
            });
        }
    </script>
@endsection
