@extends('admin.layouts.master')
@section('title' ,'إدارة الخصومات')

@section('styles')



@endsection
@section('content')
    <form id="storeCampaign" method="POST" action="{{ route('coupons.update', $coupon->id) }}"
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
                <h4 class="page-title">إدارة الخصومات</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-lg-10 col-sm-offset-1">
                <div class="card-box">
                    <h2 class="header-title m-t-0 m-b-30">تعديل </h2>

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName"> الهدية </label>
                            <input type="text" name="percentage" value="{{ $coupon->percentage or old('percentage') }}"
                                   class="form-control" required
                                   placeholder="قيمة الهدية بالشهر"
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message=" عنوان المعرض إلزامي"
                                   data-parsley-maxlength="100"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (100) حرف"

                            />

                            @if($errors->has('percentage'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('percentage') }}
                                </p>
                            @endif


                        </div>
                    </div>

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName"> عدد مرات الإستخدام </label>
                            <input type="text" name="times" value="{{ $coupon->times or old('times') }}"
                                   class="form-control" required
                                   placeholder="عدد مرات إستخدام الكود"
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message=" عدد مرات إستخدام الكود إلزامي"
                                   data-parsley-maxlength="100"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (100) حرف"

                            />

                            @if($errors->has('times'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('times') }}
                                </p>
                            @endif


                        </div>
                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName"> تاريخ إنتهاء الكود</label>
                            <input type="text" name="expire_at" value="{{ $coupon->expire_at or  old('expire_at') }}"
                                   class="form-control" required
                                   placeholder="تاريخ إنتهاء الكود"
                                   id="datepicker"

                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message="تاريخ إنتهاء الكود إلزامي"
                                   data-parsley-maxlength="100"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (100) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                            />

                            @if($errors->has('expire_at'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('expire_at') }}
                                </p>
                            @endif


                        </div>
                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName"> القسم</label>
                            {{--                            <input type="text" name="expire_at" value="{{ old('expire_at') }}"--}}
                            {{--                                   class="form-control" required--}}
                            {{--                                   placeholder="تاريخ إنتهاء الكود"--}}
                            {{--                                   data-parsley-trigger="keyup"--}}
                            {{--                                   data-parsley-required-message="تاريخ إنتهاء الكود إلزامي"--}}
                            {{--                                   data-parsley-maxlength="100"--}}
                            {{--                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (100) حرف"--}}
                            {{--                                   data-parsley-minlength="3"--}}
                            {{--                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"--}}
                            {{--                            />--}}

                            <select class="form-control" name="category">
                                <option value="all">كل الاقسام</option>

                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ $category->id == $coupon->category ? "selected" : "" }}>{{ $category->name }}</option>
                                @endforeach
                            </select>

                            @if($errors->has('category'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('category') }}
                                </p>
                            @endif


                        </div>
                    </div>


                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">كود الخصم</label>
                            <input type="text" name="code" class="form-control"
                                   value="{{ $coupon->code }}"/>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('code'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('code') }}
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="col-xs-6">
                        <div class="form-group">
                            <label for="userName">الحد الادني من الشهور للإستخدام</label>
                            <input type="text" name="limit_months" class="form-control"
                                   value="{{ $coupon->limit_months }}"/>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('limit_months'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('limit_months') }}
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


     
    </script>

@endsection


