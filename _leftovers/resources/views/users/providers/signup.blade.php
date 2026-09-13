@extends('layouts.master')

@section('content')
    <div class="hamla-header text-center text-white">
        <div class="overlay">
            <div class="w-50 m-auto header-details p-3">
                <h6 class="text-center">@lang('trans.welcome_arkabmaana')</h6>
                <h6 class="text-center">@lang('trans.add_service_data')</h6>
            </div>
        </div>
    </div>
    <!-- details  -->
    <div class="container pt-5 ">
        <div>
            <form id="provider-registration" action="{{ route('register.provider') }}" method="post"
                  class="row serviceProvider-signupForm" data-parsley-validate enctype="multipart/form-data">
                {{ csrf_field() }}
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" class="form-control" name="name" required id=""
                               placeholder="@lang('trans.company_name')"
                               data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <select class="form-control" name="service_id" required
                                data-parsley-required-message="@lang('trans.required')">
                            <option disabled selected hidden>@lang('trans.service_type')</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}">{{ anotherLangWhenDefaultNotFound($service, 'name')  }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="email" name="email" class="form-control" required id=""
                               placeholder="@lang('trans.email')" data-parsley-required-message="@lang('trans.required')"
                               data-parsley-trigger="keyup"
                               data-parsley-type="email"
                               data-parsley-type-message="@lang('trans.incorrect_email_format')"

                        >
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" name="permit_no" class="form-control" required id=""
                               placeholder="@lang('trans.permit_no')" data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" name="price_per_person" class="form-control" required id=""
                               placeholder="@lang('trans.service_price')" data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="tel" name="phone" class="form-control" required id="" placeholder="@lang('trans.phone')"
                               data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="text" name="address" class="form-control" required id="" placeholder="@lang('trans.location')"
                               data-parsley-required-message="@lang('trans.required')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <input type="password" name="password" class="form-control" required
                               data-parsley-required-message="@lang('trans.required')"
                               placeholder="@lang('trans.password')">
                    </div>
                </div>
                <div class="form-group col-xl-6">
                    <div class="bg-form">
                        <!-- add more images -->
                        <label for="">@lang('trans.images')</label>
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
                                    <input type="file" name="file3" id="image3"/>
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
                        <textarea class="form-control" name="description" rows="5" id="comment"
                                  placeholder="@lang('trans.company_description')"></textarea>
                    </div>

                    {{--<input  type="file" name="videos"/>--}}
                </div>
                <div class="form-check form-group col-xl-6 mr-3">
                    <label class="form-check-label">
                        <input type="checkbox" checked required name="terms"
                               data-parsley-required-message="@lang('trans.required')">
                        <span class="checkmark"></span>@lang('trans.agree_terms_and_conditions')
                    </label>
                </div>

                <div class="form-check form-group col-xl-12 mr-3">
                    <label class="form-check-label">
                        <input type="checkbox" checked required name="check-swear"
                               data-parsley-required-message="@lang('trans.required')">
                        <span class="checkmark"></span>@lang('trans.promise')
                    </label>
                </div>


                <div class="m-auto col-xl-12 text-center pb-5">
                    <button type="submit" class="btn default-bg text-white border-0 px-5 mt-3 mb-3" id="btnRegister">
                        @lang('trans.register')
                    </button>
                    <img id="indicatorImage" src="{{ request()->root() }}/public/assets/images/spinner.gif"
                         style="width: 50px; height: 50px; display: none;">

                </div>

            </form>
        </div>
    </div>
@endsection
@section('scripts')
    <script>

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
                    }
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

@endsection

