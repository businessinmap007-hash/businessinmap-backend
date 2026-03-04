@extends('admin.layouts.master')

@section('title', "المنشورات")

@section('content')


    <!-- Page-Title -->
    <div class="row">
        <div class="col-sm-12">
            <div class="btn-group pull-right m-t-15 ">

            </div>
            <h4 class="page-title">مشاهدة المنشورات</h4>
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

                        {{--                        @foreach (config('translatable.locales') as $locale => $value)--}}

                        <th>العنوان</th>
                        {{--                        @endforeach--}}
                        {{--                        @foreach (config('translatable.locales') as $locale => $value)--}}
                        <th>المحتوي</th>
                        {{--                        @endforeach--}}

                        <th>صور المنشور</th>
                        <th>صاحب المنشور</th>
                        <th>عدد المشاركات</th>
                        <th>الاعجاب/عدم الاعجاب</th>
                        <th>التعليقات</th>
                        <th> تاريخ الإنشاء</th>
                        <th> الخيارات</th>


                        {{--<th>@lang('trans.created_at')</th>--}}

                    </tr>
                    </thead>
                    <tbody>

                    @foreach($posts as $row)
                        <tr>


                            {{--                            @foreach (config('translatable.locales') as $locale => $value)--}}
                            <td>

                                {{ $row->title ?? "--" }}
                            </td>
                            {{--                            @endforeach--}}

                            {{--                            @foreach (config('translatable.locales') as $locale => $value)--}}
                            {{--                                <td>{{ getTextForAnotherLang($row, 'description', $locale) }}</td>--}}
                            {{--                            @endforeach--}}


                            <td>

                                {{ $row->description ?? "--" }}
                            </td>

                            <td>
                                @foreach($row->images as $image)
                                    <a data-fancybox="gallery"
                                       href="{{ $helper->getDefaultImage(request()->root().'/public/'. $image->image, request()->root().'/public/assets/admin/images/about_img.jpg') }}">
                                        <img style="width: 55px; border-radius: 50%; height: 55px;"
                                             src="{{ $helper->getDefaultImage(request()->root().'/public/'. $image->image, request()->root().'/public/assets/admin/images/about_img.jpg') }}"/>
                                    </a>
                                @endforeach
                            </td>


                            <td style="direction: ltr; text-align: right;">
                                <a href="{{ route('business.show', optional($row->user)->id ) }}">{{ optional($row->user)->name }}</a>
                            </td>

                            <td>
                                <label class="label label-inverse">
                                    {{ $row->share_count }}
                                </label>
                            </td>



                            <td>
                                <label class="label label-success" style="margin-left: 10px;">
                                    <i class="fa fa-thumbs-o-up"></i>
                                    {{ $row->likes->count() }}
                                </label>




                                <label class="label label-danger">
                                    <i class="fa fa-thumbs-o-down"></i>   {{ $row->dislikes->count() }}
                                </label>

                            </td>
                            <td>
                                <label class="label label-default">{{ $row->comments->count() }}</label>
                            </td>
                            <td>
                                {{ $row->created_at->format('Y-m-d') }}
                            </td>

                            <td>
                                <a href="{{ route('comments.index', $row->id) }}"
                                   data-toggle="tooltip" data-placement="top"
                                   data-original-title="view"
                                   class="btn btn-icon btn-xs waves-effect  btn-info">
                                    <i class="fa fa-eye"></i>
                                </a>
                            </td>


                            {{--                            <td>--}}


                            {{--                                <a href="{{ route('menus.edit', $row->id) }}"--}}
                            {{--                                   data-toggle="tooltip" data-placement="top"--}}
                            {{--                                   data-original-title="تعديل"--}}
                            {{--                                   class="btn btn-icon btn-xs waves-effect  btn-info">--}}
                            {{--                                    <i class="fa fa-edit"></i>--}}
                            {{--                                </a>--}}


                            {{--                                <a href="javascript:;" data-id="{{ $row->id }}" data-type="0"--}}
                            {{--                                   data-url="{{ route('menu.suspend') }}"--}}
                            {{--                                   style="@if($row->is_active == 0) display: none;  @endif"--}}
                            {{--                                   class="btn btn-xs  btn-success success suspendElement suspend{{ $row->id }}"--}}
                            {{--                                   id="suspendElement" data-message="هل تريد حظر القائمة؟"--}}
                            {{--                                   data-toggle="tooltip" data-placement="top"--}}
                            {{--                                   title="" data-original-title="حظر">--}}
                            {{--                                    <i class="fa fa-unlock"></i>--}}
                            {{--                                </a>--}}

                            {{--                                <a href="javascript:;" data-id="{{ $row->id }}" data-type="1"--}}
                            {{--                                   data-url="{{ route('menu.suspend') }}"--}}
                            {{--                                   style="@if($row->is_active == 1) display: none;  @endif"--}}
                            {{--                                   class="btn btn-xs btn-trans btn-danger danger suspendElement unsuspend{{ $row->id }}"--}}
                            {{--                                   id="suspendElement"--}}
                            {{--                                   data-message="هل تريد تفعيل القائمة؟"--}}
                            {{--                                   data-toggle="tooltip" data-placement="top"--}}
                            {{--                                   title="" data-original-title="فك الحظر">--}}
                            {{--                                    <i class="fa fa-lock"></i>--}}
                            {{--                                </a>--}}


                            {{--                                <a href="javascript:;" id="elementRow{{ $row->id }}" data-id="{{ $row->id }}"--}}
                            {{--                                   data-url="{{ route('menus.destroy', $row->id) }}"--}}
                            {{--                                   class="removeElement btn btn-icon btn-trans btn-xs waves-effect waves-light btn-danger">--}}
                            {{--                                    <i class="fa fa-remove"></i>--}}
                            {{--                                </a>--}}


                            {{--                            </td>--}}
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



