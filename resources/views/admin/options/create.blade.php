@extends('admin.layouts.master')
@section('title' ,'إدارة الخيارات')

@section('styles')

    <link href="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css" rel="stylesheet">


@endsection
@section('content')
    <form id="storeCampaign" method="POST" action="{{ route('options.store') }}" enctype="multipart/form-data"
          data-parsley-validate
          novalidate class="submission-form">
    {{ csrf_field() }}

    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-8 col-sm-offset-2">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title">إدارة الخيارات</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-sm-offset-2">
                <div class="card-box">
                    <h2 class="header-title m-t-0 m-b-30">إضافة خيارات </h2>


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName"> الاسم - {{ $value }} </label>

                                <input type="text" name="name:{{ $locale }}" value="{{ old('name:'.$locale) }}"
                                       class="form-control" required
                                       placeholder="الاسم باللغة {{ $value }}"
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="الاسم باللغة{{ $value }} إلزامي"
                                       data-parsley-maxlength="55"
                                       {{--data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"--}}
                                       {{--data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"--}}
                                       data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                       data-parsley-minlength="3"
                                       data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                                />

                                @if($errors->has('name:'.$locale))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('name:'.$locale) }}
                                    </p>
                                @endif


                            </div>
                        </div>
                    @endforeach



                    <div class="clearfix"></div>


                    <div class="form-group text-right m-t-20">

                        <img id="indicatorImage" src="{{ request()->root() }}/public/assets/images/spinner.gif"
                             style="width: 50px; height: 50px; display: none; margin-top: 20px;">

                        <button class="btn btn-primary waves-effect waves-light m-t-20" id="btnRegister" type="submit">
                            حفظ البيانات
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

<script>


    $('.submission-form').on('submit', function (e) {

        e.preventDefault();
        var formData = new FormData(this);
        var form = $(this);
        form.parsley().validate();
        if (form.parsley().isValid()) {
            var btnText = $('#btn-submit').text();
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

                    if (data.statusCode == 422) {
                        alert("Errors Here!!!!");
                    }
                    if (data.status == 200) {

                        $("#btn-submit").html(btnText).attr('disabled', false);
                        $("#loading-spinner").fadeOut();

                        if (data.additional && data.additional['type'] != "update" || data.login) {
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
                        $("#btn-submit").html(btnText).attr('disabled', false);

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

                        $("#btn-submit").html(btnText).attr('disabled', false);
                        $("#loading-spinner").fadeOut();
                        $("#error-message-wrapper").css('display', 'block');
                        $("#error-message").html('- ' + data.errors);
                        showMessage(data.errors[0], 'error');
                    }

                },
                error: function (data) {


                    alert(data.responseJSON[0]);
                    showMessage(data.responseJSON[0], 'error');
                }


            });
        } else {
            $("#btn-submit").attr('disabled', false);

        }
    });

</script>


@endsection


