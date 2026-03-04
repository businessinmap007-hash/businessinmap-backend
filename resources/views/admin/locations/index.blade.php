@extends('admin.layouts.master')

@section('title', "إدارة الدول والمدن")

@section('content')

    <!-- Page-Title -->
    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <div class="btn-group pull-right m-t-15">
                <a href="{{ route('locations.create') }}" class="btn btn-custom  waves-effect waves-light">
                    <span class="m-l-5">
                        <i class="fa fa-plus"></i> <span>إضافة</span> </span>
                </a>
            </div>
            <h4 class="page-title">إدارة الدول والمدن</h4>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <div class="card-box">

                <div class="dropdown pull-right">

                </div>
                <h4 class="header-title m-t-0 m-b-30">@lang('trans.categories')</h4>
                <table class="table m-0  table-striped table-hover table-condensed" id="datatable-fixed-header">
                    <thead>
                    <tr>
                        <th>
                            م
                        </th>
                        <th>اسم الدولة باللغة العربية</th>
                        <th>اسم الدولة باللغة الانجليزية</th>
                        <th>القسم الرئيسي</th>
                        <th>@lang('trans.options')</th>

                    </tr>
                    </thead>
                    <tbody>


                    @foreach($locations as $key => $location)
                        <tr>
                            <td>
                                {{ $key + 1 }}

                            </td>

                            <td>{{ $location->{'name:ar'} ?? "--" }}</td>
                            <td>{{ $location->{'name:en'} ?? "--" }}</td>
                            <td>
                                @if(request('country') != "")
                                    {{  $location->parent ? $location->parent->name : "--" }}

                                @else
                                   <a href="?country={{ $location->id }}">( {{  $location->children->count() }} ) </a>  @lang('trans.an_city')

                                @endif
                            </td>
                            <td>
                                <a href="{{ route('locations.edit', $location->id) }}"
                                   class="btn btn-icon btn-xs waves-effect btn-default">
                                    <i class="fa fa-edit"></i>
                                </a>

                                <a href="javascript:;" id="elementRow{{ $location->id }}" data-id="{{ $location->id }}"
                                   data-url="{{ route('locations.destroy', $location->id) }}"
                                   class="removeElement btn btn-icon btn-trans btn-xs waves-effect waves-light btn-danger">
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

@endsection
@section('scripts')

    <script>

        $(document).ready(function () {

            var table = $('#datatable-fixed-header-categories').DataTable({
                fixedHeader: true,
                order: [[1, "asc"]],
                "lengthMenu": [15, 25, 50, 75, 100],
                columnDefs: [{orderable: false, targets: [0]}],
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


