@extends('admin.layouts.master')
@section('title',__('maincp.personal_page'))
@section('content')




    {{--<div class="row">--}}
    {{--<div class="col-xs-6 col-md-4 col-sm-4">--}}
    {{--<h3 class="page-title">@lang('maincp.personal_page')</h3>--}}
    {{--</div>--}}

    {{--<div class="m-t-15 col-xs-6 col-md-8 col-sm-8 text-right">--}}
    {{--<a href="{{ route('users.edit', auth()->id()) }}">--}}
    {{--<button type="button" class="btn btn-success">@lang('maincp.edit_data')</button>--}}


    {{--<button type="button" class="btn btn-custom  waves-effect waves-light"--}}
    {{--onclick="window.history.back();return false;">@lang('maincp.back')<span class="m-l-5"><i--}}
    {{--class="fa fa-reply"></i></span>--}}
    {{--</button>--}}
    {{--</a>--}}
    {{--</div>--}}
    {{--</div>--}}


    <div class="row">
        <div class="col-sm-8 col-sm-offset-2">
            <div class="btn-group pull-right m-t-15">
                <a href="{{ route('users.edit', auth()->id()) }}">
                    <button type="button" class="btn btn-success">@lang('maincp.edit_data')</button>


                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;">@lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </a>
            </div>
            <h4 class="page-title">@lang('maincp.personal_page')</h4>
        </div>
    </div>




    <div class="row">
        <div class="col-sm-8 col-sm-offset-2">
            <div class="bg-picture card-box">
                <div class="profile-info-name">
                    <div class="profile-info-detail">
                        <h4 class="m-t-0 m-b-0">@lang('maincp.personal_data')</h4>

                        {{--<div class="m-t-20 text-center">--}}

                        {{--<a data-fancybox="gallery"--}}
                        {{--href="{{ $helper->getDefaultImage($user->image, request()->root().'/assets/admin/custom/images/default.png') }}">--}}
                        {{--<img class="img-thumbnail"--}}
                        {{--src="{{ $helper->getDefaultImage($user->image, request()->root().'/assets/admin/custom/images/default.png') }}"/>--}}
                        {{--</a>--}}

                        {{--</div>--}}

                        <div class="panel-body">

                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.full_name') :</label>
                                <p>{{ $user->name or $user->username }}</p>
                            </div>

                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.mobile_number') :</label>
                                <p>{{ $user->phone }}</p>
                            </div>

                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.e_mail') :</label>
                                <p>{{ $user->email }}</p>
                            </div>

                            <div class="col-lg-12 col-xs-12">
                                <label>@lang('maincp.roles')</label>


                                <ul class="m-t-20">
                                    @foreach($user->roles as $role)
                                        <li>{{ $role->title }}</li>
                                    @endforeach
                                </ul>

                            </div>



                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>@lang('maincp.account_access_count'):</label>
                                <p>{{ $user->loggedin_app_count }} @lang('maincp.once')</p>
                            </div>



                            <div class="col-lg-4 col-xs-12 col-md-6 col-sm-6">
                                <label>@lang('maincp.the_last_date_and_time_of_entry_on_the_site') :</label>
                                <p>{{ date('H:i:s || Y/m/d', strtotime($user->loggedin_app_last)) }} </p>
                            </div>





                            {{--<div class="col-lg-3 col-xs-12">--}}
                            {{--<label>@lang('maincp.address'):</label>--}}
                            {{--<p>{{ $user->address }}</p>--}}
                            {{--</div>--}}
                            {{----}}


                        </div>
                    </div>
                    <!-- end card-box-->
                </div>
            </div>
        </div>
        <!--/ meta -->
    </div>

@endsection

