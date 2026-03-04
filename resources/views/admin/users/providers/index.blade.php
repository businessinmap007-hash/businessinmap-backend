@extends('admin.layouts.master')

@section('title', "إدارة مزودي الخدمات")
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
                <a href="{{ route('create.provider') }}" type="button" class="btn btn-custom waves-effect waves-light"
                   aria-expanded="false">
                <span class="m-l-5">
                <i class="fa fa-plus"></i>
                </span>
                    إضافة مزود خدمة
                </a>
            </div>
            <h4 class="page-title">
                إدارة مزودي الخدمات
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

                    قائمة مزودي الخدمات


                </h4>

                <table id="datatable-fixed-header_users" class="table table-striped table-bordered dt-responsive nowrap"
                       cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        {{--<th width="12%">Company Logo</th>--}}


                        @foreach (config('translatable.locales') as $locale => $value)
                            <th>اسم مزود الخدمة - {{ $value }}</th>
                        @endforeach
                        <th>البريد الإلكتروني</th>
                        <th>رقم الجوال</th>
                        <th>نوع الخدمة</th>
                        <th>قيمة الفرد</th>
                        <th>رقم التصريح</th>
                        <th>تاريخ الإنشاء</th>
                        <th>الخيارات</th>
                    </tr>
                    </thead>
                    <tbody>

                    @foreach($users as $row)
                        <tr>
                            {{--<td>--}}
                            {{--<a data-fancybox="gallery"--}}
                            {{--href="{{ $helper->getDefaultImage($row->company_logo, request()->root().'/public/assets/admin/images/about_img.jpg') }}">--}}
                            {{--<img style="width: 55px; border-radius: 50%; height: 55px;"--}}
                            {{--src="{{ $helper->getDefaultImage($row->company_logo, request()->root().'/public/assets/admin/images/about_img.jpg') }}"/>--}}
                            {{--</a>--}}
                            {{--</td>--}}

                            @foreach (config('translatable.locales') as $locale => $value)
                                <td>{{  anotherLangWhenDefaultNotFound($row, 'name', $locale) }}</td>
                            @endforeach

                            <td>{{ $row->email }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>{{  @optional($row->service)->name }}</td>
                            <td>{{ $row->price_per_person }}</td>
                            <td>{{ $row->permit_no }}</td>


                            <td>{{ $row->created_at != ''? @$row->created_at->format('Y/m/d'): "--" }}</td>
                            <td>


                                <a href="{{ route('get.provider.details', $row->id) }}"
                                   data-toggle="tooltip" data-placement="top"
                                   data-original-title="التفاصيل"
                                   class="btn btn-icon btn-xs waves-effect  btn-info">
                                    <i class="fa fa-eye"></i>
                                </a>


                                {{--<a href="{{ route('users.edit', $row->id) }}"--}}
                                {{--data-toggle="tooltip" data-placement="top"--}}
                                {{--data-original-title="@lang('institutioncp.edit')"--}}
                                {{--class="btn btn-icon btn-xs waves-effect btn-trans btn-success">--}}
                                {{--<i class="fa fa-pencil"></i>--}}
                                {{--</a>--}}


                                <a href="{{ route('edit.provider', $row->id) }}"
                                   data-toggle="tooltip" data-placement="top"
                                   data-original-title="تعديل مزود الخدمة"
                                   class="btn btn-icon btn-xs waves-effect btn-trans btn-success">
                                    <i class="fa fa-pencil"></i>
                                </a>


                                <a href="#custom-modal{{ $row->id }}" data-id="{{ $row->id }}" data-type="0"
                                   data-animation="swell" data-plugin="custommodal"
                                   data-overlaySpeed="100" data-overlayColor="#36404a"
                                   style="@if($row->is_suspend == 1) display: none;  @endif"
                                   class="btn btn-xs btn-success success suspend{{ $row->id }}"

                                   data-toggle="tooltip" data-placement="top"
                                   title="" data-original-title="{{ __('trans.suspend') }}">
                                    <i class="fa fa-unlock"></i>
                                </a>


                                <!-- Modal -->
                                <div id="custom-modal{{ $row->id }}" class="modal-demo">
                                    <button type="button" class="close" onclick="Custombox.close();">
                                        <span>&times;</span><span class="sr-only">Close</span>
                                    </button>
                                    <h4 class="custom-modal-title">@lang('trans.suspend_user')</h4>
                                    <div class="custom-modal-text" style="text-align: right;">

                                        <form>
                                            <div class="form-group">
                                                <label>@lang('trans.suspend_reason')</label>
                                                <div>
                                                    <textarea class="form-control" id="reasonSuspend{{ $row->id }}"
                                                              required
                                                              rows="5"></textarea>
                                                    <p style="display: none;"
                                                       id="errorMessageRequired{{ $row->id }}">@lang('trans.required')</p>
                                                    <input type="hidden" id="isSuspendReason{{ $row->id }}"
                                                           value="1"/>
                                                </div>
                                            </div>

                                            <div class="form-group m-b-0">
                                                <div>
                                                    <button type="submit" data-url="{{ route('user.suspend') }}"
                                                            id="suspendElementReason" data-id="{{ $row->id }}"
                                                            data-type="0"
                                                            class="btn btn-info waves-effect waves-light">
                                                        @lang('trans.suspend')
                                                    </button>
                                                </div>
                                            </div>
                                        </form>


                                    </div>
                                </div>


                                <a href="javascript:;" data-id="{{ $row->id }}" data-type="1"
                                   data-url="{{ route('user.suspend') }}"
                                   style="@if($row->is_suspend == 0) display: none;  @endif"
                                   class="btn btn-xs btn-trans btn-danger danger suspendElement unsuspend{{ $row->id }}"
                                   id="suspendElement"
                                   data-message="{{ __('trans.unsuspend_in_allsystem') }}"
                                   data-toggle="tooltip" data-placement="top"
                                   title="" data-original-title="{{ __('trans.unsuspend') }}">
                                    <i class="fa fa-lock"></i>
                                </a>

                                <a href="javascript:;" id="elementRow{{ $row->id }}" data-id="{{ $row->id }}"
                                   data-url="{{ route('users.destroy', $row->id) }}"
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



