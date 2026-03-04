@extends('admin.layouts.master')

@section('title', __('trans.categoryAdd'))


@section('styles')

    <link href="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-tagsinput/dist/bootstrap-tagsinput.css"
          rel="stylesheet"/>

    <style>

        .bootstrap-tagsinput {
            width: 100% !important;
        }
    </style>
@endsection

@section('content')

    <form data-parsley-validate novalidate method="POST" action="{{ route('categories.store') }}"
          enctype="multipart/form-data">
    {{ csrf_field() }}
    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="btn-group pull-right m-t-15">


                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> رجوع <span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>


                </div>
                <h4 class="page-title">@lang('trans.categories')</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">@lang("trans.categoryAdd")</h4>


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName"> اسم التصنيف - {{ $value }} </label>

                                <input type="text" name="name:{{ $locale }}" value="{{ old('name:'.$locale) }}"
                                       class="form-control" required
                                       placeholder="اسم التصنيف باللغة {{ $value }}"
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="اسم التصنيف باللغة{{ $value }} إلزامي"
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

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName"> سعر الاشتراك للشهر </label>

                            <input type="text" name="per_month" value="{{ old('per_month') }}"
                                   class="form-control optional-required" required
                                   placeholder="سعر الاشتراك للشهر"
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="سعر الاشتراك للشهر إجباري"
                                   data-parsley-maxlength="55"

                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"


                            />

                            @if($errors->has('per_month'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('per_month') }}
                                </p>
                            @endif


                        </div>
                    </div>

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName"> سعر الاشتراك للسنه </label>

                            <input type="text" name="per_year" value="{{ old('per_year')}}"
                                   class="form-control optional-required" required
                                   placeholder="سعر الاشتراك للسنه"
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="سعر الاشتراك للسنه إجباري"
                                   data-parsley-maxlength="55"

                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"

                            />

                            @if($errors->has('per_year'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('per_year') }}
                                </p>
                            @endif


                        </div>
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">
                                <input type="checkbox" name="isSub" id="isSubCat"/>
                                <span style="font-size: 14px;">تصنيف فرعي</span>

                            </label>

                        </div>
                    </div>

                    <div id="mainCategoryIcon">

                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="userName">صورة القسم*</label>
                                <input type="file" name="image" class="form-control"/>
                                <p class="help-block" id="error_userName"></p>
                                @if($errors->has('image'))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('image') }}
                                    </p>
                                @endif
                            </div>
                        </div>

                    </div>


                    <div id="subCategoriesOptions" style="display: none;">

                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="userName"> التصنيف الرئيسي </label>

                                <select class="form-control" name="parentId">
                                    <option value="0">رئيسي</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>

                                @if($errors->has('parentId'))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('parentId') }}
                                    </p>
                                @endif


                            </div>
                        </div>


                        <div class="col-xs-12">
                            <div class="form-group">

                                <label for="userName"> خيارات التصنيف الفرعي </label>

                                <ul style="list-style: none;">


                                    @foreach($options as $option)
                                        <li style="width: 25%; float: right;">
                                            <label>
                                                <input type="checkbox" name="options[]" value="{{ $option['id'] }}"/>
                                                <span> {{ $option['name'] }} </span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>

                                {{--                                <div class="m-b-0">--}}
                                {{--                                    <select multiple data-role="tagsinput" class="form-control" name="options[]">--}}
                                {{--                                        @if(count($category->options) > 0)--}}
                                {{--                                            @foreach($category->options as $option)--}}
                                {{--                                                <option value="{{ $option->name }}">{{ $option->name }}</option>--}}
                                {{--                                            @endforeach--}}
                                {{--                                        @endif--}}
                                {{--                                    </select>--}}

                                {{--                                </div>--}}

                            </div>
                        </div>
                        {{--                        <div class="col-xs-12">--}}
                        {{--                            <div class="form-group">--}}

                        {{--                                <label for="userName"> خيارات التصنيف الفرعي </label>--}}

                        {{--                                <div class="m-b-0">--}}
                        {{--                                    <select multiple data-role="tagsinput" class="form-control" name="options[]">--}}
                        {{--                                    </select>--}}

                        {{--                                </div>--}}

                        {{--                            </div>--}}
                        {{--                        </div>--}}
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group text-right m-b-0 ">
                            <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit"> حفظ البيانات
                            </button>
                            {{--<button onclick="window.history.back();return false;"--}}
                            {{--class="btn btn-default waves-effect waves-light m-l-5 m-t-20"> إلغاء--}}
                            {{--</button>--}}

                            <a href="{{ route('categories.index') }}"
                               class="btn btn-default waves-effect waves-light m-l-5 m-t-20"> @lang('trans.cancel')
                            </a>
                        </div>
                    </div>
                    <div class="clearfix"></div>

                </div>
            </div><!-- end col -->

        </div>
        <!-- end row -->
    </form>


@endsection



@section('scripts')

    <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>

    <script src="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-tagsinput/dist/bootstrap-tagsinput.min.js"></script>



    <script type="text/javascript">


        $("#isSubCat").on('change', function () {
            var answer = $(this).is(":checked");
            if (answer) {
                $('#subCategoriesOptions').show();

                $('.optional-required').attr('required', false);
                $('.optional-required').parent().parent().hide();

                $('#mainCategoryIcon').hide();

            } else {
                $('#subCategoriesOptions').hide();
                $('.optional-required').attr('required', true);
                $('.optional-required').parent().parent().show();
                $('#mainCategoryIcon').show();

            }
        });

        $('#categoryTypeSel').on('change', function () {

            if ($(this).val() == 1) {
                $(".hiddenWhenSub").slideUp();
            } else {
                $(".hiddenWhenSub").slideDown();
            }
        });

        // $('form').on('submit', function (e) {
        //
        //     e.preventDefault();
        //
        //     var formData = new FormData(this);
        //
        //     var form = $(this);
        //
        //     form.parsley().validate();
        //
        //     if (form.parsley().isValid()) {
        //         // $('.loading').show();
        //
        //         $('#body-loader').loading({
        //             message: 'تحميل...',
        //             theme: 'dark'
        //         });
        //
        //         $.ajax({
        //             type: 'POST',
        //             url: $(this).attr('action'),
        //             data: formData,
        //             cache: false,
        //             contentType: false,
        //             processData: false,
        //             success: function (data) {
        //                 $('#body-loader').loading('stop');
        //                 // $('form').trigger("reset");
        //
        //                 var shortCutFunction = 'success';
        //                 var msg = data.message;
        //                 var title = 'نجاح';
        //                 toastr.options = {
        //                     positionClass: 'toast-top-left',
        //                     onclick: null
        //                 };
        //                 var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
        //                 $toastlast = $toast;
        //                 setTimeout(function () {
        //                     window.location.href = data.url;
        //                 }, 2000);
        //             },
        //             error: function (data) {
        //             }
        //         });
        //     } else {
        //         $('.loading').hide();
        //     }
        // });

    </script>
@endsection





