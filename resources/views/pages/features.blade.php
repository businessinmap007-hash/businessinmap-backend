@extends('layouts.master')

@section('styles')
<style>
    .feat-wrap { max-width: 1000px; margin: 0 auto; padding: 24px 16px; }
    .feat-intro { margin-bottom: 28px; }
    .feat-group { margin-bottom: 26px; }
    .feat-group h3 { border-bottom: 2px solid #eee; padding-bottom: 8px; margin-bottom: 14px; }
    .feat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
    .feat-card { border: 1px solid #eee; border-radius: 10px; padding: 14px 16px; background: #fff; }
    .feat-card .fn { font-weight: 700; margin-bottom: 6px; }
    .feat-card .fd { color: #555; line-height: 1.7; font-size: 14px; }
    .legal-box { border: 1px solid #eee; border-radius: 10px; padding: 16px 18px; background: #fafafa; margin-top: 8px; line-height: 1.9; }
    .legal-box ul { margin: 8px 18px 0; }
</style>
@endsection

@section('content')
<main class="main-content">
  <section class="aboutUs">
    <div class="feat-wrap" dir="rtl">

      <div class="feat-intro">
        <h2 class="title">{{ __('خصائص وخدمات التطبيق') }}</h2>
        <p>{{ __('هذه نظرة على أهم الخدمات المتاحة في التطبيق. باستخدامك للتطبيق فإنك توافق على الشروط والأحكام وسياسة الخصوصية.') }}</p>
      </div>

      @foreach($groups as $group)
        <div class="feat-group">
          <h3>{{ $group['title'] }}</h3>
          <div class="feat-grid">
            @foreach($group['items'] as $item)
              <div class="feat-card">
                <div class="fn">{{ $item['name'] }}</div>
                <div class="fd">{{ $item['desc'] }}</div>
              </div>
            @endforeach
          </div>
        </div>
      @endforeach

      <div class="feat-group" id="terms">
        <h3>{{ __('الشروط والأحكام وسياسة الخصوصية') }}</h3>
        <div class="legal-box">
          <p>{{ __('باستخدامك التطبيق وإنشائك حسابًا فيه، فإنك تقرّ بموافقتك على ما يلي:') }}</p>
          <ul>
            <li>{{ __('الالتزام بالاستخدام المشروع للخدمات، وعدم الاحتيال أو الإساءة، تحت طائلة الإيقاف والغرامات.') }}</li>
            <li>{{ __('أن المحفظة رصيد نقاط لأغراض الإيداع والتأمين والرسوم والتحويل، وليست حسابًا بنكيًّا.') }}</li>
            <li>{{ __('أن المعاملات بين المستخدمين والتجّار قد تخضع لنظام الثقة والتقييم والنزاعات والتحكيم.') }}</li>
            <li>{{ __('معالجة بياناتك وفق سياسة الخصوصية، بما يلزم لتشغيل الخدمة فقط.') }}</li>
            <li>{{ __('أن الرسوم اختيارية ولا تُفرض إلا عند فتح الطرف للتقييم كما هو موضّح أعلاه.') }}</li>
          </ul>
          <p style="margin-top:12px">
            {{ __('للاطّلاع على النص الكامل:') }}
            <a href="{{ route('terms') }}">{{ __('الشروط والأحكام') }}</a>
            @if(\Illuminate\Support\Facades\Route::has('privacy'))
              · <a href="{{ route('privacy') }}">{{ __('سياسة الخصوصية') }}</a>
            @endif
          </p>
          <p class="fd" style="margin-top:8px" dir="ltr">
            Terms v{{ $termsVersion }} · Privacy v{{ $privacyVersion }}
          </p>
        </div>
      </div>

    </div>
  </section>
</main>
@endsection
