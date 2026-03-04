@extends('admin.layouts.master')
@section('title', "تفاصيل العميل")

@section('styles')

<!-- Custom box css -->
<link href="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/custombox.min.css" rel="stylesheet">


@endsection

@section('content')





<!-- Page-Title -->
<div class="row">
    <div class="col-sm-12">
        <div class="btn-group pull-right m-t-15">
            <button type="button" class="btn btn-custom  waves-effect waves-light" onclick="window.history.back();return false;"> @lang('maincp.back') <span class="m-l-5"><i class="fa fa-reply"></i></span>
            </button>

        </div>
        <h4 class="page-title">تفاصيل العميل </h4>
    </div>
</div>


<div class="row">
    <div class="col-sm-12">
        <div class="card-box">

            <div class="row">

                <div class="col-xs-12 col-lg-12">


                    <div class="btn-group pull-right">

                        @if($user->social != null && $user->social->facebook != null )
                        <a href="{{ optional($user->social)->facebook  }}">
                            <i class="fa fa-facebook-official"></i>
                        </a>
                        @endif


                        @if($user->social != null && $user->social->twitter != null )
                        <a href="{{ optional($user->social)->twitter  }}">
                            <i class="fa fa-twitter-square"></i>
                        </a>
                        @endif


                        @if($user->social != null && $user->social->linkedin != null )
                        <a href="{{ optional($user->social)->linkedin  }}">
                            <i class="fa fa-linkedin-square"></i>
                        </a>
                        @endif

                        @if($user->social != null && $user->social->instagram != null )
                        <a href="{{ optional($user->social)->instagram  }}">
                            <i class="fa fa-instagram"></i>
                        </a>
                        @endif


                        @if($user->social != null && $user->social->youtube != null )
                        <a href="{{ optional($user->social)->youtube  }}">
                            <i class="fa fa-youtube"></i>
                        </a>
                        @endif


                    </div>
                    <h4>البيانات الشخصية</h4>

                    <hr>
                </div>
                <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                    <label>اسم العميل :</label>
                    <p>{{ $user->name }}</p>
                </div>
                <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                    <label>رقم الجوال :</label>
                    <p>{{ $user->phone }}</p>
                </div>
                <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                    <label>البريد الإلكتروني :</label>
                    <p>{{ $user->email }}</p>
                </div>
                <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                    <label>المدينة :</label>
                    <p>{{ $user->city != null ? optional($user->city->parent)->name .' - '.optional($user->city)->name : "--" }}</p>
                </div>
                <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                    <label>التقييم :</label>

                    <p>{!! $main_helper->html_rate_icons($user->averageRating) !!}</p>
                </div>
                <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                    <label>تاريح الإنشاء :</label>
                    <p>{{date('H:i:s || Y/m/d', strtotime($user->created_at))  }} </p>
                </div>

                <div class="col-xs-12">
                    <div class="row">


                        <div class="col-lg-3 col-xs-12 col-md-6 col-sm-6">
                            <label> الاعلانات :</label>
                            <p><a style="text-decoration: underline;" href="{{ route('sponsors.index') }}?businessId={{ $user->id }}">عرض الإعلانات</a>
                            </p>
                        </div>


                        <div class="col-lg-3 col-xs-12 col-md-6 col-sm-6">
                            <label> الالبومات :</label>
                            <p><a style="text-decoration: underline;" href="{{ route('albums.index') }}?businessId={{ $user->id }}">عرض الالبومات</a>
                            </p>
                        </div>


                        <div class="col-lg-3 col-xs-12 col-md-6 col-sm-6">
                            <label> ارصدة العميل :</label>

                            <div style="display: block">


                                <a href="#custom-modal-add-balabce" style="text-decoration: underline;" data-animation="fadein" data-plugin="custommodal" data-overlaySpeed="200" data-overlayColor="#36404a">إضافة رصيد للعميل</a>

                                <!-- Modal -->
                                <div id="custom-modal-add-balabce" class="modal-demo">
                                    <button type="button" class="close" onclick="Custombox.close();">
                                        <span>&times;</span><span class="sr-only">Close</span>
                                    </button>
                                    <h4 class="custom-modal-title">إضافة رصيد للعميل ({{ $user->name }})</h4>
                                    <div class="custom-modal-text">
                                        <form action="{{ route('transactions.charge', $user->id) }}" data-parsley-validate="" novalidate="" method="post" enctype="multipart/form-data" class="submission-form">

                                            {{ csrf_field() }}


                                            <div class="row">
                                                <div class="col-lg-12 ">
                                                    <div class="card-box">

                                                        {{--<h4 class="header-title m-t-0 m-b-30">@lang('maincp.data_about_the_application')  </h4>--}}

                                                        <div class="col-xs-12">
                                                            <div class="form-group">
                                                                <label for="userName">قيمة الشحن</label>
                                                                <input type="text" name="price" class="form-control" placeholder="قيمة الشحن">
                                                            </div>
                                                        </div>


                                                        <div class="clearfix"></div>

                                                        <div class="form-group text-right m-t-20">
                                                            <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit">
                                                                @lang('maincp.save_data')
                                                            </button>
                                                            <button type="button" onclick="Custombox.close();" class="btn btn-default waves-effect waves-light m-l-5 m-t-20">
                                                                @lang('maincp.disable')
                                                            </button>
                                                        </div>

                                                    </div>
                                                </div><!-- end col -->


                                            </div>
                                            <!-- end row -->
                                        </form>

                                    </div>
                                </div>

                            </div>


                        </div>


                        <div class="col-lg-3 col-xs-12 col-md-6 col-sm-6">
                            <label> هدايا ونسب الخصم :</label>


                            <div style="display: block">


                                <a href="#custom-modal-add-gifts" style="text-decoration: underline;" data-animation="fadein" data-plugin="custommodal" data-overlaySpeed="200" data-overlayColor="#36404a">تحديد نسب الهدايا
                                    والخصومات</a>

                                <!-- Modal -->
                                <div id="custom-modal-add-gifts" class="modal-demo">
                                    <button type="button" class="close" onclick="Custombox.close();">
                                        <span>&times;</span><span class="sr-only">Close</span>
                                    </button>
                                    <h4 class="custom-modal-title">إضافة نسب الخصم والهدايا للعميل
                                        ({{ $user->name }})</h4>
                                    <div class="custom-modal-text">
                                        <form action="{{ route('gifts.store', $user->id) }}" data-parsley-validate="" novalidate="" method="post" enctype="multipart/form-data" class="submission-form">

                                            {{ csrf_field() }}


                                            <div class="row">
                                                <div class="col-lg-8 col-lg-offset-2">
                                                    <div class="card-box">

                                                        {{--<h4 class="header-title m-t-0 m-b-30">@lang('maincp.data_about_the_application')  </h4>--}}


                                                        <div class="col-xs-12">
                                                            <div class="form-group">
                                                                <label for="userName">الاشهر العمولة</label>
                                                                <input type="text" name="commission_months" value="{{ $user->gifts != null ? $user->gifts->commission_months : 0}}" class="form-control" placeholder="الاشهر العمولة">
                                                            </div>
                                                        </div>


                                                        <div class="col-xs-12">
                                                            <div class="form-group">
                                                                <label for="userName">الاشهر المجانية</label>
                                                                <input type="text" name="free_months" value="{{ $user->gifts != null ? $user->gifts->free_months : 0}}" class="form-control" placeholder="الاشهر المجانية">
                                                            </div>
                                                        </div>


                                                        <div class="col-xs-12">
                                                            <div class="form-group">
                                                                <label for="userName">الحد الادني</label>
                                                                <input type="text" name="limit_months" value="{{ $user->gifts != null ? $user->gifts->limit_months : 0}}" class="form-control" placeholder="الحد الادني">
                                                            </div>
                                                        </div>

                                                        <div class="clearfix"></div>

                                                        <div class="form-group text-right m-t-20">
                                                            <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit">
                                                                @lang('maincp.save_data')
                                                            </button>
                                                            <button type="button" onclick="Custombox.close();" class="btn btn-default waves-effect waves-light m-l-5 m-t-20">
                                                                @lang('maincp.disable')
                                                            </button>
                                                        </div>

                                                    </div>
                                                </div><!-- end col -->


                                            </div>
                                            <!-- end row -->
                                        </form>

                                    </div>
                                </div>

                            </div>

                        </div>


                    </div>
                </div>


                <div class=" col-xs-12 ">
                    <div style="width: 100%; height: 300px;">
                        <div id="map" style="height: 285px;"></div>
                        <input id="lat" value="{{ $user->latitude }}" hidden />
                        <input id="lng" value="{{ $user->longitude }}" hidden />
                    </div>
                </div>


            </div>

        </div>
    </div>
</div>


<div class="row">
    <div class="col-sm-12">
        <div class="card-box">
            <div class="row">
                <div class="col-xs-12 col-lg-12">
                    <h4>المقالات</h4>
                    <hr>
                </div>
                <div class="col-xs-12">
                    <table id="datatable-fixed-header2" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>عنوان المقال</th>
                                <th>محتوي المقال</th>
                                <th>صور المقال</th>
                                <th>عدد المشاركات</th>
                                <th>التعليقات</th>
                                {{-- <th>@lang('trans.status')</th>--}}
                                <th>@lang('trans.created_at')</th>

                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->posts->where('type', 'post') as $key => $post)
                            <tr>
                                <td>
                                    {{ $key +1 }}
                                </td>
                                <td>
                                    {{ $post->title }}
                                </td>
                                <td>
                                    {{ substr( $post->body,0,50)  }}
                                    @if(strlen($post->body) > 50)
                                    <a href="#custom-modal{{ $post->id }}" data-animation="fadein" data-plugin="custommodal" data-overlaySpeed="200" data-overlayColor="#36404a">المزيد</a>

                                    <!-- Modal -->
                                    <div id="custom-modal{{ $post->id }}" class="modal-demo">
                                        <button type="button" class="close" onclick="Custombox.close();">
                                            <span>&times;</span><span class="sr-only">Close</span>
                                        </button>
                                        <h4 class="custom-modal-title">{{ $post->title }}</h4>
                                        <div class="custom-modal-text">
                                            {{ $post->body }}
                                        </div>
                                    </div>

                                    @endif
                                </td>
                                <td>


                                    @if($post->images->count() > 0)
                                    @foreach($post->images as $image)
                                    <a data-fancybox="gallery" href="{{ $helper->getDefaultImage(asset('public/'.$image->image), request()->root().'/public/assets/admin/images/about_img.jpg') }}">
                                        <img style="width: 35px; border-radius: 50%; height: 35px; border: 1px dotted #000;" src="{{ $helper->getDefaultImage(asset('public/'.$image->image), request()->root().'/public/assets/admin/images/about_img.jpg') }}" />
                                    </a>
                                    @endforeach
                                    @else
                                    <strong style="font-size: 12px;">لا يوجد صور</strong>
                                    @endif
                                </td>
                                <td><label class="label label-inverse">{{ $post->share_count }}</label></td>
                                <td>
                                    <label class="label label-info">{{ $post->comments->count() }}</label>

                                    <a href="{{ route('comments.index', $post->id) }}">مشاهدة</a>


                                </td>
                                <td>{{ $post->created_at->format('Y-m-d') }}</td>

                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>


            </div>

        </div>
    </div>
</div>



<div class="row">
    <div class="col-sm-12">
        <div class="card-box">
            <div class="row">
                <div class="col-xs-12 col-lg-12">
                    <h4>الوظائف</h4>
                    <hr>
                </div>
                <div class="col-xs-12">
                    <table id="datatable-fixed-header2" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>عنوان الوظيفة</th>
                                <th>وصف للوظيفة</th>
                                <th>صور الوظيفة</th>
                                <th>تاريخ إنتهاء الوظيفة</th>
                                <th>عدد المقدمين</th>
                                <th>عدد المشاركات</th>
                                <th>@lang('trans.created_at')</th>

                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->posts->where('type', 'job') as $key => $job)
                            <tr>
                                <td>
                                    {{ $key + 1 }}
                                </td>
                                <td>
                                    {{ $job->title }}
                                </td>
                                <td>
                                    {{ substr( $job->body,0, 50)  }}


                                    @if(strlen($job->body) > 50)
                                    <a href="#custom-modal{{ $job->id }}" data-animation="fadein" data-plugin="custommodal" data-overlaySpeed="200" data-overlayColor="#36404a">المزيد</a>

                                    <!-- Modal -->
                                    <div id="custom-modal{{ $job->id }}" class="modal-demo">
                                        <button type="button" class="close" onclick="Custombox.close();">
                                            <span>&times;</span><span class="sr-only">Close</span>
                                        </button>
                                        <h4 class="custom-modal-title">{{ $job->title }}</h4>
                                        <div class="custom-modal-text">
                                            {{ $job->body }}
                                        </div>
                                    </div>

                                    @endif
                                </td>
                                <td>
                                    @if($job->images->count() > 0)
                                    @foreach($job->images as $image)

                                    <a data-fancybox="gallery" href="{{ $helper->getDefaultImage(asset('public/'.$image->image), request()->root().'/public/assets/admin/images/about_img.jpg') }}">
                                        <img style="width: 35px; border-radius: 50%; height: 35px; border: 1px dotted #000;" src="{{ $helper->getDefaultImage(asset('public/'.$image->image), request()->root().'/public/assets/admin/images/about_img.jpg') }}" />
                                    </a>
                                    @endforeach

                                    @else
                                    <strong style="font-size: 12px;">لا يوجد صور</strong>
                                    @endif
                                </td>
                                <td>
                                    {{ $job->expire_at }}
                                </td>

                                <td>

                                    <label class="label label-purple"> {{ $job->applies->count() }}</label>


                                    @if($job->applies->count() > 0)
                                    <a href="#custom-modal{{ $job->id.'--'.$key }}" style="font-size: 13px;" data-animation="fadein" data-plugin="custommodal" data-overlaySpeed="200" data-overlayColor="#36404a">مشاهدة</a>

                                    <!-- Modal -->
                                    <div id="custom-modal{{ $job->id.'--'.$key }}" class="modal-demo">
                                        <button type="button" class="close" onclick="Custombox.close();">
                                            <span>&times;</span><span class="sr-only">Close</span>
                                        </button>
                                        <h4 class="custom-modal-title">{{ $job->title }}</h4>
                                        <div class="custom-modal-text">
                                            <table id="datatable-fixed-header2" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                                                <thead>

                                                    <tr>
                                                        <th>#</th>
                                                        <th>المقدم علي الوظيفة</th>
                                                        <th>تاريخ التقديم</th>
                                                        <th>تفاصيل</th>


                                                    </tr>

                                                </thead>
                                                <tbody>

                                                    @foreach($job->applies as $key => $row)
                                                    <tr>
                                                        <th style="text-align: right;direction: ltr;">{{ $key + 1 }}</th>
                                                        <th style="text-align: right;direction: ltr;">{{ optional($row->user)->name }}</th>
                                                        <th style="text-align: right;direction: ltr;">{{ $row->created_at->format('Y-m-d H:i:s A') }}</th>
                                                        <th style="text-align: right;direction: ltr;">

                                                            <a class="btn btn-xs btn-info">
                                                                <i class="fa fa-eye"></i>
                                                            </a>
                                                        </th>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    @endif

                                </td>


                                <td><label class="label label-inverse">{{ $job->share_count }}</label></td>
                                <td>{{ $job->created_at->format('Y-m-d') }}</td>

                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>


            </div>

        </div>
    </div>
</div>




<div class="row">
    <div class="col-sm-6">
        <div class="card-box">
            <div class="row">
                <div class="col-xs-12 col-lg-12">
                    <h4>البزنس والاقسام المتابعين</h4>
                </div>
                <div class="col-xs-6">
                    <table id="datatable-fixed-header2" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->followers as $key => $follower)
                            <tr>
                                <td>
                                    {{ $key + 1 }}
                                </td>
                                <td>
                                    {{ $follower->name }}
                                </td>
                                <td>
                                    <a class="btn btn-xs btn-info" href="#"><i class="fa fa-eye"></i></a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="col-xs-6">
                    <table id="datatable-fixed-header2" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->categoryFollows as $key => $cat)
                            <tr>
                                <td>
                                    {{ $key + 1 }}
                                </td>
                                <td>
                                    {{ $cat->name }}
                                </td>
                                <td>
                                    <a class="btn btn-xs btn-info" href="#"><i class="fa fa-eye"></i></a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>


            </div>

        </div>
    </div>
    <div class="col-sm-6">
        <div class="card-box">
            <div class="row">
                <div class="col-xs-12 col-lg-12">
                    <h4>البزنس والاقسام المستهدفين</h4>
                </div>
                <div class="col-xs-6">
                    <table id="datatable-fixed-header2" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->targets as $key => $follower)
                            <tr>
                                <td>
                                    {{ $key + 1 }}
                                </td>
                                <td>
                                    {{ $follower->name }}
                                </td>
                                <td>
                                    <a class="btn btn-xs btn-info" href="#"><i class="fa fa-eye"></i></a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="col-xs-6">
                    <table id="datatable-fixed-header2" class="table table-striped table-bordered dt-responsive nowrap" cellspacing="0" width="100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($user->categoryTargets as $key => $cat)
                            <tr>
                                <td>
                                    {{ $key + 1 }}
                                </td>
                                <td>
                                    {{ $cat->name }}
                                </td>
                                <td>
                                    <a class="btn btn-xs btn-info" href="#"><i class="fa fa-eye"></i></a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>


            </div>

        </div>
    </div>

</div>








@endsection


@section('scripts')

<!-- Modal-Effect -->
<script src="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/custombox.min.js"></script>
<script src="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/legacy.min.js"></script>
<script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDBr8fHyX4CFO0PMq4dxJlhPH8RrjXfyN8&amp;callback=initAutocomplete">
</script>

<script>
    $(document).ready(function() {
        $('.custom-for-comments').parent.parent.css('width', '80% !important');
    });


    var marker, map, lat, lng;


    if ($('#lat').val() != "" && $('#lng').val() != "") {
        lat = parseFloat($('#lat').val());
        lng = parseFloat($('#lng').val());
    } else {
        lat = 24.774265;
        lng = 46.738586;
    }


    function initAutocomplete() {

        map = new google.maps.Map(document.getElementById('map'), {
            center: {
                lat: lat,
                lng: lng
            },
            zoom: 16,
            mapTypeId: 'roadmap'
        });


        // getLocation();

        if (!marker)
            marker = new google.maps.Marker({
                position: {
                    lat: parseFloat($('#lat').val()),
                    lng: parseFloat($('#lng').val())
                },
                map: map
            });
        else
            marker.setPosition({
                lat: parseFloat($('#lat').val()),
                lng: parseFloat($('#lng').val())
            });


        var singleClick = false;
        google.maps.event.addListener(map, 'click', function(event) {
            singleClick = true;

            map.setZoom();
            var mylocation = event.latLng;
            map.setCenter(mylocation);

            codeLatLng(event.latLng.lat(), event.latLng.lng());


            $('#lat').val(event.latLng.lat());
            $('#lng').val(event.latLng.lng());


            setTimeout(function() {

                if (singleClick === true) {
                    $("#mapLocation").modal('hide');
                }

                if (!marker)
                    marker = new google.maps.Marker({
                        position: mylocation,
                        map: map
                    });
                else
                    marker.setPosition(mylocation);

            }, 1000);

        });


        google.maps.event.addListener(map, 'dblclick', function(event) {
            singleClick = false;
        });


        // Create the search box and link it to the UI element.
        var input = document.getElementById('pac-input');
        var searchBox = new google.maps.places.SearchBox(input);
        map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

        // Bias the SearchBox results towards current map's viewport.
        map.addListener('bounds_changed', function() {
            searchBox.setBounds(map.getBounds());
        });

        var markers = [];
        // Listen for the event fired when the user selects a prediction and retrieve
        // more details for that place.
        searchBox.addListener('places_changed', function() {
            var places = searchBox.getPlaces();
            // var location = place.geometry.location;
            // var lat = location.lat();
            // var lng = location.lng();
            if (places.length == 0) {
                return;
            }

            // Clear out the old markers.
            markers.forEach(function(marker) {
                marker.setMap(null);
            });
            markers = [];

            // For each place, get the icon, name and location.
            var bounds = new google.maps.LatLngBounds();
            places.forEach(function(place) {
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

        map.setCenter({
            lat: position.coords.latitude,
            lng: position.coords.longitude
        });
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
        }, function(results, status) {


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
</script>
@endsection