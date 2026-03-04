<!DOCTYPE html>
<html lang="en" dir="{{ $main->designDirection() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Oil Trips Dashboard">
    <meta name="author" content="Hassan Saeed">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" href="{{ request()->root() }}/public/assets/admin/images/favicon.ico">


    <title>@lang('global.dashboard') | @yield('title')</title>

    <!--Morris Chart CSS -->
    <link href="https://fonts.googleapis.com/css?family=Tajawal" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">


    @include('admin.layouts._partials.styles')

    @yield('styles')


    <style>

        /*.card, .card-box, .panal, .pop-animate {*/
        /*    transition: all 1s;*/
        /*    transform: scale(0);*/
        /*    opacity: 0.5;*/
        /*}*/

        /*.card.show, .card-box.show, .panal.show, .pop-animate.show {*/
        /*    transform: scale(1);*/
        /*    opacity: 1;*/
        /*}*/

        .ms-container {
            width: 100%;
            float: right;
        }

        .dropify-wrapper .dropify-preview .dropify-render img {
            width: 100%;
        }

        input,
        input::-webkit-input-placeholder {
            font-size: 11px;
            line-height: 3;
        }

        .dt-buttons {
            position: absolute !important;
            left: 10px !important;
            top: -30px !important;
        }

        @media print {

            body {

                direction: rtl;
            }

            .optionHidden {
                display: none !important;
            }
        }

        .ms-container .ms-selectable, .ms-container .ms-selection {
            background: #fff;
            color: #555555;
            float: right;
            width: 45%;
        }

        /* Absolute Center Spinner */
        .loading {
            position: fixed;
            z-index: 999;
            height: 2em;
            width: 2em;
            overflow: show;
            margin: auto;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }

        /* Transparent Overlay */
        .loading:before {
            content: '';
            display: block;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.3);
        }

        /* :not(:required) hides these rules from IE9 and below */
        .loading:not(:required) {
            /* hide "loading..." text */
            font: 0/0 a;
            color: transparent;
            text-shadow: none;
            background-color: transparent;
            border: 0;
        }

        .loading:not(:required):after {
            content: '';
            display: block;
            font-size: 10px;
            width: 1em;
            height: 1em;
            margin-top: -0.5em;
            -webkit-animation: spinner 1500ms infinite linear;
            -moz-animation: spinner 1500ms infinite linear;
            -ms-animation: spinner 1500ms infinite linear;
            -o-animation: spinner 1500ms infinite linear;
            animation: spinner 1500ms infinite linear;
            border-radius: 0.5em;
            -webkit-box-shadow: rgba(0, 0, 0, 0.75) 1.5em 0 0 0, rgba(0, 0, 0, 0.75) 1.1em 1.1em 0 0, rgba(0, 0, 0, 0.75) 0 1.5em 0 0, rgba(0, 0, 0, 0.75) -1.1em 1.1em 0 0, rgba(0, 0, 0, 0.5) -1.5em 0 0 0, rgba(0, 0, 0, 0.5) -1.1em -1.1em 0 0, rgba(0, 0, 0, 0.75) 0 -1.5em 0 0, rgba(0, 0, 0, 0.75) 1.1em -1.1em 0 0;
            box-shadow: rgba(0, 0, 0, 0.75) 1.5em 0 0 0, rgba(0, 0, 0, 0.75) 1.1em 1.1em 0 0, rgba(0, 0, 0, 0.75) 0 1.5em 0 0, rgba(0, 0, 0, 0.75) -1.1em 1.1em 0 0, rgba(0, 0, 0, 0.75) -1.5em 0 0 0, rgba(0, 0, 0, 0.75) -1.1em -1.1em 0 0, rgba(0, 0, 0, 0.75) 0 -1.5em 0 0, rgba(0, 0, 0, 0.75) 1.1em -1.1em 0 0;
        }

        /* Animation */

        @-webkit-keyframes spinner {
            0% {
                -webkit-transform: rotate(0deg);
                -moz-transform: rotate(0deg);
                -ms-transform: rotate(0deg);
                -o-transform: rotate(0deg);
                transform: rotate(0deg);
            }
            100% {
                -webkit-transform: rotate(360deg);
                -moz-transform: rotate(360deg);
                -ms-transform: rotate(360deg);
                -o-transform: rotate(360deg);
                transform: rotate(360deg);
            }
        }

        @-moz-keyframes spinner {
            0% {
                -webkit-transform: rotate(0deg);
                -moz-transform: rotate(0deg);
                -ms-transform: rotate(0deg);
                -o-transform: rotate(0deg);
                transform: rotate(0deg);
            }
            100% {
                -webkit-transform: rotate(360deg);
                -moz-transform: rotate(360deg);
                -ms-transform: rotate(360deg);
                -o-transform: rotate(360deg);
                transform: rotate(360deg);
            }
        }

        @-o-keyframes spinner {
            0% {
                -webkit-transform: rotate(0deg);
                -moz-transform: rotate(0deg);
                -ms-transform: rotate(0deg);
                -o-transform: rotate(0deg);
                transform: rotate(0deg);
            }
            100% {
                -webkit-transform: rotate(360deg);
                -moz-transform: rotate(360deg);
                -ms-transform: rotate(360deg);
                -o-transform: rotate(360deg);
                transform: rotate(360deg);
            }
        }

        @keyframes spinner {
            0% {
                -webkit-transform: rotate(0deg);
                -moz-transform: rotate(0deg);
                -ms-transform: rotate(0deg);
                -o-transform: rotate(0deg);
                transform: rotate(0deg);
            }
            100% {
                -webkit-transform: rotate(360deg);
                -moz-transform: rotate(360deg);
                -ms-transform: rotate(360deg);
                -o-transform: rotate(360deg);
                transform: rotate(360deg);
            }
        }

        .validationStyle {
            color: #ee4d4d;
            font-size: 13px;
            margin-top: 2px;
        }


        #topnav .navigation-menu > li > a {
            display: block;
            color: #435966;
            -webkit-transition: all .3s ease;
            transition: all .3s ease;
            line-height: 20px;
            padding-left: 5px !important;
            padding-right: 10px !important;
        }


    </style>



    @if(auth()->check())
        <script>
            var userId = '{{ auth()->id() }}';
            var url = '{{ url('/administrator/user/update/token') }}';
            var lang = '{{ config('app.locale') }}';
        </script>
    @endif


</head>


<body class="scroll-hidden" id="body-loader">


<div id="loading-spinner" style="    width: 100%;
    background: #27252563;
    height: 100%;
    position: fixed;
    top: 0;
    z-index: 99999999999; display: none;">
    <div style="position: absolute;
    left: 47%;
    background: #020202;
    padding: 30px 50px;
    z-index: 9999999;
    border-radius: 10px;
    color: #fff;
    opacity: 0.6;
    top: 40%;
    font-size: 32px;">
        <div>
            {{--<i class="fas fa-spinner fa-spin"></i>--}}
            <i class="fas fa-circle-notch fa-spin"></i>
            {{--<i class="fas fa-sync fa-spin"></i>--}}
            {{--<i class="fas fa-cog fa-spin"></i>--}}
            {{--<i class="fas fa-spinner fa-pulse"></i>--}}
            {{--<i class="fas fa-stroopwafel fa-spin"></i>--}}

        </div>
    </div>
</div>



{{--@yield('loader')--}}
@include('admin.layouts._partials.header')


<div class="loading" style="display: none;">Loading&#8230;</div>


@yield('content')


{{--<footer class="footer text-right">--}}
    {{--<div class="container">--}}
        {{--<div class="row">--}}

            {{--<div class="col-md-8 col-xs-12 text-left">&copy;--}}

                {{--@lang('institutioncp.copyrights') - @lang('institutioncp.saned_design_and_programming') <a--}}
                        {{--href="http::/saned.sa">@lang('institutioncp.saned')</a>--}}
                {{--<a href="http://saned.sa" target="_blank"><img width="55px"--}}
                                                               {{--src="{{ request()->root() }}/public/assets/admin/images/icon.png"/></a>--}}
            {{--</div>--}}
            {{--<!--<div class="col-md-4 col-xs-12">-->--}}
            {{--<!--    <ul class="list-inline m-b-0">-->--}}
            {{--<!--        <li>-->--}}
        {{--<!--            <a href="contact.html">@lang('institutioncp.contact_us')</a>-->--}}
            {{--<!--        </li>-->--}}
            {{--<!--    </ul>-->--}}
            {{--<!--</div>-->--}}


        {{--</div>--}}
    {{--</div>--}}
{{--</footer>--}}

{{--<!-- End Footer -->--}}


@include('admin.layouts._partials.scripts')
{{--<script src="https://www.gstatic.com/firebasejs/4.10.1/firebase.js"></script>--}}
{{--<script src="{{ request()->root() }}/public/assets/fcm/FCM-Setup.js"></script>--}}
<script>

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });





    $(document).ready(function () {
        setTimeout(function () {
            $('body').addClass('loaded');
            $('body').removeClass('scroll-hidden');
        }, 2000);
    });


    $('.submission-form').on('submit', function (e) {
        // for (instance in CKEDITOR.instances)
        //     CKEDITOR.instances[instance].updateElement();
        e.preventDefault();
        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();
        if (form.parsley().isValid()) {

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
</script>



{{--Datatables--}}

<script type="text/javascript">
    $(document).ready(function () {

        var table = $('#datatable-fixed-header').DataTable({
            fixedHeader: true,
            columnDefs: [{orderable: false, targets: [0]}],
            "language": {
                "lengthMenu": "@lang('maincp.show') _MENU_ @lang('maincp.perpage')",
                "info": "@lang('maincp.show') @lang('maincp.perpage') _PAGE_ @lang('maincp.from')_PAGES_",
                "infoEmpty": "@lang('maincp.no_recorded_data_available')",
                "infoFiltered": "(@lang('maincp.filter_from_max_total') _MAX_)",
                "paginate": {
                    "first": "@lang('maincp.first')",
                    "last": "@lang('maincp.last')",
                    "next": "@lang('maincp.next')",
                    "previous": "@lang('maincp.previous')"
                },
                "search": "@lang('maincp.search'):",
                "zeroRecords": "@lang('maincp.no_recorded_data_available')",

            },

        });
    });


</script>


<script type="text/javascript">


    {{--$(function () {--}}
    {{--$('body').on('click', 'datatable-fixed-header_paginate .pagination a', function (e) {--}}
    {{--e.preventDefault();--}}


    {{--alert('hassan');--}}

    {{--$('#load a').css('color', '#dfecf6');--}}
    {{--$('#load').append('<img style="position: absolute; right: 0; top: 0; z-index: 100000;" with: 20%; src="{{ request()->root() }}/public/assets/admin/custom/images/loader.gif" />');--}}

    {{--var url = $(this).attr('href');--}}
    {{--getArticles(url);--}}
    {{--window.history.pushState("", "", url);--}}
    {{--});--}}

    {{--function getArticles(url) {--}}
    {{--$.ajax({--}}
    {{--url: url--}}
    {{--}).done(function (data) {--}}
    {{--$('.articles').html(data);--}}
    {{--}).fail(function () {--}}
    {{--alert('Articles could not be loaded.');--}}
    {{--});--}}
    {{--}--}}
    {{--});--}}


    @if(session()->has('success'))
    setTimeout(function () {
        showMessage('{{ session()->get('success') }}');
    }, 1000);

    @endif

    function showMessage(message) {

        var shortCutFunction = 'success';
        var msg = message;
        var title = "@lang('institutioncp.success')";
        toastr.options = {
            positionClass: 'toast-top-center',
            onclick: null,
            // showMethod: 'slideDown',
            // hideMethod: "slideUp",
        };
        var $toast = toastr[shortCutFunction](msg, title);
        // Wire up an event handler to a button in the toast, if it exists
        $toastlast = $toast;


    }

    @if(session()->has('errorsValidation'))
    setTimeout(function () {
        showErrors('{{ session()->get('errorsValidation') }}');
    }, 1000);
    @endif



    function showErrors(message) {

        var shortCutFunction = 'error';
        var msg = message;
        var title = "@lang('institutioncp.error')";
        toastr.options = {
            positionClass: 'toast-top-center',
            onclick: null,
            showMethod: 'slideDown',
            hideMethod: "slideUp",
        };
        var $toast = toastr[shortCutFunction](msg, title);
        // Wire up an event handler to a button in the toast, if it exists
        $toastlast = $toast;


    }


    $('body').on('click', '.removeElement', function () {
        var id = $(this).attr('data-id');
        var $tr = $(this).closest($('#elementRow' + id).parent().parent());
        var url = $(this).attr('data-url');


        swal({
            title: "{{ __('maincp.make_sure') }}",
            text: "{{ __('maincp.confirm_delete_message') }}",
            type: "error",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "{{ __('maincp.accepted') }}",
            cancelButtonText: "{{ __('maincp.disable') }}",
            confirmButtonClass: 'btn-danger waves-effect waves-light',
            closeOnConfirm: true,
            closeOnCancel: true,
        }, function (isConfirm) {
            if (isConfirm) {
                $.ajax({
                    type: 'DELETE',
                    url: url,
                    data: {id: id},
                    dataType: 'json',
                    success: function (data) {
                        if (data.status == true) {
                            var shortCutFunction = 'success';
                            var msg = "@lang('institutioncp.deleted_successfully')";
                            var title = data.title;
                            toastr.options = {
                                positionClass: 'toast-top-left',
                                onclick: null
                            };
                            var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                            $toastlast = $toast;

                            $tr.find('td').fadeOut(1000, function () {
                                $tr.remove();
                            });
                        }
                        if (data.status == false) {
                            var shortCutFunction = 'error';
                            var msg = data.message;
                            var title = data.title;
                            toastr.options = {
                                positionClass: 'toast-top-left',
                                onclick: null
                            };
                            var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                            $toastlast = $toast;
                        }
                    }
                });
            } else {

                swal({
                    title: "@lang('institutioncp.cancel_done') ",
                    text: "انت لغيت عملية الحذف تقدر تحاول فى اى وقت :)",
                    type: "error",
                    showCancelButton: false,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "موافق",
                    confirmButtonClass: 'btn-info waves-effect waves-light',
                    closeOnConfirm: false,
                    closeOnCancel: false

                });

            }
        });
    });


    $(function () {
        $('body').on('change', '.filteriTems', function (e) {

                e.preventDefault();

                var keyName = $('#filterItems').val();
                var pageSize = $('#recordNumber').val();

                var url = $(this).attr('data-url');

                if (keyName != '' && pageSize != '') {
                    var path = '{{  request()->root().'/'.request()->path() }}' + '?name=' + keyName + '&pageSize=' + pageSize;
                } else if (keyName != '' && pageSize == '' && pageSize == 'all') {
                    var path = '{{  request()->root().'/'.request()->path() }}' + '?name=' + keyName;
                } else if (keyName == '' && pageSize != '') {
                    var path = '{{  request()->root().'/'.request()->path() }}' + '?pageSize=' + pageSize;
                } else {
                    var path = '{{  request()->root().'/'.request()->path() }}' + '?pageSize=' + pageSize;
                }

                $.ajax({
                    type: "POST",
                    url: url,
                    data: {keyName: keyName, path: path, pageSize: pageSize}
                }).done(function (data) {
                    window.history.pushState("", "", path);
                    $('.articles').html(data);
                }).fail(function () {
                    alert('Articles could not be loaded.');
                });


            }
        );
    });

    // $(document).ready(function () {
    //     $(".card,.card-box,.panal,.animate,.header").each(function () {
    //         $(this).addClass("show");
    //     });

    // });


    $('body').on('click', '.suspendElement', function () {


        var id = $(this).attr('data-id');
        var $tr = $(this).closest($('.unsuspend' + id).parent().parent());
        var type = $(this).attr('data-type');
        var url = $(this).attr('data-url');

        swal({
            title: "{{ __('maincp.make_sure') }}",
            text: $(this).attr('data-message'),
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "{{ __('maincp.accepted') }}",
            cancelButtonText: "{{ __('maincp.disable') }}",
            confirmButtonClass: 'btn-danger waves-effect waves-light',
            closeOnConfirm: true,
            closeOnCancel: true,
        }, function (isConfirm) {
            if (isConfirm) {
                $.ajax({
                    type: 'POST',
                    url: url,
                    data: {id: id, type: type},
                    dataType: 'json',
                    success: function (data) {

                        if (data.status == true) {


                            if (data.hiddenRow == true) {
                                $tr.find('td').fadeOut(1000, function () {
                                    $tr.remove();
                                });

                            }

                            if (data.type == 1) {
                                var shortCutFunction = 'success';
                                var msg = '{{ __('global.unblocked_success') }}';

                                $('.suspend' + data.id).delay(500).slideDown();
                                $('.unsuspend' + data.id).slideUp();

                                $('.StatusActive' + data.id).delay(500).slideDown();
                                $('.StatusNotActive' + data.id).slideUp();


                                if (data.hiddenRow == true) {
                                    $tr.find('td').fadeOut(1000, function () {
                                        $tr.remove();
                                    });
                                }


                            } else {
                                var shortCutFunction = 'success';
                                var msg = "{{ __('trans.operationDone') }}";

                                $('.unsuspend' + data.id).delay(500).slideDown();
                                $('.suspend' + data.id).slideUp();


                                $('.StatusNotActive' + data.id).delay(500).slideDown();
                                $('.StatusActive' + data.id).slideUp();


                            }


                            // var shortCutFunction = 'success';
                            // var msg = 'لقد تمت عملية الحذف بنجاح.';
                            var title = data.title;
                            toastr.options = {
                                positionClass: 'toast-top-center',
                                onclick: null,
                                showMethod: 'slideDown',
                                hideMethod: "slideUp",
                            };
                            var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                            $toastlast = $toast;
                        } else {


                            var shortCutFunction = 'error';
                            var msg = data.message;
                            var title = "@lang('institutioncp.error')";
                            toastr.options = {
                                positionClass: 'toast-top-center',
                                onclick: null,
                                showMethod: 'slideDown',
                                hideMethod: "slideUp",
                            };
                            var $toast = toastr[shortCutFunction](msg, title);
                            // Wire up an event handler to a button in the toast, if it exists
                            $toastlast = $toast;
                        }


                    }
                });
            }
        });
    });


    function redirectPage(route) {

        window.history.pushState("", "", route);
    }

    $('.dropify').dropify({
        messages: {
            'default': ' {{ __('institutioncp.insert_image') }} ',
            'replace': '{{ __('institutioncp.drag_and_drop_to_replace') }}',
            'remove': '{{ __('institutioncp.delete') }}',
            'error': ''
            {{--'error': '{{ __('institutioncp.something_went_wrong_try_again') }}'--}}
        },
        error: {
            'fileSize': '{{  __('trans.bigImageSize') }}',
            'fileExtension': ' {{ __('institutioncp.Incorrect_allowed_in_the_system') }} (png - gif - jpg - jpeg)',
        }
    });


    function checkSelect(item) {
        var checked = $(item).prop('checked');

        $('.checkboxes-items').each(function (i) {
            $(this).prop('checked', checked);
        })
    }




    $('input[type="checkbox"]').change(function(e) {

        var checked = $(this).prop("checked"),
            container = $(this).parent(),
            siblings = container.siblings();

        container.find('input[type="checkbox"]').prop({
            indeterminate: false,
            checked: checked
        });

        function checkSiblings(el) {

            var parent = el.parent().parent(),
                all = true;

            el.siblings().each(function() {
                let returnValue = all = ($(this).children('input[type="checkbox"]').prop("checked") === checked);
                return returnValue;
            });

            if (all && checked) {

                parent.children('input[type="checkbox"]').prop({
                    indeterminate: false,
                    checked: checked
                });

                checkSiblings(parent);

            } else if (all && !checked) {

                parent.children('input[type="checkbox"]').prop("checked", checked);
                parent.children('input[type="checkbox"]').prop("indeterminate", (parent.find('input[type="checkbox"]:checked').length > 0));
                checkSiblings(parent);

            } else {

                el.parents("li").children('input[type="checkbox"]').prop({
                    indeterminate: false,
                    checked: true
                });

            }

        }

        checkSiblings(container);
    });


</script>


{{--<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBjBZsq9Q11itd0Vjz_05CtBmnxoQIEGK8&language={{ config('app.locale') }}&libraries=places&callback=initAutocomplete"--}}
        {{--async defer></script>--}}


<script>


    $(document).ready(function () {
        $('form').parsley();
    });


</script>


</body>
</html>