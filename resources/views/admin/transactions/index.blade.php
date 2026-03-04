@extends('admin.layouts.master')

@section('title', "المعاملات المالية")

@section('content')


    <!-- Page-Title -->
    <div class="row">
        <div class="col-sm-12">
            <div class="btn-group pull-right m-t-15 ">

            </div>
            <h4 class="page-title">مشاهدة المعاملات المالية</h4>
        </div>
    </div>
    

    <div class="row statistics">
        <div class="col-lg-3 col-md-4">
            <a href="javascript:;">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">إجمالي المكافآت</h4>
                    <div class="widget-box-2">
                        <div class="widget-detail-2">
                                    <span class="pull-left">
                                        <i class="zmdi zmdi-accounts zmdi-hc-4x"></i>
                                    </span>
                            <h2 class="m-b-0">{{ $transactions->where('operation','award')->sum('price') }}</h2>
                            <p class="text-muted m-b-0">المكافآت</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-lg-2 col-md-4">
            <a href="javascript:;">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">إجمالي الإشتركات</h4>
                    <div class="widget-box-2">
                        <div class="widget-detail-2">
                                    <span class="pull-left">
                                        <i class="zmdi zmdi-accounts zmdi-hc-4x"></i>
                                    </span>
                            <h2 class="m-b-0">{{ $transactions->where('operation','subscription')->sum('price') }}</h2>
                            <p class="text-muted m-b-0">الإشتركات</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>


        <div class="col-lg-2 col-md-4">
            <a href="javascript:;">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">إجمالي الشحن</h4>
                    <div class="widget-box-2">
                        <div class="widget-detail-2">
                                    <span class="pull-left">
                                        <i class="zmdi zmdi-accounts zmdi-hc-4x"></i>
                                    </span>
                            <h2 class="m-b-0">{{ $transactions->where('operation','recharge')->sum('price') }}</h2>
                            <p class="text-muted m-b-0">الشحن</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>


        <div class="col-lg-2 col-md-4">
            <a href="javascript:;">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">إجمالي تحويل الرصيد</h4>
                    <div class="widget-box-2">
                        <div class="widget-detail-2">
                                    <span class="pull-left">
                                        <i class="zmdi zmdi-accounts zmdi-hc-4x"></i>
                                    </span>
                            <h2 class="m-b-0">{{ $transactions->where('operation','transfer')->sum('price') }}</h2>
                            <p class="text-muted m-b-0">تحويل الرصيد</p>
                        </div>
                    </div>
                </div>
            </a>
        </div>


        <div class="col-lg-3 col-md-4">
            <a href="javascript:;">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">إجمالي إشتراكات الإعلانات</h4>
                    <div class="widget-box-2">
                        <div class="widget-detail-2">
                                    <span class="pull-left">
                                        <i class="zmdi zmdi-accounts zmdi-hc-4x"></i>
                                    </span>
                            <h2 class="m-b-0">{{ $transactions->where('operation','advertisement')->sum('price') }}</h2>
                            <p class="text-muted m-b-0">إشتراكات الإعلانات</p>
                        </div>
                    </div>
                </div>
            </a>
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


                <table id="datatable-fixed-header" class="table table-striped table-bordered dt-responsive nowrap"
                       cellspacing="0" width="100%">
                    <thead>
                    <tr>

                        <th> #</th>
                        <th>المبلغ</th>
                        <th>نوع العملية</th>
                        <th>اسم العملية</th>
                        <th>الحساب</th>
                        <th>ملاحظات مسجلة عن طريق النظام</th>
                        <th> تاريخ الإنشاء</th>

                    </tr>
                    </thead>
                    <tbody>


                    @if($transactions->count() >0 )
                        @foreach($transactions as $key => $row)
                            <tr>


                                <td>
                                    {{ $row->id }}
                                </td>

                                <td>


                                    @if( $row->status  == "deposit")
                                        <label class="label label-success"> {{ number_format($row->price, 2) ?? "--" }}
                                            جنية
                                        </label>
                                    @else
                                        <label class="label label-danger">  {{ number_format($row->price, 2) ?? "--" }}
                                            جنية
                                        </label>
                                    @endif

                                </td>


                                <td>
                                    @if( $row->status  == "deposit")
                                        <label class="label label-info">
                                            إيداع
                                        </label>
                                    @else
                                        <label class="label label-warning">
                                            سحب
                                        </label>
                                    @endif

                                </td>

                                <td style="font-size: 14px;">
    @php $targetID = optional($row->target)->id ?? 0 @endphp

    @if($row->operation == 'recharge' && $row->target_id == null)
        شحن الحساب
    @elseif($row->operation == 'recharge' && $row->target_id != null)
        شحن الحساب عن طريق
        <a href="{{ route('business.show', $targetID) }}" class="btn btn-link">
            {{ optional($row->target)->name ?? '--' }}
        </a>

    @elseif($row->operation == 'transfer' && $row->status == 'deposit')
        تحويل رصيد من حساب
        <a href="{{ route('business.show', $targetID) }}" class="btn btn-link">
            {{ optional($row->target)->name ?? '--' }}
        </a>

    @elseif($row->operation == 'transfer' && $row->status == 'withdrawal')
        تحويل رصيد إلى حساب
        <a href="{{ route('business.show', $targetID) }}" class="btn btn-link">
            {{ optional($row->user)->name ?? 'غير موجود' }}
        </a>

    @elseif($row->operation == 'advertisement')
        دفع مصاريف إعلان

    @elseif($row->operation == 'award')
        مكافأة

    @elseif($row->operation == 'subscription' && $row->target_id == null)
        إعادة إشتراك

    @elseif($row->operation == 'subscription' && $row->target_id != null)
        إشتراك لحساب آخر
        <a href="{{ route('business.show', $targetID) }}" class="btn btn-link">
            {{ optional($row->target)->name ?? '--' }}
        </a>
    @endif
</td>

<td>
    <a href="{{ route('business.show', optional($row->user)->id) }}">
        {{ optional($row->user)->name ?? '--' }}
    </a>
</td>


                                <td>
                                    {{ $row->notes }}
                                </td>
                                <td>
                                    {{ $row->created_at->format('Y-m-d') }}
                                </td>
                            </tr>
                        @endforeach
                        @else
                        <tr>
                            <td colspan="7">
                                لا توجد نتائج
                            </td>
                        </tr>
                    @endif
                    </tbody>
                    @if(request('businessId'))
                        <tfoot>
                        <tr>
                            <th colspan="2">إجمالي الرصيد الحالي</th>
                            <th>{{ number_format($main->calculateUserBalance(\App\Models\User::whereId(request('businessId'))->first()), 2) }} جنية</th>
                            <th>إجمالي الداخل</th>
                            <th>{{ number_format($main->calculateUserBalanceType(\App\Models\User::whereId(request('businessId'))->first(), 'deposit'), 2) }} جنية</th>
                            <th>إجمالي السحب</th>
                            <th>{{ number_format($main->calculateUserBalanceType(\App\Models\User::whereId(request('businessId'))->first(), 'withdrawal'), 2) }}جنية
                            </th>
                        </tr>
                        </tfoot>
                    @endif
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



