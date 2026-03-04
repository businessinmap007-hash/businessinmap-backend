@extends('admin.layouts.master')
@section('title' ,  __('maincp.add_user'))
@section('content')

    <form method="POST" action="{{ route('companies.users.store') }}" enctype="multipart/form-data"
          data-parsley-validate
          novalidate>
    {{ csrf_field() }}

    <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title"> @lang('maincp.users_administration')</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-sm-12">


                <div class="card-box table-responsive">
                    <div class="row"> 
                    <div class="form-group">
                        <div class="col-xs-12 col-lg-12">
                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.full_name')</label>
                                <input class="form-control" name="username" value="{{ old('username') }}" type="text"
                                       placeholder="@lang('maincp.full_name')">
                                @if ($errors->has('username'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('username') }}</strong>
                                    </span>
                                @endif

                            </div>
                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.facility_type') </label>
                                <select class="form-control" name="agencyId" id="agencyCompanies"

                                        required
                                        data-parsley-required-message="@lang('global.required')">

                                    <option value="" selected>@lang('maincp.facility_main') </option>
                                    @foreach($agencies as $agency)
                                        <option value="{{ $agency->id }}"
                                                @if(old('agencyId') == $agency->id) selected @endif>{{ $agency->name }}</option>
                                    @endforeach

                                </select>
                                @if ($errors->has('agencyId'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('mainCompany') }}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.facility_name')</label>
                                <select class="form-control" name="companyId" id="companies"
                                required
                                        data-parsley-required-message="@lang('global.required')"
                                        onchange="showMaintenanceCenter(this)" readonly>
                                    <option selected value=''>@lang('maincp.facility_name')</option>

                                </select>

                                @if ($errors->has('companyId'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('companyId') }}</strong>
                                    </span>
                                @endif

                            </div>
                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.branch_name')  </label>
                                <select class="form-control" name="branchId" id="branches"
                                        required
                                        data-parsley-required-message="@lang('global.required')"
                                        readonly>
                                    <option selected value=''>@lang('maincp.branch_name')  </option>


                                </select>
                                @if ($errors->has('branchId'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('branchId') }}</strong>
                                    </span>
                                @endif

                            </div>

                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.mobile_number')</label>
                                <input class="form-control numbersOnly" data-limit="15"
                                       data-message="أقصى عدد للارقام المسموح بها 15 رقم" name="phone"
                                       value="{{ old('phone') }}" type="text"
                                       required=""
                                       maxlength="16"
                                       data-parsley-required-message="رقم الجوال مطلوب"
                                       data-parsley-trigger="keyup"
                                       data-parsley-maxlength="15"
                                       data-parsley-pattern="^[0-9]+$"
                                       data-parsley-pattern-message="حقل الهاتف لا يقبل الحروف"
                                       data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (15) حرف"
                                       placeholder="123456789">


                                <span class="phone" style="font-size: 12px; color: darkred;"></span>


                                @if ($errors->has('phone'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('phone') }}</strong>
                                    </span>
                                @endif

                            </div>

                            <!--<div class="col-lg-4 col-xs-12">-->
                            <!--    <label>@lang('maincp.unified_number')</label>-->
                            <!--    <input class="form-control  numbersOnly" data-limit="15"-->
                            <!--           data-message="أقصى عدد للارقام المسموح بها 15 رقم" value="{{ old('static_no') }}"-->
                            <!--           name="static_no" type="tel"-->
                            <!--           required=""-->
                            <!--           maxlength="16"-->
                            <!--           data-parsley-required-message="رقم الجوال مطلوب"-->
                            <!--           data-parsley-trigger="keyup"-->
                            <!--           data-parsley-maxlength="15"-->
                            <!--           data-parsley-pattern="^[0-9]+$"-->
                            <!--           data-parsley-pattern-message="حقل الهاتف لا يقبل الحروف"-->
                            <!--           data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (15) حرف"-->

                            <!--           placeholder="12345678">-->
                            <!--    <span class="static_no" style="font-size: 12px; color: darkred;"></span>-->


                            <!--    @if ($errors->has('static_no'))-->
                            <!--        <span class="help-block">-->
                            <!--            <strong>{{ $errors->first('static_no') }}</strong>-->
                            <!--        </span>-->
                            <!--    @endif-->

                            <!--</div>-->

                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.e_mail')</label>
                                <input class="form-control" value="{{ old('email') }}" name="email" type="email"
                                       required
                                       data-parsley-required-message="@lang('global.required')"
                                       placeholder="Example@saned.sa">
                                @if ($errors->has('email'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif


                            </div>

                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.address')</label>
                                <input class="form-control" name="address" value="{{ old('address') }}" type="text"
                                       required
                                       data-parsley-required-message="@lang('global.required')"
                                       placeholder="@lang('maincp.address')">
                                @if ($errors->has('address'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('address') }}</strong>
                                    </span>
                                @endif

                            </div>

                            <div class="col-lg-4 col-xs-12">
                                <label>@lang('maincp.city')</label>
                                
                                <select class="form-control" name="city">
                                     <option value="" selected disabled>@lang('maincp.select_country')</option>
                                    @foreach( $cities as $city )
                            
                                    <option value="{{ $city->id }}">{{ $city->name }} </option>

                                    @endforeach
                                </select>
                                
                                @if ($errors->has('city'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('city') }}</strong>
                                    </span>
                                @endif


                            </div>

                            <div class="col-lg-12 col-xs-12">
                                <label>@lang('maincp.permission')</label>
                                <div id="abilitiesSection">
                                    <p>@lang('maincp.please_select_a_major_facility_first_to_specify_permissions')</p>
                                </div>

                            </div>


                            <div class="col-lg-12 col-xs-12 text-right">
                                <button type="submit" class="btn btn-warning">@lang('maincp.save')</button>
                            </div>

                        </div>

                    </div>
                                        </div>

                </div>
            </div>
            <!-- end col -->
        </div>


        <!-- end row -->
    </form>
@endsection


@section('scripts')
    <script type="text/javascript">


        $("#agencyCompanies").change(function () {
            var id = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.companies.agency') }}',
                data: {id: id},
                dataType: 'json',
//                cache: false,
//                contentType: false,
//                processData: false,
                success: function (response) {
                    if (response) {

                        $("#companies").empty();
                        $("#companies").prop('disabled', false);
                        $("#companies").append('<option value="" selected disabled> اسم المنشأة</option>');
                        $.each(response, function (key, value) {
                            $("#companies").append('<option value="' + value.id + '">' + value.name + '</option>');
                        });
                        $("#companies").select2();
                    } else {
                        $("#companies").empty();
                    }
                },
                error: function (data) {
                    // $("#btn_submit").attr('disabled', 'disabled');
                    // $("#lay").show();
                }, beforeSubmit: function () {
                    //do validation here
                }, beforeSend: function () {
//                     $('#btn_submit').html("حفظ البيانات...");
                    // $("#btn_submit").attr('disabled', 'disabled');
                    // $("#lay").show();
                },
            });

        });
        $("#companies").change(function () {
            var id = $(this).val();

            $.ajax({
                type: 'post',
                url: '{{ route('get.companies.branches') }}',
                data: {id: id},
                dataType: 'json',
//                cache: false,
//                contentType: false,
//                processData: false,
                success: function (response) {
                    if (response) {

                        $("#branches").empty();
                        $("#branches").prop('disabled', false);
                        $("#branches").append('<option value="" selected disabled>  اسم الفرع</option>');
                        $("#branches").append('<option value="0">  كل الفروع (كمدير منشأة)</option>');
                        $.each(response, function (key, value) {
                            $("#branches").append('<option value="' + value.id + '">' + value.name + '</option>');
                        });
                        $("#branches").select2();
                    } else {
                        $("#branches").empty();
                    }
                },
                error: function (data) {
                    // $("#btn_submit").attr('disabled', 'disabled');
                    // $("#lay").show();
                }, beforeSubmit: function () {
                    //do validation here
                }, beforeSend: function () {
//                     $('#btn_submit').html("حفظ البيانات...");
                    // $("#btn_submit").attr('disabled', 'disabled');
                    // $("#lay").show();
                },
            });

        });

        function showMaintenanceCenter(obj) {


            $.ajax({
                type: 'POST',
                url: '{{ route('get.roles.company') }}',
                data: {id: obj.value, subCompany: obj.value, type: 'sub'},
                dataType: 'json',
                success: function (data) {
                    if (data.status == true) {
                        $('#abilitiesSection').html(data.html);
                    }
                }
            });
        }


    </script>
@endsection

