@extends('layouts.master')

@section('content')
    <!-- Main Content-->
    <main class="main-content">
        <!--products -->
        <section class="products">
            <div class="container">
                <div class="main">

                    <h3 class="title">قائمة المفضلة</h3>
                    <div class="row">

                        <div class="col-md-12">
                            @if(auth()->check())
                                <div class="productItems">
                                    <div class="row">
                                        @if(auth()->user()->wishlists->count() > 0)
                                            @foreach(auth()->user()->wishlists as $fav)

                                                <div class="col-6 col-md-4 col-lg-3" id="wishListItem{{ $fav->id }}">
                                                    <div class="productItem">
                                                        <a href="{{ route('product.details', $fav->product) }}">
                                                            <div class="pro-wrap">
                                                                <div class="pro-hover"></div>
                                                                <img src="{{ asset('public/'.optional($fav->product)->image) }}"
                                                                     alt="product">
                                                            </div>
                                                            <div class="price"><span>{{ optional($fav->product)->price }}
                                                                    رس</span></div>
                                                            <div class="pro-det">
                                                                <h5>{{ optional($fav->product)->name }}</h5>
                                                                <div class="h6 section">
                                                                    <img src="{{ asset('public/assets/front/imgs/home/tag.svg') }}">
                                                                    <span>
                                                                {{ optional($fav->product)->category->name }}
                                                            </span>
                                                                </div>
                                                            </div>
                                                            <ul class="hover-mnu">
                                                                <li><span>{{ optional($fav->product)->price }} رس</span>
                                                                </li>
                                                                <li>
                                                                    <a href="javascript:;" class="addToWishlist"
                                                                       data-id="{{ optional($fav->product)->id }}">
                                                                        @if(auth()->check() && auth()->user()->isInFavorite(optional($fav->product)->id))
                                                                            <i style="color: #ffffff;"
                                                                               class="fa fa-heart"></i>
                                                                        @else
                                                                            <i class="fas fa-heart"></i>
                                                                        @endif

                                                                    </a>
                                                                </li>

                                                                <li>
                                                                    <a href="javascript:;" class="addToCart"
                                                                       data-id="{{ optional($fav->product)->id }}">
                                                                        <i class="fas fa-shopping-cart"></i>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </a></div>
                                                </div>
                                            @endforeach

                                            @else
                                           <p class="text-center">
                                               @lang('trans.wishlist_empty')
                                           </p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{--<ul class="paging">--}}
                            {{--{{ auth()->user()->wishlists->links() }}--}}
                            {{--</ul>--}}
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
