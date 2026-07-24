@extends('layouts.master')

@section('styles')
<style>
    .ab-wrap { max-width: 1000px; margin: 0 auto; padding: 8px 4px; }
    .ab-sec { margin-bottom: 26px; }
    .ab-sec h3 { border-bottom: 2px solid #eee; padding-bottom: 8px; margin-bottom: 12px; }
    .ab-sec p, .ab-sec li { line-height: 1.9; color: #444; }
    .ab-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
    .ab-card { border: 1px solid #eee; border-radius: 10px; padding: 12px 14px; background: #fff; }
    .ab-card .fn { font-weight: 700; margin-bottom: 5px; }
    .ab-card .fd { color: #555; font-size: 14px; line-height: 1.7; }
    .ab-steps { counter-reset: step; list-style: none; margin: 0; padding: 0; }
    .ab-steps li { position: relative; padding: 8px 40px 8px 0; }
    .ab-steps li::before { counter-increment: step; content: counter(step); position: absolute; right: 0; top: 8px;
        width: 26px; height: 26px; border-radius: 50%; background: #2a72e5; color: #fff; text-align: center; line-height: 26px; font-weight: 700; }
</style>
@endsection

@section('content')
<main class="main-content">
  <section class="aboutUs">
    <div class="container">
      <div class="main ab-wrap" dir="rtl">

        <div class="ab-sec">
          <h3 class="title">معلومات عن التطبيق</h3>
          @php $aboutBody = optional($setting)->getBody('about_app_desc_'.app()->getLocale()); @endphp
          @if(!empty($aboutBody))
            <div class="aboutText">{!! htmlspecialchars_decode($aboutBody) !!}</div>
          @else
            <p>هو منصّة أعمال تربط العملاء بالتجّار ومقدّمي الخدمات في مكان واحد: تتصفّح المنتجات والخدمات،
               وتطلب أو تحجز، وتدفع نقدًا أو عبر بوابة دفع آمنة، وتتابع طلبك حتى الاستلام، ثم تُقيّم تجربتك.
               ويقوم التطبيق على منظومة ثقة وتقييم تحمي الطرفين، مع نظام نزاعات وتحكيم عند الخلاف.</p>
            <p>التطبيق يخدم فئتين: <strong>العملاء</strong> الباحثين عن منتجات وخدمات موثوقة،
               و<strong>التجّار/الأعمال</strong> الراغبين في عرض خدماتهم وإدارة طلباتهم ومدفوعاتهم.</p>
          @endif
        </div>

        <div class="ab-sec">
          <h3>كيف تتم التعاملات</h3>
          <ol class="ab-steps">
            <li>تتصفّح القوائم والمنتجات والخدمات وتختار التاجر المناسب.</li>
            <li>تُنشئ طلبًا أو حجزًا، وتحدّد طريقة الاستلام (توصيل / استلام / داخل المحل).</li>
            <li>تدفع نقدًا عند الاستلام، أو أونلاين عبر بوابة الدفع — ويُوجَّه الدفع لحساب التاجر عند تفعيله.</li>
            <li>ينفّذ التاجر الطلب وتتابع مراحله حتى التسليم والتأكيد.</li>
            <li>تُقيّم التجربة؛ ويبني ذلك سمعة الطرفين. وعند الخلاف يُفتح نزاع قد يُحسم بالتحكيم.</li>
          </ol>
        </div>

        <div class="ab-sec">
          <h3>المحفظة والرسوم</h3>
          <p>المحفظة رصيد نقاط لأغراض الإيداع والتأمين والرسوم والتحويل بين المستخدمين — وليست حسابًا بنكيًّا.
             يمكنك شحنها بأموال حقيقية عبر بوابة الدفع. والاستخدام والشراء بلا رسوم على المعاملة؛
             ولا تُفرض رسوم الخدمة إلا عند اختيار الطرف فتح تقييمه، فيتحمّلها هو وحده.</p>
        </div>

        <div class="ab-sec">
          <h3>الثقة والأمان</h3>
          <p>حماية للحسابات بسياسة كلمة مرور قوية ورمز محفظة سرّي، وقائمة حظر للهويّات المخالفة،
             ونظام تقييم موضوعي مبني على نتائج العمليات، وضمانات وتأمين على العمليات،
             ونظام نزاعات وتحكيم وغرامات لردع الإساءة والاحتيال.</p>
        </div>

        <div class="ab-sec">
          <h3>خدمات ومميزات التطبيق</h3>
          @foreach((array) config('app_features', []) as $group)
            <h4 style="margin:14px 0 8px">{{ $group['title'] }}</h4>
            <div class="ab-grid">
              @foreach($group['items'] as $item)
                <div class="ab-card">
                  <div class="fn">{{ $item['name'] }}</div>
                  <div class="fd">{{ $item['desc'] }}</div>
                </div>
              @endforeach
            </div>
          @endforeach
        </div>

        <div class="ab-sec">
          <p>
            للاطّلاع على التفاصيل القانونية:
            <a href="{{ route('terms') }}">الشروط والأحكام</a>
            @if(\Illuminate\Support\Facades\Route::has('privacy'))
              · <a href="{{ route('privacy') }}">سياسة الخصوصية</a>
            @endif
          </p>
        </div>

      </div>
    </div>
  </section>
</main>
@endsection
