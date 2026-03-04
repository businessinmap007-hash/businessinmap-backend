@extends('admin.layouts.master')
@section('title' ,'تعديل معتمر')

@section('styles')



@endsection
@section('content')
    <form id="storeCampaign" method="POST" action="{{ route('products.update', $result->id) }}"
          enctype="multipart/form-data"
          data-parsley-validate
          novalidate class="submission-form">
    {{ csrf_field() }}
    {{ method_field('PUT') }}

    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title">إدارة المعتمرين</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="card-box">
                    <h2 class="header-title m-t-0 m-b-30">إضافة تعديل </h2>


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName"> اسم المنتج - {{ $value }} </label>
                                <input type="text" name="name:{{ $locale }}"
                                       value="{{ $result->{'name:'.$locale} or  old('name_'.$locale) }}"
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
                            <select name="mainCategory" class="form-control" required>
                                <option value="">اختار التصنيف</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ $result->category_id  == $category->id ? "selected" : ""}}>{{ $category->name }}</option>
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
                            <select name="subCategory" class="form-control" required>
                                <option value="">اختار التصنيف</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ $result->category_id  == $category->id ? "selected" : ""}} >{{ $category->name }}</option>
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
                            <input type="text" name="price" value="{{ $result->price or old('price') }}"
                                   class="form-control"
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
                            <input type="text" name="price_sale" value="{{ $result->price_sale or old('price_sale') }}"
                                   class="form-control"
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
                            <input type="text" name="quantity" value="{{ $result->quantity or old('quantity') }}"
                                   class="form-control"
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

                    <div class="col-xs-4">
                        <div class="form-group">
                            <label for="userName">صورة المنتج الرئيسية*</label>
                            <input type="file" name="image" class="form-control"/>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('image'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('image') }}
                                </p>
                            @endif
                        </div>
                        <img width="100%" style="height: auto; border-radius: 10px; margin-bottom: 10px"
                             src="{{ asset('public/'.$result->image) }}">
                    </div>

                    <div class="col-xs-8">
                        <div class="form-group">
                            <label for="userName">صور آخري للمنتج*</label>
                            <input type="file" name="images[]" class="form-control" multiple/>
                            @if($errors->has('images'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('images') }}
                                </p>
                            @endif
                        </div>


                        @foreach($result->images as $image)
                            <div style="position: relative;margin: 1.5px; width: 24.5%;  height: 120px; float: right; ">

                                <a  class="deleteProductImage" data-id="{{ $image->id }}" style="position: absolute;
    right: 0px;
    background: white;
    padding: 2px;
    border-radius: 50%;
    height: 25px;
    width: 25px;
    text-align: center;
    color: #e67e7e;" href="javascript:;">
                                    <i class="fa fa-close"></i>
                                </a>




                                <img style="width: 100%; height: 100%; border-radius: 10px; margin-bottom: 10px"
                                     src="{{ asset('public/'.$image->image) }}">


                            </div>
                        @endforeach


                    </div>


                    <?php $i = 1; ?>
                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-12">
                            <div class="form-group">
                                <label for="description:{{ $locale }}">وصف المنتج باللغة - {{ $value }} </label>
                                <textarea id="editor{{ $i }}" rows="10" class="form-control msg_body"
                                          name="description:{{ $locale }}">{{ $result->{'description:'.$locale} }}</textarea>
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


        $(".deleteProductImage").on('click', function () {
            $this = $(this);
            var imageId = $this.attr('data-id');
            $.ajax({
                type: 'post',
                url: '{{ route('delete.product.image') }}',
                data: {imageId: imageId},
                dataType: 'json',
                success:
                    function (response) {

                        if (response.status) {
                            $this.parent().remove();
                        }

                    }
            });

        });
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


    </script>

@endsection


