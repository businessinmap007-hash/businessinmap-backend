@extends('admin-v2.layouts.master')

@section('title','Platform Settings')
@section('body_class','admin-v2-platform-settings')

@section('content')
<div class="a2-page">
  <div class="a2-card" style="max-width:760px">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('إعدادات المنصة') }}</h2>
        <div class="a2-hint">{{ __('المنطقة الزمنية التي تعمل بها المنصة كلها، وحالة السيرفر الذي تعمل عليه.') }}</div>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <h3 class="a2-title" style="font-size:16px;margin:8px 0 6px">{{ __('المنطقة الزمنية') }}</h3>
    <div class="a2-hint" style="margin-bottom:10px">
      {{ __('الآن') }}: <strong dir="ltr">{{ $platform['now'] }}</strong>
      — <span dir="ltr">{{ $platform['zone'] }} (UTC{{ $platform['offset'] }}{{ $platform['is_dst'] ? ', ' . __('توقيت صيفي') : '' }})</span>
      @unless($platform['from_file']) — {{ __('القيمة الافتراضية من ملف الإعدادات') }} @endunless
    </div>

    <form method="post" action="{{ route('admin.platform-settings.update') }}">
      @csrf
      @method('PUT')

      <div class="a2-field" style="margin-bottom:14px">
        <label class="a2-label" for="timezone">{{ __('منطقة المنصة') }}</label>
        <select class="a2-input" id="timezone" name="timezone" dir="ltr">
          <optgroup label="{{ __('الأكثر استخدامًا') }}">
            @foreach($platform['common'] as $z)
              <option value="{{ $z }}" @selected(old('timezone', $platform['zone']) === $z)>{{ $z }}</option>
            @endforeach
          </optgroup>
          <optgroup label="{{ __('كل المناطق') }}">
            @foreach($platform['all'] as $z)
              <option value="{{ $z }}" @selected(old('timezone', $platform['zone']) === $z)>{{ $z }}</option>
            @endforeach
          </optgroup>
        </select>
        <div class="a2-hint">{{ __('تُحسب بها مواعيد العمل والحجوزات والتذكيرات والمجدول. فترة التوقيت الصيفي تُحسب تلقائيًا.') }}</div>
      </div>

      <div class="a2-field" style="margin-bottom:14px">
        <label style="display:flex;gap:8px;align-items:center">
          <input type="checkbox" name="apply_to_businesses" value="1" @checked(old('apply_to_businesses', true))>
          <span>{{ __('طبّق المنطقة على كل الأنشطة التجارية أيضًا') }}</span>
        </label>
        <div class="a2-hint">{{ __('لكل نشاط منطقته الخاصة (تُعيَّن له تلقائيًا). إن تركتها بلا تحديد بقيت الأنشطة الحالية على منطقتها القديمة.') }}</div>
      </div>

      <div class="a2-alert a2-alert-danger" style="margin-bottom:14px">
        {{ __('تغيير المنطقة لا يحوّل المواعيد المخزّنة: تبقى بأرقامها، وتُقرأ بالمنطقة الجديدة. غيّرها عند نقل المنصة إلى بلد آخر فقط.') }}
      </div>

      <div class="a2-actionsbar">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
      </div>
    </form>

    <h3 class="a2-title" style="font-size:16px;margin:22px 0 6px">{{ __('حالة السيرفر') }}</h3>
    <div class="a2-tablewrap">
      <table class="a2-table">
        <tbody>
          @foreach($platform['checks'] as $row)
            <tr>
              <td style="white-space:nowrap">{{ $row['label'] }}</td>
              <td dir="ltr" style="text-align:start">
                {{ $row['value'] }}
                @if($row['hint'])<div class="a2-hint" dir="auto">{{ $row['hint'] }}</div>@endif
              </td>
              <td style="white-space:nowrap">
                @php
                  $badge = ['ok' => ['a2-badge-success', __('سليم')], 'bad' => ['a2-badge-danger', __('يحتاج إصلاحًا')],
                            'warn' => ['a2-badge-muted', __('تنبيه')], 'info' => ['a2-badge-muted', __('معلومة')]][$row['state']];
                @endphp
                <span class="a2-badge {{ $badge[0] }}">{{ $badge[1] }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
