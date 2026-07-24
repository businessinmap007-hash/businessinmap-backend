@extends('layouts.master')

@section('content')


    <main class="main-content">
        <!--about us content-->
        <section class="aboutUs">
            <div class="container">
                <div class="main">
                    <h3 class="title">@lang('trans.terms_and_conditions')</h3>
                    @php $termsBody = optional($setting)->getBody('terms_website_'.app()->getLocale()); @endphp
                    <div class="aboutText" dir="rtl" style="line-height:1.9">
                        @if(!empty($termsBody))
                            {!! htmlspecialchars_decode($termsBody) !!}
                        @else
                            {{-- Static fallback when no terms body is configured in settings. --}}
                            <p>{{ __('باستخدامك هذا التطبيق وإنشائك حسابًا فيه فإنك توافق على الشروط التالية:') }}</p>
                            <ol style="margin:10px 20px">
                                <li>{{ __('تلتزم بالاستخدام المشروع للخدمات، ويُمنع الاحتيال أو الإساءة أو انتحال الهوية، تحت طائلة الإيقاف والغرامات.') }}</li>
                                <li>{{ __('المحفظة رصيد نقاط لأغراض الإيداع والتأمين والرسوم والتحويل بين المستخدمين، وليست حسابًا بنكيًّا.') }}</li>
                                <li>{{ __('قد تخضع معاملاتك لنظام الثقة والتقييم، وعند الخلاف لنظام النزاعات والتحكيم بقرار مُلزم.') }}</li>
                                <li>{{ __('الرسوم اختيارية ولا تُفرض إلا عند فتح الطرف لتقييمه؛ والدفع للتاجر قد يتم عبر بوابة دفع معتمدة.') }}</li>
                                <li>{{ __('تُعالَج بياناتك وفق سياسة الخصوصية وبالقدر اللازم لتشغيل الخدمة فقط.') }}</li>
                                <li>{{ __('قد تُحدَّث هذه الشروط، ويُسجَّل إصدار الشروط الذي وافقت عليه عند إنشاء حسابك.') }}</li>
                            </ol>
                            <p dir="ltr">Terms v{{ config('legal.terms_version') }}</p>
                        @endif
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
