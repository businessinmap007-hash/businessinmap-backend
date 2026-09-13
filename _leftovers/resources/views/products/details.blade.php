@extends('layouts.master')


@section('styles')

    <link rel="stylesheet" href="{{ request()->root() }}/public/assets/front/css/star-rating.min.css">
@endsection
@section('content')

    <!-- Main Content-->
    <main class="main-content">
        <!--product-->
        <section class="product">
            <div class="container">
                <div class="main">
                    <div class="row">
                        <div class="col-md-4">
                            @if($product->images->count() > 0)
                                <ul id="lightSlider">
                                    @foreach($product->images as $image)


                                        @if(file_exists( public_path().'/'.$image->image ))


                                            <li data-thumb="{{ asset('public/'.$image->image) }}">
                                                <img src="{{ asset('public/'.$image->image) }}">
                                            </li>


                                        @endif


                                    @endforeach
                                </ul>
                            @else
                                <ul id="lightSlider">

                                    <li data-thumb="{{ asset('public/'.$product->image) }}">
                                        <img src="{{ asset('public/'.$product->image) }}">
                                    </li>

                                </ul>
                            @endif
                        </div>
                        <div class="col-md-8">
                            <h4><a href="javascript:;">{{ $product->name }}</a></h4>
                            <div dir="ltr">
                                <div class="rating">
                                    <input class="kv-rtl-theme-fas-star rating-loading" id="avgRating" dir="rtl"
                                           value="{{ (int)$product->averageRating }}"
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
                                <div class="clearfix"></div>
                                <div id="allReviews">
                                    @if(count($product->ratings) > 0 )

                                        @foreach($product->ratings as $rate)

                                            @if(@getUserInfo($rate->user_id) != null)
                                                <div class="review">
                                                    <div class="time">
                                                        <i class="far fa-calendar-alt"></i><span>{{ $rate->created_at->diffForHumans() }}</span>
                                                    </div>
                                                    <div class="row">
                                                        <div class="block-img"
                                                             style="padding: 5px; border-radius: 40px;">

                                                            @if(@getUserInfo($rate->user_id) != null)
                                                                <img src="{{ asset('public/'.getUserInfo($rate->user_id)->image) }}">
                                                            @else
                                                                <img src="{{ asset('public/assets/images/default.png') }}">
                                                            @endif
                                                        </div>
                                                        <div class="block-details">

                                                            @if($user = getUserInfo($rate->user_id) != null)
                                                                <h5>
                                                                    {{ getUserInfo($rate->user_id)->first_name . ' ' . getUserInfo($rate->user_id)->last_name }}
                                                                </h5>
                                                            @endif
                                                            <div class="rating-stars">
                                                                @for($i = 1; $i <= 5; $i++)
                                                                    <i class="{{ $i > $rate->rating ? "far": "fas" }} fa-star"></i>
                                                                @endfor
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <p class="rate">{{ $rate->comment }}</p>
                                                </div>
                                            @endif
                                        @endforeach

                                    @endif
                                </div>

                            </div>

                            @if(!auth()->check())
                                <a href="login.html" class="btn-review">سجل دخولك لتستطيع التعليق</a>
                            @else
                                <div class="review">
                                    <div class="row">
                                        <div class="block-img"><img
                                                    src="{{ request()->root() }}/public/assets/front/imgs/home/store4.png">
                                        </div>
                                        <div class="block-details">
                                            <h5>{{  auth()->user()->first_name }} {{ auth()->user()->last_name }}</h5>
                                            <div class="rating">
                                                <input class="kv-rtl-theme-fas-star rating-loading" dir="rtl" value="0"
                                                       data-size="xs">
                                            </div>

                                        </div>
                                    </div>
                                    <div class="mt-20">
                                        <input hidden value="0" id="rate_value"/>
                                        <input hidden value="{{ $product->id }}" id="productId">
                                        <textarea name="comment" id="comment" class="form-control m-t-20"
                                                  placeholder="Leave your comment" required></textarea>

                                        <p id="errorMessage"></p>
                                        <button class="btn-review" id="submitReview">تعليق</button>
                                    </div>
                                </div>
                            @endif
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
    <script src="{{ request()->root() }}/public/assets/front/js/star-rating.min.js"></script>
    <script>
        if ($('.kv-rtl-theme-fas-star').length) {
            $('.kv-rtl-theme-fas-star').rating({
                hoverOnClear: false,
                theme: 'krajee-fas',
                showCaption: 'false',
                containerClass: 'is-star',
                disabled: false
            });
            $('.kv-rtl-theme-fas-star').on('rating:change', function (event, value, caption) {
                console.log(`${value}`);
                $('#rate_value').val(value);
            });
        }


        $("#submitReview").on('click', function () {

            var btnText = $('#submitReview').text();
            // for (instance in CKEDITOR.instances)
            //     CKEDITOR.instances[instance].updateElement();

            $("#submitReview").html('<i class="fas fa-spinner fa-spin"></i>').attr('disabled', true);
            var rate = $("#rate_value").val();
            var comment = $("#comment").val();
            var productId = $("#productId").val();
            if (comment == '') {
                $("#submitReview").html(btnText).attr('disabled', false);
                $('#errorMessage').css('color', '#ff4040').html('{{ __('trans.comment_field_required') }}');

                return;
            } else {
                $('#errorMessage').html('');
            }

            $.ajax({
                type: 'POST',
                url: '{{ route('rate.comments') }}',
                data: {rate, comment, productId},
                success: function (data) {
                    if (data.status == 200) {
                        showMessage(data.message, 'success');
                        $('#allReviews').append(data.comment).fadeIn(1000);
                        $("#avgRating").val(parseInt(data.avgRating));
                    } else if (data.status == 400) {
                        showMessage(data.message, 'error');
                    }
                    $("#submitReview").html(btnText).attr('disabled', false);
                }
            });


        });

    </script>
@endsection
