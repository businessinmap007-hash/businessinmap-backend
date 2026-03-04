@extends('admin.layouts.master')

@section('title', "إعدادات القوائم")

@section('content')


    <!-- Page-Title -->
    <div class="row">
        <div class="col-sm-12">
            <div class="btn-group pull-right m-t-15 ">
                <a href="{{ route('menus.create') }}" type="button" class="btn btn-custom waves-effect waves-light"
                   aria-expanded="false">
                <span class="m-l-5">
                <i class="fa fa-plus"></i>
                </span>
                    إضافة رابط
                </a>
            </div>
            <h4 class="page-title">إعدادات القوائم</h4>
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

                <h4 class="header-title m-t-0 m-b-30">إعدادات القوائم</h4>

                <table id="datatable-fixed-header" class="table table-striped table-bordered dt-responsive nowrap"
                       cellspacing="0" width="100%">
                    <thead>
                    <tr>

                        @foreach (config('translatable.locales') as $locale => $value)
                            <th>عنوان القائمة باللغة - {{ $value }}</th>
                        @endforeach


                        <th>رابط الإعلان</th>
                        <th>مكان ظهور الإعلان</th>
                        <th> يفتح في صفحة جديدة؟</th>
                        <th>@lang('trans.status')</th>

                        {{--<th>@lang('trans.created_at')</th>--}}
                        <th>@lang('trans.options')</th>
                    </tr>
                    </thead>
                    <tbody>

                    @foreach($items as $row)
                        <tr>

                            @foreach (config('translatable.locales') as $locale => $value)
                                <td>{{ getTextForAnotherLang($row, 'name', $locale) }}</td>
                            @endforeach


                            <td style="direction: ltr; text-align: right;">
                                @if($row->url != "") <a href="{{ $row->url }}"
                                                        target="_blank"> {{ $row->url  }} </a> @endif
                            </td>

                            <td>
                                @if($row->type  == 0)
                                    روابط مهمة
                                @elseif($row->type == 1)
                                    مواقع ذات صلة
                                @else
                                    --
                                @endif
                            </td>

                            <td>
                                @if($row->new_window  == 0)
                                    نفس الصفحة
                                @elseif($row->new_window == 1)
                                    صفحة جديدة
                                @else
                                    --
                                @endif
                            </td>

                            <td>


                                <div class="StatusActive{{ $row->id }}"
                                     style="display: {{ $row->is_active == 0 ? "none" : "block" }}; text-align: center;">
                                    <img width="23px" src="{{ request()->root() }}/public/assets/admin/images/ok.png"
                                         alt="">
                                </div>

                                <div class="StatusNotActive{{ $row->id }}"
                                     style="display: {{ $row->is_active == 0 ? "block" : "none" }};  text-align: center;">
                                    <img width="23px" src="{{ request()->root() }}/public/assets/admin/images/false.png"
                                         alt="">
                                </div>


                            </td>

                            <td>


                                <a href="{{ route('menus.edit', $row->id) }}"
                                   data-toggle="tooltip" data-placement="top"
                                   data-original-title="تعديل"
                                   class="btn btn-icon btn-xs waves-effect  btn-info">
                                    <i class="fa fa-edit"></i>
                                </a>


                                <a href="javascript:;" data-id="{{ $row->id }}" data-type="0"
                                   data-url="{{ route('menu.suspend') }}"
                                   style="@if($row->is_active == 0) display: none;  @endif"
                                   class="btn btn-xs  btn-success success suspendElement suspend{{ $row->id }}"
                                   id="suspendElement" data-message="هل تريد حظر القائمة؟"
                                   data-toggle="tooltip" data-placement="top"
                                   title="" data-original-title="حظر">
                                    <i class="fa fa-unlock"></i>
                                </a>

                                <a href="javascript:;" data-id="{{ $row->id }}" data-type="1"
                                   data-url="{{ route('menu.suspend') }}"
                                   style="@if($row->is_active == 1) display: none;  @endif"
                                   class="btn btn-xs btn-trans btn-danger danger suspendElement unsuspend{{ $row->id }}"
                                   id="suspendElement"
                                   data-message="هل تريد تفعيل القائمة؟"
                                   data-toggle="tooltip" data-placement="top"
                                   title="" data-original-title="فك الحظر">
                                    <i class="fa fa-lock"></i>
                                </a>


                                <a href="javascript:;" id="elementRow{{ $row->id }}" data-id="{{ $row->id }}"
                                   data-url="{{ route('menus.destroy', $row->id) }}"
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



    <script>
        $.fn.dataTable.ext.errMode = 'none';
        $('#datatable-fixed-header').dataTable({
            "ordering": false
        });

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


        $(document).ready(function () {
            //$('#datatable').dataTable();
            //$('#datatable-keytable').DataTable( { keys: true } );
            $('#datatable-responsive').DataTable();

        });


    </script>


@endsection



