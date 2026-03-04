@extends('admin.layouts.master')
@section('title', __('maincp.users_manager'))



@section('styles')


    <style>
        #parsley-id-multiple-roles li {
            position: absolute;
            top: -22px;
            right: 80px;
        }
    </style>

@endsection

@section('content')


    <form method="POST" action="{{ route('users.update', $user->id) }}" enctype="multipart/form-data"
          data-parsley-validate novalidate>
    {{ csrf_field() }}
    {{ method_field('PUT') }}



    <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-8 col-sm-offset-2">
                <div class="btn-group pull-right m-t-15">
                    {{--  <a href="{{ route('users.create') }}" type="button" class="btn btn-custom waves-effect waves-light"
                        aria-expanded="false"> @lang('maincp.add')
                         <span class="m-l-5">
                         <i class="fa fa-plus"></i>
                     </span>
                     </a> --}}
                </div>
                <h4 class="page-title">@lang('maincp.edit_user_data')</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-8 col-sm-offset-2">
                <div class="card-box">


                    <h4 class="header-title m-t-0 m-b-30">@lang('maincp.edit_data')</h4>

                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">الاسم الاول*</label>
                                <input type="text" name="first_name"
                                       value="{{ $user->first_name or old('first_name') }}" class="form-control"
                                       required
                                       placeholder="الاسم الاول..."
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="الاسم الاول مطلوب"
                                       data-parsley-maxlength="55"
                                       data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                       data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                       data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                />
                                <p class="help-block" id="error_userName"></p>
                                @if($errors->has('first_name'))
                                    <p class="help-block">
                                        {{ $errors->first('first_name') }}
                                    </p>
                                @endif
                            </div>
                        </div>


                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">الاسم الاخير*</label>
                                <input type="text" name="last_name" value="{{ $user->last_name or old('last_name') }}"
                                       class="form-control"
                                       required
                                       placeholder="الاسم الاخير..."
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="الاسم الاخير مطلوب"
                                       data-parsley-maxlength="55"
                                       data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                       data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                       data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                />
                                <p class="help-block" id="error_userName"></p>
                                @if($errors->has('last_name'))
                                    <p class="help-block">
                                        {{ $errors->first('last_name') }}
                                    </p>
                                @endif
                            </div>
                        </div>


                        <div class="col-xs-6">
                            <div class="form-group{{ $errors->has('phone') ? ' has-error' : '' }}">
                                <label for="userPhone">@lang('maincp.mobile_number') *</label>
                                <input type="text" name="phone" value="{{ $user->phone or old('phone') }}"
                                       class="form-control numbersOnly  phone" required
                                       placeholder="@lang('maincp.mobile_number') ..."/>
                                @if($errors->has('phone'))
                                    <p class="help-block">
                                        {{ $errors->first('phone') }}
                                    </p>
                                @endif
                            </div>
                        </div>





                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                            <label for="emailAddress">@lang('maincp.e_mail') *</label>

                            <input type="email" name="email" data-parsley-trigger="keyup"
                                   value="{{ $user->email or old('email') }}"
                                   class="form-control email" placeholder="@lang('maincp.e_mail')..." required/>
                            @if($errors->has('email'))
                                <p class="help-block">{{ $errors->first('email') }}</p>
                            @endif

                        </div>

                    </div>
                    </div>

                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                            <label for="pass1">@lang('maincp.password')*</label>


                            <input type="password" name="password" id="pass1"
                                   class="form-control"
                                   placeholder="@lang('maincp.password')..."
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
                            <label for="passWord2">@lang('maincp.confirm_password')*</label>
                            <input data-parsley-equalto="#pass1" name="password_confirmation" type="password"
                                   placeholder="@lang('maincp.confirm_password').."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="تأكيد كلمة المرور مطلوب"
                                   data-parsley-equalto-message="تأكيد كلمة المرور غير متطابقة"

                                   class="form-control">
                            @if($errors->has('password_confirmation'))
                                <p class="help-block">{{ $errors->first('password_confirmation') }}</p>
                            @endif


                        </div>
                    </div>


                    @if(!$user->roles()->whereName('owner')->first() && auth()->id() != $user->id)


                        {{--<div class="form-group{{ $errors->has('roles') ? ' has-error' : '' }}">--}}
                        {{--<label for="passWord2">@lang('maincp.permission') *</label>--}}
                        {{--<select multiple="multiple" class="multi-select" id="my_multi_select1" name="roles[]"--}}
                        {{--data-plugin="multiselect">--}}
                        {{--@foreach($roles as  $value)--}}

                        {{--<option value="{{ $value->name }}"--}}
                        {{--@if($user->roles->pluck('name', 'name')) @foreach($user->roles->pluck('name', 'name') as $roleUser) @if($roleUser == $value->name) selected @endif @endforeach @endif >{{ $value->title }}</option>--}}

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
                                            <input name="roles[]" value="{{ $value->id }}" class="requiredField"
                                                   @if($user->roles->pluck('name', 'name')) @foreach($user->roles->pluck('name', 'name') as $roleUser) @if($roleUser == $value->name) checked
                                                   @endif @endforeach @endif  required id="checkbox{{ $value->id }}"
                                                   type="checkbox">
                                            <label for="checkbox{{ $value->id }}">
                                                {{ $value->title }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                                @if($errors->has('roles'))
                                    <p class="help-block"> {{ $errors->first('roles') }}</p>
                                @endif


                            </div>
                        </div>






                    @endif


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

