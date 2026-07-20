@extends('admin-v2.layouts.master')

@section('title', __('إضافة وظيفة'))
@section('body_class','admin-v2-jobs')

@section('content')
@php
    $qsKeep = $qsKeep ?? request()->only(['q','expire','per_page','sort','dir']);
    $post   = $item;
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('إضافة وظيفة') }}</h2>
        <div class="a2-hint">{{ __('الوظيفة تُنشر باسم البزنس المحدد في «صاحب الإعلان».') }}</div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index', $qsKeep) }}">{{ __('رجوع') }}</a>
      </div>
    </div>

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('admin.jobs.store') }}">
      @csrf

      {{-- Same two-column layout as the edit screen. --}}
      <div style="display:grid;grid-template-columns: 320px 1fr;gap:16px;align-items:start;">

        {{-- Left: meta --}}
        <div class="a2-card" style="padding:14px;">
          <div style="display:grid;gap:12px;">

            {{-- Only the create form needs this: edit never reassigns the owner,
                 but a job created with no user_id is an orphan with no business. --}}
            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('صاحب الإعلان (User ID)') }}</label>
              <input class="a2-input" type="number" min="1" name="user_id"
                     value="{{ old('user_id') }}" placeholder="{{ __('مثال: 184') }}">
              <div class="a2-hint" style="margin-top:6px;">
                {{ __('رقم حساب البزنس الذي ستُنشر الوظيفة باسمه.') }}
              </div>
            </div>

            {{-- validateData coerces a missing is_active to false, so without
                 this box every job created here would be silently inactive. --}}
            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('الحالة') }}</label>
              <label style="display:flex;align-items:center;gap:8px;margin-top:6px;">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                <span>{{ __('مفعّلة') }}</span>
              </label>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">Share Count</label>
              <input class="a2-input" type="number" min="0" name="share_count"
                     value="{{ old('share_count', 0) }}">
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('Expire At (نهاية الإعلان)') }}</label>
              <input class="a2-input" type="datetime-local" name="expire_at"
                     value="{{ old('expire_at') }}">
              <div class="a2-hint" style="margin-top:6px;">
                {{ __('اتركه فارغًا لو لا يوجد انتهاء.') }}
              </div>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('بداية التقديم/المقابلات') }}</label>
              <input class="a2-input" type="datetime-local" name="interview_starts_at"
                     value="{{ old('interview_starts_at') }}">
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('التصنيف الأب') }}</label>
              <select class="a2-input" name="category_id">
                <option value="">{{ __('— بدون —') }}</option>
                @foreach($categories as $c)
                  <option value="{{ $c->id }}" @selected(old('category_id') == $c->id)>{{ $c->name_ar ?: $c->name_en }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('التخصص الفرعي') }}</label>
              <select class="a2-input" name="category_child_id">
                <option value="">{{ __('— بدون —') }}</option>
                @foreach($categoryChildren as $c)
                  <option value="{{ $c->id }}" @selected(old('category_child_id') == $c->id)>{{ $c->name_ar ?: $c->name_en }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('المرتب') }}</label>
              <input class="a2-input" name="salary" value="{{ old('salary') }}" placeholder="{{ __('مثال: يحدد بعد المقابلة') }}">
            </div>

          </div>
        </div>

        {{-- Right: content --}}
        <div class="a2-card" style="padding:14px;">
          <div style="display:grid;gap:12px;">

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('العنوان') }}</label>
              <input class="a2-input" name="title" value="{{ old('title') }}" required>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('الوصف') }}</label>
              <textarea class="a2-input" name="body" rows="10"
                style="min-height:140px;white-space:pre-wrap;">{{ old('body') }}</textarea>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('الشروط المطلوبة') }}</label>
              <textarea class="a2-input" name="requirements" rows="6"
                style="min-height:100px;white-space:pre-wrap;">{{ old('requirements') }}</textarea>
            </div>

            <div class="a2-form-actions" style="margin-top:6px;">
              <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
              <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index', $qsKeep) }}">{{ __('إلغاء') }}</a>
            </div>

          </div>
        </div>

      </div>
    </form>

  </div>
</div>
@endsection
