@extends('admin.layouts.master')

@section('title', __('trans.categoriesManagement'))

@section('content')

    <!-- Page-Title -->
    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <div class="btn-group pull-right m-t-15">
                <a href="{{ route('categories.create') }}" class="btn btn-custom waves-effect waves-light">
                    <span class="m-l-5">
                        <i class="fa fa-plus"></i> <span>إضافة</span>
                    </span>
                </a>
            </div>
            <h4 class="page-title">@lang('trans.categoriesManagement')</h4>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-10 col-lg-offset-1">
            <div class="card-box">

                <h4 class="header-title m-t-0 m-b-30">@lang('trans.categories')</h4>

                <table class="table m-0 table-striped table-hover table-condensed" id="datatable-fixed-header">
                    <thead>
                        <tr>
                            <th>الصورة</th>
                            <th>اسم التصنيف باللغة العربية</th>
                            <th>اسم التصنيف باللغة الانجليزية</th>
                            <th>الترتيب</th>
                            <th>@lang('trans.options')</th>
                        </tr>
                    </thead>
                    <tbody>

                    @foreach($allCategories as $key => $category)
                        <tr>
                            <td>
                                @if($category->image != "")
                                    {{-- الحل الصحيح بدون كلمة public --}}
                                    <img style="width:55px;height:55px;border-radius:50%;object-fit:cover"
                                         src="{{ asset($category->image) }}">
                                @else
                                    <img style="width:55px;height:55px;border-radius:50%;object-fit:cover"
                                         src="{{ asset('assets/admin/images/avatarempty.png') }}">
                                @endif
                            </td>

                            <td>{{ $category->{'name:ar'} ?? "--" }}</td>
                            <td>{{ $category->{'name:en'} ?? "--" }}</td>
                            <td>{{ $category->reorder ?: "--" }}</td>

                            <td>
                                <a href="{{ route('categories.edit', $category->id) }}"
                                   class="btn btn-icon btn-xs waves-effect btn-default">
                                    <i class="fa fa-edit"></i>
                                </a>

                                <a href="javascript:;" id="elementRow{{ $category->id }}"
                                   data-id="{{ $category->id }}"
                                   data-url="{{ route('categories.destroy', $category->id) }}"
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

        var table = $('#datatable-fixed-header').DataTable({
            fixedHeader: true,
            order: [[1, "asc"]],
            lengthMenu: [15, 25, 50, 75, 100],
            columnDefs: [{ orderable: false, targets: [0] }],
            language: {
                lengthMenu: "@lang('maincp.show') _MENU_ @lang('maincp.perpage')",
                info: "@lang('maincp.show') @lang('maincp.perpage') _PAGE_ @lang('maincp.from') _PAGES_",
                infoEmpty: "@lang('maincp.no_recorded_data_available')",
                infoFiltered: "(@lang('maincp.filter_from_max_total') _MAX_)",
                paginate: {
                    first: "@lang('maincp.first')",
                    last: "@lang('maincp.last')",
                    next: "@lang('maincp.next')",
                    previous: "@lang('maincp.previous')"
                },
                search: "@lang('maincp.search'):",
                zeroRecords: "@lang('maincp.no_recorded_data_available')",
            },
        });

    });
</script>

@endsection
