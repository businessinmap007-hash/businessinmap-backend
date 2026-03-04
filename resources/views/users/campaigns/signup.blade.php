@extends('layouts.master')

@section('content')
    <div class="hamla-header text-center text-white">
        <div class="overlay">
            <div class="w-50 m-auto header-details p-3">
                <h6 class="text-center mt-3">@lang('trans.welcome_arkabmaana')</h6>
                <h6 class="text-center">@lang('trans.campaign_join')</h6>
            </div>
        </div>
    </div>


    <div class="container pt-5 ">
        <div>
            <form id="provider-registration" action="{{ route('register.campaign') }}" method="post"
                  class="row serviceProvider-signupForm" data-parsley-validate enctype="multipart/form-data">

                {{ csrf_field() }}

                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text"
                               class="form-control"
                               name="name"
                               required
                               data-parsley-required-message="@lang('trans.required')"
                               placeholder="@lang('trans.campaign_name')"
                        >
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <select class="form-control" name="service_id" required
                                data-parsley-required-message="@lang('trans.required')">
                            <option disabled selected hidden>@lang('trans.campaign_type')</option>
                            @foreach($campaignTypes as $type)
                                <option value="{{ $type->id }}">{{ anotherLangWhenDefaultNotFound($type, 'name')  }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" required
                               name="email"
                               data-parsley-trigger="keyup"
                               data-parsley-required-message="@lang('trans.required')"
                               data-parsley-type="email"
                               data-parsley-type-message="@lang('trans.incorrect_email_format')"
                               placeholder="@lang('trans.email')"
                        >
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <?php $locations = explode(',', $setting->getBody('menna_locations_' . app()->getLocale()));?>
                    <div id="output"></div>
                    <select data-placeholder="@lang('trans.mina_locations')" name="campaign_location_minaa[]" multiple
                            class="chosen-select form-control" required
                            data-parsley-required-message="@lang('trans.required')">
                        @foreach($locations as $location)
                            <option value="{{ $location }}"> {{ $location }}</option>
                        @endforeach

                    </select>
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
                        <input type="text" class="form-control" id="" name="price_per_person" placeholder="@lang('trans.price_per_person')"
                               required data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" name="seats_no" placeholder="@lang('trans.available_seats')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" name="rate" id="" placeholder="@lang("trans.campaign_rate")">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" name="permit_no" id="" placeholder="@lang('trans.permit_no')"
                               data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" id="" name="address" placeholder="@lang('trans.location')" required
                               data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="tel" class="form-control" id="" name="phone" placeholder="@lang('trans.phone')" required
                               data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="password" class="form-control" name="password" id="" placeholder="@lang('trans.password')"
                               required data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" name="requirements" placeholder="@lang('trans.campaign_requirements')">
                    </div>
                </div>

                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <textarea class="form-control h-auto" id="comment" name="features" placeholder="@lang('trans.campaign_features')"
                                  spellcheck="false"></textarea>
                    </div>
                    {{--</div>--}}

                    {{--<div class="form-group col-xl-6">--}}
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
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <textarea class="form-control" rows="5" name="description" id="comment"
                                  placeholder="@lang('trans.campaign_description')"></textarea>
                    </div>
                    <label class="text-dark">@lang('trans.want_a_connection')</label>

                    <div class="radio-item">
                        <input type="radio" id="no" name="is_connected" value="0" checked onclick="showLink()">
                        <label for="no">@lang('trans.no')</label>
                    </div>


                    <div class="radio-item">
                        <input type="radio" id="yes" name="is_connected" value="1" onclick="hideLink()">
                        <label for="yes">@lang('trans.yes')</label>
                    </div>




                    <div class=" none" id="link">


                        <div class="form-group">
                            <div class="bg-form" style="position: relative;">
                                <select class="form-control" id="selectCountryConnection"
                                        data-parsley-required-message="@lang('trans.required')">
                                    <option disabled selected value="">@lang('trans.country')</option>

                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}">{{ anotherLangWhenDefaultNotFound($country, 'name') }}</option>
                                    @endforeach
                                </select>
                                <img id="indicatorImageCountryConnection"
                                     src="{{ request()->root() }}/public/assets/images/spinner.gif"
                                     style="width: 50px; height: 50px; position: absolute; top: 5px; left: 34px; display: none;">
                            </div>
                        </div>


                        <div class="form-group">
                            <div class="bg-form">
                                <select class="form-control" name="connection_city" id="selectCityConnection"
                                        data-parsley-required-message="@lang('trans.required')">
                                    <option disabled selected hidden>@lang('trans.city')</option>
                                </select>
                            </div>
                        </div>


                        <div class="image-upload form-group">
                            <label for="custom-file-upload-image" class="filupp">
                                <span class="filupp-file-name js-value">@lang('trans.link_contract')</span>
                                <input type="file" name="image" id="custom-file-upload-image" class="upload-file"/>
                                <i class="fas fa-camera"></i> </label>
                        </div>
                    </div>


                </div>


                <div class="form-check form-group col-xl-6 mr-3">
                    <label class="form-check-label">
                        <input type="checkbox" data-parsley-checkmin="1" required checked name="terms"
                               data-parsley-required-message="@lang('trans.required')">
                        <span class="checkmark"></span>@lang('trans.agree_terms_and_conditions')
                    </label>
                </div>

                <div class="form-check form-group col-xl-12 mr-3">
                    <label class="form-check-label">
                        <input type="checkbox" data-parsley-checkmin="1" required checked name="check-swear"
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

    <script src="{{ request()->root() }}/public/assets/front/js/choose.js"></script>
    <script>
        document.getElementById('output').innerHTML = location.search;
        $(".chosen-select").chosen();


        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });


        $('#provider-registration').on('submit', function (e) {
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
    </script>

    <script>
        function hideLink() {
            $("#link").removeClass("none");
            $("#link").addClass("showDIV");

            //Make sure schoolDIV is not visible
            $("#hajjDIV").removeClass("showDIV");
            $("#hajjDIV").addClass("none");


            $("#selectCountryConnection").attr('required', true);
            $("#selectCityConnection").attr('required', true);
        }

        function showLink() {
            $("#hajjDIV").removeClass("none");
            $("#hajjDIV").addClass("showDIV");

            //Make sure bankDIV is not visible
            $("#link").removeClass("showDIV");
            $("#link").addClass("none");

            $("#selectCountryConnection").attr('required', false);
            $("#selectCityConnection").attr('required', false);

        }


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


        $("#selectCountryConnection").on('change', function (e) {
            e.preventDefault();

            $("#indicatorImageCountryConnection").css('display', 'initial');

            var countryId = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.selected.cities') }}',
                data: {countryId: countryId},
                dataType: 'json',
                success:
                    function (response) {
                        $("#indicatorImageCountryConnection").css('display', 'none');


                        if (response) {
                            $("#selectCityConnection").empty();
                            $("#selectCityConnection").prop('disabled', false);
                            $("#selectCityConnection").append('<option value="" selected disabled>اختار المدينة </option>');
                            $.each(response, function (key, value) {
                                $("#selectCityConnection").append('<option value="' + value.id + '">' + value.name + '</option>');
                            });
                            $("#selectCityConnection").select2();
                        } else {
                            $("#selectCityConnection").empty();
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

