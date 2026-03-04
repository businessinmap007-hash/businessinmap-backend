@extends('admin.layouts.master')
@section('title' ,'إضافة وظيفة')

@section('styles')

    <link href="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-datepicker/dist/css/bootstrap-datepicker.min.css"
          rel="stylesheet">

    <style>
        .datepicker-dropdown {
            z-index: 999999;
        }

        .help-block {
            display: block;
            margin-top: 5px;
            margin-bottom: 10px;
            color: #1f0192;
            font-size: 14px;
        }
    </style>

@endsection
@section('content')
    <form id="storeCampaign" method="POST" action="{{ route('jobs.store') }}" enctype="multipart/form-data"
          data-parsley-validate
          novalidate class="submission-form">
    {{ csrf_field() }}

    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title">إدارة الوظائف</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="card-box">
                    <h2 class="header-title m-t-0 m-b-30">إضافة وظيفة </h2>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">عنوان الوظيفة</label>
                            <input type="text" name="title" value="{{ old('title') }}"
                                   class="form-control" required
                                   placeholder="عنوان الوظيفة"
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="اسم الوظيفة إلزامي"
                                   data-parsley-maxlength="55"
                                   data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                   data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('title'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('title') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">اسم المؤسسة</label>
                            <input type="text" name="company" value="{{ old('company') }}"
                                   class="form-control" required
                                   placeholder="اسم المؤسسة"
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="اسم المؤسسة إلزامي"
                                   data-parsley-maxlength="55"
                                   {{--data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"--}}
                                   {{--data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"--}}
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('company'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('company') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">تاريخ بداية الوظيفة*</label>
                            <div class="input-group">
                                <input type="text" name="start_at"
                                       value="{{ old('start_at') }}" class="form-control datepicker-autoclose" required
                                       placeholder="تاريخ بداية الوظيفة..."
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="تاريخ بداية الوظيفة إلزامي"
                                       autocomplete="off"

                                />
                                <span class="input-group-addon bg-primary b-0 text-white">
                                    <i class="ti-calendar"></i>
                                </span>
                            </div>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('start_at'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('start_at') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">تاريخ إنتهاء الوظيفة*</label>
                            <div class="input-group">
                                <input type="text" name="closed_at"

                                       value="{{ old('closed_at') }}" class="form-control datepicker-autoclose" required
                                       placeholder="تاريخ إنتهاء الوظيفة..."
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="تاريخ إنتهاء الوظيفة إلزامي"
                                       autocomplete="off"

                                />

                                <span class="input-group-addon bg-primary b-0 text-white"><i
                                            class="ti-calendar"></i></span>
                            </div>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('closed_at'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('closed_at') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">سعر التقديم*</label>
                            <input type="text" name="price"
                                   value="{{ old('price') }}" class="form-control" required
                                   placeholder="سعر التقديم..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-type="number"
                                   data-parsley-type-message="لا يقبل حروف ارقام فقط"
                                   data-parsley-required-message="سعر التقديم إلزامي"

                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('price'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('price') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">القسم*</label>
                            <select name="category_id" class="form-control" required
                                    data-parsley-required-message="إختيار القسم إلزامي">
                                <option selected disabled="">إختيار القسم</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>

                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('category_id'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('category_id') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">الأوراق المطلوبه *</label>
                            <textarea name="papers"
                                      class="form-control" required
                                      placeholder="الأوراق المطلوبه..."
                                      data-parsley-trigger="keyup"
                                      data-parsley-required-message="حقل الأوراق المطلوبه إلزامي">{{ old('papers') }}</textarea>
                            <p class="help-block" id="error_userName">ضع كل من الاوراق المطلوبه في سطر منفصل</p>
                            @if($errors->has('papers'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('papers') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName"> الوصف*</label>
                            <textarea name="description" rows="6"
                                      class="form-control" required
                                      placeholder="وصف الوظيفة..."
                                      data-parsley-trigger="keyup"
                                      data-parsley-required-message="حقل وصف الوظيفة إلزامي">{{ old('description') }}</textarea>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('description'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('description') }}
                                </p>
                            @endif
                        </div>
                    </div>


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

    <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>

    <script src="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js"></script>


    <script>

        jQuery('.datepicker-autoclose').datepicker({
            autoclose: true,
            todayHighlight: true,
            format: "yyyy-mm-dd",
        });

    </script>




@endsection


