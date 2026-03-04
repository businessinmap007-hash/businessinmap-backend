@extends('admin.layouts.master')
@section('title', __('maincp.users_manager'))
@section('content')

    <!-- Page-Title -->
    <div class="row zoomIn">
        <div class="col-sm-12">
            <div class="btn-group pull-right m-t-15">

                <a href="{{ route('companies.users.create') }}" type="button"
                   class="btn btn-custom waves-effect waves-light"
                   aria-expanded="false"> @lang('maincp.add')
                    <span class="m-l-5">
                        <i class="fa fa-plus"></i>
                    </span>
                </a>

            </div>
            <h4 class="page-title">@lang('maincp.user_list') </h4>
        </div>
    </div>




    <div class="card-box table-responsive">
        <div class="col-xs-12">
            <h4 class="header-title m-t-0 m-b-30">@lang('maincp.personal_data')</h4>
        </div>


        <form action="{{ route('companies.users') }}">
            <div class="col-sm-3 col-xs-12" style="margin-bottom: 10px">
                <select class="form-control" name="city">
                    <option value="all">@lang('maincp.select_country')</option>
                    <option value="all">@lang('maincp.all')</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}"
                                @if(request('city') == $city->id) selected @endif>{{ $city->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-3 col-xs-12" style="margin-bottom: 10px">
                <select class="form-control" name="type" id="agencyCompanies">
                    <option value="all">@lang('maincp.facility_type') </option>
                    <option value="all">@lang('maincp.all')</option>
                    @foreach($agencies  as $agency)
                        <option value="{{ $agency->id }}"
                                @if(request('type') == $agency->id) selected @endif>{{ $agency->name }}</option>
                    @endforeach

                </select>
            </div>
            <div class="col-sm-3 col-xs-12">
                <select class="form-control" name="company" id="companies" @if(!request('company')) disabled @endif>
                    <option value="all">@lang('maincp.facility_name')</option>
                    @if(request('company'))
                        @foreach($companies  as $company)
                            <option value="{{ $company->id }}"
                                    @if(request('company') == $company->id) selected @endif>{{ $company->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>
            <div class="col-sm-3 col-xs-12" style="margin-bottom: 10px">
                <select class="form-control" name="branch" id="branches"
                        @if(!request('branch') && !request('company')) disabled @endif>
                    <option value="all">@lang('maincp.branch')</option>

                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}"
                                @if(request('branch') == $branch->id) selected @endif>{{ $branch->name }}</option>
                    @endforeach

                </select>
            </div>

            <div class="col-xs-12">
                <input type="submit" class="btn btn-success" style="float: left;" value="@lang('maincp.search')"/>
            </div>

        </form>

    </div>



    <div class="row zoomIn">
        <div class="col-sm-12">
            <div class="card-box rotateOutUpRight ">

                <div class="row">
                    <div class="col-sm-4 col-xs-8 m-b-30" style="display: inline-flex">
                        @lang('maincp.personal_data')
                    </div>
                </div>

                <table id="datatable-fixed-header" class="table  table-striped">
                    <thead>
                    <tr>
                        <th>@lang('maincp.full_name') </th>
                        <th>@lang('maincp.e_mail') </th>
                        <th>@lang('maincp.mobile_number')</th>
                        <th>@lang('maincp.facility_type')</th>
                        <th>@lang('maincp.branch')</th>
                        <th>@lang('maincp.account_access_count')</th>
                        {{--<th>الحالة</th>--}}
                        <th>@lang('maincp.choose') </th>

                    </tr>
                    </thead>
                    <tbody>

                    @foreach($users as $user)

                        <tr>


                            <td>{{ $user->username  }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone }}</td>
                            <td>{{ ($user->companies)? ($user->companies->agency)?$user->companies->agency->name:"--" : "--"}}</td>

                            <td>
                                {{ ($user->branch_id != 0)? $user->branch->name : __('maincp.name_of_the_facility_manager') }}
                            </td>

                            <td>{{ $user->loggedin_count }}</td>

                            {{--<td>--}}
                            {{--@if($user->is_active == 1)--}}
                            {{--<label class="label label-success label-xs">@lang('maincp.active')</label>--}}
                            {{--@else--}}
                            {{--<label class="label label-danger label-xs">@lang('maincp.unactive')</label>--}}
                            {{--@endif--}}
                            {{--</td>--}}

                            <td>


                                <a href="{{ route('companies.user.edit',$user->id) }}"
                                   class="btn btn-icon btn-xs waves-effect btn-default m-b-5">
                                    <i class="fa fa-edit"></i>
                                </a>


                                <a href="javascript:;" id="elementRow{{ $user->id }}" data-id="{{ $user->id }}" data-url="{{ route('users.destroy', $user->id) }}"
                                   class="removeElement btn-xs btn-icon btn-trans btn-sm waves-effect waves-light btn-danger m-b-5">
                                    <i class="fa fa-remove"></i>

                                </a>
                            </td>
                        </tr>

                    @endforeach
                    </tbody>
                </table>

            </div>
        </div>
    </div>
    <!-- End row -->
@endsection

@section('scripts')




    <script>

        {{--@if(session()->has('success'))--}}
        {{--setTimeout(function () {--}}
        {{--showMessage('{{ session()->get('success') }}');--}}
        {{--}, 3000);--}}
        {{--@endif--}}

        $('body').on('click', '.removeElement', function () {
            var id = $(this).attr('data-id');
            var $tr = $(this).closest($('#elementRow' + id).parent().parent());
            swal({
                title: "هل انت متأكد؟",
                text: "يمكنك استرجاع المحذوفات مرة اخرى لا تقلق.",
                type: "error",
                showCancelButton: true,
                confirmButtonColor: "#DD6B55",
                confirmButtonText: "موافق",
                cancelButtonText: "إلغاء",
                confirmButtonClass: 'btn-danger waves-effect waves-light',
                closeOnConfirm: true,
                closeOnCancel: true,
            }, function (isConfirm) {
                if (isConfirm) {
                    $.ajax({
                        type: 'POST',
                        url: '{{ route('user.delete') }}',
                        data: {id: id},
                        dataType: 'json',
                        success: function (data) {
                            $('#catTrashed').html(data.trashed);
                            if (data) {
                                var shortCutFunction = 'success';
                                var msg = 'لقد تمت عملية الحذف بنجاح.';
                                var title = data.title;
                                toastr.options = {
                                    positionClass: 'toast-top-center',
                                    onclick: null,
                                    showMethod: 'slideDown',
                                    hideMethod: "slideUp",
                                };
                                var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                                $toastlast = $toast;
                            }

                            $tr.find('td').fadeOut(1000, function () {
                                $tr.remove();
                            });
                        }
                    });
                } else {

                    swal({
                        title: "تم الالغاء",
                        text: "انت لغيت عملية الحذف تقدر تحاول فى اى وقت :)",
                        type: "error",
                        showCancelButton: false,
                        confirmButtonColor: "#DD6B55",
                        confirmButtonText: "موافق",
                        confirmButtonClass: 'btn-info waves-effect waves-light',
                        closeOnConfirm: false,
                        closeOnCancel: false

                    });

                }
            });
        });

        $('.getSelected').on('click', function () {
            // var items = $('.checkboxes-items').val();
            var sum = [];
            $('.checkboxes-items').each(function () {
                if ($(this).prop('checked') == true) {
                    sum.push(Number($(this).val()));
                }

            });

            if (sum.length > 0) {
                //var $tr = $(this).closest($('#elementRow' + id).parent().parent());
                swal({
                    title: "هل انت متأكد؟",
                    text: "يمكنك استرجاع المحذوفات مرة اخرى لا تقلق.",
                    type: "error",
                    showCancelButton: true,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "موافق",
                    cancelButtonText: "إلغاء",
                    confirmButtonClass: 'btn-danger waves-effect waves-light',
                    closeOnConfirm: true,
                    closeOnCancel: true,
                }, function (isConfirm) {
                    if (isConfirm) {
                        $.ajax({
                            type: 'POST',
                            url: '{{ route('users.group.delete') }}',
                            data: {ids: sum},
                            dataType: 'json',
                            success: function (data) {
                                $('#catTrashed').html(data.trashed);
                                if (data) {
                                    var shortCutFunction = 'success';
                                    var msg = 'لقد تمت عملية الحذف بنجاح.';
                                    var title = data.title;
                                    toastr.options = {
                                        positionClass: 'toast-top-center',
                                        onclick: null,
                                        showMethod: 'slideDown',
                                        hideMethod: "slideUp",
                                    };
                                    var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                                    $toastlast = $toast;
                                }

                                // $('.checkboxes-items').each(function () {
                                //     if ($(this).prop('checked') == true) {
                                //         $(this).parent('tr').remove();
                                //     }
                                // });

                                $('.checkboxes-items').each(function () {
                                    if ($(this).prop('checked') == true) {
                                        $(this).parent().parent().parent().fadeOut(1000);
                                    }
                                });

//                        $tr.find('td').fadeOut(1000, function () {
//                            $tr.remove();
//                        });
                            }
                        });
                    } else {
                        swal({
                            title: "تم الالغاء",
                            text: "انت لغيت عملية الحذف تقدر تحاول فى اى وقت :)",
                            type: "error",
                            showCancelButton: false,
                            confirmButtonColor: "#DD6B55",
                            confirmButtonText: "موافق",
                            confirmButtonClass: 'btn-info waves-effect waves-light',
                            closeOnConfirm: false,
                            closeOnCancel: false
                        });
                    }
                });
            } else {
                swal({
                    title: "تحذير",
                    text: "قم بتحديد عنصر على الاقل",
                    type: "warning",
                    showCancelButton: false,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "موافق",
                    confirmButtonClass: 'btn-warning waves-effect waves-light',
                    closeOnConfirm: false,
                    closeOnCancel: false

                });
            }


        });

        $('.getSelectedAndSuspend').on('click', function () {


            var type = $(this).attr('data-userType');
            var sum = [];
            $('.checkboxes-items').each(function () {
                if ($(this).prop('checked') == true) {
                    sum.push(Number($(this).val()));
                }
            });

            if (sum.length > 0) {
                //var $tr = $(this).closest($('#elementRow' + id).parent().parent());
                swal({
                    title: "هل انت متأكد؟",
                    text: "يمكنك استرجاع المحذوفات مرة اخرى لا تقلق.",
                    type: "error",
                    showCancelButton: true,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "موافق",
                    cancelButtonText: "إلغاء",
                    confirmButtonClass: 'btn-danger waves-effect waves-light',
                    closeOnConfirm: true,
                    closeOnCancel: true,
                }, function (isConfirm) {
                    if (isConfirm) {
                        $.ajax({
                            type: 'POST',
                            url: '{{ route('users.group.suspend') }}',
                            data: {ids: sum, type: type},
                            dataType: 'json',
                            success: function (data) {


                                if (data.status == true) {
                                    var shortCutFunction = 'success';
                                    var msg = data.message;
                                    var title = data.title;
                                    toastr.options = {
                                        positionClass: 'toast-top-center',
                                        onclick: null,
                                        showMethod: 'slideDown',
                                        hideMethod: "slideUp",
                                    };
                                    var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                                    $toastlast = $toast;
                                    $('.checkboxes-items').each(function () {
                                        if ($(this).prop('checked') == true) {
                                            $(this).parent().parent().parent().fadeOut(1000);
                                            $(this).prop('checked', false);


                                        }
                                    });


                                    if (data.susp == 1) {
                                        $('#suspUsersBtn').fadeIn(500);
                                    }

                                }


//                        $tr.find('td').fadeOut(1000, function () {
//                            $tr.remove();
//                        });
                            }
                        });
                    } else {
                        swal({
                            title: "تم الالغاء",
                            text: "انت لغيت عملية الحذف تقدر تحاول فى اى وقت :)",
                            type: "error",
                            showCancelButton: false,
                            confirmButtonColor: "#DD6B55",
                            confirmButtonText: "موافق",
                            confirmButtonClass: 'btn-info waves-effect waves-light',
                            closeOnConfirm: false,
                            closeOnCancel: false
                        });
                    }
                });
            } else {
                swal({
                    title: "تحذير",
                    text: "قم بتحديد عنصر على الاقل",
                    type: "warning",
                    showCancelButton: false,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "موافق",
                    confirmButtonClass: 'btn-warning waves-effect waves-light',
                    closeOnConfirm: false,
                    closeOnCancel: false

                });
            }


        });

    </script>





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


    </script>





@endsection

