@extends('admin.layouts.master')
@section('title', "إعدادات القوائم")


@section('styles')



@endsection
@section('content')


    <form method="POST" action="{{ route('menus.store') }}" enctype="multipart/form-data"
          data-parsley-validate novalidate>
    {{ csrf_field() }}



    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="btn-group pull-right m-t-15">
                    {{--  <a href="{{ route('users.create') }}" type="button" class="btn btn-custom waves-effect waves-light"
                        aria-expanded="false"> @lang('maincp.add')
                         <span class="m-l-5">
                         <i class="fa fa-plus"></i>
                     </span>
                     </a> --}}
                </div>
                <h4 class="page-title">إضافة رابط</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="card-box">


                    <h4 class="header-title m-t-0 m-b-30">إعدادات القوائم</h4>

                    <div class="row">


                        @foreach (config('translatable.locales') as $locale => $value)
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label for="userName">عنوان القائمة باللغة {{ $value }}</label>
                                    <input type="text" name="name_{{ $locale }}" data-parsley-trigger="keyup" required
                                           data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                           data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                           placeholder="عنوان القائمة باللغة {{ $value }}..."
                                           class="form-control requiredFieldWithMaxLenght"
                                           id="userName" value="{{ old('name_'.$locale) }}"
                                           data-parsley-required-message="هذا الحقل إلزامي">

                                    @if($errors->has('name_'.$locale))
                                        <p class="help-block validationStyle">
                                            {{ $errors->first('name_'.$locale) }}
                                        </p>
                                    @endif

                                </div>
                            </div>


                        @endforeach


                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">الرابط</label>
                                <input type="text" name="url" value="{{ old('url') }}"
                                       class="form-control requiredFieldWithMaxLenght"
                                       required
                                       placeholder="الرابط..."/>
                                <p class="help-block" id="error_userName"></p>
                                @if($errors->has('url'))
                                    <p class="help-block">
                                        {{ $errors->first('url') }}
                                    </p>
                                @endif
                            </div>
                        </div>


                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName">نوع القائمة</label>
                                <select class="form-control" name="type" required>
                                    <option value="0">روابط مهمة</option>
                                    <option value="1">مواقع ذات صلة</option>
                                </select>
                                <p class="help-block" id="error_userName"></p>
                                @if($errors->has('type'))
                                    <p class="help-block">
                                        {{ $errors->first('type') }}
                                    </p>
                                @endif
                            </div>
                        </div>


                        <div class="form-group ">
                            <div class="col-xs-12">
                                <div class="checkbox checkbox-custom">
                                    <input id="checkbox-signup" type="checkbox"
                                           name="new_window" value="1">
                                    <label for="checkbox-signup">
                                        يفتح في صفحة جديدة؟
                                    </label>
                                </div>

                            </div>
                        </div>


                    </div>

                    {{--<div class="col-xs-6">--}}
                    {{--<div class="form-group">--}}
                    {{--<label for="userName">34</label>--}}
                    {{--<select class="form-control" name="type_id" required data-parsley-required-message="من فضلك اختار مكان الإعلان">--}}
                    {{--<option value="">مكان الإعلان</option>--}}
                    {{--</select>--}}

                    {{--<p class="help-block" id="error_userName"></p>--}}
                    {{--@if($errors->has('position'))--}}
                    {{--<p class="help-block">--}}
                    {{--{{ $errors->first('position') }}--}}
                    {{--</p>--}}
                    {{--@endif--}}
                    {{--</div>--}}
                    {{--</div>--}}


                    <div class="clearfix"></div>
                    <div class="form-group text-right m-t-20">
                        <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit">
                            @lang('maincp.save_data')
                        </button>
                        <a href="{{ route('banks.index') }}" type="button"
                           class="btn btn-default waves-effect waves-light m-l-5 m-t-20"
                           aria-expanded="false">
                            @lang('maincp.disable')
                        </a>
                    </div>
                </div>
            </div>
        </div><!-- end col -->


        <!-- end row -->
    </form>

@endsection



@section('scripts')

    <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>
    <script type="text/javascript">

        $(".positionType").on('change', function (e) {
            e.preventDefault();

            var positionType = $(this).val();

            $("#indicatorImageAds").css('display', 'initial');

            $.ajax({
                type: 'post',
                url: '{{ route('get.selected.subs') }}',
                data: {positionType: positionType},
                dataType: 'json',
                success:
                    function (response) {
                        $("#indicatorImageAds").css('display', 'none');

                        if (positionType == 1) {
                            $("#selectSub").removeAttr('required', true);
                        } else {
                            $("#selectSub").attr('required', true);
                        }
                        if (response) {
                            $("#selectSub").empty();
                            $("#selectSub").prop('disabled', false);
                            $("#selectSub").append('<option value="" selected disabled>المكان المناسب </option>');
                            $.each(response, function (key, value) {
                                $("#selectSub").append('<option value="' + value.id + '">' + value.name + '</option>');
                            });
                        } else {
                            $("#selectSub").empty();
                        }
                    },
                error: function (data) {
                    // $("#btn_submit").attr('disabled', 'disabled');
                    // $("#lay").show();
                },
                beforeSubmit: function () {
                    //do validation here
                },
                beforeSend: function () {
//                     $('#btn_submit').html("حفظ البيانات...");
                    // $("#btn_submit").attr('disabled', 'disabled');
                    // $("#lay").show();
                },
            });


        });

        //        $('form').on('submit', function (e) {
        //
        //            e.preventDefault();
        //
        //            var formData = new FormData(this);
        //
        //            var form = $(this);
        //
        //            form.parsley().validate();
        //
        //            if (form.parsley().isValid()) {
        //            $('.loading').show();
        //                $.ajax({
        //                    type: 'POST',
        //                    url: $(this).attr('action'),
        //                    data: formData,
        //                    cache: false,
        //                    contentType: false,
        //                    processData: false,
        //                    success: function (data) {
        //                        $('.loading').hide();
        //                        // $('form').trigger("reset");
        //
        //                        var shortCutFunction = 'success';
        //                        var msg = data.message;
        //                        var title = 'نجاح';
        //                        toastr.options = {
        //                            positionClass: 'toast-top-left',
        //                            onclick: null
        //                        };
        //                        var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
        //                        $toastlast = $toast;
        //                        setTimeout(function () {
        //                            window.location.href = data.url;
        //                        }, 2000);
        //                    },
        //                    error: function (data) {
        //                    }
        //                });
        //            } else {
        //                $('.loading').hide();
        //            }
        //        });

    </script>
@endsection

