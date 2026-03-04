@extends('layouts.master')

@section('content')
    <!-- Main Content-->
    <main class="main-content">
        <!--products -->
        <section id="deals">
            <div class="container">
                <div class="main">
                    <h3 class="title">العروض</h3>
                    <div class=" deal-category-items">
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                        <div class="deal-category-item">
                            <div class="deal-category-item-img"><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png"></div>
                            <div class="deal-category-item-title"><a href="">قسم رئيسى</a></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="productItems">
                                <div class="row">
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod1.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod2.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod3.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod4.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod5.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod6.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod21.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod22.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod23.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod24.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod25.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="productItem"><a href="product.html">
                                                <div class="pro-wrap">
                                                    <div class="pro-hover"></div><img src="{{ request()->root() }}/public/assets/front/imgs/home/prod26.png" alt="product">
                                                </div>
                                                <div class="price"><span>100 رس</span></div>
                                                <div class="pro-det">
                                                    <h5>أسم المنتج هنا</h5>
                                                    <div class="h6 section"><img src="{{ request()->root() }}/public/assets/front/imgs/home/tag.svg"><span>كاتيجورى</span></div>
                                                </div>
                                                <ul class="hover-mnu">
                                                    <li><span>100 رس</span></li>
                                                    <li><i class="fas fa-heart"></i></li>
                                                    <li><i class="fas fa-shopping-cart"></i></li>
                                                </ul>
                                            </a></div>
                                    </div>
                                </div>
                            </div>
                            <ul class="paging">
                                <li class="active"><a href="#"><span>1</span></a></li>
                                <li><a href="#"><span>2</span></a></li>
                                <li><a href="#"><span>3</span></a></li>
                                <li><a href="#"><span>4</span></a></li>
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
