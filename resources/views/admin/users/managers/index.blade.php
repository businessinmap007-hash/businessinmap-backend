@extends('admin.layouts.master')
@section('title', __('maincp.manage_the_application'))
@section('content')

    <!-- Page-Title -->
    <div class="row zoomIn">
        <div class="col-sm-12">
            <div class="btn-group pull-right m-t-15">

                <a href="{{ route('users.create') }}" type="button" class="btn btn-custom waves-effect waves-light"
                   aria-expanded="false"> @lang('maincp.add')
                    <span class="m-l-5">
                        <i class="fa fa-plus"></i>
                    </span>
                </a>

            </div>
            <h4 class="page-title">@lang('trans.managers_system') </h4>
        </div>
    </div>

    <div class="row zoomIn">
        <div class="col-sm-12">
            <div class="card-box rotateOutUpRight ">


                <table id="datatable-fixed-header" class="table  table-striped">
                    <thead>
                    <tr>

                        {{--<th>@lang('maincp.image')</th>--}}
                        <th>@lang('trans.username')</th>
                        <th>@lang('maincp.e_mail') </th>
                        <th>@lang('maincp.mobile_number')</th>
                        <th>@lang('maincp.choose')</th>

                    </tr>
                    </thead>
                    <tbody>

                    @foreach($users as $user)

                        <tr>

                            {{--<td style="width: 10%;">--}}
                            {{--<a data-fancybox="gallery"--}}
                            {{--href="{{ $helper->getDefaultImage($user->image, request()->root().'/assets/admin/custom/images/default.png') }}">--}}
                            {{--<img style="width: 50%; border-radius: 50%; height: 49px;"--}}
                            {{--src="{{ $helper->getDefaultImage($user->image, request()->root().'/assets/admin/custom/images/default.png') }}"/>--}}
                            {{--</a>--}}

                            {{--</td>--}}

                            <td>{{ $user->name  }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone }}</td>


                            <td>

                                <a href="{{ route('users.show',$user->id) }}"
                                   class="btn btn-trans btn-xs btn-info m-b-5"
                                   data-toggle="tooltip"
                                   data-placement="top" title="" data-original-title="@lang('maincp.show_detailes')">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <a href="javascript:;" id="elementRow{{ $user->id }}" data-id="{{ $user->id }}"
                                   data-toggle="tooltip"
                                   data-placement="top" title="" data-original-title="@lang('maincp.delete_user')"
                                   class="removeElement btn-xs btn-icon btn-trans btn-sm waves-effect waves-light btn-danger m-b-5">
                                    <i class="fa fa-remove"></i>

                                </a>


                                <a href="javascript:;" data-id="{{ $user->id }}" data-type="0"
                                   data-url="{{ route('user.suspend') }}"
                                   style="@if($user->is_suspend == 0) display: none;  @endif"
                                   class="btn btn-xs btn-trans btn-danger danger m-b-5 suspendElement suspend{{ $user->id }}"
                                   id="suspendElement" data-message="@lang('maincp.suspend_message')"
                                   data-toggle="tooltip" data-placement="top"
                                   title="" data-original-title="@lang('maincp.suspend')">
                                    <i class="fa fa-lock"></i>
                                </a>

                                <a href="javascript:;" data-id="{{ $user->id }}" data-type="1"
                                   data-url="{{ route('user.suspend') }}"
                                   style="@if($user->is_suspend == 1) display: none;  @endif"
                                   class="btn btn-xs btn-trans btn-success success m-b-5 suspendElement unsuspend{{ $user->id }}"
                                   id="suspendElement" data-message="@lang('maincp.suspend_message')"
                                   data-toggle="tooltip" data-placement="top"
                                   title="" data-original-title="@lang('maincp.del_suspend')">
                                    <i class="fa fa-unlock"></i>
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

        $('body').on('click', '.removeElement', function () {
            var id = $(this).attr('data-id');
            var $tr = $(this).closest($('#elementRow' + id).parent().parent());
            swal({
                title: "@lang('maincp.make_sure')",
                text: "@lang('maincp.confirm_delete_message')",
                type: "error",
                showCancelButton: true,
                confirmButtonColor: "#DD6B55",
                confirmButtonText: "@lang('maincp.accepted')",
                cancelButtonText: "@lang('maincp.cancel')",
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
                            if (data.status == true) {


                                var shortCutFunction = 'success';
                                var msg = '@lang('maincp.del_confirm')';
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


        $('body').on('click', '.suspendElement', function () {
            var id = $(this).attr('data-id');
            var type = $(this).attr('data-type');
            swal({
                title: "هل انت متأكد؟",
                text: $(this).attr('data-message'),
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
                        url: '{{ route('user.suspend') }}',
                        data: {id: id, type: type},
                        dataType: 'json',
                        success: function (data) {

                            if (data.status == true) {

                                if (data.type == 1) {
                                    var shortCutFunction = 'success';
                                    var msg = 'لقد تم فك الحظر عن المستخدم بنجاح.';

                                    $('.suspend' + data.id).delay(500).slideDown();
                                    $('.unsuspend' + data.id).slideUp();

                                } else {
                                    var shortCutFunction = 'success';
                                    var msg = 'لقد تم حظر المستخدم بنجاح.';

                                    $('.unsuspend' + data.id).delay(500).slideDown();
                                    $('.suspend' + data.id).slideUp();
                                }


                                // var shortCutFunction = 'success';
                                // var msg = 'لقد تمت عملية الحذف بنجاح.';
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


                        }
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

        // function showMessage(message) {
        //
        //     var shortCutFunction = 'success';
        //     var msg = message;
        //     var title = 'نجاح!';
        //     toastr.options = {
        //         positionClass: 'toast-top-center',
        //         onclick: null,
        //         showMethod: 'slideDown',
        //         hideMethod: "slideUp",
        //     };
        //     var $toast = toastr[shortCutFunction](msg, title);
        //     // Wire up an event handler to a button in the toast, if it exists
        //     $toastlast = $toast;
        //
        //
        // }


        @php




            $url = str_replace(request()->url(), '',request()->fullUrl());



        @endphp
        function getChildrenCats(selectObject) {
            var value = selectObject.value;
            var url = "{{ $url }}";


            window.location.href = "{{ route('users.index').'?city=' }}" + value;


            {{--$.ajax({--}}
            {{--type: 'POST',--}}
            {{--url: '{{ route('get.users.byCity') }}',--}}
            {{--data: {value: value, url: url},--}}
            {{--dataType: 'json',--}}
            {{--success: function (data) {--}}
            {{--if (data.status == true) {--}}

            {{--window.location.href = data.url;--}}

            {{--}--}}

            {{--}--}}
            {{--});--}}

        }

    </script>


@endsection

