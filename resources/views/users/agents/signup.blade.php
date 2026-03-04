@extends('layouts.master')

@section('content')
    <div class="hamla-header text-center text-white">
        <div class="overlay">
            <div class="w-50 m-auto header-details p-3">
                <h6 class="text-center mt-3">@lang('trans.welcome_arkabmaana')</h6>
                <h6 class="text-center">@lang('trans.add_agent_data')</h6>
            </div>
        </div>
    </div>


    <div class="container pt-5 ">
        <div>
            <form id="agent-registration" action="{{ route('register.agent') }}" method="post"
                  class="row serviceProvider-signupForm" data-parsley-validate enctype="multipart/form-data">

                {{ csrf_field() }}


                <div class="form-group col-xl-12">
                    <div class="bg-form">

                        <select class="form-control" required name="service_id"
                                data-parsley-required-message="@lang('trans.required')">
                            <option value="" selected disabled hidden>@lang('trans.select_campaign_type')</option>
                            @foreach($agentTypes as $agentType)
                                <option value="{{ $agentType->id }}">{{ getTextForAnotherLang($agentType, 'name', app()->getLocale()) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text"
                               class="form-control"
                               name="name"
                               required
                               data-parsley-required-message="@lang('trans.required')"
                               placeholder="@lang('trans.company_name')"
                        >
                    </div>
                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text"
                               class="form-control"
                               name="agent_name"
                               required
                               data-parsley-required-message="@lang('trans.required')"
                               placeholder="@lang('trans.agent_name')"
                        >
                    </div>
                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" required
                               name="email"
                               data-parsley-trigger="keyup"
                               data-parsley-required-message="@lang('trans.required')"
                               data-parsley-type="email"
                               data-parsley-type-message="يجب ادخال صيغة بريد الكترونى صحيحة مثال (example@example.com)"
                               placeholder="@lang('trans.email')"
                        >
                    </div>
                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="tel" class="form-control" id="" name="phone" placeholder="@lang('trans.phone')"
                               required
                               data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <select class="form-control" id="selectCountry" required
                                data-parsley-required-message="@lang('trans.required')">
                            <option disabled selected value="">@lang('trans.country')</option>

                            @foreach($countries as $country)
                                <option value="{{ $country->id }}">{{ anotherLangWhenDefaultNotFound($country, 'name') }}</option>
                            @endforeach
                        </select>
                        <img id="indicatorImageCountry" src="{{ request()->root() }}/public/assets/images/spinner.gif"
                             style="width: 50px; height: 50px; position: absolute; top: 5px; left: 34px; display: none;">
                    </div>
                </div>

                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <select class="form-control" name="city_id" id="selectCity" required
                                data-parsley-required-message="@lang('trans.required')">
                            <option disabled selected hidden>@lang('trans.city')</option>
                        </select>
                    </div>
                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" name="address" placeholder="@lang('trans.location')"
                               required
                               data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="password" class="form-control" name="password" id=""
                               placeholder="@lang('trans.password')"
                               required data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <select class="form-control" name="root_type" required
                                data-parsley-required-message="@lang('trans.required')">
                            <option disabled selected hidden>@lang('trans.service_type')</option>
                            <option value="airport">@lang('trans.airport')</option>
                            <option value="permit">@lang('trans.permit')</option>
                            <option value="hotels">@lang('trans.hotels')</option>
                            <option value="transport">@lang('trans.transport')</option>
                        </select>
                    </div>


                    <label class="text-dark">@lang('trans.choose_activity_type')</label>
                    <div class="radio-item">
                        <input type="radio" id="omra" name="activityType" value="0" checked onclick="showOmra()">
                        <label for="omra">@lang('trans.umrah')</label>
                    </div>

                    <div class="radio-item">
                        <input type="radio" id="hajj" onclick="showHajj()" name="activityType" value="1">
                        <label for="hajj">@lang('trans.haj')</label>
                    </div>
                    <div class="form-group ">
                        <div class="bg-form" id="omraDIV">

                            {{--<select class="form-control" name="weeks_no">--}}
                            {{--<option value="1">@lang('trans.week_1') </option>--}}
                            {{--<option value="2">@lang('trans.week_2') </option>--}}
                            {{--<option value="3">@lang('trans.week_3') </option>--}}
                            {{--</select>--}}

                            <input type="text" class="form-control" name="weeks_no"
                                   placeholder="@lang('trans.enter_period')">
                            <span class="help-block">@lang('trans.period_hint')</span>

                        </div>
                        <div class="none " id="hajjDIV">
                            <div class="row">
                                <div class="form-group col-xl-6">
                                    <!-- <input type="text" id="date" class="form-control floating-label" placeholder="Date"> -->
                                    <div class="bg-form">
                                        <input type="text" class="form-control " placeholder="@lang('trans.from')"
                                               name="dateFrom"
                                               id="date-start">
                                        <i class="fas fa-calendar-alt event-icon"></i>
                                    </div>
                                </div>
                                <div class="form-group col-xl-6">
                                    <div class="bg-form">
                                        <input type="text" class="form-control " placeholder="@lang('trans.to')"
                                               name="dateTo"
                                               id="date-end">
                                        <i class="fas fa-calendar-alt event-icon"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>


                </div>


                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <textarea class="form-control" rows="5" name="description" id="comment"
                                  placeholder="@lang('trans.company_description')"></textarea>
                    </div>


                    <div class="bg-form" style="margin-top: 15px;">
                        <!-- add more images -->
                        <label for="">@lang('trans.image')</label>

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


                <div class="form-check form-group col-xl-6 mr-3">
                    <label class="form-check-label">
                        <input type="checkbox" name="terms" checked required
                               data-parsley-required-message="يجب الموافقة علي الشروط والاحكام">
                        <span class="checkmark"></span> @lang('trans.agree_terms_and_conditions')
                    </label>
                </div>

                <div class="form-check form-group col-xl-12 mr-3">
                    <label class="form-check-label">
                        <input type="checkbox" data-parsley-checkmin="1" checked required name="check-swear"
                               data-parsley-required-message="يجب الموافقة علي التعهد والقسم">
                        <span class="checkmark"></span>@lang('trans.promise')
                    </label>
                </div>


                <div class="m-auto col-xl-12 text-center pb-5">

                    <button type="submit" class="btn default-bg text-white border-0 px-5 mt-3 mb-3" id="btnRegister">
                        @lang('trans.signup')
                    </button>
                    <img id="indicatorImage" src="{{ request()->root() }}/public/assets/images/spinner.gif"
                         style="width: 50px; height: 50px; display: none;">

                </div>


            </form>
        </div>
    </div>


@endsection


@section('scripts')


    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-material-design/0.5.10/js/ripples.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-material-design/0.5.10/js/material.min.js"></script>
    <script type="text/javascript"
            src="https://rawgit.com/FezVrasta/bootstrap-material-design/master/dist/js/material.min.js"></script>
    <script type="text/javascript" src="https://momentjs.com/downloads/moment-with-locales.min.js"></script>
    <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/front/js/bootstrap-material-datetimepicker.js"></script>

    <script>
        (function (i, s, o, g, r, a, m) {
            i['GoogleAnalyticsObject'] = r;
            i[r] = i[r] || function () {
                (i[r].q = i[r].q || []).push(arguments)
            }, i[r].l = 1 * new Date();
            a = s.createElement(o),
                m = s.getElementsByTagName(o)[0];
            a.async = 1;
            a.src = g;
            m.parentNode.insertBefore(a, m)
        })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

        ga('create', 'UA-60343429-1', 'auto');
        ga('send', 'pageview');
    </script>
    <script type="text/javascript">
        $(document).ready(function () {
            $('#date').bootstrapMaterialDatePicker({
                time: false,
                clearButton: true
            });

            $('#time').bootstrapMaterialDatePicker({
                date: false,
                shortTime: false,
                format: 'HH:mm'
            });

            $('#date-format').bootstrapMaterialDatePicker({
                format: 'dddd DD MMMM YYYY - HH:mm'
            });
            $('#date-fr').bootstrapMaterialDatePicker({
                format: 'DD/MM/YYYY HH:mm',
                lang: 'fr',
                weekStart: 1,
                cancelText: 'ANNULER',
                nowButton: true,
                switchOnClick: true
            });

            $('#date-end').bootstrapMaterialDatePicker({
                weekStart: 0,
                format: 'YYYY-MM-DD HH:mm',
                shortTime: false
            });
            $('#date-start').bootstrapMaterialDatePicker({
                weekStart: 0,
                format: 'YYYY-MM-DD HH:mm',
                shortTime: false
            }).on('change', function (e, date) {
                $('#date-end').bootstrapMaterialDatePicker('setMinDate', date);
            });

            $('#min-date').bootstrapMaterialDatePicker({
                format: 'DD/MM/YYYY HH:mm',
                minDate: new Date()
            });

            $.material.init()
        });
    </script>
    <!-- show and hide hajj , omra section -->


    <script>

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $('#agent-registration').on('submit', function (e) {
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
                    },
                    beforeSubmit: function () {
                        $("#progress-bar").width('0%');
                    },
                    uploadProgress: function (event, position, total, percentComplete) {
                        $("#progress-bar").width(percentComplete + '%');
                        $("#progress-bar").html('<div id="progress-status">' + percentComplete + ' %</div>')
                    },
                    resetForm: true
                });
            } else {

                $("#btnRegister").html("{{ __('trans.signup') }}");
                $("#indicatorImage").css('display', 'none');
            }


        });


        {{--$('.uploadFile').on('change',function () {--}}
        {{--var file = $(this).val();--}}


        {{--$.ajax({--}}
        {{--type: 'POST',--}}
        {{--url: '{{ route('upload.file') }}',--}}
        {{--data: {file: file},--}}
        {{--// cache: false,--}}
        {{--// contentType: false,--}}
        {{--// processData: false,--}}
        {{--success: function (data) {--}}

        {{--if (data.status == 200) {--}}


        {{--}--}}

        {{--if (data.status == 400) {--}}


        {{--}--}}

        {{--},--}}
        {{--error: function (data) {--}}
        {{--}--}}
        {{--});--}}
        {{--});--}}
    </script>


    <script>
        function showOmra() {
            $("#omraDIV").removeClass("none");
            $("#omraDIV").addClass("showDIV");

            //Make sure schoolDIV is not visible
            $("#hajjDIV").removeClass("showDIV");
            $("#hajjDIV").addClass("none");
        }

        function showHajj() {
            $("#hajjDIV").removeClass("none");
            $("#hajjDIV").addClass("showDIV");

            //Make sure bankDIV is not visible
            $("#omraDIV").removeClass("showDIV");
            $("#omraDIV").addClass("none");

        }

        function hideLink() {
            $("#link").removeClass("none");
            $("#link").addClass("showDIV");

            //Make sure schoolDIV is not visible
            $("#hajjDIV").removeClass("showDIV");
            $("#hajjDIV").addClass("none");
        }

        function showLink() {
            $("#hajjDIV").removeClass("none");
            $("#hajjDIV").addClass("showDIV");

            //Make sure bankDIV is not visible
            $("#link").removeClass("showDIV");
            $("#link").addClass("none");
        }


    </script>
    <script>


        $("#selectCountry").on('change', function (e) {
            e.preventDefault();

            $("#indicatorImageCountry").css('display', 'initial');

            var countryId = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.selected.cities') }}',
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


    </script>

@endsection

