@extends('admin.layouts.master')
@section('title', __('trans.categoriesManagement'))

@section('content')

    <div id="messageError"></div>
    <form data-parsley-validate novalidate method="POST" action="{{ route('locations.update', $location->id) }}"
          enctype="multipart/form-data">
    {{ csrf_field() }}
    {{ method_field('PUT') }}
    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
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
            <div class="col-lg-8 col-lg-offset-2">
                <div class="card-box">


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName"> الاسم - {{ $value }} </label>

                                <input type="text" name="name_{{ $locale }}" value="{{ $location->{'name:'.$locale} }}"
                                       class="form-control" required
                                       placeholder="الاسم باللغة {{ $value }}"
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="الاسم باللغة{{ $value }} إلزامي"
                                       data-parsley-maxlength="55"
                                       data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                       data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                       data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                       data-parsley-minlength="3"
                                       data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                                />

                                @if($errors->has('name'))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('name') }}
                                    </p>
                                @endif


                            </div>
                        </div>
                    @endforeach



                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">  الرئيسي </label>

                            <select class="form-control" name="parentId">
                                <option value="0">رئيسي</option>
                                @foreach($locations as $cat)
                                    <option value="{{ $cat->id }}" {{ $cat->id == $location->parent_id ? "selected" : "" }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>

                            @if($errors->has('parentId'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('parentId') }}
                                </p>
                            @endif


                        </div>
                    </div>


                    <div class="col-xs-12">
                        <div class="form-group text-right m-b-0 ">
                            <button class="btn btn-primary waves-effect waves-light m-t-20" type="submit"> حفظ البيانات
                            </button>
                            {{--<button onclick="window.history.back();return false;"--}}
                            {{--class="btn btn-default waves-effect waves-light m-l-5 m-t-20"> إلغاء--}}
                            {{--</button>--}}

                            <a href="{{ route('categories.index') }}"
                               class="btn btn-default waves-effect waves-light m-l-5 m-t-20"> @lang('trans.cancel')
                            </a>
                        </div>
                    </div>
                    <div class="clearfix"></div>

                </div>
            </div><!-- end col -->
        </div>

    </form>


@endsection



@section('scripts')

    <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>
    <script type="text/javascript">


        $('#categoryTypeSel').on('change', function () {

            if($(this).val() == 1){
                $(".hiddenWhenSub").slideUp();
            }else{
                $(".hiddenWhenSub").slideDown();
            }
        });

        // $('form').on('submit', function (e) {
        //
        //     e.preventDefault();
        //
        //     var formData = new FormData(this);
        //
        //     var form = $(this);
        //
        //     form.parsley().validate();
        //
        //     if (form.parsley().isValid()) {
        //         // $('.loading').show();
        //
        //         $('#body-loader').loading({
        //             message: 'تحميل...',
        //             theme: 'dark'
        //         });
        //
        //         $.ajax({
        //             type: 'POST',
        //             url: $(this).attr('action'),
        //             data: formData,
        //             cache: false,
        //             contentType: false,
        //             processData: false,
        //             success: function (data) {
        //                 $('#body-loader').loading('stop');
        //                 // $('form').trigger("reset");
        //
        //                 var shortCutFunction = 'success';
        //                 var msg = data.message;
        //                 var title = 'نجاح';
        //                 toastr.options = {
        //                     positionClass: 'toast-top-left',
        //                     onclick: null
        //                 };
        //                 var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
        //                 $toastlast = $toast;
        //                 setTimeout(function () {
        //                     window.location.href = data.url;
        //                 }, 2000);
        //             },
        //             error: function (data) {
        //             }
        //         });
        //     } else {
        //         $('.loading').hide();
        //     }
        // });

    </script>
@endsection









