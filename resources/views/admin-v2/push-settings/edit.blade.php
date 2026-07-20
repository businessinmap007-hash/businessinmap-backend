@extends('admin-v2.layouts.master')

@section('title','Push Settings')
@section('body_class','admin-v2-push-settings')

@section('content')
@php
    [$badgeText, $badgeKind] = match($firebase['source']) {
        'db'  => ['محفوظ في قاعدة البيانات', 'success'],
        'env' => ['من ملف env (افتراضي)', 'muted'],
        default => ['غير مضبوط', 'danger'],
    };
@endphp
<div class="a2-page">
  <div class="a2-card" style="max-width:820px">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('إعدادات الإشعارات — Firebase (FCM)') }}</h2>
        <div class="a2-hint">{{ __('الصق ملف الحساب الخدمي (Service Account JSON) من إعدادات مشروع Firebase. يُحفَظ مشفّرًا ويُستخدم في الإرسال التالي دون تعديل الكود أو إعادة النشر.') }}</div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-field" style="margin-bottom:14px">
      <span class="a2-label">
        {{ __('الحالة') }}
        <span class="a2-badge a2-badge-{{ $badgeKind }}" style="margin-inline-start:8px">{{ $badgeText }}</span>
      </span>
      @if($firebase['is_set'])
        <div class="a2-hint" dir="ltr" style="text-align:start">
          project_id: <strong>{{ $firebase['project_id'] ?? '—' }}</strong><br>
          client_email: <strong>{{ $firebase['client_email'] ?? '—' }}</strong>
        </div>
      @else
        <div class="a2-hint">{{ __('لم تُضبَط بيانات اعتماد بعد — الإشعارات لن تصل للأجهزة حتى تُحفَظ.') }}</div>
      @endif
    </div>

    <form method="post" action="{{ route('admin.push-settings.update') }}">
      @csrf
      @method('PUT')

      <div class="a2-field" style="margin-bottom:16px">
        <label class="a2-label" for="service_account_json">Service Account JSON</label>
        <textarea class="a2-input" id="service_account_json" name="service_account_json"
                  dir="ltr" rows="12" spellcheck="false" autocomplete="off"
                  placeholder="{{ $firebase['is_set'] ? '•••••••• (مضبوط — الصق ملفًا جديدًا للاستبدال، أو اتركه فارغًا للإبقاء عليه)' : '{\n  \"type\": \"service_account\",\n  \"project_id\": \"...\",\n  \"private_key\": \"...\",\n  \"client_email\": \"...\"\n}' }}"></textarea>
        <div class="a2-hint" dir="ltr" style="text-align:start">Firebase Console → Project settings → Service accounts → Generate new private key</div>
      </div>

      <div class="a2-actionsbar" style="margin-top:8px">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ بيانات الاعتماد') }}</button>
      </div>
    </form>

    <form method="post" action="{{ route('admin.push-settings.test') }}" style="margin-top:12px">
      @csrf
      <button class="a2-btn a2-btn-ghost" type="submit" @disabled(! $firebase['is_set'])>{{ __('اختبار الاتصال بـ Firebase') }}</button>
      <span class="a2-hint">{{ __('يتحقّق من صحّة الملف بمحاولة الحصول على رمز وصول من Google.') }}</span>
    </form>

    <div class="a2-alert a2-alert-muted" style="margin-top:20px">
      <strong>{{ __('ملاحظات:') }}</strong>
      <ul style="margin:8px 18px 0;line-height:1.9">
        <li>{{ __('الملف يحوي مفتاحًا خاصًّا — يُخزَّن مشفّرًا ولا يُعرَض مرّة أخرى بعد الحفظ.') }}</li>
        <li>{{ __('يستقبل التطبيق رموز الأجهزة عبر') }} <code dir="ltr">POST /api/v2/push-tokens</code>{{ __('؛ الإرسال يتمّ لأجهزة المستخدم النشطة فقط.') }}</li>
        <li>{{ __('يبقى') }} <code dir="ltr">FCM_SERVICE_ACCOUNT_JSON</code> {{ __('في ملف env يعمل كبديل احتياطي إن لم تُضبَط هنا.') }}</li>
      </ul>
    </div>
  </div>
</div>
@endsection
