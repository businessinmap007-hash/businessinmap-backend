@extends('admin.layouts.master')
@section('title', __('trans.categoriesManagement'))
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


    <form data-parsley-validate novalidate method="POST"
          action="{{ route('categories.update', $category->id) }}"
          enctype="multipart/form-data">
        {{ csrf_field() }}
        {{ method_field('PUT') }}

        <div id="messageError"></div>

        <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="btn-group pull-right m-t-15">

                    <a href="{{ route('categories.create') }}" class="btn btn-custom  waves-effect waves-light">
                    <span class="m-l-5">
                        <i class="fa fa-plus"></i> <span>إضافة</span> </span>
                    </a>

                </div>
                <h4 class="page-title">@lang('trans.categoriesManagement')</h4>
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

                                <input type="text" name="name_{{ $locale }}" value="{{ $category->{'name:'.$locale} }}"
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

                                @if($errors->has('name'))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('name') }}
                                    </p>
                                @endif


                            </div>
                        </div>
                    @endforeach

                    @if($category->parent_id == 0)

                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName"> سعر الاشتراك للشهر </label>

                                <input type="text" name="per_month" value="{{ $category->per_month}}"
                                       class="form-control" required
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

                                <input type="text" name="per_year" value="{{ $category->per_year}}"
                                       class="form-control" required
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
                    @endif

                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">
                                <input type="checkbox" name="isSub" disabled
                                       {{ $category->parent_id == 0 ? "" : "checked" }} id="isSubCat"/>
                                <span style="font-size: 14px;">تصنيف فرعي</span>

                            </label>

                        </div>
                    </div>

                    @if($category->parent_id == 0)
                        <div id="mainCategoryIcon">
                            <div class="col-xs-6">
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
                    @endif


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">الترتيب</label>
                            <input type="text" name="reorder" class="form-control" placeholder="ترتيب القسم..." value="{{ $category->reorder }}"/>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('reorder'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('reorder') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div id="subCategoriesOptions" style="display: {{ $category->parent_id == 0 ? "none" : "block" }};">

                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="userName"> التصنيف الرئيسي </label>

                                <select class="form-control" name="parentId">
                                    <option value="0">رئيسي</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ $cat->id == $category->parent_id ? "selected" : "" }}>{{ $cat->name }}</option>
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
                                                <input type="checkbox" name="options[]"
                                                       value="{{ $option['id'] }}" {{ collect($category->options->pluck('id'))->contains($option->id) ? "checked" : "" }}/>
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
                    </div>


                    @if($category->parent_id == 0)
                        <hr/>
                        <br/>
                        <br/>
                        <h2 style="font-size: 18px;">الاقسام الفرعية</h2>
                        <table class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th>الاسم باللغة العربية</th>
                                <th>الاسم باللغة الانجليزية</th>
                                <th>الخيارات</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($category->children as $child)
                                <tr>
                                    <td>{{ $child->{'name:ar'} }}</td>
                                    <td>{{ $child->{'name:en'} }}</td>
                                    <td>
                                        <a href="{{ route('categories.edit', $child->id) }}"
                                           class="btn btn-icon btn-xs waves-effect btn-default">
                                            <i class="fa fa-edit"></i>
                                        </a>

                                        <a href="javascript:;" id="elementRow{{ $child->id }}"
                                           data-id="{{ $child->id }}"
                                           data-url="{{ route('categories.destroy', $child->id) }}"
                                           class="removeElement btn btn-icon btn-trans btn-xs waves-effect waves-light btn-danger">
                                            <i class="fa fa-remove"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif

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
                $('#mainCategoryIcon').hide();
            } else {
                $('#subCategoriesOptions').hide();
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









