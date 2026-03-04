@extends('admin.layouts.master')

@section("title", __('maincp.technical_support'))

@section('content')
    <form action="{{ route('administrator.settings.store') }}" data-parsley-validate="" novalidate="" method="post"
          enctype="multipart/form-data">
    {{ csrf_field() }}
    <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="btn-group pull-right m-t-0">
                    <div class="btn-group pull-right m-t-15">
                        <button type="button" class="btn btn-custom  waves-effect waves-light"
                                onclick="window.history.back();return false;"> @lang('maincp.back')<span class="m-l-5"><i
                                        class="fa fa-reply"></i></span>
                        </button>
                    </div>

                </div>
                <h4 class="page-title">@lang('maincp.technical_support') </h4>
            </div>
        </div>


        <div class="row">
            <div class="col-sm-12">
                <div class="card-box table-responsive" style="overflow: hidden;">
                    <div class="form-group">
                        <div class="col-xs-12 m-b-10">
                            <label>@lang('maincp.opening_statement'):</label>
                            <textarea class="form-control" name="support_welcome_screen"
                                      placeholder="@lang('maincp.opening_statement')">{{ $setting->getBody('support_welcome_screen') }}</textarea>
                        </div>
                        
                        <div class="col-lg-4 col-xs-12">
                            <label>@lang('web.whatsapp_number') :</label>
                            <input class="form-control" name="support_phone"
                                   value="{{ $setting->getBody('whatsapp1') }}" type="tel" placeholder="0123456789"
                                   maxlength="15" required>
                        </div>
                        
                         <div class="col-lg-4 col-xs-12">
                            <label>@lang('web.whatsapp_number') :</label>
                            <input class="form-control" name="support_phone"
                                   value="{{ $setting->getBody('whatsapp2') }}" type="tel" placeholder="0123456789"
                                   maxlength="15" required>
                        </div>
                    
                    
                    
                     <div class="col-lg-4 col-xs-12">
                            <label>@lang('web.whatsapp_number') :</label>
                            <input class="form-control" name="support_phone"
                                   value="{{ $setting->getBody('whatsapp3') }}" type="tel" placeholder="0123456789"
                                   maxlength="15" required>
                        </div>
                    
                    

                        <div class="col-lg-6 col-xs-12">
                            <label>@lang('maincp.mobile_number') :</label>
                            <input class="form-control" name="support_phone"
                                   value="{{ $setting->getBody('support_phone') }}" type="tel" placeholder="0123456789"
                                   maxlength="15" required>
                        </div>

                        <div class="col-lg-6 col-xs-12">
                            <label>@lang('maincp.email_technical_support')  :</label>
                            <input class="form-control" type="email" name="support_email"
                                   value="{{ $setting->getBody('support_email') }}" placeholder="Example@saned.sa"
                                   maxlength="75" required>
                        </div>

                        <div class="col-lg-6 col-xs-12 m-t-10">
                            <label>@lang('maincp.fixed_number')  :</label>
                            <input class="form-control" type="tel" name="support_static_number"
                                   value="{{ $setting->getBody('support_static_number') }}" placeholder="123456789"
                                   maxlength="15" required>
                        </div>
                    </div>
                    <div class="col-xs-12 text-right">

                            <button type="submit" class="btn btn-warning">
                               @lang('maincp.save_data')   <i style="display: none;" id="spinnerDiv" class="fa fa-spinner fa-spin"></i>
                            </button>

                    </div>
                </div>
            </div>
            <!-- end col -->
        </div>
        <!-- end row -->


        <!-- end row -->
    </form>
@endsection


@section('scripts')
    <script type="text/javascript">

        $('form').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            $('#spinnerDiv').show();

            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {

                    //  $('#messageError').html(data.message);
                    $('#spinnerDiv').hide();
                    var shortCutFunction = 'success';
                    var msg = data.message;
                    var title = 'نجاح';
                    toastr.options = {
                        positionClass: 'toast-top-left',
                        onclick: null
                    };
                    var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                    $toastlast = $toast;
                    {{--setTimeout(function () {--}}
                    {{--window.location.href = '{{ route('categories.index') }}';--}}
                    {{--}, 3000);--}}
                },
                error: function (data) {
                }
            });
        });

    </script>
@endsection







