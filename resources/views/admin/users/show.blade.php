@extends('admin.layouts.master')
@section('title', __('maincp.user_data'))

@section('styles')

    <!-- Custom box css -->
    <link href="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/custombox.min.css" rel="stylesheet">


@endsection

@section('content')





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



                        <div class="col-lg-3 col-xs-12 col-md-6 col-sm-6">
                            <label> هدايا ونسب الخصم :</label>


                            <div style="display: block">


                                <a href="#custom-modal-add-gifts" style="text-decoration: underline;"
                                   data-animation="fadein" data-plugin="custommodal"
                                   data-overlaySpeed="200" data-overlayColor="#36404a">تحديد نسب الهدايا
                                    والخصومات</a>

                                <!-- Modal -->
                                <div id="custom-modal-add-gifts" class="modal-demo">
                                    <button type="button" class="close" onclick="Custombox.close();">
                                        <span>&times;</span><span class="sr-only">Close</span>
                                    </button>
                                    <h4 class="custom-modal-title">إضافة نسب الخصم والهدايا للعميل
                                        ({{ $user->name }})</h4>
                                    <div class="custom-modal-text">
                                        <form action="{{ route('gifts.store', $user->id) }}"
                                              data-parsley-validate="" novalidate="" method="post"
                                              enctype="multipart/form-data" class="submission-form">

                                            {{ csrf_field() }}


                                            <div class="row">
                                                <div class="col-lg-8 col-lg-offset-2">
                                                    <div class="card-box">

                                                        {{--<h4 class="header-title m-t-0 m-b-30">@lang('maincp.data_about_the_application')  </h4>--}}


                                                        <div class="col-xs-12">
                                                            <div class="form-group">
                                                                <label for="userName">الاشهر العمولة</label>
                                                                <input type="text" name="commission_months"
                                                                       value="{{ $user->gifts != null ? $user->gifts->commission_months : 0}}"
                                                                       class="form-control"
                                                                       placeholder="الاشهر العمولة">
                                                            </div>
                                                        </div>


                                                        <div class="col-xs-12">
                                                            <div class="form-group">
                                                                <label for="userName">الاشهر المجانية</label>
                                                                <input type="text" name="free_months"
                                                                       value="{{ $user->gifts != null ? $user->gifts->free_months : 0}}"
                                                                       class="form-control"
                                                                       placeholder="الاشهر المجانية">
                                                            </div>
                                                        </div>


                                                        <div class="col-xs-12">
                                                            <div class="form-group">
                                                                <label for="userName">الحد الادني</label>
                                                                <input type="text" name="limit_months"
                                                                       value="{{ $user->gifts != null ? $user->gifts->limit_months : 0}}"
                                                                       class="form-control"
                                                                       placeholder="الحد الادني">
                                                            </div>
                                                        </div>

                                                        <div class="clearfix"></div>

                                                        <div class="form-group text-right m-t-20">
                                                            <button class="btn btn-primary waves-effect waves-light m-t-20"
                                                                    type="submit">
                                                                @lang('maincp.save_data')
                                                            </button>
                                                            <button type="button" onclick="Custombox.close();"
                                                                    class="btn btn-default waves-effect waves-light m-l-5 m-t-20">
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




                    @if($user->is_user == 0)
                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>@lang('maincp.roles') :</label>
                                <ul>
                                    @foreach($user->roles as $role)
                                        <li>{{ $role->title }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

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




@endsection


@section('scripts')


    <script src="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/custombox.min.js"></script>
@endsection

