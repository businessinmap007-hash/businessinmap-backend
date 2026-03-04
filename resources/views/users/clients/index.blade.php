@extends('layouts.master')


@section('content')



<div class="hamalat-header text-center text-white">
    <div class="overlay">
        <div class="w-50 m-auto header-details">
            <h4 class="text-center mb-4">@lang('trans.campaigns') {{ anotherLangWhenDefaultNotFound($campaignType, 'name') }}</h4>
            <p>{{ anotherLangWhenDefaultNotFound($campaignType, 'description') }}</p>
        </div>

    </div>
</div>
<!-- internal hajj  -->
<div class="container pt-5 internal-hajj">
    <div>
        <ul class="row pt-5 text-center">

            @foreach($campaignType->campaigns as $campaign)
            <li class="col-xl-3 col-md-4 col-sm-6 mb-4">
                <a href="{{ route('campaign.details', $campaign->id) }}">
                    <div class="card">

                        @if (count($campaign->files) > 0)
                        <img class="campaign-img m-0" src="{{ $campaign->files[0]->url}}" alt="صورة الحملة">
                        @endif

                        <div class="card-body" data-maxlength="60">
                            <h5 class="campaign-title text-dark">{{anotherLangWhenDefaultNotFound($campaign, 'name')}} </h5>
                            <h6 class="campaign-loc"> المنصورة , مصر </h6>
                            <p class="card-text">
                                {{ substr(anotherLangWhenDefaultNotFound($campaign, 'description'), 0, 30) }}
                            </p>
                        </div>
                    </div>
                </a>
            </li>
            @endforeach
        </ul>
    </div>
</div>

{{--<!-- advert -->--}}
{{--<div class="container-fluid advert">--}}
{{--<div class="row">--}}
{{--<div class="col-xl-12 p-0">--}}
{{--<img src="{{ request()->root() }}/public/assets/front/images/assest/BANNAR@2x.png" class="img-fluid">--}}
{{--</div>--}}

{{--</div>--}}
{{--</div>--}}
{{--<!-- elmozawden -->--}}
{{--<div class="container  internal-hajj">--}}
{{--<div>--}}
{{--<ul class="row pt-5 text-center">--}}
{{--<li class="col-xl-3 col-md-4 col-sm-6 mb-4">--}}
{{--<a href="hamla-details.html">--}}
{{--<div class="card">--}}
{{--<img class="campaign-img m-0" src="images/assest/el7mlat/1.png" alt="صورة الحملة">--}}
{{--<div class="card-body" data-maxlength="60">--}}
{{--<h5 class="campaign-title text-dark">اسم الحملة</h5>--}}
{{--<h6 class="campaign-loc"> المنصورة , مصر </h6>--}}
{{--<p class="card-text"> هذا النص هو مثال لنص يمكن أن يستبدل في نفس المساحةلى زيادة عدد--}}
{{--الحروف التى يولدها التطبيق.--}}
{{--</p>--}}
{{--</div>--}}
{{--</div>--}}
{{--</a>--}}
{{--</li>--}}
{{--<li class="col-xl-3 col-md-4 col-sm-6 mb-4">--}}
{{--<a href="hamla-details.html">--}}
{{--<div class="card">--}}
{{--<img class="campaign-img m-0" src="images/assest/el7mlat/2.png" alt="صورة الحملة">--}}
{{--<div class="card-body" data-maxlength="60">--}}
{{--<h5 class="campaign-title text-dark">اسم الحملة</h5>--}}
{{--<h6 class="campaign-loc"> المنصورة , مصر </h6>--}}
{{--<p class="card-text"> هذا النص هو مثال لنص يمكن أن يستبدل في نفس المساحةلى زيادة عدد--}}
{{--الحروف التى يولدها التطبيق.--}}
{{--</p>--}}
{{--</div>--}}
{{--</div>--}}
{{--</a>--}}
{{--</li>--}}
{{--<li class="col-xl-3 col-md-4 col-sm-6 mb-4">--}}
{{--<a href="hamla-details.html">--}}
{{--<div class="card">--}}
{{--<img class="campaign-img m-0" src="images/assest/el7mlat/3.png" alt="صورة الحملة">--}}
{{--<div class="card-body" data-maxlength="60">--}}
{{--<h5 class="campaign-title text-dark">اسم الحملة</h5>--}}
{{--<h6 class="campaign-loc"> المنصورة , مصر </h6>--}}
{{--<p class="card-text"> هذا النص هو مثال لنص يمكن أن يستبدل في نفس المساحةلى زيادة عدد--}}
{{--الحروف التى يولدها التطبيق.--}}
{{--</p>--}}
{{--</div>--}}
{{--</div>--}}
{{--</a>--}}
{{--</li>--}}
{{--<li class="col-xl-3 col-md-4 col-sm-6 mb-4">--}}
{{--<a href="hamla-details.html">--}}
{{--<div class="card">--}}
{{--<img class="campaign-img m-0" src="images/assest/el7mlat/4.png" alt="صورة الحملة">--}}
{{--<div class="card-body" data-maxlength="60">--}}
{{--<h5 class="campaign-title text-dark">اسم الحملة</h5>--}}
{{--<h6 class="campaign-loc"> المنصورة , مصر </h6>--}}
{{--<p class="card-text"> هذا النص هو مثال لنص يمكن أن يستبدل في نفس المساحةلى زيادة عدد--}}
{{--الحروف التى يولدها التطبيق.--}}
{{--</p>--}}
{{--</div>--}}
{{--</div>--}}
{{--</a>--}}
{{--</li>--}}

{{--</ul>--}}

{{--</div>--}}
{{--</div>--}}



@endsection