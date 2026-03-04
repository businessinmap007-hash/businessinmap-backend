@extends('admin.layouts.master')
@section('title' ,'إضافة حملة')

@section('styles')


    <style>
        #parsley-id-multiple-roles li {
            position: absolute;
            top: -22px;
            right: 80px;
        }

        .deleteImageCampaign{
            position: absolute;
            background: red;
            color: #FFF;
            top: -10px;
            right: -7px;
            padding: 5px;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 16px;
            text-align: center;
        }
    </style>

@endsection
@section('content')
    <form id="storeCampaign" method="POST" action="{{ route('update.campaign', $campaign->id) }}"
          enctype="multipart/form-data"
          data-parsley-validate
          novalidate>
    {{ csrf_field() }}

        
    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title">إدارة الحملات</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">إضافة حملة </h4>
                    <div class="row">
                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="userName">اسم الحملة - {{ $value }}</label>
                                    <input type="text" name="name_{{$locale}}"
                                           value="{{ getTextForAnotherLang($campaign, 'name', app()->getLocale()) }}"
                                           class="form-control" required
                                           placeholder="{{ $value }} اسم الحملة باللغة -"
                                           data-parsley-trigger="keyup"
                                           data-parsley-required-message="اسم الحملة - {{ $value }} مطلوب"
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


                        {{ $campaign->service_id }}

                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">نوع الحملة*</label>

                                <select class="form-control" required name="service_id"
                                        data-parsley-required-message="نوع الحملة مطلوب" data-parsley-trigger="change">
                                    <option value="">إختار نوع الحملة</option>
                                    @foreach($campaignTypes as $type)
                                        <option value="{{ $type->id }}"
                                                @if($campaign->service_id == $type->id) selected @endif>{{ getTextForAnotherLang($type, 'name', app()->getLocale()) }}</option>
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

                        <div class="col-xs-6">
                            <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                                <label for="emailAddress">@lang('maincp.e_mail') *</label>

                                <input type="email" name="email" data-parsley-trigger="keyup"
                                       value="{{ $campaign->email }}"
                                       class="form-control email"
                                       placeholder="@lang('maincp.e_mail') ..." required
                                       data-parsley-required-message="البريد الإلكتروني مطلوب"
                                />
                                @if($errors->has('email'))
                                    <p class="help-block">{{ $errors->first('email') }}</p>
                                @endif

                            </div>

                        </div>
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">الدولة*</label>

                                <select class="form-control" name="" required id="selectCountry"
                                        data-parsley-required-message="نوع الحملة مطلوب" data-parsley-trigger="change">
                                    <option value="">إختار الدولة</option>

                                    @if($campaign->city)
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}"
                                                    @if($campaign->city->country->id == $country->id) selected @endif>{{ getTextForAnotherLang($country, 'name', app()->getLocale()) }}</option>
                                        @endforeach
                                    @else
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}">{{ getTextForAnotherLang($country, 'name', app()->getLocale()) }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                <p class="help-block" id="error_userName"></p>


                                <img id="indicatorImageCountry"
                                     src="{{ request()->root() }}/public/assets/images/spinner.gif"
                                     style="width: 35px; height: 35px;position: absolute; top: 35px;left: 20px;display: none;">
                            </div>
                        </div>

                        <div class="col-xs-6">
                            <div class="form-group{{ $errors->has('city_id') ? ' has-error' : '' }}">
                                <label for="emailAddress">المدينة *</label>

                                <select class="form-control" required name="city_id" id="selectCity"
                                        data-parsley-required-message="إختيار المدينة مطلوب">
                                    @if($campaign->city)
                                        @foreach($campaign->city->country->cities as $city)
                                            <option value="{{ $city->id }}"
                                                    @if($city->id == $campaign->city_id) selected @endif>{{ getTextForAnotherLang($city, 'name', app()->getLocale()) }}</option>
                                        @endforeach

                                    @endif


                                </select>

                                @if($errors->has('city_id'))
                                    <p class="help-block">{{ $errors->first('city_id') }}</p>
                                @endif

                            </div>

                        </div>


                    </div>


                    <div class="col-xs-12">
                        <div class="form-group{{ $errors->has('mina_locations') ? ' has-error' : '' }}">
                            <label for="emailAddress">موقع الحملة في مني *</label>
                            <br/>

                            <?php $locations = explode(',', $setting->getBody('menna_locations_' . app()->getLocale()));?>

                            @foreach($locations as $location)

                                <label>
                                    <input type="checkbox"
                                           @if(collect(unserialize($campaign->mina_locations))->contains($location)) checked
                                           @endif name="campaign_location_minaa[]" value="{{ $location }}" required>
                                    {{ $location }}
                                </label>
                            @endforeach

                        </div>

                    </div>


                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                            <label for="pass1">@lang('maincp.password') *</label>
                            <input type="password" name="password" id="pass1" value="{{ old('password') }}"
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
                            <label for="passWord2">@lang('maincp.confirm_password') *</label>
                            <input data-parsley-equalto="#pass1" name="password_confirmation" type="password"
                                   data-parsley-trigger="keyup"
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


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">قيمة الفرد*</label>
                            <input type="text" name="price_per_person"
                                   value="{{ $campaign->price_per_person or old('price_per_person') }}"
                                   class="form-control" required
                                   placeholder="قيمة الفرد..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="قيمة الفرد مطلوبه"
                                   data-parsley-maxlength="55"
                                   data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                   data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('price_per_person'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('price_per_person') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">المقاعد المتاحة*</label>
                            <input type="text" name="seats_no" value="{{ $campaign->seats_no or  old('seats_no') }}"
                                   class="form-control"
                                   required
                                   placeholder=" المقاعد المتاحة..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="المقاعد المتاحة مطلوبه"
                                   data-parsley-maxlength="55"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('seats_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('seats_no') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">تقييم الحملة*</label>
                            <input type="text" name="rate" value="{{ $campaign->rate or old('rate') }}"
                                   class="form-control" required
                                   placeholder="تقييم الحملة..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="تقييم الحملة مطلوب"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('rate'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('rate') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">رقم التصريح*</label>
                            <input type="text" name="permit_no" value="{{ $campaign->permit_no or old('permit_no') }}"
                                   class="form-control"
                                   required
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


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">موقع الحملة*</label>
                            <input type="text" name="address" value="{{ $campaign->address or old('address') }}"
                                   class="form-control" required
                                   placeholder="موقع الحملة..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="موقع الحملة مطلوب"
                                   data-parsley-maxlength="55"
                                   data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                   data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('address'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('address') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group{{ $errors->has('phone') ? ' has-error' : '' }}">
                            <label for="userName">رقم التواصل*</label>
                            <input type="text" name="phone" value="{{ $campaign->phone or  old('phone') }}"
                                   class="form-control numbersOnly phone" required
                                   placeholder="رقم التواصل..."
                                   data-parsley-required-message="رقم الجوال مطلوب" data-limit="10"
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


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">مميزات الحملة*</label>
                            <textarea type="text" name="features" class="form-control"
                                      placeholder="مميزات الحملة..."
                                      data-parsley-trigger="keyup"
                                      data-parsley-required-message="متطلبات الحملة مطلوبه"
                                      data-parsley-minlength="3"
                                      data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                            >{{ $campaign->features  or old('features') }}</textarea>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('features'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('features') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">متطلبات الحملة*</label>
                            <textarea type="text" name="requirements" class="form-control"
                                      placeholder="متطلبات الحملة..."
                                      data-parsley-trigger="keyup"
                                      data-parsley-required-message="متطلبات الحملة مطلوبه"
                                      data-parsley-minlength="3"
                                      data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                            >{{  $campaign->requirements or old('requirements') }}</textarea>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('requirements'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('requirements') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">وصف الحملة*</label>
                            <textarea type="text" name="description" class="form-control"
                                      placeholder="وصف الحملة..."
                                      data-parsley-trigger="keyup"
                                      data-parsley-required-message="وصف الحملة إجباري"
                                      data-parsley-minlength="3"
                                      data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                            >{{ $campaign->description or old('description') }}</textarea>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('description'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('description') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{--<div class="col-xs-4">--}}
                    {{--<div class="form-group">--}}
                    {{--<label for="userName">دولة الإرتباط*</label>--}}

                    {{--<select class="form-control" name="country_id" required data-parsley-required-message="دولة الإرتباط مطلوبه" data-parsley-trigger="change">--}}
                    {{--<option value="">إختار دولة الإرتباط</option>--}}
                    {{--@foreach(\App\Models\Country::orderBy('created_at', 'desc')->get() as $country)--}}
                    {{--<option value="{{ $country->id }}">{{ anotherLangWhenDefaultNotFound($country, 'name') }}</option>--}}
                    {{--@endforeach--}}
                    {{--</select>--}}
                    {{--<p class="help-block" id="error_userName"></p>--}}
                    {{--@if($errors->has('country_id'))--}}
                    {{--<p class="help-block validationStyle">--}}
                    {{--{{ $errors->first('country_id') }}--}}
                    {{--</p>--}}
                    {{--@endif--}}
                    {{--</div>--}}
                    {{--</div>--}}


                    <div class="row">
                        <div class=" col-xl-12">
                            <div class="bg-form">
                                <!-- add more images -->
                                <label for="">صور الحملة</label>

                                <ul class="row m-0 hamla-pics mb-3" style="list-style: none">
                                    @foreach($campaign->files as $file)
                                        <li class=" p-0 m-2 " style="float: right; height: 170px; margin: 5px; position:relative;" id="image-container{{ $file->id }}">
                                            <a href="javascript:;" class="deleteImageCampaign remove-img" data-id="{{ $file->id }}">X</a>
                                            <img src="{{ $file->url }}" style="width: 100%; height: 100%;">
                                        </li>
                                    @endforeach
                                </ul>

                            </div>
                        </div>
                    </div>


                    <div class="row">
                        <div class=" col-xl-12">
                            <div class="bg-form">
                                <!-- add more images -->
                                <label for="">صور الحملة</label>

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
                    </div>

                    <div class="form-group text-right m-t-20">

                        <img id="indicatorImage" src="{{ request()->root() }}/public/assets/images/spinner.gif"
                             style="width: 50px; height: 50px; display: none; margin-top: 20px;">

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
    <script>

        $('#storeCampaign').on('submit', function (e) {
            // var checkOline = navigator.onLine;
            // alert(checkOline);
            $("#btnRegister").html("{{ __('trans.signingup') }}");
            $("#indicatorImage").css('display', 'initial');

            e.preventDefault();
            var formData = new FormData(this);
            var form = $(this);
            form.parsley().validate();
            if (form.parsley().isValid()) {

                $.ajax({
                    type: 'POST',
                    url: $(this).attr('action'),
                    data: formData,
                    cache: false,
                    contentType: false,
                    processData: false,
                    success: function (data) {

                        if (data.status == 200) {
                            $("#btnRegister").html("{{ __('trans.signup') }}");
                            $("#indicatorImage").css('display', 'none');
                            var shortCutFunction = 'success';
                            var msg = data.message;
                            var title = '{{ __('trans.success') }}';
                            toastr.options = {
                                positionClass: 'toast-top-left',
                                onclick: null
                            };
                            var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                            $toastlast = $toast;

                            setTimeout(function () {
                                window.location.href = data.url;
                            }, 1000);
                        }

                        if (data.status == 400) {
                            $("#btnRegister").html("{{ __('trans.signup') }}");
                            $("#indicatorImage").css('display', 'none');

                        }
                        if (data.status == 402) {
                            $("#btnRegister").html("{{ __('trans.signup') }}");
                            $("#indicatorImage").css('display', 'none');

                            var shortCutFunction = 'error';
                            var msg = data.errors;
                            var title = 'Validation Error!';
                            toastr.options = {
                                positionClass: 'toast-top-left',
                                onclick: null
                            };
                            var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                            $toastlast = $toast;


                        }

                    },
                    error: function (data) {
                        $("#btnRegister").html("{{ __('trans.signup') }}");
                        $("#indicatorImage").css('display', 'none');
                    }
                });
            } else {

                $("#btnRegister").html("{{ __('trans.signup') }}");
                $("#indicatorImage").css('display', 'none');
            }
        });


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

        $('.remove-img').on('click', function (e) {
            e.preventDefault();
            var imageId = $(this).attr('data-id');

            $.ajax({
                type: 'post',
                url: '{{ route('delete.user.image') }}',
                data: {imageId: imageId},
                dataType: 'json',
                success:
                    function (data) {
                        if (data.status == 200) {
                            $('#image-container' + imageId).remove();
                            if (data.imageCount < 1) {
                                $("#currentImagesContainer").remove();
                            }
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


