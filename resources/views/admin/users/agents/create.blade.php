@extends('admin.layouts.master')
@section('title' ,'إضافة وكيل')

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
    <form id="storeCampaign" method="POST" action="{{ route('store.agent') }}" enctype="multipart/form-data"
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
                <h4 class="page-title">إدارة االوكلاء</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">إضافة وكيل </h4>
                    <div class="row">


                        {{--Comany Name--}}
                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="userName">اسم الشركة - {{ $value }}</label>
                                    <input type="text" name="name_{{$locale}}" value="{{ old('name_'.$locale) }}"
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

                        {{--Agent Name--}}
                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="userName">اسم الوكيل - {{ $value }}</label>
                                    <input type="text" name="requirements_{{$locale}}"
                                           value="{{ old('requirements_'.$locale) }}"
                                           class="form-control" required
                                           placeholder="{{ $value }} اسم الوكيل باللغة -"
                                           data-parsley-trigger="keyup"
                                           data-parsley-required-message="اسم الوكيل - {{ $value }} مطلوب"
                                           data-parsley-maxlength="55"
                                           data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                           data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                           data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                           data-parsley-minlength="3"
                                           data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                                    />
                                    <p class="help-block" id="error_userName"></p>
                                    @if($errors->has('requirements_'.$locale))
                                        <p class="help-block validationStyle">
                                            {{ $errors->first('requirements_'.$locale) }}
                                        </p>
                                    @endif
                                </div>

                            </div>

                        @endforeach


                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="userName">نوع الوكيل*</label>

                                <select class="form-control" name="agentType" required
                                        data-parsley-required-message="إختيار نوع الوكيل مطلوب"
                                        data-parsley-trigger="change">
                                    <option value="">إختار نوع الوكيل</option>
                                    @foreach($agentstypes as $agentstype)
                                        <option value="{{ $agentstype->id }}">{{ getTextForAnotherLang($agentstype, 'name', app()->getLocale()) }}</option>
                                    @endforeach
                                </select>
                                <p class="help-block" id="error_userName"></p>


                                <img id="indicatorImageCountry"
                                     src="{{ request()->root() }}/public/assets/images/spinner.gif"
                                     style="width: 35px; height: 35px;position: absolute; top: 35px;left: 20px;display: none;">
                            </div>
                        </div>

                        {{--Email--}}
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

                        {{--Phone--}}
                        <div class="col-xs-6">
                            <div class="form-group{{ $errors->has('phone') ? ' has-error' : '' }}">
                                <label for="userName">رقم التواصل*</label>
                                <input type="text" name="phone" value="{{ old('phone') }}"
                                       class="form-control numbersOnly  phone" required
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
                        {{--Country--}}
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">الدولة*</label>

                                <select class="form-control" name="" required id="selectCountry"
                                        data-parsley-required-message="إختيار الدولة مطلوب"
                                        data-parsley-trigger="change">
                                    <option value="">إختار الدولة</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ anotherLangWhenDefaultNotFound($country, 'name') }}</option>
                                    @endforeach
                                </select>
                                <p class="help-block" id="error_userName"></p>


                                <img id="indicatorImageCountry"
                                     src="{{ request()->root() }}/public/assets/images/spinner.gif"
                                     style="width: 35px; height: 35px;position: absolute; top: 35px;left: 20px;display: none;">
                            </div>
                        </div>

                        {{--City--}}
                        <div class="col-xs-6">
                            <div class="form-group{{ $errors->has('city_id') ? ' has-error' : '' }}">
                                <label for="emailAddress">المدينة *</label>

                                <select class="form-control" required name="city_id" id="selectCity"
                                        data-parsley-required-message="إختيار المدينة مطلوب">

                                </select>

                                @if($errors->has('city_id'))
                                    <p class="help-block">{{ $errors->first('city_id') }}</p>
                                @endif

                            </div>

                        </div>

                    </div>

                    {{--Password--}}
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

                    {{--Confirm Password--}}
                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('password_confirmation') ? ' has-error' : '' }}">
                            <label for="passWord2">@lang('maincp.confirm_password') *</label>
                            <input data-parsley-equalto="#pass1" name="password_confirmation" type="password" required
                                   data-parsley-trigger="keyup"
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

                    {{--Address--}}
                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">الموقع - {{ $value }}*</label>
                                <textarea type="text" name="address_{{ $locale }}" class="form-control"
                                          placeholder="الموقع - {{ $value }}..."
                                          data-parsley-trigger="keyup"
                                          data-parsley-required-message="الموقع باللغة - {{ $value }}"
                                          data-parsley-minlength="3"
                                          data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                                >{{ old('address_'.$locale) }}</textarea>
                                <p class="help-block" id="error_userName"></p>
                                @if($errors->has('address_'.$locale))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('address_'.$locale) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    {{--Description--}}
                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">وصف البرنامج - {{ $value }}*</label>
                                <textarea type="text" name="description_{{ $locale }}" class="form-control"
                                          placeholder="وصف البرنامج - {{ $value }}..."
                                          data-parsley-trigger="keyup"
                                          data-parsley-required-message="وصف البرنامج باللغة - {{ $value }}"
                                          data-parsley-minlength="3"
                                          data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                                >{{ old('description_'.$locale) }}</textarea>
                                <p class="help-block" id="error_userName"></p>
                                @if($errors->has('description_'.$locale))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('description_'.$locale) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach


                    <div class="col-xs-6">
                        <div class="form-group{{ $errors->has('root_type') ? ' has-error' : '' }}">
                            <label for="emailAddress">نوع الخدمة  *</label>

                            <select class="form-control" required name="root_type" id="selectCity"
                                    data-parsley-required-message="إختيار نوع الغرف مطلوب">

                                <option value="airport">@lang('trans.airport')</option>
                                <option value="permit">@lang('trans.permit')</option>
                                <option value="hotels">@lang('trans.hotels')</option>
                                <option value="transport">@lang('trans.transport')</option>

                            </select>

                            @if($errors->has('root_type'))
                                <p class="help-block">{{ $errors->first('root_type') }}</p>
                            @endif

                        </div>

                    </div>

                    <div class="col-xs-6">

                        <div class="form-group">
                            <label for="userName">حدد نوع النشاط</label>

                            <div class="radio radio-info radio-inline">
                                <input type="radio" name="activityType" class="changeActivity" id="inlineRadio1"
                                       value="0"
                                       checked="">
                                <label for="inlineRadio1"> عمرة </label>
                            </div>

                            <div class="radio radio-info radio-inline">
                                <input type="radio" name="activityType" class="changeActivity" id="inlineRadio2"
                                       value="1"
                                >
                                <label for="inlineRadio2"> حج </label>
                            </div>
                        </div>
                        {{--</div>--}}


                        {{--<div class="col-xs-6">--}}

                        <div class="form-group" id="weeksActivity">
                            <label for="userName">عدد الاسابيع</label>

                            <select class="form-control" name="weeks_no" required
                                    data-parsley-required-message="تحدد عدد الاسابيع مطلوب"
                                    data-parsley-trigger="change">
                                <option value="1"> اسبوع</option>
                                <option value="2"> اسبوعين</option>
                                <option value="3"> 3 اسابيع</option>

                            </select>
                        </div>


                        <div class="form-group" id="dataRangeActivity" style="display: none;">
                            <label class="control-label"> التاريخ من / الي</label>

                            <div class="input-daterange input-group" id="date-range">
                                <input type="text" class="form-control"  name="dateFrom"/>
                                <span class="input-group-addon bg-primary b-0 text-white">إلي</span>
                                <input type="text" class="form-control" name="dateTo"/>
                            </div>

                        </div>


                    </div>

                    <div class="clearfix"></div>

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

    <script src="{{ request()->root() }}/public/assets/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>
    <script src="{{ request()->root() }}/public/assets/plugins/bootstrap-daterangepicker/daterangepicker.js"></script>

    <script>

        jQuery('#date-range').datepicker({
            toggleActive: true
        });


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

        $('.changeActivity').on('change', function () {

            var currentVal = $(this).val();

            if (currentVal == 0) {
                $('#weeksActivity').show();
                $('#dataRangeActivity').hide();
            } else {
                $('#dataRangeActivity').show();
                $('#weeksActivity').hide();
            }

        });
    </script>

@endsection


