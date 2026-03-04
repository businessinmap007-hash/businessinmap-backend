<!DOCTYPE html>
<html lang="@if(app()->getLocale() == 'ar') ar @else en @endif"
      dir="@if(app()->getLocale() == 'ar') rtl @else ltr @endif">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>واندر هاند</title>

    @include('layouts._partials.styles')

    <style>

        .fixed-btn-advert {
            z-index: 0;
        }

        ‎

    </style>

    @if(auth()->check())
        <script>
            var userId = '{{ auth()->id() }}';
            var url = '{{ route('user.update.token') }}';
            var lang = '{{ config('app.locale') }}';
        </script>
    @endif


    <style>
        .remove-img {
            position: absolute;
            right: -5px;
            background: #c70000;
            padding: 5px;
            width: 25px;
            height: 25px;
            top: -6px;
            border-radius: 50%;
            color: white;
            font-size: 14px
        }

        .remove-img:hover {
            color: #c70000;
            background: #FFF;

        }

        #error_email {
            color: #a00000;
        }

        .select-convert{
            background: #00000033;
            width: 100%;
            margin: 10px 0;
            height: 40px;
            border: none;
            border-radius: 10px;
            color: #848484;
            outline: none;
        }

        .list-group-item.active {
            z-index: 2;
            color: #fff;
            background-color: #377D59;
            border-color: #377d59;
            padding-bottom: 40px;
        }
        .badge-primary {
            color: #fff;
            background-color: #377d59;
        }
    </style>


</head>


<body data-spy="scroll" data-target=".navbar-collapse" data-offset="100">

@include('layouts._partials.header')

@yield('content')


@include('layouts._partials.footer')

@include('layouts._partials.scripts')


<script src="https://www.gstatic.com/firebasejs/4.10.1/firebase.js"></script>
<script src="{{ request()->root() }}/public/assets/fcm/FCM-Setup.js"></script>

<script>

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

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




    $('.submission-form').on('submit', function (e) {

        e.preventDefault();
        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();
        if (form.parsley().isValid()) {

            // for (instance in CKEDITOR.instances)
            //     CKEDITOR.instances[instance].updateElement();

            $("#btn-submit").html('<i class="fas fa-spinner fa-spin"></i>').attr('disabled', true);
            $("#loading-spinner").fadeIn();

            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,

                success: function (data) {

                    if (data.status == 200) {

                        $("#btn-submit").html('{{ __('trans.submit') }}').attr('disabled', false);
                        $("#loading-spinner").fadeOut();

                        if (data.additional && data.additional['type'] !=  "update" || data.login) {
                            $('.submission-form')[0].reset();
                        }

                        $("#error-message-wrapper").css('display', 'none');


                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = '';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null,
                            "preventDuplicates": true,
                            "preventOpenDuplicates": true
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;


                        if (data.url) {
                            setTimeout(function () {
                                window.location.href = data.url;
                            }, 1000);
                        } else {
                            $('.hide-modal').modal('hide');
                        }

                    }

                    if (data.status == 400) {
                        $("#btn-submit").html('{{ __('trans.submit') }}').attr('disabled', false);

                        $("#loading-spinner").fadeOut();
                        $("#error-message-wrapper").css('display', 'block');
                        $("#error-message").html('- ' + data.message);

                        var shortCutFunction = 'error';
                        var msg = data.message;
                        var title = '';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null,
                            "preventDuplicates": true,
                            "preventOpenDuplicates": true

                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
                    }
                    if (data.status == 402) {

                        $("#btn-submit").html('{{ __('trans.submit') }}').attr('disabled', false);
                        $("#loading-spinner").fadeOut();
                        $("#error-message-wrapper").css('display', 'block');
                        $("#error-message").html('- ' + data.errors);

                        showMessage(data.errors, 'error');


                    }

                },


            });
        } else {
            $("#btn-submit").attr('disabled', false);

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
                dataType: "json",
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

        e.preventDefault();


        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();

        if (form.parsley().isValid()) {

            $("#registerBtnSubmit").html('{{ __('trans.signingup') }}');
            $("#indicatorImageRegUser").css('display', 'initial');
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {

                    $("#registerBtnSubmit").html('{{ __('trans.signup') }}');
                    $("#indicatorImageRegUser").css('display', 'none');
                    if (data.status == 200) {

                        setTimeout(function () {
                            $("#newMemberForm").modal('hide');
                        }, 1000);


                        // window.history.pushState("", "", '?activation=yes');
                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = '{{ __('trans.success') }}';
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
                        var shortCutFunction = 'error';
                        var msg = data.errors;
                        var title = 'خطأ';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;
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
                    var title = '{{ __('trans.success') }}';
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



    function isValidEmailAddress(emailAddress) {
        var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
        return pattern.test(emailAddress);
    };


    $("#subscriptionBtn").on('click', function () {


        $(this).html('<i class="fas fa-1x fa-spinner fa-spin" style="margin-top: 6px"></i>');
        var subscriptionEmail = $("#subscriptionEmail").val();
        if (subscriptionEmail == "") {
            $("#error_email").html("{{ __('trans.email_required') }}");
            $(this).html('{{ __('trans.subscription') }}');
            return;
        }

        if (!isValidEmailAddress(subscriptionEmail)) {
            $("#error_email").html("{{ __('trans.incorrect_email_format') }}");
            $(this).html('{{ __('trans.subscription') }}');
            return;
        }

        $.ajax({
            type: 'POST',
            {{--url: "{{ route('subscription.newsletter') }}",--}}
            data: {email: subscriptionEmail},
            // cache: false,
            // contentType: false,
            // processData: false,
            success: function (data) {
                $("#error_email").html("");

                $("#subscriptionBtn").html('{{ __('trans.subscription') }}');
                if (data.status == 200) {
                    $("#subscriptionEmail").val("");
                    var shortCutFunction = 'success';
                    var msg = data.message;
                    var title = '{{ __('trans.success') }}';
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

            },
            error: function (data) {
            }
        });


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

</script>

<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBjBZsq9Q11itd0Vjz_05CtBmnxoQIEGK8&language={{ config('app.locale') }}&libraries=places&callback=initAutocomplete"
        async defer></script>


</body>

</html>