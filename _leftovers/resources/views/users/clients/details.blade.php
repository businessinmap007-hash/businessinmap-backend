@extends('layouts.master')


@section('content')





    <div class="hamla-header text-center text-white">
        <div class="overlay">
            <div class="w-50 m-auto header-details">
                <h4 class="text-center">{{ @$campaign->name }}</h4>
            </div>
        </div>
    </div>
    <!-- details  -->
    <div class="container pt-5 ">
        <div class="row">
            <div class="col-xl-8 mt-5">
                <p>
                    {{ $campaign->description }}
                </p>
                <div class="details">
                    <h6 class="home-title my-4">التفاصيل</h6>
                    <dl class="dl-horizontal m-b-0">

                        <dt>
                            المدينة
                        </dt>
                        <dd>
                            @if($campaign->city)
                                {{ @anotherLangWhenDefaultNotFound(@$campaign->city, 'name') }}
                            @else
                                -- @lang('trans.unknown')
                            @endif
                        </dd>
                        <dt>
                            الدولة
                        </dt>
                        <dd>
                            @if($campaign->city != "")
                                {{ @anotherLangWhenDefaultNotFound(@$campaign->city->country, 'name') }}
                            @else
                                -- @lang('trans.unknown')
                            @endif
                        </dd>
                        <dt>
                            قيمة الفرد
                        </dt>
                        <dd>
                            {{ $campaign->price_per_person }}
                        </dd>
                        <dt>
                            المقاعد المتاحة
                        </dt>
                        <dd>
                            {{ $campaign->seats_no }} @lang('trans.seat')
                        </dd>
                        <dt>
                            تقيم الحملة
                        </dt>
                        <dd>
                            {{ $campaign->rate }}

                        </dd>
                        <dt>
                            رقم التصريح
                        </dt>
                        <dd>
                            {{ $campaign->permit_no }}
                        </dd>
                        <dt>
                            موقع المكتب
                        </dt>
                        <dd>
                            {{ $campaign->address }}
                        </dd>
                        <dt>
                            رقم الهاتف
                        </dt>
                        <dd>
                            {{ $campaign->phone }}
                        </dd>


                    </dl>
                </div>
                <div class="details">
                    <h6 class="home-title my-4">متطلبات الحملة</h6>
                    <strong> {{ anotherLangWhenDefaultNotFound($campaign->campaignType, 'name') }}</strong>


                </div>
            </div>
            <div class="col-xl-4">
                <h6 class="home-title pb-4">صور الحملة</h6>
                <div>
                    <ul class="row m-0 hamla-pics mb-5">
                        @foreach($campaign->files as $file)
                            <li class="col-6 p-0">
                                <img src="{{ $file->url }}" class="img-fluid">
                            </li>
                        @endforeach

                    </ul>
                </div>
            </div>
            <div class="m-auto pb-5">
                <a href="hamla-details-form.html" class="btn default-bg text-white border-0 px-3 mt-5">تقديم في
                    الحملة</a>
            </div>
        </div>
    </div>



@endsection
