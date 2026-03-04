@extends('admin.layouts.master')
@section('title' ,'تعديل معتمر')

@section('styles')



@endsection
@section('content')
    <form id="storeCampaign" method="POST" action="{{ route('pilgrims.update', $result->id) }}"
          enctype="multipart/form-data"
          data-parsley-validate
          novalidate class="submission-form">
    {{ csrf_field() }}
    {{ method_field('PUT') }}

    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title">إدارة المعتمرين</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="card-box">
                    <h2 class="header-title m-t-0 m-b-30">تعديل معتمر </h2>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">اسم المعتمر</label>
                            <input type="text" name="name" value="{{ $result->name or old('name') }}"
                                   class="form-control" required
                                   placeholder="اسم المعتمر باللغة"
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="اسم المعتمر إلزامي"
                                   data-parsley-maxlength="55"
                                   data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                   data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('name'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('name') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">الرحلة*</label>
                            <select name="tripId" class="form-control" id="selectTrip" required>
                                <option value="">إختيار الرحلة</option>
                                @foreach($trips as $tp)
                                    <option value="{{ $tp->id }}" @if($trip && $trip->pivot->trip_id == $tp->id) selected @endif>{{ $tp->name }}</option>
                                @endforeach
                            </select>

                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('ssn_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('ssn_no') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">الاتوبيس*</label>
                            <select name="busId" class="form-control" id="selectBusTrip"  required>
                                <option value="">إختيار الاتوبيس</option>
                                @foreach($buses as $bus)
                                    <option value="{{ $bus->id }}" @if($trip && $trip->pivot->bus_id == $bus->id) selected @endif>{{ $bus->bus_no }}</option>
                                @endforeach
                            </select>

                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('ssn_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('ssn_no') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                            <label for="emailAddress">@lang('maincp.e_mail') *</label>
                            <input type="email" name="email" data-parsley-trigger="keyup"
                                   value="{{ $result->email or old('email') }}"
                                   class="form-control email"
                                   placeholder="@lang('maincp.e_mail') ..." required
                                   data-parsley-required-message="البريد الإلكتروني مطلوب"
                            />
                            @if($errors->has('email'))
                                <p class="help-block">{{ $errors->first('email') }}</p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group{{ $errors->has('phone') ? ' has-error' : '' }}">
                            <label for="userName">رقم التواصل*</label>
                            <input type="text" name="phone" value="{{ $result->phone or old('phone') }}"
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

                    {{--<div class="col-xs-6">--}}
                    {{--<div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">--}}
                    {{--<label for="pass1">@lang('maincp.password') *</label>--}}
                    {{--<input type="password" name="password" id="pass1" value="{{ old('password') }}"--}}
                    {{--class="form-control"--}}
                    {{--placeholder="@lang('maincp.password')..."--}}
                    {{--required--}}
                    {{--data-parsley-trigger="keyup"--}}
                    {{--data-parsley-required-message="كلمة المرور مطلوبة"--}}
                    {{--data-parsley-maxlength="55"--}}
                    {{--data-parsley-minlength="6"--}}
                    {{--data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"--}}
                    {{--data-parsley-minlength-message=" أقل عدد الحروف المسموح بها هى (6) حرف"--}}
                    {{--/>--}}

                    {{--@if($errors->has('password'))--}}
                    {{--<p class="help-block">{{ $errors->first('password') }}</p>--}}
                    {{--@endif--}}

                    {{--</div>--}}
                    {{--</div>--}}


                    {{--<div class="col-xs-6">--}}
                    {{--<div class="form-group{{ $errors->has('password_confirmation') ? ' has-error' : '' }}">--}}
                    {{--<label for="passWord2">@lang('maincp.confirm_password') *</label>--}}
                    {{--<input data-parsley-equalto="#pass1" name="password_confirmation" type="password" required--}}
                    {{--data-parsley-trigger="keyup"--}}
                    {{--placeholder="@lang('maincp.confirm_password') ..." class="form-control"--}}

                    {{--id="passWord2" required--}}
                    {{--data-parsley-required-message="تأكيد كلمة المرور مطلوب"--}}
                    {{--data-parsley-equalto-message="تأكيد كلمة المرور غير متطابقة"--}}
                    {{--data-parsley-maxlength="55"--}}
                    {{--data-parsley-minlength="6"--}}
                    {{--data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"--}}
                    {{--data-parsley-minlength-message=" أقل عدد الحروف المسموح بها هى (6) حرف">--}}
                    {{--@if($errors->has('password_confirmation'))--}}
                    {{--<p class="help-block">{{ $errors->first('password_confirmation') }}</p>--}}
                    {{--@endif--}}


                    {{--</div>--}}
                    {{--</div>--}}


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">الجنسية*</label>
                            <input type="text" name="nationality" value="{{ optional($result->profile)->nationality }}"
                                   class="form-control" required
                                   placeholder="الجنسية..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="حقل الجنسية إلزامي"
                                   data-parsley-maxlength="55"
                                   data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                   data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('nationality'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('nationality') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">الرقم الوطني*</label>
                            <input type="text" name="ssn_no" value="{{ optional($result->profile)->ssn_no }}"
                                   class="form-control"
                                   required
                                   placeholder="الرقم الوطني او الهوية..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="الرقم الوطني إلزامي"
                                   data-parsley-maxlength="55"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('ssn_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('ssn_no') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">رقم جواز السفر*</label>
                            <input type="text" name="passport_no" value="{{ optional($result->profile)->passport_no }}"
                                   class="form-control" required
                                   placeholder="رقم جواز السفر..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="رقم جواز السفر إلزامي"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('passport_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('passport_no') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">رقم التأشيرة*</label>
                            <input type="text" name="visa_no" value="{{optional($result->profile)->visa_no }}"
                                   class="form-control"
                                   required
                                   placeholder="رقم التأشيرة..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="رقم التأشيرة إلزامي"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('visa_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('visa_no') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">تاريخ إصدار الجواز*</label>
                            <input type="text" name="passport_issuance_date"
                                   value="{{ date('Y-m-d', strtotime(optional($result->profile)->passport_issuance_date)) }}" class="form-control"
                                   required
                                   data-mask="9999-99-99"
                                   placeholder="تاريخ إصدار الجواز..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="تاريخ إصدار الجواز إلزامي"
                                    {{--data-parsley-maxlength="55"--}}
                                    {{--data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"--}}
                                    {{--data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"--}}
                                    {{--data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"--}}
                                    {{--data-parsley-minlength="3"--}}
                                    {{--data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"--}}

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('passport_issuance_date'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('passport_issuance_date') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">تاريخ إنتهاء الجواز*</label>
                            <input type="text" name="passport_expired_date"
                                   value="{{ date('Y-m-d', strtotime(optional($result->profile)->passport_expired_date)) }}" class="form-control"
                                   required
                                   data-mask="9999-99-99"
                                   placeholder="تاريخ إنتهاء الجواز..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="تاريخ إنتهاء الجواز إلزامي"
                                    {{--data-parsley-maxlength="55"--}}
                                    {{--data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"--}}
                                    {{--data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"--}}
                                    {{--data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"--}}
                                    {{--data-parsley-minlength="3"--}}
                                    {{--data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"--}}

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('passport_expired_date'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('passport_expired_date') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">مكان إصدار الجواز*</label>
                            <input type="text" name="passport_issuance_location"
                                   value="{{ optional($result->profile)->passport_issuance_location }}"
                                   class="form-control" required
                                   placeholder="مكان إصدار الجواز..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="مكان إصدار الجواز إلزامي"
                                   data-parsley-maxlength="55"
                                   {{--data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"--}}
                                   {{--data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"--}}
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (255) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('passport_issuance_location'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('passport_issuance_location') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">الوظيفة*</label>
                            <input type="text" name="job"
                                   value="{{  optional($result->profile)->job }}"
                                   class="form-control" required
                                   placeholder="الوظيفة..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="حقل الوظيفة إلزامي"
                                   data-parsley-maxlength="55"
                                   {{--data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"--}}
                                   {{--data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"--}}
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('job'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('job') }}
                                </p>
                            @endif
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
    <script>


    </script>
    <script src="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-inputmask/bootstrap-inputmask.min.js" type="text/javascript"></script>




    <script>
        $("#selectTrip").on('change', function (e) {
            e.preventDefault();

            // $("#indicatorImageCountryConnection").css('display', 'initial');

            var tripID = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.selected.buses') }}',
                data: {tripID: tripID},
                dataType: 'json',
                success:
                    function (response) {
                        // $("#indicatorImageCountryConnection").css('display', 'none');


                        if (response) {
                            $("#selectBusTrip").empty();
                            $("#selectBusTrip").prop('disabled', false);
                            $("#selectBusTrip").append('<option value="" selected disabled> إختيار الاتوبيس </option>');
                            $.each(response, function (key, value) {
                                $("#selectBusTrip").append('<option value="' + value.id + '">' + value.bus_no + '</option>');
                            });
                            $("#selectBusTrip").select2();
                        } else {
                            $("#selectBusTrip").empty();
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


