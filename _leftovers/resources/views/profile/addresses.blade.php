@extends('layouts.master')

@section('content')



    <!-- Main Content-->
    <main class="main-content">
        <!--account content-->
        <section class="account">
            <div class="container">
                <div class="main">
                    <h3 class="title">My Address</h3>
                    <div class="row">
                        <div class="col-md-3">
                            <aside>
                                <ul class="sidebar">
                                    <li><a href="account.html">الحساب الشخصي</a></li>
                                    <li><a class="active" href="my-address.html">عناوينى</a></li>
                                    <li><a href="orders.html">الطلبات</a></li>
                                    <li><a href="purchases.html">المشتريات</a></li>
                                </ul>
                            </aside>
                        </div>
                        <div class="col-md-9">
                            <form class="needs-validation" novalidate method="post" id="form">
                                <div class="form-group">
                                    <label class="checkbox" for="radio1">
                                        <input id="radio1" type="radio" name="address" checked>السعودية - الدمام
                                    </label>
                                    <label class="checkbox" for="radio2">
                                        <input id="radio2" type="radio" name="address">اضافة عنوان جديد
                                    </label>
                                </div>
                                <div class="new-address">
                                    <div class="form-group">
                                        <label>البلد</label>
                                        <select class="form-control" name="country" id="selectCountry">
                                            <option value="">اختر بلد</option>
                                            @foreach($countries as $country)
                                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    {{--<div class="form-group">--}}
                                    {{--<label>المنطقة</label>--}}
                                    {{--<select class="form-control" name="region">--}}
                                    {{--<option>اختر منطقة</option>--}}
                                    {{--<option>...</option>--}}
                                    {{--</select>--}}
                                    {{--</div>--}}
                                    <div class="form-group">
                                        <label>المدينة</label>
                                        <select class="form-control" name="location_id" id="selectCity">
                                            <option value="">اختر مدينة</option>

                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <input id="inp1" type="text" name="neighborhood" class="form-control"
                                               placeholder="الحى "
                                               required/>
                                    </div>
                                    <div class="form-group">
                                        <input id="inp1" type="text" name="postal_code" class="form-control"
                                               placeholder="الرمز البريدى "
                                               required/>
                                    </div>
                                    <div class="form-group">
                                        <input id="inp1" type="text" name="street" class="form-control"
                                               placeholder="إسم الشارع "
                                               required/>
                                    </div>
                                    <h6>تحديد المكان</h6>
                                    <h6>اسحب المؤشر لتحديد المكان على الخريطة</h6>
                                    {{--<div id='gmap_canvas'></div>--}}
                                    {{--<div id="map2"></div>--}}
                                    <div style="width: 100%; height: 300px;">
                                        <div id="map-company" style="height: 285px;"></div>
                                        <input type="hidden" name="latitude" id="lat"
                                        />
                                        <input type="hidden" name="longitude" id="lng"
                                        />
                                    </div>
                                </div>
                                <div class="mb-5"></div>
                                <hr class="mb-4">

                                <div>
                                    <input type="submit" class="the-btn1 btn-default" value="حفظ">
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


        $('.new-address').hide();
        $('#radio1').click(function () {
            if ($(this).prop("checked") == true) {
                $('.new-address').slideUp('slow');
            }
        });
        $('#radio2').click(function () {
            if ($(this).prop("checked") == true) {
                $('.new-address').slideDown('slow');
            }
        });

        var marker, map;


        // if ($('#lat').val() != "" && $('#lng').val() != "") {
        //     lat = parseFloat($('#lat').val());
        //     lng = parseFloat($('#lng').val());
        // } else {
        //     lat = 24.774265;
        //     lng = 46.738586;
        // }


        function initAutocomplete() {

            map = new google.maps.Map(document.getElementById('map-company'), {
                center: {lat: 24.774265, lng: 46.738586},
                zoom: 16,
                mapTypeId: 'roadmap'
            });


            getLocation();


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


    </script>


    <script>
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
