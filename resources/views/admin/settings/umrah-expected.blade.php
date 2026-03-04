@extends('admin.layouts.master')
@section('title' ,__('trans.about_website'))

@section('styles')

    <style>



        .no-js #loader {
            display: none;
        }
        .js #loader {
            display: block;
            position: absolute;
            left: 100px;
            top: 0;
        }
        .se-pre-con {
            position: fixed;
            left: 0px;
            top: 0px;
            width: 100%;
            height: 100%;
            z-index: 9999;
            background: url("{{ request()->root() }}/public/assets/admin/images/preloader.gif") center no-repeat #fff;
        }
    </style>


@endsection
@section('content')

    <div class="se-pre-con"></div>


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
                <h4 class="page-title">مؤشر العمرة ( الأسبوعي ) </h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="card-box">

                    <?php $i = 1; ?>
                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="about_app_desc">محتوي الموقع باللغة - {{ $value }} </label>
                                <textarea id="editor{{ $i }}" rows="10" class="form-control msg_body" name="about_app_desc_{{ $locale }}">{{ $setting->getBody('about_app_desc_'.$locale) }}</textarea>
                            </div>
                        </div>
                        <?php $i++; ?>
                    @endforeach

                    <div class="form-group text-right m-t-20">
                        <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit" id="btnSubmit">
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


        CKEDITOR.replace('editor1');
        CKEDITOR.replace('editor2');



    </script>



    <script type="text/javascript">

        $('form').on('submit', function (e) {
            e.preventDefault();

            $("#btnSubmit").html("جاري حفظ البيانات...");

            for (instance in CKEDITOR.instances)
                CKEDITOR.instances[instance].updateElement();


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


                        if (data.status == true) {
                            $("#btnSubmit").html("حفظ البيانات");
                            var shortCutFunction = 'success';

                            var msg = data.message;
                            var title = 'نجاح';
                            toastr.options = {
                                maxOpened: 1,
                                preventDuplicates: 1,
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
                $("#btnSubmit").html("حفظ البيانات");
            }

        });
        $(window).load(function () {
            // Animate loader off screen
            $(".se-pre-con").fadeOut();
        });

    </script>

@endsection




