@extends('admin.layouts.master')
@section('title', __('maincp.user_data'))
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
                <h4 class="page-title">@lang('maincp.user_data') </h4>
            </div>
        </div>


        <div class="row">
            <div class="col-sm-12">
                <div class="card-box">

                    <div class="row">

                        <div class="col-xs-12 col-lg-12">
                            <h4>@lang('maincp.personal_data')</h4>
                            <hr>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('maincp.customer_name') :</label>
                            <p>{{ $user->name }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('maincp.mobile_number') :</label>
                            <p>{{ $user->phone }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('maincp.e_mail') :</label>
                            <p>{{ $user->email }}</p>
                        </div>

                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('trans.created_at') :</label>
                            <p>{{date('H:i:s || Y/m/d', strtotime($user->created_at))  }} </p>
                        </div>


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('maincp.account_access_count'):</label>
                            <p>{{ $user->loggedin_app_count > 0 ? $user->loggedin_app_count : 0 }} @lang('maincp.once')</p>
                        </div>


                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('maincp.the_last_date_and_time_of_entry_on_the_site') :</label>
                            <p>{{ date('H:i:s || Y/m/d', strtotime($user->loggedin_app_last)) }} </p>
                        </div>

                @if($user->is_user == 0)
                        <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                            <label>@lang('maincp.roles') :</label>
                            <ul>
                                @foreach($user->roles as $role)
                                    <li>{{ $role->title }}</li>
                                @endforeach
                            </ul>
                        </div>


                    </div>
                </div>
                @endif


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

