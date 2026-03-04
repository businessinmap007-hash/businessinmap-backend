@extends('admin.layouts.master')
@section('title' , __('maincp.add_user'))

@section('styles')


    <style>
        #parsley-id-multiple-roles li{
            position: absolute;
            top: -22px;
            right: 80px;
        }
    </style>

@endsection
@section('content')


    <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" data-parsley-validate
          novalidate class="submission-form">
    {{ csrf_field() }}

    <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-8 col-sm-offset-2" >
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title">@lang('maincp.manage_individuals')</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-8 col-sm-offset-2" >
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">@lang('maincp.add_user') </h4>
                    
                    
                    <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">اسم الاول *</label>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" class="form-control" required
                                   placeholder="اسم المستخدم AR..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="اسم الاول مطلوب"
                                   data-parsley-maxlength="55"
                                   data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                   data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('first_name'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('first_name') }}
                                </p>
                            @endif
                        </div>

                    </div>

                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">اسم الاخير *</label>
                                <input type="text" name="last_name" value="{{ old('last_name') }}" class="form-control" required
                                       placeholder="اسم المستخدم EN..."
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="اسم الاخير مطلوب"
                                       data-parsley-maxlength="55"
                                       data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                       data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                       data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                       data-parsley-minlength="3"
                                       data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"

                                />
                                <p class="help-block" id="error_userName"></p>
                                @if($errors->has('first_name'))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('first_name') }}
                                    </p>
                                @endif
                            </div>

                        </div>




                    {{--<div class="col-xs-6">--}}
                    {{--<div class="form-group{{ $errors->has('username') ? ' has-error' : '' }}">--}}
                    {{--<label for="usernames">@lang('maincp.name') *</label>--}}
                    {{--<input type="text" name="username" value="{{ old('username') }}" class="form-control"--}}
                    {{--required placeholder="@lang('maincp.name') ..."--}}
                    {{--data-parsley-required-message="اسم المستخدم مطلوب"/>--}}
                    {{--@if($errors->has('username'))--}}
                    {{--<p class="help-block">--}}
                    {{--{{ $errors->first('username') }}--}}
                    {{--</p>--}}
                    {{--@endif--}}
                    {{--</div>--}}
                    {{--</div>--}}


                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('phone') ? ' has-error' : '' }}">
                            <label for="userPhone">@lang('maincp.mobile_number') *</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="form-control numbersOnly  phone" required
                                   placeholder="رقم @lang('maincp.mobile_number')..." data-parsley-required-message="رقم الجوال مطلوب" data-limit="10"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (10) حرف"
                                   data-parsley-maxlength="10"
                            />
                            @if($errors->has('phone'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('phone') }}
                                </p>
                            @endif
                        </div>
                    </div>



                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                            <label for="emailAddress">@lang('maincp.e_mail') *</label>

                            <input type="email" name="email" data-parsley-trigger="keyup" value="{{ old('email') }}"
                                   class="form-control email"
                                   placeholder="@lang('maincp.e_mail') ..." required
                                   data-parsley-required-message="البريد الإلكتروني مطلوب"
                            />
                            @if($errors->has('email'))
                                <p class="help-block">{{ $errors->first('email') }}</p>
                            @endif

                        </div>

                    </div>

                    </div>
                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                            <label for="pass1">@lang('maincp.password') *</label>


                            <input type="password" name="password" id="pass1" value="{{ old('password') }}"
                                   class="form-control"
                                   placeholder="@lang('maincp.password')..."
                                   required
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="كلمة المرور مطلوبة"
                                   data-parsley-maxlength="55"
                                   data-parsley-minlength="6"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength-message=" أقل عدد الحروف المسموح بها هى (6) حرف"
                            />

                            @if($errors->has('password'))
                                <p class="help-block">{{ $errors->first('password') }}</p>
                            @endif

                        </div>
                    </div>



                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('password_confirmation') ? ' has-error' : '' }}">
                            <label for="passWord2">@lang('maincp.confirm_password') *</label>
                            <input data-parsley-equalto="#pass1" name="password_confirmation" type="password" required data-parsley-trigger="keyup"
                                   placeholder="@lang('maincp.confirm_password') ..." class="form-control" 
                                   
                                   id="passWord2"
                                   data-parsley-required-message="تأكيد كلمة المرور مطلوب"
                                   data-parsley-equalto-message="تأكيد كلمة المرور غير متطابقة"
                                   data-parsley-maxlength="55"
                                   data-parsley-minlength="6"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength-message=" أقل عدد الحروف المسموح بها هى (6) حرف">
                            @if($errors->has('password_confirmation'))
                                <p class="help-block">{{ $errors->first('password_confirmation') }}</p>
                            @endif


                        </div>
                    </div>

                    {{--<div class="form-group{{ $errors->has('roles') ? ' has-error' : '' }}">--}}
                    {{--<label for="passWord2">@lang('maincp.permission') *</label>--}}
                    {{--<select multiple="multiple" class="multi-select" id="my_multi_select1" name="roles[]" required--}}
                    {{--data-parsley-required-message="@lang('maincp.please_select_at_least_the_validity')"--}}
                    {{--data-plugin="multiselect">--}}
                    {{--@foreach($roles as  $value)--}}

                    {{--<option value="{{ $value->name }}" {{ (collect(old('roles'))->contains($value->name)) ? 'selected':'' }}>{{ $value->title }}</option>--}}
                    {{--@endforeach--}}

                    {{--</select>--}}

                    {{--@if($errors->has('roles'))--}}
                    {{--<p class="help-block"> {{ $errors->first('roles') }}</p>--}}
                    {{--@endif--}}

                    {{--</div>--}}



                    <div class="col-xs-12">
                        <label for="passWord2">@lang('maincp.permission') *</label>
                        <div class="form-group{{ $errors->has('roles') ? ' has-error' : '' }}">

                            @foreach($roles as  $value)

                                <div class="col-sm-4">
                                    <div class="checkbox checkbox-primary">
                                        <input name="roles[]" value="{{ $value->id }}" {{ (collect(old('roles'))->contains($value->name)) ? 'checked':'' }} required id="checkbox{{ $value->id }}"
                                               type="checkbox" class="requiredField">
                                        <label for="checkbox{{ $value->id }}">
                                            {{ $value->title }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach


                        </div>
                    </div>


                    <div class="form-group text-right m-t-20">
                        <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit">
                            @lang('maincp.save_data')
                        </button>
                        <button onclick="window.history.back();return false;" type="reset"
                                class="btn btn-default waves-effect waves-light m-l-5 m-t-20">
                            @lang('maincp.disable')
                        </button>
                    </div>

                </div>
            </div><!-- end col -->

        </div>
        <!-- end row -->
    </form>
@endsection


@section('scripts')

<script type="text/javascript"
        src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>

@endsection


