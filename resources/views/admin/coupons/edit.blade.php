@extends('admin.layouts.master')
@section('title' ,'إدارة الخصومات')

@section('styles')



@endsection
@section('content')
    <form id="storeCampaign" method="POST" action="{{ route('banners.update', $result->id) }}"
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


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName"> رابط البانر </label>
                            <input type="text" name="link" value="{{ $result->link }}"
                                   class="form-control" required
                                   placeholder="الرابط"
                                   data-parsley-trigger="keyup"
                                   data-parsley-required-message=" عنوان المعرض إلزامي"
                                   data-parsley-maxlength="100"
                                   data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (100) حرف"
                                   data-parsley-minlength="3"
                                   data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                            />

                            @if($errors->has('link'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('link') }}
                                </p>
                            @endif


                        </div>
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">صورة البانر الإعلاني*</label>
                            <input type="file" name="image" class="form-control" required/>
                            <p class="help-block" id="error_userName"></p>
                            @if($errors->has('image'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('image') }}
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
        // CKEDITOR.replace('editor1');
        // CKEDITOR.replace('editor2');


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


