<!DOCTYPE html>
<html lang="@if(app()->getLocale() == 'ar') ar @else en @endif"
      dir="@if(app()->getLocale() == 'ar') rtl @else ltr @endif">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Diet Dish</title>
    @include('layouts._partials.styles')

    <link type="text/css" rel="stylesheet" href="{{ request()->root() }}/public/assets/front/css/jquery-datepicker.css">

    <style>
        .fa-user, .fa-envelope {
            position: absolute;
            top: 15px;
            left: 30px;
            color: #bbb;
        }

        .cta-error {
            color: #a94442 !important;
        }

        .cta-success {
            color: #468847 !important;
        }

        input.parsley-success, textarea.parsley-success {
            color: #468847;
            border: 1px solid #d6e9c6;
            background-color: #dff0d8;
        }

        input.parsley-error, textarea.parsley-error {
            color: #b94a48;
            border: 1px solid #eed3d7;
            background-color: #f2dede;
        }

        ul.parsley-errors-list {
            font-size: 12px;
            margin: 2px;
            padding: 0;
            list-style-type: none;
        }

        ul.parsley-errors-list li {
            line-height: 12px;
            color: #a94442;
            font-size: 14px;
            text-align: right;
            width: 100%;
            margin: 0.5em auto;

        }

        input.parsley-error::-webkit-input-placeholder {
            color: #a94442 !important;
        }

        input.parsley-error:-moz-placeholder {
            color: #a94442 !important;
        }

        input.parsley-error::-moz-placeholder {
            color: #a94442 !important;
        }

        input.parsley-error:-ms-input-placeholder {
            color: #a94442 !important;
        }

        #activationCodeAfterRegisteration input {
            width: 20%;
        }

        section#orderDetails .card b {
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: auto;
            max-width: 90%;
        }
    </style>


    @if(auth()->check())
        <script>
            var userId = '{{ auth()->id() }}';
            var url = '{{ route('user.update.token') }}';
            var lang = '{{ config('app.locale') }}';
        </script>
@endif


<!-- Start of happydiet Zendesk Widget script -->
    <script id="ze-snippet"
            src="https://static.zdassets.com/ekr/snippet.js?key=b3ce13d0-526b-4605-b4e6-492f51fd0d7d"></script>
    <!-- End of happydiet Zendesk Widget script -->

    <!-- Facebook Pixel Code -->
    <script>
        !function (f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function () {
                n.callMethod ?
                    n.callMethod.apply(n, arguments) : n.queue.push(arguments)
            };
            if (!f._fbq) f._fbq = n;
            n.push = n;
            n.loaded = !0;
            n.version = '2.0';
            n.queue = [];
            t = b.createElement(e);
            t.async = !0;
            t.src = v;
            s = b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t, s)
        }(window, document, 'script',
            'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '585909221912702');
        fbq('track', 'PageView');
    </script>
    <noscript>
        <img height="1" width="1"
             src="https://www.facebook.com/tr?id=585909221912702&ev=PageView
&noscript=1"/>
    </noscript>

    <script type="text/javascript">
        window.zESettings = {
            webWidget: {
                launcher: {
                    chatLabel: {
                        '*': 'المحادثة المباشرة '
                    }
                },
                color: {
                    theme: '#8fa448',
                    launcher: '#8fa448',
                    launcherText: '#fff',
                    button: '#8fa448',
                    buttonText: '#fff',
                    resultLists: '#691840',
                    header: '#8fa448',
                    articleLinks: '#FF4500'
                }
            }
        };

        $("#page_site").addClass("container");


    </script>


</head>

<body data-spy="scroll" data-target=".navbar-collapse" data-offset="100">

@include('layouts._partials.header')

@yield('content')




<!-- LOGIN Modal -->
<div class="modal fade" id="login" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <h1>@lang('otrans.login')</h1>

                <p id="seccessMessage"></p>
                <form id="login-form" method="post" action="{{ route('user.login') }}" class="validation-form">
                    <div class="form-group">
                        <input type="number" class="form-control" name="phone" required
                               placeholder="@lang('otrans.phone')">
                        <span id="requiredPhone"></span>
                    </div>
                    <div class="form-group">
                        <input type="password" name="password" class="form-control" required
                               placeholder="@lang('otrans.password')"
                               id="myPass">
                    </div>
                    {{--<div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" required placeholder="كلمة المرور"
                               id="myPass">
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" onclick="myFunction()"><i
                                        class="ion-eye"></i>
                            </button>
                        </div>

                    </div>--}}
                    <a href="#" class="d-block mb-3" data-toggle="modal" data-target="#forgetPass"
                       data-dismiss="modal">@lang('otrans.forget_pass')</a>
                    <a href="javascript:;"
                       class="d-block mb-3 signupFormRegister">@lang('otrans.sub_with_diet_dish')</a>
                    <button type="submit" class="btn btn-primary" id="loginBtnSubmit">@lang('otrans.log_in')</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- signup Modal -->
<div class="modal fade" id="signup" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <h1>@lang("otrans.sub_with_diet_dish")</h1>
                <form id="signUp-form" action="{{ route('user.signup') }}" method="post" class="validation-form">
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <input type="text" name="name" class="form-control"
                                       placeholder="@lang('otrans.fullname')" required
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="@lang('trans.full_name')"
                                       data-parsley-maxlength="55"
                                       data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                       data-parsley-pattern-message="@lang('trans.system_not_accept_special_chars')"
                                       data-parsley-maxlength-message="@lang('trans.max_length55')">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <input type="number" name="phone" class="form-control phone"
                                       placeholder="@lang('otrans.phone')" required>
                                <span id="requiredPhoneReg"></span>
                            </div>
                        </div>


                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <input type="email" name="email" class="form-control email"
                                       placeholder="@lang('trans.email')"
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="@lang('trans.email')"
                                       data-parsley-maxlength="55"

                                >
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="input-group mb-3">
                                <input type="password" name="password" class="form-control"
                                       placeholder="@lang('otrans.password')" id="myPass1" required
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="@lang('otrans.pass_required')"
                                       data-parsley-maxlength="55"
                                       data-parsley-minlength="6"
                                       data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                       data-parsley-minlength-message=" أقل عدد الحروف المسموح بها هى (6) حرف">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="myFunction()"><i
                                                class="ion-eye"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <select class="form-control" name="city" required
                                        data-parsley-required-message="@lang('trans.required')">
                                    <option value="" selected disabled>@lang('otrans.city')</option>

                                    @foreach($cities->where('is_active', 1) as  $city)
                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                    @endforeach

                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <input type="text" name="state" value=""
                                       class="form-control minAndMax"
                                       placeholder="@lang('trans.state')" required data-parsley-required-message="@lang('trans.required')">
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <input type="text" name="street" value=""
                                       class="form-control minAndMax"
                                       placeholder="@lang('trans.street')" required  data-parsley-required-message="@lang('trans.required')">

                            </div>
                        </div>

                        <input type="hidden" name="lat" id="lat"
                               value="{{ auth()->check() ? auth()->user()->lat : "" }}">
                        <input type="hidden" name="lng" id="lng"
                               value="{{ auth()->check() ? auth()->user()->lng : "" }}">
                        <input type="hidden" name="address" id="addressApp">

                        <div class="col-12 col-md-8">
                            <p id="demoApp">


                                {{--ابراج غرناطة - المبنى الرابع - الدور الثاني عشر - الرياض 11332--}}

                                <img src="{{ request()->root() }}/public/assets/front/img/spinner.gif"
                                     style="margin-bottom:0 !important; width: 35px; height: 25px;"/>
                            </p>

                        </div>
                        <div class="col-12 col-md-4">
                            <button type="button" id="showMapModal"
                                    class="btn btn-outline-primary m-0">@lang('otrans.change')
                            </button>
                        </div>
                    </div>


                    <p>
                        @lang('otrans.by_subs')
                        <a href="#" class="mb-3" data-toggle="modal"
                           data-target="#terms">@lang('otrans.terms_conditions')</a>
                    </p>
                    <button class="btn btn-primary">@lang('otrans.log_in')</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- forget password Modal -->
<div class="modal fade" id="forgetPass" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <h1>@lang('otrans.forget_pass')</h1>
                <p>@lang('otrans.please_enter_phone')</p>
                <form id="forgotpassword-form" action="{{ route('user.forgot.password') }}" method="post">
                    <div class="form-group">
                        <input type="number" name="phone" class="form-control" placeholder="@lang('otrans.phone')">
                    </div>
                    <button class="btn btn-primary">@lang('otrans.send')</button>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="activationCode" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <h1>@lang('otrans.forget_pass')</h1>
                <p>@lang('otrans.please_enter_phone')</p>
                <form id="checkCode" action="{{ route('check.reset.code') }}" method="post">

                    <input type="hidden" id="resendActivationPhoneReset" value="{{ session()->get("phone") }}">

                    <div class="row w-75 m-auto mb-2 text-center justify-content-center">
                        <input type="text" name="activation_code" class="form-control text-center m-1" placeholder=""
                               maxlength="4" style="width: 100% !important;letter-spacing: 15px;">
                        {{--<input type="text" name="code2" class="form-control text-center m-1" placeholder="-" maxlength="1" min="0">--}}
                        {{--<input type="text" name="code3" class="form-control text-center m-1" placeholder="-" maxlength="1" min="0">--}}
                        {{--<input type="text" name="code4" class="form-control text-center m-1" placeholder="-" maxlength="1" min="0">--}}
                    </div>
                    <a href="javascript:;" class="d-block mb-3 resendActivation"
                       date-type="reset">@lang('otrans.resend_code')</a>
                    <button class="btn btn-primary">@lang('otrans.send')</button>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="activationCodeAfterRegisteration" tabindex="-1" role="dialog"
     aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <h1>@lang('otrans.activate_account')</h1>
                <p>@lang("otrans.enter_phone_to_send_activation_code")</p>
                <form id="checkCodeAndActivation" action="{{ route('activation.account') }}" method="post">
                    <input type="hidden" id="resendActivationPhone" value="{{ session()->get("phoneForResend") }}">
                    <div class="row w-75 m-auto mb-2 text-center justify-content-center">
                        <input type="text" name="activation_code" class="form-control text-center m-1" placeholder=""
                               maxlength="4" style="width: 100% !important;letter-spacing: 15px;">

                    </div>
                    <a href="javascript:;" class="d-block mb-3 resendActivation"
                       data-type="activation"> @lang('otrans.resend_activation_code')</a>
                    <button class="btn btn-primary">@lang('otrans.send')</button>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- resetPassword Modal -->
<div class="modal fade" id="resetPassword" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <h1>@lang('otrans.re_assign_new_pass')</h1>
                <form id="paswordReset-form" action="{{ route('reset.password') }}" method="post">
                    <div class="form-group">
                        <input type="password" name="password" class="form-control"
                               placeholder="@lang('otrans.new_pass')">
                    </div>
                    <div class="form-group">
                        <input type="password" name="password_confirmation" class="form-control"
                               placeholder="@lang('otrans.new_pass_confirm')">
                    </div>
                    <button class="btn btn-primary">@lang('otrans.log_in')</button>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- terms Modal -->
<div class="modal fade" id="terms" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle"
     aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <h1>@lang('otrans.terms_conditions')</h1>
                <p style="margin: 0 20px">

                    {!! htmlspecialchars_decode($setting->getBody('terms_website_'.app()->getLocale())) !!}


                </p>
                <a href="#" class="btn btn-primary" data-dismiss="modal">@lang('otrans.accept')</a>
            </div>
        </div>
    </div>
</div>


@if(Auth::user() && Auth::user()->user_type_id != 1)
    <div class="chat">
        <div id="chat-circle" class="btn btn-raised">
            <div id="chat-overlay"></div>
            <p>{{ trans('otrans.instant_chat') }}</p>
        </div>

        <div class="chat-box">
            <div class="chat-box-header">
                {{ trans('otrans.instant_chat') }}
                <span class="chat-box-toggle"><i class="ionicons ion-close-round"></i></span>

                {{--<div class="users">--}}
                {{--<label class="alert alert-light"><img src="{{request()->root()}}/public/assets/front/img/account.png">Abdulaziz--}}
                {{--<!--{{ trans('messages.abdulaziz') }}-->--}}
                {{--</label>--}}
                {{--<label class="alert alert-light"><img src="{{request()->root()}}/public/assets/front/img/account.png">Abdullah--}}
                {{--<!--{{ trans('messages.abdullah') }}-->--}}
                {{--</label>--}}
                {{--<label class="alert alert-light"><img src="{{request()->root()}}/public/assets/front/img/account.png">Sabiha--}}
                {{--<!--{{ trans('messages.sbeha') }}-->--}}
                {{--</label>--}}
                {{--</div>--}}
            </div>
            <div class="chat-box-body">
                <div class="chat-box-overlay">
                </div>
                <div class="chat-logs">
                    @foreach(\App\Models\Messages::where('sender_id',Auth::user()->id)->orWhere('reciever_id',Auth::user()->id)->orderBy('id','ASC')->get() as $message)
                        <div id="cm-msg-{{ $message->id }}"
                             class="chat-msg {{ $message->sender_id==Auth::user()->id ? 'self' : 'user' }}" style="">
                    <span class="msg-avatar">
                        <img src="/site/img/account.png">
                    </span>
                            <div class="cm-msg-text">
                                {{ $message->message }}
                            </div>
                        </div>
                    @endforeach
                </div><!--chat-log -->
            </div>
            <div class="chat-input">
                <form>
                    <input type="text" id="chat-input" placeholder="{{ trans('otrans.your_message') }}"/>
                    <button type="submit" class="chat-submit" id="chat-submit"><i class="ionicons ion-android-send"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    {{--@elseif(!Auth::user())--}}
    {{--<div class="chat">--}}
    {{--<a href="/{{ App::getLocale() }}/login">--}}
    {{--<div id="chat-circle" class="btn btn-raised">--}}
    {{--<div id="chat-overlay"></div>--}}
    {{--<p>{{ trans('otrans.instant_chat') }}</p>--}}
    {{--</div>--}}
    {{--</a>--}}
    {{--</div>--}}
@endif





<!-- Modal -->
<div class="modal fade" id="mapLocation" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body" style="overflow: hidden; padding: 1em !important;">

                <div id="map" style="width: 100%; height: 300px;"></div>

            </div>
        </div>
    </div>
</div>

@include('layouts._partials.scripts')

<script type="text/javascript"
        src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>

<script src="https://www.gstatic.com/firebasejs/4.10.1/firebase.js"></script>
<script src="{{ request()->root() }}/public/assets/fcm/FCM-Setup.js"></script>
<script type="text/javascript" src="{{ request()->root() }}/public/assets/front/js/jquery-datepicker.js"></script>

<script>


    @if(request('try-access') && request('try-access') == "yes")
    $("#login").modal("show");
    @endif

    @if(session()->has('success'))
    setTimeout(function () {
        showMessage('{{ session()->get('success') }}');
    }, 1000);

    @endif



    @if(session()->has('error'))
    setTimeout(function () {
        showMessageError('{{ session()->get('error') }}');
    }, 1000);

    @endif

    function showMessage(message) {

        var shortCutFunction = 'success';
        var msg = message;
        var title = "@lang('institutioncp.success')";
        toastr.options = {
            positionClass: 'toast-top-left',
            onclick: null
        };
        var $toast = toastr[shortCutFunction](msg, title);
        // Wire up an event handler to a button in the toast, if it exists
        $toastlast = $toast;


    }


    $('#showMapModal').on('click', function () {
        $('#mapLocation').modal('show');
    });


    $('.signupFormRegister').on('click', function () {
        $('#login').modal('hide');
        $('#signup').modal('show');
    });


    @if(session()->has('errorSubs'))
    setTimeout(function () {
        showMessageError('{{ session()->get('errorSubs') }}');
    }, 1000);

    @endif

    function showMessageError(message) {

        var shortCutFunction = 'error';
        var msg = message;
        var title = "@lang('institutioncp.error')";
        toastr.options = {
            positionClass: 'toast-top-left',
            onclick: null
        };
        var $toast = toastr[shortCutFunction](msg, title);
        // Wire up an event handler to a button in the toast, if it exists
        $toastlast = $toast;


    }


    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });


    $('#login-form').on('submit', function (e) {

        e.preventDefault();

        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();
        if (form.parsley().isValid()) {
            $("#loginBtnSubmit").html('@lang('otrans.current_login')');
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {

                    $("#loginBtnSubmit").html('@lang('otrans.log_in')');

                    if (data.status == 200) {

                        $("#login").modal('hide');

                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = '@lang('otrans.success')';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;


                        setTimeout(function () {
                            window.location.href = "{{ url()->current() }}";
                        }, 2000);
                    }

                    if (data.status == 400) {


                        if (data.type && data.type == "notactive") {
                            $("#login").modal("hide");
                            $("#activationCodeAfterRegisteration").modal("show");
                        }

                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = '@lang('otrans.error')';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }

                    if (data.status == 402) {
                        if (data.errors['phone']) {
                            $("#requiredPhone").html(data.errors['phone']);
                        }
                        if (data.errors['password']) {
                            console.log(data.errors['password']);
                        }
                    }

                },
                error: function (data) {
                }
            });
        } else {
            $('.loading').hide();
        }
    });

    $('#forgotpassword-form').on('submit', function (e) {
        // $("#loginBtnSubmit").html('جاري تسجيل الدخول...');
        e.preventDefault();

        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();
        if (form.parsley().isValid()) {
            $('.loading').show();
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {

                    $("#loginBtnSubmit").html('دخول');

                    if (data.status == 200) {

                        $("#login-form").modal('hide');
                        $("#forgetPass").modal('hide');
                        $("#activationCode").modal('show');

                        $("#resendActivationPhoneReset").val(data.phone);

                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = 'نجاح';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }
                    if (data.status == 400) {
                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = 'خطأ';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }

                    if (data.status == 402) {
                        if (data.errors['phone']) {
                            $("#requiredPhone").html(data.errors['phone']);
                        }
                        if (data.errors['password']) {
                            console.log(data.errors['password']);
                        }
                    }

                },
                error: function (data) {
                }
            });
        } else {
            $('.loading').hide();
        }
    });

    $('#checkCode').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();
        if (form.parsley().isValid()) {
            $('.loading').show();
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {

                    if (data.status == 200) {
                        $("#resetPassword").modal('show');
                        $("#checkCode").modal('hide');
                        $("#activationCode").modal('hide');
                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = 'نجاح';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };

                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;

                    }

                    if (data.status == 400) {
                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = 'خطأ';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }

                    if (data.status == 402) {
                        if (data.errors['phone']) {
                            $("#requiredPhone").html(data.errors['phone']);
                        }
                        if (data.errors['password']) {
                            console.log(data.errors['password']);
                        }
                    }

                },
                error: function (data) {
                }
            });
        } else {
            $('.loading').hide();
        }
    });

    $('#paswordReset-form').on('submit', function (e) {
        // $("#loginBtnSubmit").html('جاري فحص الكود...');
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

                        $("#resetPassword").modal('hide');

                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = 'نجاح';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;

                    }

                    if (data.status == 400) {
                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = 'خطأ';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }

                    if (data.status == 402) {
                        if (data.errors['phone']) {
                            $("#requiredPhone").html(data.errors['phone']);
                        }
                        if (data.errors['password']) {
                            console.log(data.errors['password']);
                        }
                    }

                },
                error: function (data) {
                }
            });
        } else {
            $('.loading').hide();
        }
    });

    $('#checkCodeAndActivation').on('submit', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();
        if (form.parsley().isValid()) {
            // $("#loginBtnSubmit").html('جاري فحص الكود...');
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {
                    if (data.status == 200) {
                        $("#login").modal('show');
                        $("#activationCodeAfterRegisteration").modal("hide");
                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = 'نجاح';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;

                    }

                    if (data.status == 400) {
                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = 'خطأ';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }

                    if (data.status == 402) {
                        if (data.errors['phone']) {
                            $("#requiredPhone").html(data.errors['phone']);
                        }
                        if (data.errors['password']) {
                            console.log(data.errors['password']);
                        }
                    }

                },
                error: function (data) {
                }
            });
        } else {
            $('.loading').hide();
        }
    });

    $('#signUp-form').on('submit', function (e) {
        // $("#loginBtnSubmit").html('جاري فحص الكود...');
        e.preventDefault();


        var addressApp = $("#addressApp").val();
        if (addressApp == "") {
            $("#demoApp").html("{{ __('trans.select_correct_address') }}");
            $("#demoApp").css("margin-top", "20px");
            return false;
        }


        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();

        if (form.parsley().isValid()) {
            $('.loading').show();
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {

                    if (data.status == 200) {

                        $("#signup").modal('hide');

                        $("#activationCodeAfterRegisteration").modal("show");
                        window.history.pushState("", "", '?activation=yes');
                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = 'نجاح';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;

                    }

                    if (data.status == 400) {
                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = 'خطأ';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }

                    if (data.status == 402) {
                        if (data.errors['phone']) {
                            $("#requiredPhoneReg").html(data.errors['phone']);
                        }
                        if (data.errors['password']) {
                            console.log(data.errors['password']);
                        }
                    }

                },
                error: function (data) {
                }
            });
        } else {


            $('.loading').hide();
        }
    });


    $(".resendActivation").on('click', function () {


        var type = $(this).attr('data-type');

        if (type == 'reset') {


            var phone = $("#resendActivationPhoneReset").val();

        } else {
            var phone = $("#resendActivationPhone").val();
        }


        $.ajax({
            type: 'POST',
            url: "{{ route('resend.activation.code') }}",
            data: {phone: phone},
            // cache: false,
            // contentType: false,
            // processData: false,
            success: function (data) {

                if (data.status == 200) {
                    var shortCutFunction = 'success';
                    var msg = data.message;
                    var title = 'نجاح';
                    toastr.options = {
                        positionClass: 'toast-top-left',
                        onclick: null
                    };
                    var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                    $toastlast = $toast;
                }

                if (data.status == 400) {
                    var shortCutFunction = 'error';
                    var msg = data.message;
                    var title = 'خطأ';
                    toastr.options = {
                        positionClass: 'toast-top-left',
                        onclick: null
                    };
                    var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                    $toastlast = $toast;
                }

                if (data.status == 402) {
                    if (data.errors['phone']) {
                        $("#requiredPhoneReg").html(data.errors['phone']);
                    }
                    if (data.errors['password']) {
                        console.log(data.errors['password']);
                    }
                }

            },
            error: function (data) {
            }
        });
    });

    @if(request('activation') == 'yes')
    $("#activationCodeAfterRegisteration").modal("show");
    $('#activationCodeAfterRegisteration2').modal('show');
    @endif

    $(document).ready(function () {
        $('.validation-form').parsley();
    });


    <!--chat script-->
    //
    // $(function () {
    //     var INDEX = 0;
    //     $("#chat-submit").click(function (e) {
    //         e.preventDefault();
    //         var msg = $("#chat-input").val();
    //         if (msg.trim() == '') {
    //             return false;
    //         }
    //         generate_message(msg, 'self');
    //         setTimeout(function () {
    //             generate_message(msg, 'user');
    //         }, 1000)
    //
    //     });
    //
    //     function generate_message(msg, type) {
    //         INDEX++;
    //         var str = "";
    //         str += "<div id='cm-msg-" + INDEX + "' class=\"chat-msg " + type + "\">";
    //         str += "          <span class=\"msg-avatar\">";
    //         str += "            <img src='img/account.png'>";
    //         str += "          <\/span>";
    //         str += "          <div class=\"cm-msg-text\">";
    //         str += msg;
    //         str += "          <\/div>";
    //         str += "        <\/div>";
    //         $(".chat-logs").append(str);
    //         $("#cm-msg-" + INDEX).hide().fadeIn(300);
    //         if (type == 'self') {
    //             $("#chat-input").val('');
    //         }
    //         $(".chat-logs").stop().animate({scrollTop: $(".chat-logs")[0].scrollHeight}, 1000);
    //     }
    //
    //
    //     $(document).delegate(".chat-btn", "click", function () {
    //         var value = $(this).attr("chat-value");
    //         var name = $(this).html();
    //         $("#chat-input").attr("disabled", false);
    //         generate_message(name, 'self');
    //     });
    //
    //     $("#chat-circle").click(function () {
    //         $("#chat-circle").toggle('scale');
    //         $(".chat-box").toggle('scale');
    //     });
    //
    //     $(".chat-box-toggle").click(function () {
    //         $("#chat-circle").toggle('scale');
    //         $(".chat-box").toggle('scale');
    //     })
    //
    // })


    <!--chat script-->

    $(function () {
        var INDEX = 0;
        $(".chat-logs").stop().animate({scrollTop: $(".chat-logs")[0].scrollHeight}, 1000);
        $("#chat-submit").click(function (e) {
            e.preventDefault();
            var msg = $("#chat-input").val();
            if (msg.trim() == '') {
                return false;
            }

            $("#chat-input").val(' ');

            $.ajax({
                url: "{{ route('send.chat.message') }}",
                type: "POST",
                data: {message: msg},
                dataType: "json",
                success: function (data) {
                    generate_message(data.message, 'self', data.id);
                    $("#chat-input").val('')
                }
            });


            //generate_message(msg, 'self');


        });

        function generate_message(msg, type, ind) {
            var str = "";
            str += "<div id='cm-msg-" + ind + "' class=\"chat-msg " + type + "\">";
            str += "<span class=\"msg-avatar\">";
            str += "<img src='/site/img/account.png'>";
            str += "<\/span>";
            str += "<div class=\"cm-msg-text\">";
            str += msg;
            str += "<\/div>";
            str += "        <\/div>";
            $(".chat-logs").append(str);
            if (type == 'self') {
                $("#chat-input").val('');
            }
            $(".chat-logs").stop().animate({scrollTop: $(".chat-logs")[0].scrollHeight}, 1000);
        }


        $(document).delegate(".chat-btn", "click", function () {
            var value = $(this).attr("chat-value");
            var name = $(this).html();
            $("#chat-input").attr("disabled", false);
            generate_message(name, 'self');
        });

        $("#chat-circle").click(function () {
            $("#chat-circle").toggle('scale');
            $("#notificationIcon").toggle('scale');
            $(".chat-box").toggle('scale');
        });

        $(".chat-box-toggle").click(function () {
            $("#chat-circle").toggle('scale');
            $("#notificationIcon").toggle('scale');
            $(".chat-box").toggle('scale');
        })

        @if(Auth::user())
        setInterval(ajaxCall, 5000); //300000 MS == 5 minutes

        function ajaxCall() {
            $.ajax({
                url: "{{ route('get.chat.message') }}",
                type: "Get",
                // data: {message : msg},
                dataType: "json",
                success: function (data) {
                    if (data == 0) {
                    } else {
                        generate_message(data.message, 'user', data.id);
                    }
                }
            });
        }
        @endif
    });


</script>


<script>


    var map;

    function initAutocomplete() {


        map = new google.maps.Map(document.getElementById('map'), {
            center: {lat: 24.774265, lng: 46.738586},
            zoom: 16,
            mapTypeId: 'roadmap'
        });


        // bounds = map.getBounds();
        // var zoom = map.getZoom();

        // console.log(zoom);


        getLocation();


        var marker;

        var singleClick = false;
        google.maps.event.addListener(map, 'click', function (event) {
            singleClick = true;

            map.setZoom();
            var mylocation = event.latLng;
            map.setCenter(mylocation);

            codeLatLng(event.latLng.lat(), event.latLng.lng());


            $('#lat').val(event.latLng.lat());
            $('#lng').val(event.latLng.lng());


            setTimeout(function () {

                if (singleClick === true) {
                    $("#mapLocation").modal('hide');
                }

                if (!marker)
                    marker = new google.maps.Marker({position: mylocation, map: map});
                else
                    marker.setPosition(mylocation);

            }, 3000);

        });


        google.maps.event.addListener(map, 'dblclick', function (event) {
            singleClick = false;
        });


        // Create the search box and link it to the UI element.
        var input = document.getElementById('pac-input');
        var searchBox = new google.maps.places.SearchBox(input);
        map.controls[google.maps.ControlPosition.TOP_LEFT].push(input);

        // Bias the SearchBox results towards current map's viewport.
        map.addListener('bounds_changed', function () {
            searchBox.setBounds(map.getBounds());
        });

        var markers = [];
        // Listen for the event fired when the user selects a prediction and retrieve
        // more details for that place.
        searchBox.addListener('places_changed', function () {
            var places = searchBox.getPlaces();
            // var location = place.geometry.location;
            // var lat = location.lat();
            // var lng = location.lng();
            if (places.length == 0) {
                return;
            }

            // Clear out the old markers.
            markers.forEach(function (marker) {
                marker.setMap(null);
            });
            markers = [];

            // For each place, get the icon, name and location.
            var bounds = new google.maps.LatLngBounds();
            places.forEach(function (place) {
                if (!place.geometry) {
                    console.log("Returned place contains no geometry");
                    return;
                }
                var icon = {
                    url: place.icon,
                    size: new google.maps.Size(71, 71),
                    origin: new google.maps.Point(0, 0),
                    anchor: new google.maps.Point(17, 34),
                    scaledSize: new google.maps.Size(25, 25)
                };

                // Create a marker for each place.
                markers.push(new google.maps.Marker({
                    map: map,
                    icon: icon,
                    title: place.name,
                    position: place.geometry.location
                }));

                if (place.geometry.viewport) {
                    // Only geocodes have viewport.
                    bounds.union(place.geometry.viewport);
                } else {
                    bounds.extend(place.geometry.location);
                }
                $('#lat').val(place.geometry.location.lat());
                $('#lng').val(place.geometry.location.lng());


            });
            map.fitBounds(bounds);


        });


    }


    function getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(showPosition);

        } else {

        }
    }


    function showPosition(position) {

        map.setCenter({lat: position.coords.latitude, lng: position.coords.longitude});
        $("#lat").val(position.coords.latitude);
        $("#lng").val(position.coords.longitude);
        codeLatLng(position.coords.latitude, position.coords.longitude);


    }


    function codeLatLng(lat, lng) {

        var geocoder = new google.maps.Geocoder();
        var latlng = new google.maps.LatLng(lat, lng);
        geocoder.geocode({
            'latLng': latlng
        }, function (results, status) {


            if (status === google.maps.GeocoderStatus.OK) {
                if (results[0]) {
                    // console.log(results[1].formatted_address);
                    $("#demoApp").html(results[0].formatted_address);
                    $("#addressApp").val(results[0].formatted_address);


                    // console.log(results);


                } else {
                    //alert('No results found');
                }
            } else {
                alert('Geocoder failed due to: ' + status);
            }
        });
    }


    // $('#showMapModal').on('click', function () {
    //     $('#mapLocation').modal('show');
    // });
</script>


<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBjBZsq9Q11itd0Vjz_05CtBmnxoQIEGK8&language={{ config('app.locale') }}&libraries=places&callback=initAutocomplete"
        async defer></script>


<script type="text/javascript">
    $('.jquery-datepicker').datepicker({
        date: "+2d",
        startDate: "+2d",
        format: 'yyyymmdd',

        en: {
            months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            days: ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU']
        }

    });
</script>

</body>

</html>