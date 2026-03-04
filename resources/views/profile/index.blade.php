@extends('layouts.master')

@section('content')
    <!-- Main Content-->
    <main class="main-content">
        <!--account content-->
        <section class="account">
            <div class="container">
                <div class="main">
                    <h3 class="title">صفحتي</h3>
                    <div class="row">
                        <div class="col-md-3">
                            <aside>
                                <ul class="sidebar">
                                    <li><a class="{{ request()->route()->getName() == 'profile' ? "active" : "" }}"
                                           href="{{ route('profile') }}">الحساب الشخصي</a></li>
                                    <li>
                                        <a class="{{ request()->route()->getName() == 'addresses.index' ? "active" : "" }}"
                                           href="{{ route('addresses.index') }}">عناوينى</a></li>
                                    <li><a href="#">الطلبات</a></li>
                                    <li><a href="{{ route('wishlist') }}">@lang('trans.wishlists')</a></li>
                                    <li><a href="{{ route('user.auth.logout') }}">تسجيل خروج</a></li>
                                </ul>
                            </aside>
                        </div>
                        <div class="col-md-9">
                            <h4 class="title">عدل حسابك الشخصي</h4>
                            <form class="submission-form" action="{{ route('profile.update') }}" method="post"
                                  data-parsley-validate novalidate>
                                {{ csrf_field() }}
                                <div class="row">


                                    @if(auth()->check() && auth()->user()->type == "vendor")
                                        <div class="col-md-6">
                                            <h5>اسم المتجر</h5>
                                            <input name="first_name" value="{{ $user->vendor_name }}"
                                                   class="form-control grey-input" type="text">
                                        </div>
                                    @endif


                                    @if(auth()->check() && auth()->user()->type == "vendor")
                                        <div class="col-md-6">
                                            <h5>اسم المتجر</h5>
                                            <select class="form-control" name="vendor_type">
                                                <option value="0" {{ auth()->user()->vendor_type == 0 ? "selected" : "" }}>
                                                    فرد
                                                </option>
                                                <option value="1" {{ auth()->user()->vendor_type == 1 ? "selected" : "" }}>
                                                    شركة
                                                </option>
                                            </select>
                                        </div>
                                    @endif


                                    <div class="col-md-6">
                                        <h5>الاسم الاول</h5>
                                        <input name="first_name" value="{{ $user->first_name }}"
                                               class="form-control grey-input" type="text">
                                    </div>

                                    <div class="col-md-6">
                                        <h5>الاسم الاخير</h5>
                                        <input name="last_name" value="{{ $user->last_name }}"
                                               class="form-control grey-input" type="text">
                                    </div>

                                    <div class="col-md-6">
                                        <h5>رقم الهاتف</h5>
                                        <input name="phone" value="{{ $user->phone }}" class="form-control grey-input"
                                               type="text">
                                    </div>
                                    <div class="col-md-6">
                                        <h5>البريد الالكتروني</h5>
                                        <input name="email" value="{{ $user->email }}" class="form-control grey-input"
                                               type="text">
                                    </div>
                                    <div class="col-md-12">
                                        <h5>الصوره الشخصيه</h5>
                                        <input name="image" class="form-control grey-input" type="file">
                                    </div>
                                    <div class="col-sm-6">
                                        <h5>الدوله</h5>
                                        <select id="selectCountry" class="form-control">
                                            @foreach($countries as $country)
                                                <option value="{{ $country->id }}" {{ $user->city->parent->id == $country->id ? "selected" : "" }}>{{ $country->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-sm-6">
                                        <h5>المدينه</h5>
                                        <select id="selectCity" name="location_id" class="form-control">
                                            <option value="">@lang('trans.select_city')</option>
                                            @foreach($user->city->parent->children as $city)
                                                <option value="{{ $city->id }}" {{ $user->city->id == $city->id ? "selected": "" }}>{{ $city->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>كلمه المرور</h5>
                                        <input name="password" class="form-control grey-input" type="password">
                                    </div>
                                    <div class="col-md-6">
                                        <h5>تاكيد كلمه المرور</h5>
                                        <input name="password_confirmation" class="form-control grey-input"
                                               type="password">
                                    </div>

                                    <div class="col-md-12">
                                        <div>
                                            <h5>اختر موقعك علي الخريطه</h5>
                                            {{--<div id="map2"></div>--}}
                                            <div style="width: 100%; height: 300px;">
                                                <div id="map-company" style="height: 285px;"></div>
                                                <input type="hidden" name="latitude" id="lat"
                                                       value="{{ $user->latitude }}"/>
                                                <input type="hidden" name="longitude" id="lng"
                                                       value="{{ $user->longitude }}"/>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-md-12">
                                        <button class="btn-bid">تعديل</button>
                                    </div>
                                </div>
                            </form>
                        </div>
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
        var marker, map, lat, lng;


        if ($('#lat').val() != "" && $('#lng').val() != "") {
            lat = parseFloat($('#lat').val());
            lng = parseFloat($('#lng').val());
        } else {
            lat = 24.774265;
            lng = 46.738586;
        }


        function initAutocomplete() {

            map = new google.maps.Map(document.getElementById('map-company'), {
                center: {lat: lat, lng: lng},
                zoom: 16,
                mapTypeId: 'roadmap'
            });


            // getLocation();

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
