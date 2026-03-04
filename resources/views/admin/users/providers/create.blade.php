@extends('admin.layouts.master')
@section('title' , "إضافة مزود خدمة")

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
    <form id="storeProvider" method="POST" action="{{ route('store.provider') }}" enctype="multipart/form-data" data-parsley-validate
          novalidate>
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
                <h4 class="page-title">إدارة مزودي الخدمات</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-8 col-sm-offset-2" >
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">إضافة مزود خدمة</h4>
                    
                    
                    <div class="row">
                        @foreach (config('translatable.locales') as $locale => $value)


                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="userName">اسم الشركة - {{ $value }}</label>
                                    <input type="text" name="name_{{$locale}}" value="{{ old('name_'.$locale) }}" class="form-control" required
                                           placeholder="{{ $value }} اسم الشركة باللغة -"
                                           data-parsley-trigger="keyup"
                                           data-parsley-required-message="اسم الشركة - {{ $value }} مطلوب"
                                           data-parsley-maxlength="55"
                                           data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                           data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                           data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                           data-parsley-minlength="3"
                                           data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                                    />
                                    <p class="help-block" id="error_userName"></p>
                                    @if($errors->has('name_'.$locale))
                                        <p class="help-block validationStyle">
                                            {{ $errors->first('name_'.$locale) }}
                                        </p>
                                    @endif
                                </div>

                            </div>

                        @endforeach


                            <div class="col-xs-12">
                                <div class="form-group">
                                    <label for="userName">نوع الخدمة</label>
                                    <select class="form-control" name="service_id" required data-parsley-trigger="change" data-parsley-required-message="إختيار نوع الخدمة إجباري">
                                        <option value="">اختار نوع الخدمة</option>
                                        @foreach($services as $service)
                                        <option value="{{ $service->id }}">{{ anotherLangWhenDefaultNotFound($service, 'name') }}</option>
                                        @endforeach
                                    </select>
                                    <p class="help-block" id="error_userName"></p>
                                    @if($errors->has('service_id'))
                                        <p class="help-block validationStyle">
                                            {{ $errors->first('service_id') }}
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
                                   
                                   id="passWord2" required
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



                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">رقم التصريح</label>
                            <input type="text" name="permit_no" value="{{ old('permit_no') }}" class="form-control" required
                                   placeholder="رقم التصريح..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="رقم التصريح مطلوب"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('permit_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('permit_no') }}
                                </p>
                            @endif
                        </div>

                    </div>

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">قيمة الفرد</label>
                            <input type="text" name="price_per_person" value="{{ old('price_per_person') }}" class="form-control" required
                                   placeholder="قيمة الفرد..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="قيمة الفرد مطلوب"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('price_per_person'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('price_per_person') }}
                                </p>
                            @endif
                        </div>

                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">موقع الشركة</label>
                            <textarea type="text" name="address" class="form-control" required
                                   placeholder="موقع الشركة..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="موقع الشركة مطلوب"
                            >{{ old('address') }}</textarea>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('address'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('address') }}
                                </p>
                            @endif
                        </div>

                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">نبذة عن الشركة</label>
                            <textarea type="text" name="description" class="form-control"
                                      placeholder="نبذة عن الشركة..."
                            >{{ old('description') }}</textarea>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('description'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('description') }}
                                </p>
                            @endif
                        </div>

                    </div>

                    <div class="form-group col-xl-6">
                        <div class="bg-form">
                            <!-- add more images -->
                            <label for="">صور الخدمة</label>

                            <ul class="row m-0 hamla-pics mb-3">
                                <li class=" p-0 m-2 ">
                                    <div class="wrap-custom-file">
                                        <input type="file" name="file1" id="image1" accept=".gif, .jpg, .png"/>
                                        <label for="image1" id="image">
                                            <i class="fa fa-plus-circle" id="add"></i>
                                        </label>
                                    </div>
                                </li>
                                <li class=" p-0 m-2">
                                    <div class="wrap-custom-file">
                                        <input type="file" name="file2" id="image2" accept=".gif, .jpg, .png"/>
                                        <label for="image2" id="image">
                                            <i class="fa fa-plus-circle" id="add"></i>

                                        </label>
                                    </div>
                                </li>
                                <li class=" p-0 m-2">
                                    <div class="wrap-custom-file">
                                        <input type="file" name="file3" id="image3" accept=".gif, .jpg, .png"/>
                                        <label for="image3" id="image">
                                            <i class="fa fa-plus-circle" id="add"></i>

                                        </label>
                                    </div>
                                </li>
                                <li class=" p-0 m-2">
                                    <div class="wrap-custom-file">
                                        <input type="file" name="file4" id="image4" accept=".gif, .jpg, .png"/>
                                        <label for="image4" id="image">
                                            <i class="fa fa-plus-circle" id="add"></i>

                                        </label>
                                    </div>
                                </li>
                                <li class=" p-0 m-2">
                                    <div class="wrap-custom-file">
                                        <input type="file" name="file5" id="image5" accept=".gif, .jpg, .png"/>
                                        <label for="image5" id="image">
                                            <i class="fa fa-plus-circle" id="add"></i>

                                        </label>
                                    </div>
                                </li>
                                <li class=" p-0 m-2">
                                    <div class="wrap-custom-file">
                                        <input type="file" name="file6" id="image6" accept=".gif, .jpg, .png"/>
                                        <label for="image6" id="image">
                                            <i class="fa fa-plus-circle" id="add"></i>

                                        </label>
                                    </div>
                                </li>
                            </ul>

                        </div>
                    </div>
                    <div class="form-group text-right m-t-20">
                        <img id="indicatorImage" src="{{ request()->root() }}/public/assets/images/spinner.gif" style="width: 50px; height: 50px; display: none; margin-top: 20px;">
                        <button class="btn btn-primary waves-effect waves-light m-t-20" id="btnRegister" type="submit">
                            @lang('trans.signup')
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


