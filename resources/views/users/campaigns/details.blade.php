@extends('layouts.master')

@section('styles')

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.css">

@endsection

@section('content')



    <div class="hamla-header text-center text-white">
        <div class="overlay">
            <div class="w-50 m-auto header-details">
                <h4 class="text-center">
                    {{ getTextForAnotherLang($campaign, 'name', app()->getLocale()) }}
                </h4>
            </div>
        </div>
    </div>
    <!-- details  -->
    <div class="container pt-5 ">
        <div class="row">
            <div class="col-xl-8 mt-5">

                <input type="hidden" value="{{ $campaign->id }}" id="targetId"/>
                <p>
                    {{ getTextForAnotherLang($campaign, 'description', app()->getLocale()) }}
                </p>

                <div id="rateYoReadOnly" disabled="true"></div>
                <div class="details">
                    <h6 class="home-title my-4">@lang('trans.details')</h6>
                    <dl class="dl-horizontal m-b-0">

                        @if(getTextForAnotherLang($campaign->campaignType, 'name', app()->getLocale()))
                            <dt>
                                @lang('trans.campaign_type')
                            </dt>
                            <dd>
                                {{ getTextForAnotherLang($campaign->campaignType, 'name', app()->getLocale()) }}
                            </dd>
                        @endif

                        @if(getTextForAnotherLang($campaign->city, 'name',app()->getLocale()))
                            <dt>
                                @lang('trans.city')
                            </dt>
                            <dd>
                                @if($campaign->city)
                                    {{ @getTextForAnotherLang($campaign->city, 'name',app()->getLocale()) }}
                                @else
                                    -- @lang('trans.unknown')
                                @endif
                            </dd>
                        @endif

                        @if(getTextForAnotherLang(@$campaign->city->country, 'name',app()->getLocale()))
                            <dt>
                                @lang('trans.country')
                            </dt>
                            <dd>
                                @if($campaign->city != "")
                                    {{ @getTextForAnotherLang(@$campaign->city->country, 'name',app()->getLocale()) }}
                                @else
                                    -- @lang('trans.unknown')
                                @endif
                            </dd>
                        @endif

                        @if($campaign->price_per_person != "")
                            <dt>
                                @lang('trans.price_per_person')
                            </dt>
                            <dd>
                                {{ $campaign->price_per_person }}
                            </dd>
                        @endif

                        @if($campaign->seats_no != "")
                            <dt>
                                @lang('trans.available_seats')
                            </dt>
                            <dd>
                                {{ $campaign->seats_no }} @lang('trans.seat')
                            </dd>
                        @endif

                        @if($campaign->rate != "")
                            <dt>
                                @lang('trans.campaign_rate')
                            </dt>
                            <dd>
                                {{ $campaign->rate }}

                            </dd>
                        @endif

                        @if($campaign->permit_no != "")
                            <dt>
                                @lang('trans.permit_no')
                            </dt>
                            <dd>
                                {{ $campaign->permit_no }}
                            </dd>
                        @endif

                        @if(getTextForAnotherLang($campaign, 'address', app()->getLocale()))
                            <dt>
                                @lang('trans.location')
                            </dt>
                            <dd>
                                {{ getTextForAnotherLang($campaign, 'address', app()->getLocale()) }}
                            </dd>
                        @endif

                        @if($campaign->phone != "")
                            <dt>
                                @lang('trans.phone')
                            </dt>
                            <dd>
                                {{ $campaign->phone }}
                            </dd>
                        @endif


                    </dl>
                </div>
                @if(getTextForAnotherLang($campaign, 'requirements', app()->getLocale()) != "")
                    <div class="details">
                        <h6 class="home-title my-4">@lang('trans.requirements')</h6>
                        <strong> {{ getTextForAnotherLang($campaign, 'requirements', app()->getLocale()) }}</strong>
                    </div>
                @endif


                @if(getTextForAnotherLang($campaign, 'features', app()->getLocale()) != "")
                    <div class="details">
                        <h6 class="home-title my-4">@lang('trans.features')</h6>
                        <strong> {{ getTextForAnotherLang($campaign, 'features', app()->getLocale()) }}</strong>
                    </div>
                @endif

            </div>


            <div class="col-xl-4">
                <h6 class="home-title pb-4">@lang('trans.images')</h6>
                <div>
                    <ul class="row m-0 hamla-pics mb-5">
                        @foreach($campaign->files as $file)
                            <li class="col-6 p-0">

                                <a data-fancybox="gallery"
                                   href="{{ $helper->getDefaultImage(config('constants.options.image_url').$file->url, request()->root().'/assets/admin/custom/images/default.png') }}">
                                    <img class="img-fluid"
                                         src="{{ $helper->getDefaultImage(config('constants.options.image_url').$file->url, request()->root().'/assets/admin/custom/images/default.png') }}"/>
                                </a>

                            </li>
                        @endforeach

                    </ul>
                </div>
            </div>


            <div class="col-xs-4">

                <div id="rateYo" disabled="true"></div>
            </div>
            <div class="m-auto pb-5 mt-5">
               &nbsp;
                {{--<a href="hamla-details-form.html" class="btn default-bg text-white border-0 px-3 mt-5">تقديم في--}}
                {{--الحملة</a>--}}
                <br/>
            </div>
        </div>
    </div>

    <input hidden value="{{ $campaign->userSumRating ?  $campaign->userSumRating  : 0}}" id="userSumRating"/>
    <input hidden value="{{ $campaign->averageRating ? $campaign->averageRating: 0  }}" id="averageRating"/>

@endsection



@section('scripts')

    <!-- Latest compiled and minified JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.js"></script>

    <script>
        $(function () {


            $("#rateYoReadOnly").rateYo({
                fullStar: true,
                rating: $('#averageRating').val(),
                readOnly: true,
            });

            $("#rateYo").rateYo({

                fullStar: true,
                rating: $('#userSumRating').val(),
                readOnly: '{{ $campaign->userSumRating ? true :false }}',
                onSet: function (rating, rateYoInstance) {

                    var targetId = $("#targetId").val();
                    $.ajax({
                        type: 'POST',
                        url: "{{ route('post.rate') }}",
                        data: {rating: rating, targetId: targetId},
                        dataType: "json",

                        success: function (data) {

                            if (data.status == 200) {
                                $("#rateYoReadOnly").rateYo("option", "rating", data.rating);
                                $("#rateYo").rateYo("option", "readOnly", true);
                                var shortCutFunction = 'success';
                                var msg = data.message;
                                var title = '';
                                toastr.options = {
                                    positionClass: 'toast-top-left',
                                    onclick: null
                                };
                                var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                                $toastlast = $toast;

                            } else {

                                var shortCutFunction = 'error';
                                var msg = data.message;
                                var title = '';
                                toastr.options = {
                                    positionClass: 'toast-top-left',
                                    onclick: null
                                };
                                var $toast = toastr[shortCutFunction](msg, title); // Wire up an event handler to a button in the toast, if it exists
                                $toastlast = $toast;
                            }


                        },
                        error: function (data) {
                        }
                    });
                }
            });
        });
    </script>
@endsection
