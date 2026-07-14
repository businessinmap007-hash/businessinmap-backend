@extends('admin-v2.layouts.master')

@section('title','Payment Settings')
@section('body_class','admin-v2-payment-settings')

@section('content')
@php
    $labels = [
        'base_url'      => ['ar' => 'رابط Fawry الأساسي', 'hint' => 'https://atfawry.com (اختبار: https://atfawrystaging.com)', 'secret' => false],
        'merchant_code' => ['ar' => 'كود التاجر (Merchant Code)', 'hint' => 'من لوحة تاجر Fawry بعد التعاقد', 'secret' => false],
        'security_key'  => ['ar' => 'المفتاح الأمني (Security Key)', 'hint' => 'سرّي — يُخزَّن مشفّرًا. اتركه فارغًا للإبقاء على الحالي', 'secret' => true],
        'currency'      => ['ar' => 'العملة', 'hint' => 'EGP', 'secret' => false],
        'return_url'    => ['ar' => 'رابط العودة بعد الدفع', 'hint' => 'يوجَّه إليه العميل بعد إتمام الدفع (UX فقط)', 'secret' => false],
    ];
    $badge = fn($source) => match($source) {
        'db'  => ['محفوظ في قاعدة البيانات', 'success'],
        'env' => ['من ملف env (افتراضي)', 'muted'],
        default => ['غير مضبوط', 'danger'],
    };
@endphp
<div class="a2-page">
  <div class="a2-card" style="max-width:760px">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">إعدادات بوابة الدفع — Fawry</h2>
        <div class="a2-hint">الصق أكواد Fawry هنا بعد التعاقد. تُحفَظ فورًا وتُستخدم في الشحن التالي دون الحاجة لتعديل الكود أو إعادة النشر.</div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('admin.payment-settings.update') }}">
      @csrf
      @method('PUT')

      @foreach($fawry as $field => $meta)
        @php [$badgeText, $badgeKind] = $badge($meta['source']); @endphp
        <div class="a2-field" style="margin-bottom:18px">
          <label class="a2-label" for="f_{{ $field }}">
            {{ $labels[$field]['ar'] }}
            <span class="a2-badge a2-badge-{{ $badgeKind }}" style="margin-inline-start:8px">{{ $badgeText }}</span>
          </label>

          @if($meta['secret'])
            <input class="a2-input" id="f_{{ $field }}" type="password" name="{{ $field }}" autocomplete="new-password"
                   dir="ltr" placeholder="{{ $meta['is_set'] ? '•••••••• (مضبوط — اتركه فارغًا للإبقاء عليه)' : 'غير مضبوط' }}">
          @else
            <input class="a2-input" id="f_{{ $field }}" type="text" name="{{ $field }}"
                   dir="ltr" value="{{ old($field, $meta['value']) }}">
          @endif

          <div class="a2-hint" dir="ltr" style="text-align:start">{{ $labels[$field]['hint'] }}</div>
        </div>
      @endforeach

      <div class="a2-actionsbar" style="margin-top:8px">
        <button class="a2-btn a2-btn-primary" type="submit">حفظ الإعدادات</button>
      </div>
    </form>

    <div class="a2-alert a2-alert-muted" style="margin-top:20px">
      <strong>قبل التشغيل الحقيقي:</strong>
      <ul style="margin:8px 18px 0;line-height:1.9">
        <li>أكّد مع Fawry ترتيب حقول توقيع الإشعار (callback signature) لمنتج التاجر الخاص بك.</li>
        <li>اضبط رابط الإشعار server-to-server في لوحة Fawry على: <code dir="ltr">/api/v2/wallet/topup/callback</code></li>
      </ul>
    </div>
  </div>
</div>
@endsection
