@extends('admin.layouts.master')

@section('title' ,  __('maincp.notification'))

@section('content')


    <!-- Page-Title -->
    <div class="row">
        <div class="col-sm-12">
            <div class="btn-group pull-right m-t-15">
                <button type="button" class="btn btn-custom  waves-effect waves-light"
                        onclick="window.history.back();return false;">@lang('maincp.back') <span class="m-l-5"><i
                                class="fa fa-reply"></i></span>
                </button>

            </div>
            <h4 class="page-title">@lang('maincp.notification')  </h4>
        </div>
    </div>



    <div class="row">
        <div class="col-lg-12">
            <div class="card-box">
                <div class="dropdown pull-right">

                </div>

                <h4 class="header-title m-t-0 m-b-30">@lang('maincp.view_notification') </h4>

                <table id="datatable-fixed-header-notify" class="table table-striped table-hover table-condensed"
                       style="width:100%">
                    <thead>
                    <tr>
                        <th>العنوان</th>
                        <th>المحتوي</th>
                        <th>الرابط</th>
                        <th>@lang('maincp.date_notify') </th>
                        <th>@lang('maincp.choose') </th>
                    </tr>
                    </thead>
                    <tbody>


                    @foreach($notifications as $row)
                        <tr>


                            <td>
                                {{ $row->title }}

                            </td>
                            <td>

                                {{ $row->body }}



                            </td>


                            <td>
                                @if($row->url != "")
                                    <a href="{{ $row->url }}">{{ $row->url }}</a>
                                @endif
                            </td>


                            <td>
                                {{ $row->created_at->format("d/m/Y H:i:s") }}
                            </td>
                            <td>

                                {{--@if($row->type == 10)--}}
                                {{--<a href="{{ route('bank.transfer.company') }}"--}}
                                {{--class=" btn btn-icon btn-trans btn-xs waves-effect waves-light btn-info m-b-5">--}}
                                {{--<i class="fa fa-eye"></i>--}}
                                {{--</a>--}}
                                {{----}}
                                {{--@elseif($row->type == 9)--}}
                                {{----}}
                                {{--<a href="{{ route('reports.dues.transporter') }}"--}}
                                {{--class=" btn btn-icon btn-trans btn-xs waves-effect waves-light btn-info m-b-5">--}}
                                {{--<i class="fa fa-eye"></i>--}}
                                {{--</a>--}}
                                {{----}}
                                {{----}}
                                {{----}}
                                {{--@elseif($row->type == 11)--}}
                                {{--<a href="{{ route('support.index') }}"--}}
                                {{--class=" btn btn-icon btn-trans btn-xs waves-effect waves-light btn-info m-b-5">--}}
                                {{--<i class="fa fa-eye"></i>--}}
                                {{--</a>--}}
                                {{----}}
                                {{--@endif--}}

                                <a href="javascript:;" id="elementRow{{ $row->id }}" data-id="{{ $row->id }}"
                                   data-url="{{ route('notify.delete', $row->id) }}"
                                   class="removeElement btn btn-icon btn-trans btn-xs waves-effect waves-light btn-danger m-b-5">
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

        $('.getSelected').on('click', function () {

            var sum = [];
            $('.checkboxes-items').each(function () {
                if ($(this).prop('checked') == true) {
                    sum.push($(this).val());
                }
            });
            if (sum.length > 0) {
                //var $tr = $(this).closest($('#elementRow' + id).parent().parent());
                swal({
                    title: "{{ __('maincp.make_sure') }}",
                    text: "{{ __('maincp.confirm_delete_message') }}",
                    type: "error",
                    showCancelButton: true,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "موافق",
                    cancelButtonText: "{{ __('maincp.disable')}}",
                    confirmButtonClass: 'btn-danger waves-effect waves-light',
                    closeOnConfirm: true,
                    closeOnCancel: true,
                }, function (isConfirm) {
                    if (isConfirm) {
                        $.ajax({
                            type: 'POST',
                            url: '{{ route('notifications.group.delete') }}',
                            data: {ids: sum},
                            dataType: 'json',
                            success: function (data) {
                                if (data) {
                                    var shortCutFunction = 'success';
                                    var msg = "__('institutioncp.deleted_successfully')";
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
                    title: "{{__('maincp.warning')}}",
                    text: "{{__('maincp.warning_select_one_element')}}  ",
                    type: "warning",
                    showCancelButton: false,
                    confirmButtonColor: "#DD6B55",
                    confirmButtonText: "{{__('maincp.accepted')}} ",
                    confirmButtonClass: 'btn-warning waves-effect waves-light',
                    closeOnConfirm: false,
                    closeOnCancel: false

                });
            }


        });


    </script>


    <script type="text/javascript">
        $(document).ready(function () {

            var table = $('#datatable-fixed-header-notify').DataTable({
                fixedHeader: true,
                "order": [[4, "desc"]],
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
        });


    </script>



@endsection



