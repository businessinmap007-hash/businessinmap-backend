@extends('admin.layouts.master')
@section('title' ,'إضافة منتج')

@section('styles')



@endsection
@section('content')
    <form id="storeCampaign" method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data"
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
                <h4 class="page-title">إدارة المنتجات</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="card-box">
                    <h2 class="header-title m-t-0 m-b-30">إضافة منتج </h2>


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName"> اسم المنتج - {{ $value }} </label>
                                <input type="text" name="name:{{ $locale }}" value="{{ old('name:'.$locale) }}"
                                       class="form-control" required
                                       placeholder="اسم المنتج باللغة {{ $value }}"
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="اسم المنتج باللغة{{ $value }} إلزامي"
                                       data-parsley-maxlength="55"
                                       data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                       data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
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
                            <label for="userName">التصنيف الرئيسيي*</label>
                            <select name="mainCategory" class="form-control" required id="selectCategory">
                                <option value="0">اختار التصنيف</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>

                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('ssn_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('ssn_no') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">التصنيف الفرعي*</label>
                            <select name="subCategory" class="form-control" required id="selectSubCategory">
                                <option value="0">اختار التصنيف</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>

                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('ssn_no'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('ssn_no') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">سعر المنتج*</label>
                            <input type="text" name="price" value="{{ old('price') }}" class="form-control"
                                   required
                                   placeholder="سعر المنتج..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="سعر المنتج إلزامي"
                                   data-parsley-maxlength="55"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('price'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('price') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">سعر المنتج بعد التخفيض*</label>
                            <input type="text" name="price_sale" value="{{ old('price_sale') }}" class="form-control"
                                   placeholder="سعر المنتج بعد التخفيض..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-maxlength="55"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('price_sale'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('price_sale') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">الكمية*</label>
                            <input type="text" name="quantity" value="{{ old('quantity') }}" class="form-control"
                                   required
                                   placeholder="الكمية..."
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="رقم جواز السفر إلزامي"
                            />
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('quantity'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('quantity') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">بلد المنشأ*</label>
                            <select name="location_id" class="form-control" required>
                                <option value="0">اختار البلد</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>

                            @if($errors->has('country'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('country') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">منتج مميز*</label>
                            <select name="is_featured" class="form-control" required>
                                <option value="">منتج مميز؟</option>
                                <option value="0">غير مميز</option>
                                <option value="1"> مميز</option>
                            </select>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('is_featured'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('is_featured') }}
                                </p>
                            @endif
                        </div>
                    </div>


                        <div class="col-xs-12 m-b-30">
                            <label for="materials">مادة التصنيع*</label>
                            <input type="text" name="materials" value="{{ old('materials') }}" class="form-control"
                                   placeholder="مادة التصنيع..."
                                   data-parsley-trigger="keyup"
                            />
                        </div>

                    <div class="row">

                        <input type="hidden" id="color_numbers" name="color_numbers" value="1"/>
                        <div class="col-xs-6">

                            <div class="col-lg-12 mg-t-20">
                                <div class="form-group">

                                    <div class="row">

                                        <div class="col-md-6">
                                            <label for="userName">الالوان الخاصة بالمنتج </label>
                                        </div>
                                        <div class="col-md-6">
                                            <a class="btn btn-primary" style="float: left !important; color: #FFF"
                                               id="addNewColor"><i
                                                        class="fa fa-plus-circle"></i> إضافة لون </a>
                                        </div>


                                        <div class="col-md-12">


                                            <table class="table table-bordered table-hover table-striped">
                                                <thead>
                                                <tr>
                                                    <th>كود اللون</th>
                                                    <th width="10%">حذف</th>
                                                </tr>
                                                </thead>
                                                <tbody id="colorDetails">
                                                <tr>


                                                    <td>
                                                        <input type="text" class="form-control colorName"
                                                               placeholder="من فضلك ادخل كود اللون"
                                                               name="colors[]">
                                                    </td>
                                                    <td align="center">
                                                        <a class="btn btn-danger btn-xs remove tx-white"
                                                           style="color: #FFF;">
                                                            <div>
                                                                <i class="fa fa-close"></i>
                                                            </div>
                                                        </a>
                                                    </td>
                                                </tr>
                                                </tbody>

                                            </table>

                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>


                        <div class="col-xs-6">
                            <input type="hidden" id="size_numbers" name="size_numbers" value="1"/>
                            <div class="col-lg-12 mg-t-20">
                                <div class="form-group">

                                    <div class="row">

                                        <div class="col-md-6">
                                            <label for="userName">الاحجام الخاصة بالمنتج </label>
                                        </div>
                                        <div class="col-md-6">
                                            <a class="btn btn-primary" style="float: left !important; color: #FFF"
                                               id="addNewSize"><i
                                                        class="fa fa-plus-circle"></i> إضافة حجم </a>
                                        </div>


                                        <div class="col-md-12">


                                            <table class="table table-bordered table-hover table-striped">
                                                <thead>
                                                <tr>
                                                    <th>اسم الحجم</th>
                                                    <th width="10%">حذف</th>
                                                </tr>
                                                </thead>
                                                <tbody id="sizeDetails">
                                                <tr>
                                                    <td>
                                                        <input type="text"
                                                               class="form-control sizeName"
                                                               placeholder="من فضلك ادخل اسم الحجم"
                                                               name="sizes[]">
                                                    </td>
                                                    <td align="center">
                                                        <a class="btn btn-danger btn-xs remove tx-white"
                                                           style="color: #FFF;">
                                                            <div>
                                                                <i class="fa fa-close"></i>
                                                            </div>
                                                        </a>
                                                    </td>
                                                </tr>
                                                </tbody>

                                            </table>

                                        </div>

                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">صورة المنتج الرئيسية*</label>
                            <input type="file" name="image" class="form-control" required/>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('image'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('image') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-8">
                        <div class="form-group">
                            <label for="userName">صور آخري للمنتج*</label>
                            <input type="file" name="images[]" class="form-control" multiple required/>
                            @if($errors->has('images'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('images') }}
                                </p>
                            @endif
                        </div>
                    </div>


                    <?php $i = 1; ?>
                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="description:{{ $locale }}">وصف المنتج باللغة - {{ $value }} </label>
                                <textarea id="editor{{ $i }}" rows="10" class="form-control msg_body"
                                          name="description:{{ $locale }}"></textarea>
                            </div>
                        </div>
                        <?php $i++; ?>
                    @endforeach


                    <div class="clearfix"></div>


                    <div class="form-group text-right m-t-20">

                        <img id="indicatorImage" src="{{ request()->root() }}/public/assets/images/spinner.gif"
                             style="width: 50px; height: 50px; display: none; margin-top: 20px;">

                        <button class="btn btn-primary waves-effect waves-light m-t-20" id="btnRegister" type="submit">
                            إضافة المنتج
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


    </script>
    <script src="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-inputmask/bootstrap-inputmask.min.js"
            type="text/javascript"></script>


    <script>
        CKEDITOR.replace('editor1');
        CKEDITOR.replace('editor2');


        $("#selectTrip").on('change', function (e) {
            e.preventDefault();

            // $("#indicatorImageCountryConnection").css('display', 'initial');

            var tripID = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.selected.buses') }}',
                data: {tripID: tripID},
                dataType: 'json',
                success:
                    function (response) {
                        // $("#indicatorImageCountryConnection").css('display', 'none');


                        if (response) {
                            $("#selectBusTrip").empty();
                            $("#selectBusTrip").prop('disabled', false);
                            $("#selectBusTrip").append('<option value="" selected disabled> إختيار الاتوبيس </option>');
                            $.each(response, function (key, value) {
                                $("#selectBusTrip").append('<option value="' + value.id + '">' + value.bus_no + '</option>');
                            });
                            $("#selectBusTrip").select2();
                        } else {
                            $("#selectBusTrip").empty();
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


        $("#addNewColor").on('click', function (e) {
            e.preventDefault();


            var num = ($("#colorDetails tr").length - 0) + 1;
            var isValidate = 1;


            $('.colorName').each(function (i, e) {
                if ($(this).val() == '') {
                    $('.colorName').attr('placeholder', 'اكتب اسم اللون').addClass('red-placeholder');
                    isValidate = 0;
                } else {
                }
            });


            if (isValidate == 1) {
                var tr = '<tr>'
                    + '<td>'
                    + '<input type="text" class="form-control colorName" placeholder="من فضلك ادخل اسم اللون" name="colors[]">'
                    + '</td>'
                    + '<td><a class=" btn btn-xs btn-danger  tx-white remove"><i class="fa fa-close"></i></a></td>'
                    + '<tr>';


                $("#colorDetails").append(tr);
                $("#color_numbers").val(num);
            }


        });


        $('#colorDetails').delegate('.remove', 'click', function () {
            $(this).parent().parent().slideDown(1500).remove();
        });


        $("#addNewSize").on('click', function (e) {
            e.preventDefault();


            var num = ($("#sizeDetails tr").length - 0) + 1;
            var isValidate = 1;


            $('.sizeName').each(function (i, e) {
                if ($(this).val() == '') {
                    {{--$('.colorImage').attr('title', '{{ trans('core.third_number') }}');--}}
                    {{--$('.colorImage').css('border', '1px solid red');--}}


                    $('.sizeName').attr('placeholder', 'ادخل اسم الحجم').addClass('red-placeholder');


                    isValidate = 0;
                } else {
                    // $('.colorImage').css('border-color', '');
                }
            });


            if (isValidate == 1) {
                var tr = '<tr>'
                    + '<td>'
                    + '<input type="text" class="form-control sizeName" placeholder="من فضلك اسم الحجم" name="sizes[]">'
                    + '</td>'
                    + '<td align="center"><a class="btn btn-xs btn-danger  tx-white remove"> <div><i class="fa fa-close"></i></div></a></td>'
                    + '<tr>';

                $("#sizeDetails").append(tr);
                $("#size_numbers").val(num);
            }


        });


        $('#sizeDetails').delegate('.remove', 'click', function () {
            $(this).parent().parent().slideDown(1500).remove();
        });



        $("#selectCategory").on('change', function (e) {
            e.preventDefault();

            var categoryId = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.all.selected.categories') }}',
                data: {categoryId: categoryId},
                dataType: 'json',
                success:
                    function (response) {



                        if (response) {
                            $("#selectSubCategory").empty();
                            $("#selectSubCategory").prop('disabled', false);
                            $("#selectSubCategory").append('<option value="" selected disabled>اختار القسم الفرعي </option>');
                            $.each(response, function (key, value) {
                                $("#selectSubCategory").append('<option value="' + value.id + '">' + value.name + '</option>');
                            });
                            $("#selectSubCategory").select2();
                        } else {
                            $("#selectSubCategory").empty();
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

    </script>

@endsection


