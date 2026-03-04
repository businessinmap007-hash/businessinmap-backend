@extends('admin.layouts.master')

@section('title', "إدارة المنتجات")
@section('styles')

    <!-- Custom box css -->
    <link href="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/custombox.min.css" rel="stylesheet">

    <style>
        .errorValidationReason {
            border: 1px solid red;
        }
    </style>
@endsection
@section('content')

    <!-- Page-Title -->
    <div class="row">
        <div class="col-sm-12">
            <div class="btn-group pull-right m-t-15 ">
                <a href="{{ route('sliders.create') }}" type="button" class="btn btn-custom waves-effect waves-light"
                   aria-expanded="false">
                <span class="m-l-5">
                <i class="fa fa-plus"></i>
                </span>
                    إضافة معرض صور
                </a>
            </div>
            <h4 class="page-title">


                إدارة معرض الصور


            </h4>
        </div>
    </div>


    <div class="row">
        <div class="col-sm-12">
            <div class="card-box table-responsive">

                <div class="dropdown pull-right">
                    {{--<a href="#" class="dropdown-toggle card-drop" data-toggle="dropdown" aria-expanded="false">--}}
                    {{--<i class="zmdi zmdi-more-vert"></i> --}}
                    {{--</a>--}}

                </div>

                <h4 class="header-title m-t-0 m-b-30">
                    قائمة المعارض


                </h4>

                <table id="datatable-fixed-header_users" class="table table-striped table-bordered dt-responsive nowrap"
                       cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th>صورة المعرض</th>
                        <th>اسم المنتج (AR)</th>
                        <th>اسم المنتج (EN)</th>

                        <th>تاريخ الإنشاء</th>
                        <th>الخيارات</th>
                    </tr>
                    </thead>
                    <tbody>

                    @foreach($results as $row)
                        <tr>
                            <td>
                                <a data-fancybox="gallery"
                                   href="{{ $helper->getDefaultImage(request()->root().'/public/'. $row->image, request()->root().'/public/assets/admin/images/about_img.jpg') }}">
                                    <img style="width: 55px; border-radius: 50%; height: 55px;"
                                         src="{{ $helper->getDefaultImage(request()->root().'/public/'. $row->image, request()->root().'/public/assets/admin/images/about_img.jpg') }}"/>
                                </a>
                            </td>
                            <td>{{ $row->{'title:ar'} }}</td>
                            <td>{{ $row->{'title:en'} }}</td>



                            <td>{{ $row->created_at != ''? @$row->created_at->format('Y/m/d'): "--" }}</td>
                            <td>
                                <a href="{{ route('sliders.show', $row->id) }}"
                                   data-toggle="tooltip" data-placement="top"
                                   data-original-title='التفاصيل'
                                   class="btn btn-icon btn-xs waves-effect  btn-info">
                                    <i class="fa fa-eye"></i>
                                </a>
                                <a href="{{ route('sliders.edit', $row->id) }}"
                                   data-toggle="tooltip" data-placement="top"
                                   data-original-title="تعديل الحملة"
                                   class="btn btn-icon btn-xs waves-effect btn-trans btn-success">
                                    <i class="fa fa-pencil"></i>
                                </a>

                                <a href="javascript:;" id="elementRow{{ $row->id }}" data-id="{{ $row->id }}"
                                   data-url="{{ route('sliders.destroy', $row->id) }}"
                                   class="removeElement btn btn-icon btn-trans btn-xs waves-effect waves-light btn-danger">
                                    <i class="fa fa-remove"></i>
                                </a>


                            </td>
                        </tr>
                    @endforeach

                    </tbody>
                </table>
            </div>
        </div><!-- end col -->
    </div>
    <!-- end row -->


@endsection


@section('scripts')

    <!-- Modal-Effect -->
    <script src="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/custombox.min.js"></script>
    <script src="{{ request()->root() }}/public/assets/admin/plugins/custombox/dist/legacy.min.js"></script>



    <!--"order": [[ 0, "desc" ]],-->

    <script>

        $('body').on('click', '.removeElement', function () {
            var id = $(this).attr('data-id');
            var url = $(this).attr('data-url');
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
                        url: url,
                        data: {id: id},
                        dataType: 'json',
                        success: function (data) {

                            if (data.status == true) {
                                var shortCutFunction = 'success';
                                var msg = 'لقد تمت عملية الحذف بنجاح.';
                                var title = data.title;
                                toastr.options = {
                                    positionClass: 'toast-top-left',
                                    onclick: null
                                };
                                var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                                $toastlast = $toast;

                                $tr.find('td').fadeOut(1000, function () {
                                    $tr.remove();
                                });
                            }
                        }
                    });
                }
            });
        });


        var table = $('#datatable-fixed-header_users').DataTable({
            order: [[4, "desc"]],
            fixedHeader: true,

            // columnDefs: [{orderable: false, targets: [0]}],
            "language": {
                "lengthMenu": "@lang('maincp.show') _MENU_ @lang('maincp.perpage')",
                "info": "@lang('maincp.show') @lang('maincp.perpage') _PAGE_ @lang('maincp.from')_PAGES_",
                "infoEmpty": "@lang('maincp.no_recorded_data_available')",
                "infoFiltered": "(@lang('maincp.filter_from_max_total') _MAX_)",
                "paginate": {
                    "first": "@lang('maincp.first')",
                    "last": "@lang('maincp.last')",
                    "next": "@lang('maincp.next')",
                    "previous": "@lang('maincp.previous')"
                },
                "search": "@lang('maincp.search'):",
                "zeroRecords": "@lang('maincp.no_recorded_data_available')",

            },

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
                            url: '{{ route('companies.group.delete') }}',
                            data: {ids: sum},
                            dataType: 'json',
                            success: function (data) {
                                $('#catTrashed').html(data.trashed);
                                if (data) {
                                    var shortCutFunction = 'success';
                                    var msg = 'لقد تمت عملية الحذف بنجاح.';
                                    var title = data.title;
                                    toastr.options = {
                                        positionClass: 'toast-top-left',
                                        onclick: null
                                    };
                                    var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                                    $toastlast = $toast;
                                }

                                $('.checkboxes-items').each(function () {
                                    if ($(this).prop('checked') == true) {
                                        $(this).parent().parent().parent().fadeOut();
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

        $('body').delegate('#suspendElementReason', 'click', function (e) {
            $("#suspendElementReason").html('{{ __('trans.processing') }}');
            e.preventDefault();

            var id = $(this).attr('data-id');
            var type = $(this).attr('data-type');
            var url = $(this).attr('data-url');
            var reason = $("#reasonSuspend" + id).val();
            var isReason = $("#isSuspendReason" + id).val();


            if (reason == "") {

                $("#reasonSuspend" + id).css("border", "1px solid red");
                $("#errorMessageRequired" + id).fadeIn();


            } else {
                $("#reasonSuspend" + id).css("border", "1px solid #E3E3E3");
                $("#errorMessageRequired" + id).fadeOut();


            }

            $.ajax({
                type: 'POST',
                url: url,
                data: {id: id, type: type, reason: reason},
                dataType: 'json',
                success: function (data) {

                    if (data.status == true) {

                        $("#suspendElementReason").html('{{ __('trans.suspend') }}');

                        $("#reasonSuspend" + id).val("");

                        if (data.type == 1) {


                            $('.suspend' + data.id).delay(500).slideDown();
                            $('.unsuspend' + data.id).slideUp();

                            $('.StatusActive' + data.id).delay(500).slideDown();
                            $('.StatusNotActive' + data.id).slideUp();
                            Custombox.close();

                        } else {


                            $('.unsuspend' + data.id).delay(500).slideDown();
                            $('.suspend' + data.id).slideUp();


                            $('.StatusNotActive' + data.id).delay(500).slideDown();
                            $('.StatusActive' + data.id).slideUp();
                            Custombox.close();

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


        });


        $(document).ready(function () {
            //$('#datatable').dataTable();
            //$('#datatable-keytable').DataTable( { keys: true } );
            $('#datatable-responsive').DataTable();

        });


    </script>


@endsection



