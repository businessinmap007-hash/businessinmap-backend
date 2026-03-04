@extends('admin.layouts.master')

@section('title', trans('global.settings'))

@section('content')

    <div id="messageError"></div>
    <form data-parsley-validate novalidate method="POST" action="{{ route('administrator.settings.store') }}"
          enctype="multipart/form-data">
    {{ csrf_field() }}

    <!-- Page-Title -->

        <!-- Page-Title -->
        <div class="row">
            <div class="col-sm-12">
                <div class="btn-group pull-right m-t-15">
                    <button type="button" class="btn btn-custom  waves-effect waves-light"
                            onclick="window.history.back();return false;">@lang('maincp.back')<span class="m-l-5"><i
                                    class="fa fa-reply"></i></span>
                    </button>
                </div>
                <h4 class="page-title">@lang('maincp.adjust_the_time_interval_for_pricing')</h4>
            </div>
        </div>


        <!-- start form -->
        <div class="card-box">
            <div class="text-center">
                {{--<h4 class="text-uppercase font-bold m-b-0">@lang('maincp.adjust_the_time_interval_for_pricing')</h4>--}}
            </div>
            <div class="panel-body">

                <div class="row">
                    <div class="col-lg-3 col-xs-12 m-b-20">
                        <label>@lang('maincp.time_for_pricing_customer_requests'):</label>
                        <input id="client_pricing_time" class="maxLingth" type="number"
                               value="{{ $setting->getBody('client_pricing_time') }}" name="client_pricing_time"
                               data-bts-min="15"
                               data-bts-max="999"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    <div class="col-lg-3 col-xs-12 m-b-20">
                        <label>@lang('maincp.time_of_retail_and_wholesale_order_pricing') :</label>
                        <input id="pricing_order_time" class="maxLingth" type="number"
                               value="{{ $setting->getBody('pricing_order_time') }}"
                               name="pricing_order_time" data-bts-min="60"
                               data-bts-max="999"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    <div class="col-lg-3 col-xs-12 m-b-20">
                        <label>@lang('maincp.time_for_pricing_maintenance_requests'):</label>
                        <input id="pricing_order_maintaince" class="maxLingth" type="number"
                               value="{{ $setting->getBody('pricing_order_maintaince') }}"
                               name="pricing_order_maintaince" data-bts-min="20"
                               data-bts-max="999"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    <div class="col-lg-3 col-xs-12 m-b-20">
                        <label>@lang('maincp.order_pricing_time_(100_items)'):</label>
                        <input id="pricing_orders_100_caregories" class="maxLingth" type="number"
                               value="{{ $setting->getBody('pricing_orders_100_caregories') }}"
                               name="pricing_orders_100_caregories" data-bts-min="60"
                               data-bts-max="999"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>

                    </div>

                    <div class="col-lg-3 col-xs-12 m-b-20">
                        <label>@lang('maincp.order_pricing_time_(more_than_100_items)'):</label>
                        <input id="pricing_orders_more_100_caregories" class="maxLingth" type="number"
                               value="{{ $setting->getBody('pricing_orders_more_100_caregories') }}"
                               name="pricing_orders_more_100_caregories"
                               data-bts-min="120" data-bts-max="999"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>

                    </div>
                    <div class="col-lg-3 col-xs-12 m-b-20">
                        <label>@lang('maincp.current_orders_is_priced') :</label>
                        <input id="maxlimit_current_pricing_orders" class="maxLingth" type="number"
                               value="{{ $setting->getBody('maxlimit_current_pricing_orders') }}"
                               name="maxlimit_current_pricing_orders" data-bts-min="0"
                               data-bts-max="1000"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    <div class="col-lg-3 col-xs-12 m-b-20">
                        <label>@lang('maincp.profit_rate_on_individual_applications') :</label>
                        <input id="percentage_profit_on_individual_orders" class="maxLingth" type="number"
                               value="{{ $setting->getBody('percentage_profit_on_individual_orders') }}"
                               name="percentage_profit_on_individual_orders"
                               data-bts-min="0" data-bts-max="1000"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    <div class="col-lg-3 col-xs-12 m-b-20">
                        <label>@lang('maincp.profit_ratio_on_retail_orders') :</label>
                        <input id="percentage_profit_on_retail_stores" class="maxLingth" type="number"
                               value="{{ $setting->getBody('percentage_profit_on_retail_stores') }}"
                               name="percentage_profit_on_retail_stores"
                               data-bts-min="35" data-bts-max="1000"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    <div class="col-lg-3 col-xs-12">
                        <label>@lang('maincp.profit_rate_on_wholesale_orders') :</label>
                        <input id="percentage_profit_on_wholesale_stores" class="maxLingth" type="number"
                               value="{{ $setting->getBody('percentage_profit_on_wholesale_stores') }}"
                               name="percentage_profit_on_wholesale_stores"
                               data-bts-min="30" data-bts-max="1000"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    <div class="col-lg-3 col-xs-12">
                        <label>@lang('maincp.profit_rate_on_corporate_applications'):</label>
                        <input id="percentage_profit_on_company_orders" class="maxLingth" type="number"
                               value="{{ $setting->getBody('percentage_profit_on_company_orders') }}"
                               name="percentage_profit_on_company_orders"
                               data-bts-min="30" data-bts-max="1000"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    <div class="col-lg-3 col-xs-12">
                        <label>@lang('maincp.percentage_of_profit_on_requests_of_maintenance_centers') :</label>
                        <input id="percentage_profit_on_maintain_center" class="maxLingth" type="number"
                               value="{{ $setting->getBody('percentage_profit_on_maintain_center') }}"
                               name="percentage_profit_on_maintain_center" data-bts-min="30" data-bts-max="1000"
                               data-bts-init-val="" data-bts-step="1" data-bts-decimal="0"
                               data-bts-step-interval="100" data-bts-force-step-divisibility="round"
                               data-bts-step-interval-delay="500" data-bts-prefix="" data-bts-postfix=""
                               data-bts-prefix-extra-class="" data-bts-postfix-extra-class=""
                               data-bts-booster="true" data-bts-boostat="10" data-bts-max-boosted-step="false"
                               data-bts-mousewheel="true" data-bts-button-down-class="btn btn-default"
                               data-bts-button-up-class="btn btn-default"/>
                    </div>

                    {{--<div class="col-lg-3 col-xs-12 m-t-0">--}}
                        {{--<label>تحديد سعر تركيب البطاريات :</label>--}}
                        {{--<div class="input-group">--}}
                                {{--<span class="input-group-addon" id="basic-addon2">--}}
                                    {{--<input type="checkbox" data-plugin="switchery" data-color="red"--}}
                                           {{--data-secondary-color="whitesmoke" data-size="small"/>--}}
                                {{--</span>--}}
                            {{--<input type="text" class="form-control" placeholder="$$" aria-label="Recipient's username"--}}
                                   {{--aria- describedby="basic-addon2">--}}
                        {{--</div>--}}
                    {{--</div>--}}
                    {{--<div class="col-lg-3 col-xs-12 m-t-20">--}}
                        {{--<label>تحديد سعر تركيب الكفرات :</label>--}}
                        {{--<div class="input-group">--}}
                                {{--<span class="input-group-addon" id="basic-addon2"><input type="checkbox"--}}
                                                                                         {{--data-plugin="switchery"--}}
                                                                                         {{--data-color="#ef5350"--}}
                                                                                         {{--data-secondary-color="whitesmoke"--}}
                                                                                         {{--data-size="small"/></span>--}}
                            {{--<input type="text" class="form-control" placeholder="$$"--}}
                                   {{--aria-label="Recipient's username" aria- describedby="basic-addon2">--}}
                        {{--</div>--}}

                    {{--</div>--}}
                    {{--<div class="col-lg-3 col-xs-12 m-t-20">--}}
                        {{--<label>وقت فتح و اغلاق التسعير للمراكز :</label>--}}
                        {{--<div class="input-group">--}}
                                {{--<span class="input-group-addon" id="basic-addon2"><input type="checkbox"--}}
                                                                                         {{--data-plugin="switchery"--}}
                                                                                         {{--data-color="#ef5350"--}}
                                                                                         {{--data-secondary-color="whitesmoke"--}}
                                                                                         {{--data-size="small"/></span>--}}
                            {{--<input type="text" class="form-control" placeholder="$$"--}}
                                   {{--aria-label="Recipient's username" aria- describedby="basic-addon2">--}}
                        {{--</div>--}}

                    {{--</div>--}}
                    {{--<div class="col-lg-3 col-xs-12 m-t-20">--}}
                        {{--<label>وقت فتح و اغلاق تسعير طلبات العملاء :</label>--}}
                        {{--<div class="input-group">--}}
                                {{--<span class="input-group-addon" id="basic-addon2"><input type="checkbox"--}}
                                                                                         {{--data-plugin="switchery"--}}
                                                                                         {{--data-color="#ef5350"--}}
                                                                                         {{--data-secondary-color="whitesmoke"--}}
                                                                                         {{--data-size="small"/></span>--}}
                            {{--<input type="text" class="form-control" placeholder="$$"--}}
                                   {{--aria-label="Recipient's username" aria- describedby="basic-addon2">--}}
                        {{--</div>--}}
                    {{--</div>--}}


                    {{--<div class="col-lg-3 col-xs-12 m-t-20">--}}
                        {{--<label>وقت فتح و اغلاق تسعير طلبات العملاء :</label>--}}
                        {{--<div class="form-inline">من :--}}
                            {{--<input type="time" class="form-control" required style="width: 88px"> الى :--}}
                            {{--<input type="time" class="form-control" required style="width: 88px">--}}
                        {{--</div>--}}
                    {{--</div>--}}

                    {{--<div class="col-lg-3 col-xs-12 m-t-20">--}}
                        {{--<label>وقت فتح و اغلاق تسعير مراكز الصيانة :</label>--}}
                        {{--<div class="form-inline">من :--}}
                            {{--<input type="time" class="form-control" required style="width: 88px"> الى :--}}
                            {{--<input type="time" class="form-control" required style="width: 88px">--}}
                        {{--</div>--}}
                    {{--</div>--}}

                    <div class="col-lg-12 col-xs-12 text-right" style="margin-top: 20px;">
                        <a href="index.html">
                            <button class="btn btn-warning confirm">@lang('maincp.save')</button>
                        </a>
                    </div>

                </div>
            </div>
        </div>
        <!-- end form -->
    </form>

@endsection


@section('scripts')

    <script src="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-touchspin/dist/jquery.bootstrap-touchspin.min.js"
            type="text/javascript"></script>
    <script src="{{ request()->root() }}/public/assets/admin/plugins/bootstrap-maxlength/bootstrap-maxlength.min.js"
            type="text/javascript"></script>



    <script type="text/javascript">


        //Bootstrap-TouchSpin
        $(".vertical-spin").TouchSpin({
            verticalbuttons: true,
            buttondown_class: "btn btn-primary",
            buttonup_class: "btn btn-primary",
            verticalupclass: 'ti-plus',
            verticaldownclass: 'ti-minus'
        });
        var vspinTrue = $(".vertical-spin").TouchSpin({
            verticalbuttons: true
        });
        if (vspinTrue) {
            $('.vertical-spin').prev('.bootstrap-touchspin-prefix').remove();
        }

        $("input[name='demo1']").TouchSpin({
            min: 0,
            max: 100,
            step: 0.1,
            decimals: 2,
            boostat: 5,
            maxboostedstep: 10,
            buttondown_class: "btn btn-primary",
            buttonup_class: "btn btn-primary",
            postfix: '%'
        });
        $("input[name='demo2']").TouchSpin({
            min: -1000000000,
            max: 1000000000,
            stepinterval: 50,
            buttondown_class: "btn btn-primary",
            buttonup_class: "btn btn-primary",
            maxboostedstep: 10000000,
            prefix: '$'
        });
        $("input[name='demo3']").TouchSpin({
            buttondown_class: "btn btn-primary",
            buttonup_class: "btn btn-primary"
        });
        $("input[name='demo3_21']").TouchSpin({
            initval: 40,
            buttondown_class: "btn btn-primary",
            buttonup_class: "btn btn-primary"
        });
        $("input[name='demo3_22']").TouchSpin({
            initval: 40,
            buttondown_class: "btn btn-primary",
            buttonup_class: "btn btn-primary"
        });

        $("input[name='demo5']").TouchSpin({
            prefix: "pre",
            postfix: "post",
            buttondown_class: "btn btn-primary",
            buttonup_class: "btn btn-primary"
        });
        $(".maxLingth").TouchSpin({
            buttondown_class: "btn btn-inverse",
            buttonup_class: "btn btn-inverse"
        });


        //Bootstrap-MaxLength
        $('input#defaultconfig').maxlength()

        $('input#thresholdconfig').maxlength({
            threshold: 20
        });

        $('input#moreoptions').maxlength({
            alwaysShow: true,
            warningClass: "label label-success",
            limitReachedClass: "label label-danger"
        });

        $('input#alloptions').maxlength({
            alwaysShow: true,
            warningClass: "label label-success",
            limitReachedClass: "label label-danger",
            separator: ' out of ',
            preText: 'You typed ',
            postText: ' chars available.',
            validate: true
        });

        $('textarea#textarea').maxlength({
            alwaysShow: true
        });

        $('input#placement').maxlength({
            alwaysShow: true,
            placement: 'top-left'
        });

        $('.success').click(function () {
            swal({
                title: "هل تريد الحفظ ؟",
                type: "error",
                showCancelButton: true,
                cancelButtonText: 'عودة',
                confirmButtonClass: 'btn-success waves-effect waves-light',
                confirmButtonText: 'تأكيد'
            });
        });

        $('form').on('submit', function (e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                type: 'POST',
                url: $(this).attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {

                    //  $('#messageError').html(data.message);

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




