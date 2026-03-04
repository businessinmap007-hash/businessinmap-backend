@extends('layouts.master')
@section('styles')
    <style>
        .remove-mb {
            margin-bottom: 0px !important;
        }

        .mb-15 {
            margin-bottom: 15px;
        }

    </style>
@endsection
@section('content')



    <!-- Main Content-->
    <main class="main-content">
        <!--login content-->
        <section class="login">
            <div class="container">
                <div class="main">
                    <div class="row">

                        <!-- start sign-in-up -->
                        <div class="sign-in-up">
                            <ul class="nav nav-pills">
                                <li><a class="active" data-toggle="pill" href="#sign-in">تسجيل دخول</a></li>
                                <li><a data-toggle="pill" class="enable-map" data-user="client" href="#sign-up">انشاء
                                        حساب عميل</a></li>
                                <li><a data-toggle="pill" class="enable-map" data-user="vendor" href="#sign-up-vendor">انشاء
                                        حساب تاجر</a></li>
                            </ul>

                            <div class="tab-content">
                                <div id="sign-in" class="tab-pane fade in active">
                                    <div>
                                        <form class="submission-form" action="{{ route('user.login') }}" method="post"
                                              data-parsley-validate novalidate>
                                            {{ csrf_field() }}
                                            <h5>البريد الالكتروني</h5>
                                            <div style="margin-bottom: 15px;">
                                                <input style="margin-bottom: 0px;" name="email"
                                                       class="form-control grey-input" type="text"
                                                       required
                                                       data-parsley-required-message="@lang('trans.email_required')"
                                                >

                                            </div>
                                            <h5>كلمه المرور</h5>
                                            <div style="margin-bottom: 15px;">
                                                <input style="margin-bottom: 0px;" name="password"
                                                       class="form-control grey-input"
                                                       type="password"
                                                       required
                                                       data-parsley-required-message="@lang('trans.password_required')">
                                            </div>
                                            <button type="submit" id="btn-submit" class="the-btn3">تسجيل الدخول</button>
                                            <div class="form-footer">
								<a href="#">نسيت كلمة المرور ؟</a>
							</div>
                                        </form>
                                    </div>
                                </div>
                                <div id="sign-up" class="tab-pane fade in">
                                    <form class="submission-form" action="{{ route('user.signup') }}" method="post"
                                          data-parsley-validate novalidate>
                                        {{ csrf_field() }}
                                        <input type="hidden" value="client" name="auth"/>
                                        <div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>الاسم الاول</h5>
                                                    <input name="first_name" class="form-control grey-input remove-mb"
                                                           required
                                                           data-parsley-required-message="@lang('trans.required')"
                                                           type="text">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>الاسم الاخير</h5>
                                                    <input name="last_name" class="form-control grey-input remove-mb"
                                                           required
                                                           data-parsley-required-message="@lang('trans.required')"
                                                           type="text">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>رقم الهاتف</h5>
                                                    <input type="text" data-parsley-type="number" name="phone"
                                                           class="form-control grey-input remove-mb"
                                                           required
                                                           data-parsley-trigger="keyup"
                                                           data-parsley-type-message="@lang('trans.phone_should_be_number')"
                                                           data-parsley-required-message="@lang('trans.phone_required')">
                                                </div>


                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>البريد الالكتروني</h5>
                                                    <input name="email"
                                                           class="form-control grey-input remove-mb"
                                                           type="text"
                                                           required
                                                           data-parsley-required-message="@lang('trans.email_required')">
                                                </div>
                                            </div>

                                            <div class="col-sm-6">
                                                <div class="mb-15">
                                                    <h5>الدولة</h5>
                                                    <select name="country" id="selectCountry" required
                                                            data-parsley-required-message="@lang('trans.required')"
                                                            class="remove-mb form-control">
                                                        <option value="">@lang('trans.select_country')</option>
                                                        @foreach($countries as $country)
                                                            <option value="{{ $country->id }}">{{ $country->{'name:'.app()->getLocale()} }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-15">
                                                    <h5>المدينه</h5>
                                                    <select name="location_id" id="selectCity" required
                                                            data-parsley-required-message="@lang('trans.required')"
                                                            class="remove-mb form-control">
                                                        <option value="">@lang('trans.select_city')</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>كلمه المرور</h5>
                                                    <input class="form-control grey-input remove-mb" type="password"
                                                           required
                                                           name="password" id="password1" data-parsley-trigger="keyup"
                                                           data-parsley-required-message="@lang('trans.password_required')">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>تاكيد كلمه المرور</h5>
                                                    <input class="form-control grey-input remove-mb" required
                                                           type="password"
                                                           name="password_confirmation"
                                                           data-parsley-equalto="#password1"
                                                           data-parsley-trigger="keyup"
                                                           data-parsley-equalto-message="تأكيد كلمة المرور غير متطابقة"
                                                           data-parsley-required-message="@lang('trans.password_confirmation_required')">
                                                </div>
                                            </div>
                                            {{--<div class="col-12">--}}
                                            {{--<div class="mb-15">--}}
                                            {{--<h5>اختر موقعك علي الخريطه</h5>--}}
                                            {{--<div id="map2"></div>--}}
                                            {{--<div style="width: 100%; height: 300px;">--}}
                                            {{--<div class="show-map" id="map-company" style="height: 300px;"></div>--}}
                                            {{--<input type="hidden" name="latitude" id="lat"/>--}}
                                            {{--<input type="hidden" name="longitude" id="lng"/>--}}
                                            {{--</div>--}}
                                            {{--</div>--}}
                                            {{--</div>--}}
                                        </div>
                                        <button type="submit" id="btn-submit" class="the-btn3">تسجيل</button>

                                    </form>


                                </div>

                                <div id="sign-up-vendor" class="tab-pane fade in">
                                    <form class="submission-form" action="{{ route('user.signup') }}" method="post"
                                          data-parsley-validate novalidate>
                                        {{ csrf_field() }}
                                        <input type="hidden" value="vendor" name="auth"/>
                                        <div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>اسم المتجر</h5>
                                                    <input name="vendor_name" class="form-control grey-input remove-mb"
                                                           required
                                                           data-parsley-required-message="@lang('trans.required')"
                                                           type="text">
                                                </div>
                                            </div>


                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>نوع المتجر</h5>
                                                    {{--<input name="vendor_name" class="form-control grey-input remove-mb"--}}
                                                           {{--required--}}
                                                           {{--data-parsley-required-message="@lang('trans.required')"--}}
                                                           {{--type="text">--}}

                                                    <select name="vendor_type" class="form-control grey-input remove-mb">
                                                        <option value="0">فرد</option>
                                                        <option value="1">شركة</option>
                                                    </select>
                                                </div>
                                            </div>


                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>الاسم الاول</h5>
                                                    <input name="first_name" class="form-control grey-input remove-mb"
                                                           required
                                                           data-parsley-required-message="@lang('trans.required')"
                                                           type="text">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>الاسم الاخير</h5>
                                                    <input name="last_name" class="form-control grey-input remove-mb"
                                                           required
                                                           data-parsley-required-message="@lang('trans.required')"
                                                           type="text">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>رقم الهاتف</h5>
                                                    <input type="text" data-parsley-type="number" name="phone"
                                                           class="form-control grey-input remove-mb"
                                                           required
                                                           data-parsley-trigger="keyup"
                                                           data-parsley-type-message="@lang('trans.phone_should_be_number')"
                                                           data-parsley-required-message="@lang('trans.phone_required')">
                                                </div>


                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>البريد الالكتروني</h5>
                                                    <input name="email"
                                                           class="form-control grey-input remove-mb"
                                                           type="text"
                                                           required
                                                           data-parsley-required-message="@lang('trans.email_required')">
                                                </div>
                                            </div>

                                            <div class="col-sm-6">
                                                <div class="mb-15">
                                                    <h5>الدولة</h5>
                                                    <select name="country" id="selectCountryVendor" required
                                                            data-parsley-required-message="@lang('trans.required')"
                                                            class="remove-mb form-control">
                                                        <option value="">@lang('trans.select_country')</option>
                                                        @foreach($countries as $country)
                                                            <option value="{{ $country->id }}">{{ $country->{'name:'.app()->getLocale()} }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="mb-15">
                                                    <h5>المدينه</h5>
                                                    <select name="location_id" id="selectCityVendor" required
                                                            data-parsley-required-message="@lang('trans.required')"
                                                            class="remove-mb form-control">
                                                        <option value="">@lang('trans.select_city')</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>كلمه المرور</h5>
                                                    <input class="form-control grey-input remove-mb" type="password"
                                                           required
                                                           name="password" id="password2" data-parsley-trigger="keyup"
                                                           data-parsley-required-message="@lang('trans.password_required')">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-15">
                                                    <h5>تاكيد كلمه المرور</h5>
                                                    <input class="form-control grey-input remove-mb" required
                                                           type="password"
                                                           name="password_confirmation"
                                                           data-parsley-equalto="#password2"
                                                           data-parsley-trigger="keyup"
                                                           data-parsley-equalto-message="تأكيد كلمة المرور غير متطابقة"
                                                           data-parsley-required-message="@lang('trans.password_confirmation_required')">
                                                </div>
                                            </div>
                                            {{--<div class="col-12">--}}
                                            {{--<div class="mb-15">--}}
                                            {{--<h5>اختر موقعك علي الخريطه</h5>--}}
                                            {{--<div id="map2"></div>--}}
                                            {{--<div style="width: 100%; height: 300px;">--}}
                                            {{--<div class="show-map" id="map-company" style="height: 300px;"></div>--}}
                                            {{--<input type="hidden" name="latitude" id="lat"/>--}}
                                            {{--<input type="hidden" name="longitude" id="lng"/>--}}
                                            {{--</div>--}}
                                            {{--</div>--}}
                                            {{--</div>--}}

                                            <div class="col-md-12">
                                                <div class="mb-15">
                                                    <button type="submit" id="btn-submit" class="the-btn3">تسجيل</button>
                                                </div>
                                            </div>
                                        </div>


                                    </form>


                                </div>
                            </div>

                        </div>
                    </div>
                    <!-- end sign-in-up -->

                </div>
            </div>
            </div>
        </section>
    </main>
    <!-- End Main Content-->
@endsection


@section('scripts')

    <script async defer
            src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDBr8fHyX4CFO0PMq4dxJlhPH8RrjXfyN8&amp;callback=initAutocomplete">
    </script>


    <script>

        var marker, map;

        function initAutocomplete() {

            map = new google.maps.Map(document.getElementsByClassName('show-map'), {
                center: {lat: 24.774265, lng: 46.738586},
                zoom: 16,
                mapTypeId: 'roadmap'
            });


            getLocation();

            if (!marker)
                marker = new google.maps.Marker({
                    position: {
                        lat: parseFloat($('#lat').val()),
                        lng: parseFloat($('#lng').val())
                    }, map: map
                });
            else
                marker.setPosition({lat: parseFloat($('#lat').val()), lng: parseFloat($('#lng').val())});


            var singleClick = false;
            google.maps.event.addListener(map, 'click', function (event) {
                singleClick = true;

                map.setZoom();
                var mylocation = event.latLng;
                map.setCenter(mylocation);

                codeLatLng(event.latLng.lat(), event.latLng.lng());


                $('#lat').val(event.latLng.lat());
                $('#lng').val(event.latLng.lng());


                setTimeout(function () {

                    if (singleClick === true) {
                        $("#mapLocation").modal('hide');
                    }

                    if (!marker)
                        marker = new google.maps.Marker({position: mylocation, map: map});
                    else
                        marker.setPosition(mylocation);

                }, 1000);

            });


            google.maps.event.addListener(map, 'dblclick', function (event) {
                singleClick = false;
            });


            // Create the search box and link it to the UI element.
            var input = document.getElementById('pac-input');
            var searchBox = new google.maps.places.SearchBox(input);
            map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

            // Bias the SearchBox results towards current map's viewport.
            map.addListener('bounds_changed', function () {
                searchBox.setBounds(map.getBounds());
            });

            var markers = [];
            // Listen for the event fired when the user selects a prediction and retrieve
            // more details for that place.
            searchBox.addListener('places_changed', function () {
                var places = searchBox.getPlaces();
                // var location = place.geometry.location;
                // var lat = location.lat();
                // var lng = location.lng();
                if (places.length == 0) {
                    return;
                }

                // Clear out the old markers.
                markers.forEach(function (marker) {
                    marker.setMap(null);
                });
                markers = [];

                // For each place, get the icon, name and location.
                var bounds = new google.maps.LatLngBounds();
                places.forEach(function (place) {
                    if (!place.geometry) {
                        console.log("Returned place contains no geometry");
                        return;
                    }
                    var icon = {
                        url: place.icon,
                        size: new google.maps.Size(71, 71),
                        origin: new google.maps.Point(0, 0),
                        anchor: new google.maps.Point(17, 34),
                        scaledSize: new google.maps.Size(25, 25)
                    };

                    // Create a marker for each place.
                    markers.push(new google.maps.Marker({
                        map: map,
                        icon: icon,
                        title: place.name,
                        position: place.geometry.location
                    }));

                    if (place.geometry.viewport) {
                        // Only geocodes have viewport.
                        bounds.union(place.geometry.viewport);
                    } else {
                        bounds.extend(place.geometry.location);
                    }
                    $('#lat').val(place.geometry.location.lat());
                    $('#lng').val(place.geometry.location.lng());


                    $('#lat-input').val(place.geometry.location.lat());
                    $('#lng-input').val(place.geometry.location.lng());


                });
                map.fitBounds(bounds);


            });


        }


        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(showPosition);


                // // Try HTML5 geolocation.
                // if (navigator.geolocation) {
                //     navigator.geolocation.getCurrentPosition(function(position) {
                //         var pos = {
                //             lat: position.coords.latitude,
                //             lng: position.coords.longitude
                //         };
                //
                //         infoWindow.setPosition(pos);
                //         infoWindow.setContent('Location found.');
                //         infoWindow.open(map);
                //         map.setCenter(pos);
                //     }, function() {
                //         handleLocationError(true, infoWindow, map.getCenter());
                //     });
                // } else {
                //     // Browser doesn't support Geolocation
                //     handleLocationError(false, infoWindow, map.getCenter());
                // }

            }

        }

        function showPosition(position) {

            map.setCenter({lat: position.coords.latitude, lng: position.coords.longitude});
            $("#lat").val(position.coords.latitude);
            $("#lng").val(position.coords.longitude);
            $("#lat-input").val(position.coords.latitude);
            $("#lng-input").val(position.coords.longitude);
            codeLatLng(position.coords.latitude, position.coords.longitude);


        }


        function codeLatLng(lat, lng) {

            var geocoder = new google.maps.Geocoder();
            var latlng = new google.maps.LatLng(lat, lng);
            geocoder.geocode({
                'latLng': latlng
            }, function (results, status) {


                if (status === google.maps.GeocoderStatus.OK) {
                    if (results[0]) {
                        // console.log(results[1].formatted_address);
                        $("#demoApp").html(results[0].formatted_address);
                        $("#addressApp").val(results[0].formatted_address);


                        // console.log(results);


                    } else {
                        //alert('No results found');
                    }
                } else {
                    alert('Geocoder failed due to: ' + status);
                }
            });
        }


        // $('#showMapModal').on('click', function () {
        //     $('#mapLocation').modal('show');
        // });

        function isValidEmailAddress(emailAddress) {
            var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
            return pattern.test(emailAddress);
        };


        $("#subscriptionBtn").on('click', function () {


            $(this).html('<span><i class="fas fa-1x fa-spinner fa-spin" style="margin-top: 6px"></i></span>');
            var subscriptionEmail = $("#subscriptionEmail").val();
            if (subscriptionEmail == "") {
                $("#error_email").html("{{ __('trans.email_required') }}");
                $(this).html('<span>Subscribe</span>');
                return;
            }

            if (!isValidEmailAddress(subscriptionEmail)) {
                $("#error_email").html("{{ __('trans.incorrect_email_format') }}");
                $(this).html('<span>Subscribe</span>');
                return;
            }

            $.ajax({
                type: 'POST',
                url: "{{ route('subscription.newsletter') }}",
                data: {email: subscriptionEmail},
                // cache: false,
                // contentType: false,
                // processData: false,
                success: function (data) {
                    $("#error_email").html("");

                    $("#subscriptionBtn").html("<span>Subscribe</span>");
                    if (data.status == 200) {
                        $("#subscriptionEmail").val("");
                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = '';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }

                    if (data.status == 400) {
                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = '';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }


                    if (data.status == 402) {
                        $("#error_email").html(data.message);

                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = '';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }

                },
                error: function (data) {
                }
            });


        });




        $("#selectCountry").on('change', function (e) {
            e.preventDefault();


            var countryId = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.all.selected.cities') }}',
                data: {countryId: countryId},
                dataType: 'json',
                success:
                    function (response) {
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



        $("#selectCountryVendor").on('change', function (e) {
            e.preventDefault();


            var countryId = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.all.selected.cities') }}',
                data: {countryId: countryId},
                dataType: 'json',
                success:
                    function (response) {



                        if (response) {
                            $("#selectCityVendor").empty();
                            $("#selectCityVendor").prop('disabled', false);
                            $("#selectCityVendor").append('<option value="" selected disabled>اختار المدينة </option>');
                            $.each(response, function (key, value) {
                                $("#selectCityVendor").append('<option value="' + value.id + '">' + value.name + '</option>');
                            });
                            $("#selectCityVendor").select2();
                        } else {
                            $("#selectCityVendor").empty();
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