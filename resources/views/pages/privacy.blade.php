@extends('layouts.master')

@section('content')


    <main class="main-content">
        <!--about us content-->
        <section class="aboutUs">
            <div class="container">
                <div class="main">
                    <h3 class="title">@lang('trans.terms_and_conditions')</h3>
                    <div class="aboutText">
                        {!! htmlspecialchars_decode($setting->getBody('privacy_'.app()->getLocale()))  !!}
                    </div>
                    {{--<h3 class="title">لماذا نحن</h3>--}}
                    {{--<div class="row">--}}
                        {{--<div class="col-md-4">--}}
                            {{--<div class="about">--}}
                                {{--<div class="h4"><i class="fas fa-camera-retro"></i>Cool Staff--}}
                                {{--</div>--}}
                                {{--<p>هناك حقيقة مثبتة منذ زمن طويل وهي أن المحتوى المقروء لصفحة ما سيلهي القارئ عن التركيز--}}
                                    {{--على الشكل الخارجي للنص أو شكل توضع الفقرات في الصفحة التي يقرأها. </p>--}}
                            {{--</div>--}}
                        {{--</div>--}}
                        {{--<div class="col-md-4">--}}
                            {{--<div class="about">--}}
                                {{--<div class="h4"><i class="far fa-clock"></i>24 Service--}}
                                {{--</div>--}}
                                {{--<p>هناك حقيقة مثبتة منذ زمن طويل وهي أن المحتوى المقروء لصفحة ما سيلهي القارئ عن التركيز--}}
                                    {{--على الشكل الخارجي للنص أو شكل توضع الفقرات في الصفحة التي يقرأها. </p>--}}
                            {{--</div>--}}
                        {{--</div>--}}
                        {{--<div class="col-md-4">--}}
                            {{--<div class="about">--}}
                                {{--<div class="h4"><i class="far fa-lightbulb"></i>Quality Work--}}
                                {{--</div>--}}
                                {{--<p>هناك حقيقة مثبتة منذ زمن طويل وهي أن المحتوى المقروء لصفحة ما سيلهي القارئ عن التركيز--}}
                                    {{--على الشكل الخارجي للنص أو شكل توضع الفقرات في الصفحة التي يقرأها. </p>--}}
                            {{--</div>--}}
                        {{--</div>--}}
                    {{--</div>--}}
                </div>
            </div>
        </section>
    </main>
@endsection
