@extends('layouts.master')


@section('styles')
    <style>

        .table > tbody > tr > th{
            vertical-align: middle !important;
        }
    </style>
    @endsection
@section('content')

    <!-- Main Content-->
    <main class="main-content">
        <!--cart table-->
        <section class="cart">
            <div class="container">
                <div class="main">
                    <h3 class="title">عربة التسوق</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th scope="col">حذف</th>
                                <th scope="col" width="20%">الصوره</th>
                                <th scope="col">اسم المنتج</th>
                                <th scope="col" width="10%">الكميه</th>
                                <th scope="col">السعر</th>
                                <th scope="col" width="10%">الاجمالي</th>
                            </tr>
                            </thead>
                            <tbody id="table-details">
                            <?php $total = 0; ?>
                            @foreach($carts as $cart)
                                <?php $total += ($cart->price * $cart->qty); ?>
                                <tr>
                                    <th>
                                        <a class="deleteItemFromCart" data-id="{{ $cart->id }}">
                                            <span id="delete"><i class="far fa-trash-alt"></i></span>
                                        </a>
                                    </th>
                                    <th><img width="100%" src="{{ asset('public/'.optional($cart->product)->image) }}">
                                    </th>
                                    <th>
                                        <a href="{{ route('product.details',optional($cart->product)->id) }}">
                                            <span>{{ optional($cart->product)->name }}</span>
                                            {{--<div class="rating-stars">--}}
                                            {{--<i class="fas fa-star"></i>--}}
                                            {{--<i class="far fa-star"></i>--}}
                                            {{--<i class="far fa-star"></i>--}}
                                            {{--<i class="far fa-star"></i>--}}
                                            {{--<i class="far fa-star"></i>--}}
                                            {{--</div>--}}
                                        </a>
                                    </th>
                                    <th>
                                        <input class="increase changeCartQty form-control" data-id="{{ $cart->id }}"
                                               type="number" id="changeCartQty{{ $cart->id }}"
                                               value="{{ $cart->qty }}" min="1">
                                    </th>
                                    <th>
                                        <span class="curPrice">{{ number_format( optional($cart->product)->price , 2) }}</span><span>@lang('trans.currency')</span>
                                    </th>
                                    <th>
                                        <span class="increased"><strong
                                                    id="productTotal{{$cart['id']}}">{{ number_format( $cart->product->price * $cart->qty , 2) }}</strong></span>
                                        <span>@lang('trans.currency')</span>
                                    </th>
                                </tr>
                            @endforeach

                            </tbody>
                        </table>
                    </div>
                    <!-- payment and address-->
                    <div class="payment-det">

                        <div class="row">

                            <div class="col-md-12">
                                <h4 class="title">الاجمالي</h4>
                                <div class="total">
                                    <div class="row">
                                        <div class="col-6">
                                            <h5>المبلغ إجمالي</h5>
                                        </div>
                                        <div class="col-6">
                                            <h5>
                                                <span class="totalVal"><strong
                                                            id="cartTotal">{{ number_format($total, 2) }}</strong></span>
                                                <span>@lang('trans.currency')</span>
                                            </h5>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6">
                                            <h5>المبلغ الكلي</h5>
                                        </div>
                                        <div class="col-6">
                                            <h5>
                                                <span class="totalVal2"><strong
                                                            id="cartTotal">{{ number_format($total,2) }}</strong></span>
                                                <span>@lang('trans.currency')</span>
                                            </h5>
                                        </div>
                                    </div>
                                </div>
                                <div class="hidden">
                                    <div class="row">
                                        <div class="col-8">
                                            <input class="form-control grey-input" type="text"
                                                   placeholder="Apply Coupon Code">
                                        </div>
                                        <div class="col-4">
                                            <button class="btn-bid apply">Apply</button>
                                        </div>
                                    </div>
                                </div>


                                <a  href="{{ route('get.user.login') }}?from-page=cart" class="btn-review {{ auth()->check() ? "hidden" : "" }}">سجل دخولك للمتابعه</a>
                                <a href="#" class="btn-bid {{ !auth()->check() ? "hidden" : "" }}">اطلب الان</a>
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


        window.load = doShowAll();

        /*
    =====> Checking browser support.
    //This step might not be required because most modern browsers do support HTML5.
    */

        //Function below might be redundant.
        function CheckBrowser() {
            if ('localStorage' in window && window['localStorage'] !== null) {
                // We can use localStorage object to store data.
                return true;
            } else {
                return false;
            }
        }

        // Dynamically populate the table with shopping list items.
        //Step below can be done via PHP and AJAX, too.
        function doShowAll() {

            if (CheckBrowser()) {

                let products = [];
                if (localStorage.getItem('products')) {
                    products = JSON.parse(localStorage.getItem('products'));
                }


                $.each(products, function (key, value) {

                    var tr = '<tr>'
                        +
                        '<th><a href="javascript:;" data-id="' + value.productId + '" class="removeElementFromCart"><span id="delete"><i class="far fa-trash-alt"></i></span></a></th>\n' +
                        '<th><img src="{{ request()->root() }}/public/assets/front/imgs/cart/glasses.jpg"></th>\n' +
                        '<th><a href="#"><span>Sun Glass</span>\n' +
                        '<div class="rating-stars"><i class="fas fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i><i class="far fa-star"></i></div></a></th>\n' +
                        '<th>\n' +
                        '<input class="increase" type="number" value="' + value.qty + '" min="1">\n' +
                        '</th>\n' +
                        '<th><span class="curPrice">' + value.price + '</span><span>SR</span></th>\n' +
                        '<th><span class="increased">' + (value.price * value.qty) + '</span><span>SR</span></th>\n' +
                        '</tr>';
                    $("#CartTable").append(tr);
                });


                // var key = "";
                // var list = "<tr><th>Item</th><th>Value</th></tr>\n";
                // var i = 0;
                // //For a more advanced feature, you can set a cap on max items in the cart.
                // for (i = 0; i <= products.length - 1; i++) {
                //     key = localStorage.key(i);
                //     list += "<tr><td>" + key + "</td>\n<td>"
                //         + products[key]+ "</td></tr>\n";
                // }
                // // //If no item exists in the cart.
                // if (list == "<tr><th>Item</th><th>Value</th></tr>\n") {
                //     list += "<tr><td><i>empty</i></td>\n<td><i>empty</i></td></tr>\n";
                // }
                // // //Bind the data to HTML table.
                // // //You can use jQuery, too.
                // // document.getElementById('list').innerHTML = list;
                // //
                // console.log(list);

            } else {
                alert('Cannot save shopping list as your browser does not support HTML 5');
            }
        }


        $(".removeElementFromCart").on('click', function () {
            var productId = $(this).attr('data-id');

            removeProduct(productId);

        });

        function removeProduct(productId) {

            // Your logic for your app.

            let storageProducts = JSON.parse(localStorage.getItem('products'));
            let products = storageProducts.filter(product => product.productId !== productId);
            localStorage.setItem('products', JSON.stringify(products));

            console.log(products);
        }


        $(".deleteItemFromCart").on('click', function (e) {
            e.preventDefault();
            var $this = $(this);
            var cartId = $(this).attr('data-id');

            $.confirm({
                theme: 'modern',
                // closeIcon: true,
                animation: 'scale',
                type: 'red',
                // rtl: true,
                buttons: {
                    agree: {
                        text: 'Agree',
                        // With spaces and symbols
                        action: function () {

                            $("#cartTotal").html('<i class="fas fa-circle-notch fa-spin"></i>');
                            $.ajax({
                                type: 'post',
                                url: '{{ route('delete.item.cart') }}',
                                data: {cartId: cartId},
                                dataType: 'json',
                                success:
                                    function (response) {
                                        if (response.status == 200) {
                                            $this.parent().parent().remove();

                                            $("#cartTotal").html(response.cartTotal);

                                        }
                                    }
                            });
                        }
                    },
                    heyThere: {
                        text: 'Cancel',
                        // With spaces and symbols
                        action: function () {

                        }
                    }
                }
            });

        });


        $('.changeCartQty').on('change', function () {
            var cartId = $(this).attr('data-id');
            var qty = $(this).val();
            $('#changeCartQty'+ cartId).attr('type', 'text');

            $("#productTotal" + cartId).html('<i class="fas fa-circle-notch fa-spin"></i>');
            $("#cartTotal").html('<i class="fas fa-circle-notch fa-spin"></i>');


            $.ajax({
                type: 'post',
                url: '{{ route('update.item.cart') }}',
                data: {cartId: cartId, qty: qty},
                dataType: 'json',
                success:
                    function (response) {
                        if (response.status == 200) {
                            // $("#productTotal").html(response.productTotal);
                            $("#productTotal" + cartId).html(response.productTotal);
                            $("#cartTotal").html(response.cartTotal);
                            $('#changeCartQty'+cartId).attr('type', 'number');
                        }
                    }
            });

        });


        $("#selectCountry").on('change', function (e) {
            e.preventDefault();

            $("#indicatorImageCountry").css('display', 'initial');

            var countryId = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.all.selected.cities') }}',
                data: {countryId: countryId},
                dataType: 'json',
                success:
                    function (response) {
                        $("#indicatorImageCountry").css('display', 'none');


                        if (response) {
                            $("#selectCity").empty();
                            $("#selectCity").prop('disabled', false);
                            $("#selectCity").append('<option value="" selected disabled>اختار المدينة </option>');
                            $.each(response, function (key, value) {
                                $("#selectCity").append('<option value="' + value.id + '">' + value.name + '</option>');
                            });
                            $("#selectCity").select2();
                        } else {
                            $("#selectCity").empty();
                        }
                    },
                error: function (data) {
                    // $("#btn_submit").attr('disabled', 'disabled');
                    // $("#lay").show();
                },
                beforeSubmit: function () {
                    //do validation here
                },
                beforeSend: function () {
//                     $('#btn_submit').html("حفظ البيانات...");
                    // $("#btn_submit").attr('disabled', 'disabled');
                    // $("#lay").show();
                },
            });
        });
    </script>


@endsection
