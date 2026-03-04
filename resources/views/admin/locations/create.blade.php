@extends('admin.layouts.master')

@section('title', __('trans.categoryAdd'))

@section('content')

    <form data-parsley-validate novalidate method="POST" action="{{ route('locations.store') }}"
          enctype="multipart/form-data">
    {{ csrf_field() }}
    <!-- Page-Title -->
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="btn-group pull-right m-t-15">


                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;"> رجوع <span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>


                </div>
                <h4 class="page-title">@lang('trans.categories')</h4>
            </div>
        </div>


        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <div class="card-box">
                    <h4 class="header-title m-t-0 m-b-30">@lang("trans.categoryAdd")</h4>


                    @foreach (config('translatable.locales') as $locale => $value)
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label for="userName"> الاسم باللغة - {{ $value }} </label>

                                <input type="text" name="name:{{ $locale }}" value="{{ old('name:'.$locale) }}"
                                       class="form-control" required
                                       placeholder=" الاسم باللغة - {{ $value }}"
                                       data-parsley-trigger="keyup"
                                       data-parsley-required-message="الاسم باللغة {{ $value }} إلزامي"
                                       data-parsley-maxlength="55"
                                       data-parsley-pattern="^[a-zA-Z0-9\u0621-\u064A\u0660-\u0669 ]+$"
                                       data-parsley-pattern-message="النظام لا يقبل العلامات الخاصة"
                                       data-parsley-maxlength-message=" أقصى عدد الحروف المسموح بها هى (55) حرف"
                                       data-parsley-minlength="3"
                                       data-parsley-minlength-message="اقل عدد حروف مسموح به هو 3 حروف"
                                />

                                @if($errors->has('name:ar'))
                                    <p class="help-block validationStyle">
                                        {{ $errors->first('name:ar') }}
                                    </p>
                                @endif


                            </div>
                        </div>
                    @endforeach
                    <div class="clearfix"></div>


                    <div class="col-xs-12">
                        <div class="form-group">
                            <label for="userName">الدولة</label>

                            <select class="form-control" name="parent_id">
                                <option value="0">الدولة</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>

                            @if($errors->has('parent_id'))
                                <p class="help-block validationStyle">
                                    {{ $errors->first('parent_id') }}
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
        <!-- end row -->
    </form>


@endsection



@section('scripts')

    <script type="text/javascript"
            src="{{ request()->root() }}/public/assets/admin/js/validate-{{ config('app.locale') }}.js"></script>
    <script type="text/javascript">


        $('#categoryTypeSel').on('change', function () {

            if ($(this).val() == 1) {
                $(".hiddenWhenSub").slideUp();
            } else {
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





