@extends('layouts.master')

@section('content')


    <main class="main-content">
        <!--about us content-->
        <section class="aboutUs">
            <div class="container">
                <div class="main">
                    <h3 class="title">سياسة الخصوصية</h3>
                    @php $privacyBody = optional($setting)->getBody('privacy_'.app()->getLocale()); @endphp
                    <div class="aboutText" dir="rtl" style="line-height:1.9">
                        @if(!empty($privacyBody))
                            {!! htmlspecialchars_decode($privacyBody) !!}
                        @else
                            {{-- Static fallback (basics) shown when no privacy body is configured. --}}
                            <p style="color:#777">آخر تحديث: {{ config('legal.privacy_version') }} — صياغة أساسية عامة يُنصح بمراجعتها قانونيًّا.</p>

                            <h4>البيانات التي نجمعها</h4>
                            <p>بيانات الحساب (الاسم، البريد، الهاتف)، وبيانات الاستخدام والطلبات والمعاملات، وبيانات الموقع عند تفعيلها لأغراض التوصيل، بالقدر اللازم لتشغيل الخدمة.</p>

                            <h4>كيف نستخدمها</h4>
                            <p>لإنشاء حسابك وتشغيل الخدمات، ومعالجة الطلبات والمدفوعات، وتفعيل منظومة الثقة والتقييم والنزاعات، وإرسال الإشعارات، وتحسين الخدمة وحمايتها من الاحتيال.</p>

                            <h4>مشاركة البيانات</h4>
                            <p>لا نبيع بياناتك. قد تُشارَك بالقدر اللازم مع التاجر المعني بطلبك، ومع مزوّدي خدمات موثوقين (كبوابة الدفع والإشعارات)، أو عند إلزام نظامي.</p>

                            <h4>الأمان والاحتفاظ</h4>
                            <p>نطبّق إجراءات حماية معقولة، ونحتفظ ببياناتك للمدة اللازمة لتشغيل الخدمة والوفاء بالالتزامات النظامية.</p>

                            <h4>حقوقك</h4>
                            <p>يمكنك تحديث بياناتك أو طلب حذف حسابك (مع مهلة سماح للاستعادة قبل الحذف النهائي).</p>

                            <h4>التواصل</h4>
                            <p>لأي استفسار عن الخصوصية، تواصل عبر قنوات الدعم داخل التطبيق.</p>

                            <p dir="ltr" style="margin-top:14px;color:#999">Privacy v{{ config('legal.privacy_version') }}</p>
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
