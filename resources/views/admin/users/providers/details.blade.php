@extends('admin.layouts.master')
@section('title', "إدارة مزودي الخدمات")
@section('content')


    <form method="POST" action="{{ route('users.update', $user->id) }}" enctype="multipart/form-data"
          data-parsley-validate novalidate>
    {{ csrf_field() }}
    {{ method_field('PUT') }}



    <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back') <span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>

                </div>
                <h4 class="page-title">بيانات مزود الخدمة - {{ anotherLangWhenDefaultNotFound($user, 'name') }} </h4>
            </div>
        </div>


        <div class="row">
            <div class="col-sm-12">
                <div class="card-box">

                    <div class="row">

                        <div class="col-xs-12 col-lg-12">
                            <h4>بيانات مزود الخدمة</h4>
                            <hr>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>اسم مزود الخدمة</label>
                            <p>{{ $user->name }}</p>
                        </div>


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>نوع الخدمة</label>
                            <p>{{ @optional($user->service)->name }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>رقم الجوال:</label>
                            <p>{{ $user->phone }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>البريد الإلكتروني:</label>
                            <p>{{ $user->email }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label> رقم التصريح:</label>
                            <p>{{ $user->permit_no }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label> قيمة الفرد:</label>
                            <p>{{ $user->permit_no }}</p>
                        </div>


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label> عنوان مزود الخدمة :</label>
                            @if($user->address != "")
                                <p>{{ anotherLangWhenDefaultNotFound($user, 'address') }}</p>
                            @else
                                <br/>
                                --
                            @endif
                        </div>


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('trans.created_at') :</label>
                            <p>{{date('H:i:s || Y/m/d', strtotime($user->created_at))  }} </p>
                        </div>


                        <div class="col-lg-12 col-xs-12 col-md-6 col-sm-6">
                            <label> وصف الخدمة :</label>
                            @if($user->description != "")
                                <p>{{ anotherLangWhenDefaultNotFound($user, 'description') }}</p>
                            @else
                                --
                            @endif
                        </div>

                        <hr/>

                        <div class="col-lg-12 col-xs-12 col-md-6 col-sm-6">
                            <label> صور الخدمة :</label>
                            <br/>
                            @if($user->files->count() > 0)
                                @foreach($user->files as $file)
                                    <img src="{{ $file->url }}"
                                         style="margin: 4px; vertical-align: middle; width: 195px; height: 100px;"/>
                                @endforeach
                            @else
                                --
                            @endif
                        </div>


                        {{--<div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">--}}
                        {{--<label>@lang('maincp.account_access_count'):</label>--}}
                        {{--<p>{{ $user->loggedin_app_count > 0 ? $user->loggedin_app_count : 0 }} @lang('maincp.once')</p>--}}
                        {{--</div>--}}


                        {{--<div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">--}}
                        {{--<label>@lang('maincp.the_last_date_and_time_of_entry_on_the_site') :</label>--}}
                        {{--<p>{{ date('H:i:s || Y/m/d', strtotime($user->loggedin_app_last)) }} </p>--}}
                        {{--</div>--}}


                    </div>
                </div>


                <!--<div class="card-box table-responsive">-->
                <!--    <div class="col-xs-12 col-lg-12">-->

            <!--        <h4>@lang('maincp.number_of_orders')</h4>-->
                <!--        <hr>-->


                <!--        <div class="col-lg-3 col-md-6">-->
                <!--            <div class="card-box">-->
            <!--                <h4 class="header-title m-t-0 m-b-30">@lang('maincp.number_of_completed_applications') :</h4>-->
                <!--                <div class="widget-box-2">-->
                <!--                    <div class="widget-detail-2">-->
                <!--                                    <span class="pull-left m-t-5">-->
                <!--                            <a href="no.fully-oreders.html">-->
            <!--                                <button class="btn btn-info">@lang('maincp.details') </button>-->
                <!--                            </a>-->
                <!--                        </span>-->
                <!--                        <h2 class="m-b-0"> 8451 </h2>-->
            <!--                        <p class="text-muted m-b-0">@lang('maincp.order') </p>-->
                <!--                    </div>-->
                <!--                </div>-->
                <!--            </div>-->
                <!--        </div>-->

                <!--        <div class="col-lg-3 col-md-6">-->
                <!--            <div class="card-box">-->
            <!--                <h4 class="header-title m-t-0 m-b-30">@lang('maincp.order_not_available'):</h4>-->
                <!--                <div class="widget-box-2">-->
                <!--                    <div class="widget-detail-2">-->
                <!--                                    <span class="pull-left m-t-5">-->
                <!--                            <a href="notexist_orders.html">-->
            <!--                                <button class="btn btn-info">@lang('maincp.details') </button>-->
                <!--                            </a>-->
                <!--                        </span>-->
                <!--                        <h2 class="m-b-0"> 8451 </h2>-->
            <!--                        <p class="text-muted m-b-0">@lang('maincp.order') </p>-->
                <!--                    </div>-->
                <!--                </div>-->
                <!--            </div>-->
                <!--        </div>-->

                <!--        <div class="col-lg-3 col-md-6">-->
                <!--            <div class="card-box">-->
            <!--                <h4 class="header-title m-t-0 m-b-30">@lang('maincp.order_not_finish') :</h4>-->
                <!--                <div class="widget-box-2">-->
                <!--                    <div class="widget-detail-2">-->
                <!--                                    <span class="pull-left m-t-5">-->
            <!--                            <a href="nonfully_orders.html"><button class="btn btn-info">@lang('maincp.details') </button></a>-->
                <!--                        </span>-->
                <!--                        <h2 class="m-b-0"> 8451 </h2>-->
            <!--                        <p class="text-muted m-b-0">@lang('maincp.order') </p>-->
                <!--                    </div>-->
                <!--                </div>-->
                <!--            </div>-->
                <!--        </div>-->

                <!--        <div class="col-lg-3 col-md-6">-->
                <!--            <div class="card-box">-->
            <!--                <h4 class="header-title m-t-0 m-b-30">@lang('maincp.orders_being_priced') :</h4>-->
                <!--                <div class="widget-box-2">-->
                <!--                    <div class="widget-detail-2">-->
                <!--                                    <span class="pull-left m-t-5">-->
                <!--                            <a href="fully-prices.html">-->
            <!--                                <button class="btn btn-info">@lang('maincp.details') </button>-->
                <!--                            </a>-->
                <!--                        </span>-->
                <!--                        <h2 class="m-b-0"> 8451 </h2>-->
            <!--                        <p class="text-muted m-b-0">@lang('maincp.order') </p>-->
                <!--                    </div>-->
                <!--                </div>-->
                <!--            </div>-->
                <!--        </div>-->

                <!--        <div class="col-lg-3 col-md-6">-->
                <!--            <div class="card-box">-->
            <!--                <h4 class="header-title m-t-0 m-b-30">@lang('maincp.orders_that_have_been_priced') :</h4>-->
                <!--                <div class="widget-box-2">-->
                <!--                    <div class="widget-detail-2">-->
                <!--                                    <span class="pull-left m-t-5">-->
            <!--                            <a href="fully-priced.html"><button class="btn btn-info">@lang('maincp.details') </button></a>-->
                <!--                        </span>-->
                <!--                        <h2 class="m-b-0"> 8451 </h2>-->
            <!--                        <p class="text-muted m-b-0">@lang('maincp.order') </p>-->
                <!--                    </div>-->
                <!--                </div>-->
                <!--            </div>-->
                <!--        </div>-->

                <!--    </div>-->
                <!--</div>-->

            </div>

        </div>


    </form>

@endsection

