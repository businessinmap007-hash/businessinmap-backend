@extends('admin.layouts.master')

@section('title' , __('maincp.public_notication'))

@section('content')

    <form data-parsley-validate novalidate method="POST" action="{{ route('send.public.notifications') }}"
          enctype="multipart/form-data">
    {{ csrf_field() }}
    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="btn-group pull-right m-t-15">


                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>


                </div>
                <h4 class="page-title">@lang('maincp.public_notication')</h4>
            </div>
        </div>


        <div class="row">


            <div class="col-lg-8 col-lg-offset-2">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">@lang('maincp.content_notify')</h4>
                    {{--<div class="row">--}}


                        {{--<div class="col-lg-6">--}}
                            {{--<div class="form-group">--}}
                                {{--<label for="userName">@lang('trans.users_type') *</label>--}}

                                {{--<select class="form-control" name="usersType">--}}
                                    {{--<option value="all">@lang('trans.allUsers')</option>--}}
                                    {{--<option value="companies">@lang('trans.transporters')</option>--}}
                                    {{--<option value="clients">@lang('trans.stations')</option>--}}
                                    {{--<option value="drivers">@lang('trans.drivers')</option>--}}
                                {{--</select>--}}


                                {{--<input type="text" name="notification_title" parsley-trigger="change"--}}
                                {{--placeholder="@lang('maincp.title_notify_arabic')..." class="form-control"--}}
                                {{--id="userName">--}}
                            {{--</div>--}}
                        {{--</div>--}}


                        {{--<div class="col-lg-6">--}}
                            {{--<div class="form-group">--}}
                                {{--<label for="userName">@lang('trans.cities') *</label>--}}

                                {{--<select class="form-control" name="city">--}}
                                    {{--<option value="all">@lang('trans.allCities')</option>--}}
                                    {{--@foreach($cities as $city)--}}
                                        {{--<option value="{{ $city->id }}">{{ $city->name }}</option>--}}
                                    {{--@endforeach--}}
                                {{--</select>--}}
                            {{--</div>--}}
                        {{--</div>--}}

                    {{--</div>--}}


                    <div class="form-group">
                        <label for="userName">@lang('trans.title_notify_arabic') *</label>
                        <input type="text" name="notification_title" parsley-trigger="change" required
                               placeholder="@lang('trans.title_notify_arabic')..." class="form-control requiredField "
                               id="userName">
                    </div>

                    <div class="form-group">
                        <label for="userName">@lang('trans.title_notify_en') *</label>
                        <input type="text" name="notification_title_en" parsley-trigger="change" required
                               placeholder="@lang('trans.title_notify_en')..." class="form-control requiredField"
                               id="userName">
                    </div>


                    <div class="form-group {{ $errors->has('notification_message') ? 'has-error' : '' }}">
                        <label for="notification_message">@lang('trans.content_notify_arabic') </label>
                        <textarea  class="form-control requiredField " name="notification_message" required></textarea>
                    </div>


                    <div class="form-group {{ $errors->has('notification_message_en') ? 'has-error' : '' }}">
                        <label for="notification_message_en">@lang('trans.content_notify_en') </label>
                        <textarea  class="form-control requiredField" name="notification_message_en" required></textarea>
                    </div>


                    <div class="form-group text-right m-b-0 ">
                        <button class="btn btn-warning waves-effect waves-light m-t-20"
                                type="submit">@lang('maincp.save_data')
                        </button>
                        <button onclick="window.history.back();return false;"
                                class="btn btn-default waves-effect waves-light m-l-5 m-t-20"> @lang('maincp.disable')
                        </button>
                    </div>


                </div>
            </div><!-- end col -->

        {{--<div class="col-lg-4">--}}
        {{--<div class="card-box" style="overflow: hidden;">--}}

        {{--<h4 class="header-title m-t-0 m-b-30">الصورة</h4>--}}

        {{--<div class="form-group">--}}
        {{--<input type="file" name="image" class="dropify" data-max-file-size="6M"/>--}}
        {{--</div>--}}

        {{--</div>--}}
        {{--</div>--}}


        <!-- end col -->


        </div>
        <!-- end row -->
    </form>


@endsection


@section('scripts')



@endsection



