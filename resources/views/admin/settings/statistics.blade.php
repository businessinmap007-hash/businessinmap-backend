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
            <div class="col-lg-10 col-lg-offset-1">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>

                </div>
                <h4 class="page-title">الإحصائيات واعلانات وزارة الحج </h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 col-lg-offset-1">
                <div class="card-box">

                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">عنوان الاحصائيات - {{ $value }} </label>
                                <input type="text" name="statistics_title_{{$locale}}"
                                       value="{{ $setting->getBody('statistics_title_'.$locale) }}" class="form-control"
                                       placeholder="عنوان الاحصائيات - {{ $value }}">
                                <p class="help-block"></p>
                            </div>
                        </div>
                    @endforeach

                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-3">

                            <div class="form-group">
                                <label for="userName"> الاحصائية رقم#1 - العنوان {{ $value }} </label>
                                <input type="text" name="permit_no_title_{{$locale}}"
                                       value="{{ $setting->getBody('permit_no_title_'.$locale) }}" class="form-control"
                                       placeholder="الاحصائية رقم#1 - {{ $value }}">
                                <p class="help-block"></p>
                            </div>

                        </div>
                    @endforeach

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">عدد الاحصائية رقم#1 </label>
                            <input type="text" name="permit_no"
                                   value="{{ $setting->getBody('permit_no') }}" class="form-control"
                                   placeholder="عدد الاحصائية رقم#1 - العنوان">
                            <p class="help-block"></p>
                        </div>
                    </div>


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-3">
                            <div class="form-group">
                                <label for="userName"> الاحصائية رقم#2 - العنوان {{ $value }} </label>
                                <input type="text" name="entry_no_title_{{$locale}}"
                                       value="{{ $setting->getBody('entry_no_title_'.$locale) }}" class="form-control"
                                       placeholder="الاحصائية رقم#2 - {{ $value }}">
                                <p class="help-block"></p>
                            </div>
                        </div>
                    @endforeach

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">عدد الاحصائية رقم#2 </label>
                            <input type="text" name="entry_no"
                                   value="{{ $setting->getBody('entry_no') }}" class="form-control"
                                   placeholder="الاحصائية رقم#2 - العنوان">
                            <p class="help-block"></p>
                        </div>
                    </div>


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-3">
                            <div class="form-group">
                                <label for="userName"> الاحصائية رقم#3 - العنوان {{ $value }} </label>
                                <input type="text" name="exsit_no_title_{{$locale}}"
                                       value="{{ $setting->getBody('exsit_no_title_'.$locale) }}" class="form-control"
                                       placeholder="الاحصائية رقم#3 - {{ $value }}">
                                <p class="help-block"></p>
                            </div>
                        </div>
                    @endforeach

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">عدد الاحصائية رقم#3 </label>
                            <input type="text" name="exsit_no"
                                   value="{{ $setting->getBody('exsit_no') }}" class="form-control"
                                   placeholder="الاحصائية رقم#3 - العنوان">
                            <p class="help-block"></p>
                        </div>
                    </div>


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-3">
                            <div class="form-group">
                                <label for="userName"> الاحصائية رقم#4 - العنوان {{ $value }} </label>
                                <input type="text" name="haj_no_title_{{$locale}}"
                                       value="{{ $setting->getBody('haj_no_title_'.$locale) }}" class="form-control"
                                       placeholder="الاحصائية رقم#4 - {{ $value }}">
                                <p class="help-block"></p>
                            </div>
                        </div>
                    @endforeach

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">عدد الاحصائية رقم#4 </label>
                            <input type="text" name="haj_no"
                                   value="{{ $setting->getBody('haj_no') }}" class="form-control"
                                   placeholder="الاحصائية رقم#4 - العنوان">
                            <p class="help-block"></p>
                        </div>
                    </div>

                    <div class="col-xs-12">
                        <input type="hidden" name="phone_numbers" id="phone_numbers" value="1"/>
                        <label> إعلانات وزراة الحج </label>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>الإعلان</th>
                                <th width="10%">
                                    <button type="button" class="btn btn-xs btn-primary " id="addNewHajAd">
                                        <i class="fa fa-plus-circle"></i> إضافه إعلان جديد
                                    </button>
                                </th>
                            </tr>
                            </thead>

                            <tbody id="phoneNumbers">

                            @if($setting->getBody('haj_ads') && $setting->getBody('haj_ads') != "")


                                @foreach(unserialize($setting->getBody('haj_ads')) as $key => $ad )
                                    @if(!$ad)
                                        @continue
                                    @endif
                                    <tr>
                                        <td>
                                            <input name="haj_ads[]" class="form-control hajAd" value="{{ $ad }}"
                                                   placeholder="إعلان وزارة الحج..."
                                                   data-parsley-trigger="keyup"
                                                   data-parsley-required-message="رقم الجوال إجباري"/>
                                        </td>

                                        <td align="center">
                                            <a class="btn btn-danger btn-xs remove"><i class="fa fa-close"></i> حذف </a>
                                        </td>
                                    </tr>

                                @endforeach


                            @endif


                            <tr>
                                <td>
                                    <input name="haj_ads[]" class="form-control hajAd"
                                           placeholder="إعلان وزارة الحج..."/>
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
                        <input type="hidden" name="haj_ads_en" id="haj_ads_en" value="1"/>
                        <label> إعلانات وزراة الحج باللغة الانجليزية</label>
                        <table class="table table-bordered">
                            <thead>
                            <tr>
                                <th>الإعلان</th>
                                <th width="10%">
                                    <button type="button" class="btn btn-xs btn-primary " id="addNewHajAdEn">
                                        <i class="fa fa-plus-circle"></i> إضافه إعلان جديد
                                    </button>
                                </th>
                            </tr>
                            </thead>

                            <tbody id="hajAdsEn">

                            @if($setting->getBody('haj_ads_en') && $setting->getBody('haj_ads_en') != "")


                                    @foreach(unserialize($setting->getBody('haj_ads_en')) as $key => $ad )
                                        @if(!$ad)
                                            @continue
                                        @endif
                                        <tr>
                                            <td>
                                                <input name="haj_ads_en[]" class="form-control hajAdEN"
                                                       value="{{ $ad }}"
                                                       placeholder="إعلان وزارة الحج..."
                                                       data-parsley-trigger="keyup"/>
                                            </td>

                                            <td align="center">
                                                <a class="btn btn-danger btn-xs remove"><i class="fa fa-close"></i> حذف
                                                </a>
                                            </td>
                                        </tr>

                                    @endforeach

                            @endif
                            <tr>
                                <td>
                                    <input name="haj_ads_en[]" class="form-control hajAdEn"
                                           placeholder="إعلان وزارة الحج..."/>
                                </td>

                                <td align="center">
                                    <a class="btn btn-danger btn-xs "><i class="fa fa-close"></i> حذف </a>
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


        $("#addNewHajAd").on('click', function (e) {
            e.preventDefault();


            var num = ($("#haj_ads tr").length - 0) + 1;
            var isValidate = 1;


            $('.hajAd').each(function (i, e) {
                if ($(this).val() == '') {
                    {{--$('.colorImage').attr('title', '{{ trans('core.third_number') }}');--}}
                    {{--$('.colorImage').css('border', '1px solid red');--}}


                    $('.hajAd').attr('placeholder', 'الإعلان إجباري');
                    $('.hajAd').addClass('red-placeholder');


                    isValidate = 0;
                } else {
                    // $('.colorImage').css('border-color', '');
                }
            });


            if (isValidate == 1) {
                var tr = '<tr>'
                    + '<td>'
                    + '<input type="text" class="form-control hajAd" placeholder="من فضلك ادخل رقم الجوال" name="haj_ads[]">'
                    + '</td>'
                    + '<td align="center"><a class=" btn btn-xs btn-danger remove"><i class="fa fa-close"></i> حذف </a></td>'
                    + '<tr>';


                $("#phoneNumbers").append(tr);
                $("#phone_numbers").val(num);
            }


        });


        $('#hajAds').delegate('.remove', 'click', function () {
            $(this).parent().parent().slideDown(1500).remove();
        });


        $("#addNewHajAdEn").on('click', function (e) {
            e.preventDefault();


            var num = ($("#haj_ads_en tr").length - 0) + 1;
            var isValidate = 1;


            $('.hajAdEn').each(function (i, e) {
                if ($(this).val() == '') {
                    {{--$('.colorImage').attr('title', '{{ trans('core.third_number') }}');--}}
                    {{--$('.colorImage').css('border', '1px solid red');--}}


                    $('.hajAdEn').attr('placeholder', 'الإعلان إجباري');
                    $('.hajAdEn').addClass('red-placeholder');


                    isValidate = 0;
                } else {
                    // $('.colorImage').css('border-color', '');
                }
            });


            if (isValidate == 1) {
                var tr = '<tr>'
                    + '<td>'
                    + '<input type="text" class="form-control hajAdEn" placeholder="من فضلك ادخل رقم الجوال" name="haj_ads[]">'
                    + '</td>'
                    + '<td align="center"><a class=" btn btn-xs btn-danger remove"><i class="fa fa-close"></i> حذف </a></td>'
                    + '<tr>';


                $("#hajAdsEn").append(tr);
                $("#haj_ads_en").val(num);
            }


        });

        $('#hajAdsEn').delegate('.remove', 'click', function () {
            $(this).parent().parent().slideDown(1500).remove();
        });


    </script>

@endsection




