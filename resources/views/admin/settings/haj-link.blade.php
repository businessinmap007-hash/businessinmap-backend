@extends('admin.layouts.master')
@section('title' , "صفحة رابط فتاوي الحج")



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




         .red-placeholder::-webkit-input-placeholder {
             color: #b71c1c;
             font-size: 12px;
         }

        .red-placeholder::-moz-input-placeholder {
            color: #b71c1c;
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
                <h4 class="page-title">صفحة رابط فتاوي الحج </h4>
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
                                <textarea id="editor{{ $i }}" rows="10" class="form-control msg_body" name="haj_link_description_{{ $locale }}">{{ $setting->getBody('haj_link_description_'.$locale) }}</textarea>
                            </div>
                        </div>
                        <?php $i++; ?>
                    @endforeach


                    <div class="col-xs-12">
                        <input type="hidden" name="phone_numbers" id="phone_numbers" value="1" />
                        <label> أرقام التواصل </label>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>رقم الجوال</th>
                                    <th width="10%"> <button type="button" class="btn btn-xs btn-primary " id="addNewPhone"><i class="fa fa-plus-circle"></i> إضافه جوال </button></th>
                                </tr>
                            </thead>

                            <tbody id="phoneNumbers">

                            @foreach(unserialize($setting->getBody('phones')) as $key => $phone )
                                    @if(!$phone)
                                        @continue
                                        @endif
                                <tr>
                                    <td>
                                        <input name="phones[]" class="form-control phoneNumber" value="{{ $phone }}" placeholder="رقم الجوال..."
                                        data-parsley-trigger="keyup",
                                        data-parsley-required-message="رقم الجوال إجباري"
                                        data-parsley-maxlength="25",
                                        data-parsley-maxlength-message ="اقصى عدد للارقام مسموح به هو 25",
                                        data-parsley-minlength="10",
                                        data-parsley-minlength-message="اقل عدد ارقام مسموح به هو 10",
                                        data-parsley-type="number",
                                        data-parsley-type-message="يجب ان يحتوى الحقل على ارقام فقط",
                                        data-parsley-pattern="/(05)[0-9]/",
                                        data-parsley-pattern-message = "يجب ادخال صيغة هاتف صحيحة مثال (0512345678)" />
                                    </td>

                                    <td align="center">
                                        <a class="btn btn-danger btn-xs remove"><i class="fa fa-close"></i> حذف </a>
                                    </td>
                                </tr>

                            @endforeach

                            <tr>
                                <td>
                                    <input name="phones[]" class="form-control phoneNumber" placeholder="رقم الجوال..." />
                                </td>

                                <td align="center">
                                    <a class="btn btn-danger btn-xs remove"><i class="fa fa-close"></i> حذف </a>
                                </td>
                            </tr>
                            </tbody>
                        </table>

                        {{--<div class="form-group">--}}
                            {{--<label for="userName">أرقام التواصل</label>--}}
                            {{--<input type="text" name="haj_link_phones"--}}
                                   {{--value="{{ $setting->getBody('haj_link_phones') }}" class="form-control"--}}
                                   {{--placeholder="أرقام التواصل..."/>--}}
                            {{--<span class="help-block"--}}
                                  {{--style="font-size: 12px;">لإدخال اكتر من رقم ضع بعد كل رقم (,)</span>--}}

                        {{--</div>--}}

                    </div>



                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">رابط الموقع الخاص بفتاوي الحج </label>
                            <input type="text" name="haj_link_url"
                                   value="{{ $setting->getBody('haj_link_url') }}" class="form-control" required
                                   placeholder="رابط الموقع الخاص بفتاوي الحج ..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="رابط الموقع الخاص بفتاوي الحج إجباري"/>
                            <p class="help-block"></p>

                        </div>

                    </div>


                    <input type="hidden" name="haj_link_image_old"
                           value="{{ $setting->getBody('haj_link_image') }}">
                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">صورة</label>
                            <input type="hidden" name="about_app_image_old"
                                   value="{{ $setting->getBody('haj_link_image') }}">
                            <input type="file" name="haj_link_image" class="dropify" data-max-file-size="6M"
                                   data-default-file="{{ request()->root() . '/' . $setting->getBody('haj_link_image') }}"
                                   data-show-remove="false"
                                   data-allowed-file-extensions="pdf png gif jpg jpeg"
                                   data-errors-position="outside"/>
                        </div>
                    </div>


                    <div class="form-group text-right m-t-20">
                        <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit" id="btnSubmit">
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



        $("#addNewPhone").on('click', function (e) {
            e.preventDefault();


            var num = ($("#phoneNumbers tr").length - 0) + 1;
            var isValidate = 1;


            $('.phoneNumber').each(function (i, e) {
                if ($(this).val() == '') {
                    {{--$('.colorImage').attr('title', '{{ trans('core.third_number') }}');--}}
                    {{--$('.colorImage').css('border', '1px solid red');--}}


                    $('.phoneNumber').attr('placeholder', 'رقم الجوال إجباري');
                    $('.phoneNumber').addClass('red-placeholder');


                    isValidate = 0;
                } else {
                    // $('.colorImage').css('border-color', '');
                }
            });


            if (isValidate == 1) {
                var tr = '<tr>'
                    + '<td>'
                    + '<input type="text" class="form-control phoneNumber" placeholder="من فضلك ادخل رقم الجوال" name="phones[]">'
                    + '</td>'
                    + '<td align="center"><a class=" btn btn-xs btn-danger remove"><i class="fa fa-close"></i> حذف </a></td>'
                    + '<tr>';


                $("#phoneNumbers").append(tr);
                $("#phone_numbers").val(num);
            }


        });


        $('#phoneNumbers').delegate('.remove', 'click', function () {
            $(this).parent().parent().slideDown(1500).remove();
        });


    </script>

@endsection




