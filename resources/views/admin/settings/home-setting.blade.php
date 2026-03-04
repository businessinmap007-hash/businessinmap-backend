@extends('admin.layouts.master')
@section('title' , 'إعدادات عامة')
@section('content')
    <form action="{{ route('administrator.settings.store') }}" data-parsley-validate="" novalidate="" method="post"
          enctype="multipart/form-data">

    {{ csrf_field() }}

    <!-- Page-Title -->

        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title">إعدادات عامة للموقع </h4>
            </div>
        </div>

        <div class="row">


            <div class="col-lg-10 col-lg-offset-1">


                <div class="card-box card-tabs">
                    <ul class="nav nav-pills pull-right">
                        <li class="active">
                            <a href="#cardpills-1" data-toggle="tab" aria-expanded="true">السكشن الاول</a>
                        </li>
                        <li class="">
                            <a href="#cardpills-2" data-toggle="tab" aria-expanded="false">السكشن الثاني</a>
                        </li>
                        <li class="">
                            <a href="#cardpills-3" data-toggle="tab" aria-expanded="false">السكشن الثالث</a>
                        </li>
                    </ul>
                    <h4 class="header-title m-b-30"> إعدادات الصفحة الرئيسية (محتوي السليدر) </h4>

                    <div class="tab-content">
                        <div id="cardpills-1" class="tab-pane fade in active">
                            <div class="row">
                                <div class="col-md-12">
                                    @foreach (config('translatable.locales') as $locale => $value)
                                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                                            <div class="form-group">
                                                <label for="userName"> اليد العجيبة- {{ $value }} </label>
                                                <input type="text" name="wonder_hand_title_{{ $locale }}"
                                                       class="form-control" required placeholder="عنوان السكشن الاول..."
                                                       value="{{ $setting->getBody('wonder_hand_title_'.$locale) }}"/>

                                            </div>
                                        </div>
                                    @endforeach

                                    @foreach (config('translatable.locales') as $locale => $value)
                                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                                            <div class="form-group">
                                                <label for="userName">وصف اليد العجيبة - {{ $value }} </label>
                                                <textarea type="text" name="wonder_hand_description_{{ $locale }}"
                                                          class="form-control" required
                                                          placeholder="وصف السكشن الاول...">{{ $setting->getBody('wonder_hand_description_'.$locale) }}</textarea>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div class="col-xs-12">
                                        <div class="form-group">
                                            <label for="userName">الرابط </label>
                                            <input type="text" name="wonder_hand_url"
                                                   value="{{ $setting->getBody('wonder_hand_url') }}"
                                                   class="form-control" required placeholder="الرابط..."/>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="cardpills-2" class="tab-pane fade">
                            <div class="row">
                                <div class="col-md-12">
                                    @foreach (config('translatable.locales') as $locale => $value)
                                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                                            <div class="form-group">
                                                <label for="userName"> الصانع العجيب - {{ $value }} </label>
                                                <input type="text" name="wonder_man_title_{{ $locale }}"
                                                       class="form-control" required
                                                       placeholder="عنوان السكشن الثاني..."
                                                       value="{{ $setting->getBody('wonder_man_title_'.$locale) }}"/>

                                            </div>
                                        </div>
                                    @endforeach

                                    @foreach (config('translatable.locales') as $locale => $value)
                                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                                            <div class="form-group">
                                                <label for="userName">وصف الصانع العجيب - {{ $value }} </label>
                                                <textarea name="wonder_man_description_{{ $locale }}"
                                                          class="form-control"
                                                          required
                                                          placeholder="وصف السكشن الثاني...">{{ $setting->getBody('wonder_man_description_'.$locale) }}</textarea>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div class="col-xs-12">
                                        <div class="form-group">
                                            <label for="userName">الرابط </label>
                                            <input type="text" name="wonder_man_url"
                                                   value="{{ $setting->getBody('wonder_man_url') }}"
                                                   class="form-control" required placeholder="الرابط..."/>

                                        </div>
                                    </div>


                                </div>
                            </div>
                        </div>
                        <div id="cardpills-3" class="tab-pane fade">
                            <div class="row">
                                <div class="col-md-12">
                                    @foreach (config('translatable.locales') as $locale => $value)
                                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                                            <div class="form-group">
                                                <label for="userName"> عاشق الفنون و الروائع - {{ $value }} </label>
                                                <input type="text" name="wonder_sec3_title_{{ $locale }}"
                                                       class="form-control" required
                                                       placeholder="عنوان السكشن الثالث..."
                                                       value="{{ $setting->getBody('wonder_sec3_title_'.$locale) }}"/>

                                            </div>
                                        </div>
                                    @endforeach

                                    @foreach (config('translatable.locales') as $locale => $value)
                                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                                            <div class="form-group">
                                                <label for="userName">وصف الصانع العجيب - {{ $value }} </label>
                                                <textarea type="text" name="wonder_sec3_description_{{ $locale }}"
                                                          class="form-control"
                                                          required
                                                          placeholder="وصف السكشن الثالث...">{{ $setting->getBody('wonder_sec3_description_'.$locale) }}</textarea>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div class="col-xs-12">
                                        <div class="form-group">
                                            <label for="userName">الرابط </label>
                                            <input type="text" name="wonder_man_url"
                                                   value="{{ $setting->getBody('wonder_man_url') }}"
                                                   class="form-control" required placeholder="الرابط..."/>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-box">


                    <h3>الاعمال الفخرية</h3>
                    <br/>




                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                            <div class="form-group">
                                <label for="userName"> عنوان السكشن (الاعمال الفخرية) - {{ $value }} </label>
                                <input type="text" name="section_cat_title_{{ $locale }}" class="form-control" required
                                       placeholder="عنوان السكشن (الاعمال الفخرية) ..."
                                       value="{{ $setting->getBody('section_cat_title_'.$locale) }}"/>

                            </div>
                        </div>
                    @endforeach

                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                            <div class="form-group">
                                <label for="userName"> وصف السكشن (الاعمال الفخرية)  - {{ $value }} </label>
                                <textarea name="section_cat_description_{{ $locale }}" class="form-control"
                                          required
                                          placeholder="وصف السكشن (الاعمال الفخرية)...">{{ $setting->getBody('section_cat_description_'.$locale) }}</textarea>

                            </div>
                        </div>
                    @endforeach





                    <h3>إعدادات ذيل الصفحة</h3>

                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-{{ 12 / count(config('translatable.locales')) }}">
                            <div class="form-group">
                                <label for="userName"> وصف مختصر - {{ $value }} </label>
                                <textarea type="text" name="footer_description_{{ $locale }}" class="form-control"
                                          required
                                          placeholder="وصف مختصر...">{{ $setting->getBody('footer_description_'.$locale) }}</textarea>

                            </div>
                        </div>
                    @endforeach

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

    </script>

    <script>
        CKEDITOR.replace('editor1');
        CKEDITOR.replace('editor2');

    </script>

@endsection



