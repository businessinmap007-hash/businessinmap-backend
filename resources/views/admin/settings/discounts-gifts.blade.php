@extends('admin.layouts.master')
@section('title' , 'الخصومات والهدايا')
@section('content')
    <form action="{{ route('administrator.settings.store') }}" data-parsley-validate="" novalidate="" method="post"
          enctype="multipart/form-data">

    {{ csrf_field() }}

    <!-- Page-Title -->

        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>

                </div>
                <h4 class="page-title">إعدادات الخصومات والهدايا </h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="card-box">

                    {{--<h4 class="header-title m-t-0 m-b-30">@lang('maincp.data_about_the_application')  </h4>--}}

                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">الاشهر العمولة</label>
                            <input type="text" name="commission_months"
                                   value="{{ $setting->getBody('commission_months') }}" class="form-control"
                                   placeholder="الاشهر العمولة">
                        </div>
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">الاشهر المجانية</label>
                            <input type="text" name="free_months"
                                   value="{{ $setting->getBody('free_months') }}" class="form-control"
                                   placeholder="الاشهر المجانية">
                        </div>
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">الحد الادني</label>
                            <input type="text" name="limit_months"
                                   value="{{ $setting->getBody('limit_months') }}" class="form-control"
                                   placeholder="الحد الادني">
                        </div>
                    </div>

                    <div class="clearfix"></div>

                    <div class="form-group text-right m-t-20">
                        <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit">
                            @lang('maincp.save_data')
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
        $('form').on('submit', function (e) {
            $('.loading').show();
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
                        $('.loading').hide();
                        // $('form').trigger("reset");

                        var shortCutFunction = 'success';
                        var msg = data.message;
                        var title = 'نجاح';
                        toastr.options = {
                            positionClass: 'toast-top-left',
                            onclick: null
                        };
                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                        $toastlast = $toast;

                    },
                    error: function (data) {
                    }
                });
            } else {
                $('.loading').hide();
            }
        });
    </script>

    {{--<script>--}}
    {{--CKEDITOR.replace('editor1');--}}
    {{--CKEDITOR.replace('editor2');--}}

    {{--</script>--}}

@endsection



