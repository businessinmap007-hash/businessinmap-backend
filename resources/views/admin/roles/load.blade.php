<div id="load">


    <table id="tech-companies-1" class="table  table-striped">
        <thead>
        <tr>
            <th>
                <div class="checkbox checkbox-primary checkbox-single">
                    <input type="checkbox" name="check" onchange="checkSelect(this)"
                           value="option2"
                           aria-label="Single checkbox Two">
                    <label></label>
                </div>
            </th>
            {{--<th>الصورة</th>--}}
            <th>الاسم الظاهر</th>
            <th>الاسم</th>
            <th>تاريخ الإنشاء</th>
            <th>عدد المسجلين بالصلاحيات</th>
            <th>الخيارات</th>

        </tr>
        </thead>
        <tbody>

        @foreach($roles as $role)

            <tr>
                <th>
                    <div class="checkbox checkbox-primary checkbox-single">
                        <input type="checkbox" class="checkboxes-items"
                               value="{{ $role->id }}"
                               aria-label="Single checkbox Two">
                        <label></label>
                    </div>
                </th>
                {{--<td style="width: 10%;">--}}
                {{--<a data-fancybox="gallery"--}}
                {{--href="{{ $helper->getDefaultImage($user->image, request()->root().'/assets/admin/custom/images/default.png') }}">--}}
                {{--<img style="width: 50%; border-radius: 50%; height: 49px;"--}}
                {{--src="{{ $helper->getDefaultImage($user->image, request()->root().'/assets/admin/custom/images/default.png') }}"/>--}}
                {{--</a>--}}

                {{--</td>--}}

                <td>{{ $role->title }}</td>
                <td>{{ $role->name }}</td>
                <td>{{ $role->created_at }}</td>

                <td> {{ $role->users->count() }}</td>

                <td>
                    <a href="{{ route('roles.edit',$role->id) }}"
                       class="btn btn-icon btn-xs waves-effect btn-default m-b-5">
                        <i class="fa fa-edit"></i>
                    </a>

                    <a href="javascript:;" id="elementRow{{ $role->id }}" data-id="{{ $role->id }}"
                       class="removeElement btn btn-icon btn-trans btn-xs waves-effect waves-light btn-danger m-b-5">
                        <i class="fa fa-remove"></i>

                    </a>
                </td>
            </tr>

        @endforeach
        </tbody>
    </table>

</div>


{{ $roles->links() }}



@section('scripts')

    <script>
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
                        url: '{{ route('role.delete') }}',
                        data: {id: id},
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
                            url: '{{ route('roles.group.delete') }}',
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
                                        $(this).parent('tr').remove();
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
                                        $(this).parent('tr').remove();
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

    </script>


@endsection








{{--<div id="load">--}}


{{--<table class="table m-0">--}}
{{--<thead>--}}
{{--<tr>--}}
{{--<th>--}}
{{--<div class="checkbox checkbox-primary checkbox-single">--}}
{{--<input type="checkbox" class="checkboxes-items" onchange="checkSelect(this)" id="singleCheckbox2" value="option2"--}}
{{--aria-label="Single checkbox Two">--}}
{{--<label></label>--}}
{{--</div>--}}


{{--</th>--}}
{{--<th>صورة المنشأة</th>--}}
{{--<th>اسم المنشأة</th>--}}
{{--<th>نوع المنشأة</th>--}}
{{--<th>تاريخ الانشاء</th>--}}
{{--<th>الخيارات</th>--}}
{{--</tr>--}}
{{--</thead>--}}
{{--<tbody>--}}


{{--@foreach($roles as $role)--}}
{{--<tr>--}}
{{--<td>--}}

{{--<div class="checkbox checkbox-primary checkbox-single">--}}
{{--<input type="checkbox" class="checkboxes-items" id="singleCheckbox2" value="option2"--}}
{{--aria-label="Single checkbox Two">--}}
{{--<label></label>--}}
{{--</div>--}}


{{--</td>--}}

{{--<td style="width: 10%;">--}}


{{--<a data-fancybox="gallery"--}}
{{--href="{{ $helper->getDefaultImage($category->image, request()->root().'/assets/admin/custom/images/default.png') }}">--}}
{{--<img style="width: 50%; border-radius: 50%; height: 49px;"--}}
{{--src="{{ $helper->getDefaultImage($category->image, request()->root().'/assets/admin/custom/images/default.png') }}"/>--}}

{{--</a>--}}

{{--</td>--}}
{{--<td>{{ $role->name }}</td>--}}
{{--<td>{{ $role->name }}</td>--}}
{{--<td>{{ $role->name }}</td>--}}
{{--<td>--}}
{{--<button class="btn btn-icon btn-trans btn-sm waves-effect waves-light btn-danger m-b-5">--}}
{{--<i class="fa fa-remove"></i>--}}
{{--</button>--}}
{{--<button class="btn btn-icon btn-sm waves-effect btn-default m-b-5">--}}
{{--<i class="fa fa-edit"></i>--}}
{{--</button>--}}
{{--</td>--}}
{{--</tr>--}}
{{--@endforeach--}}


{{--</tbody>--}}
{{--</table>--}}

{{--@foreach($categories as $category)--}}


{{--<div style="display: block;">--}}


{{--<a data-fancybox="gallery"--}}
{{--href="{{ $helper->getDefaultImage($category->image, request()->root().'/assets/admin/custom/images/default.png') }}">--}}
{{--<img style="width: 20%;"--}}
{{--src="{{ $helper->getDefaultImage($category->image, request()->root().'/assets/admin/custom/images/default.png') }}"/>--}}

{{--</a>--}}


{{--{{ $category->name }}--}}

{{--{{ $category->created_at }}--}}

{{--{{ $category->updated_at }}--}}


{{--<button class="btn btn-icon btn-trans btn-sm waves-effect waves-light btn-danger m-b-5">--}}
{{--<i class="fa fa-remove"></i>--}}
{{--</button>--}}
{{--<button class="btn btn-icon btn-sm waves-effect btn-default m-b-5">--}}
{{--<i class="fa fa-edit"></i>--}}
{{--</button>--}}


{{--</div>--}}
{{--@endforeach--}}
{{--</div>--}}


{{--{{ $roles->links() }}--}}
