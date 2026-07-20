@extends('admin-v2.layouts.master')

@section('title','Edit Job')
@section('body_class','admin-v2-jobs')

@section('content')
@php
    $qsKeep = $qsKeep ?? request()->only(['q','expire','per_page','sort','dir']);
    $post   = $item ?? $post;

    $deleteUrl = route('admin.jobs.destroy', ['post' => $post->id] + $qsKeep);
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <h2 class="a2-title">{{ __('الوظائف') }}</h2>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index', $qsKeep) }}">{{ __('رجوع') }}</a>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.show', ['post'=>$post->id] + $qsKeep) }}">{{ __('عرض') }}</a>
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.applicants', ['post'=>$post->id]) }}">{{ __('المتقدمون') }}</a>

        <button type="button" class="a2-btn a2-btn-danger" id="btnOpenDeleteModal">
          {{ __('حذف') }}
        </button>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('admin.jobs.update', ['post'=>$post->id] + $qsKeep) }}">
      @csrf
      @method('PUT')

      {{-- ✅ نفس Layout show: عمودين --}}
      <div style="display:grid;grid-template-columns: 320px 1fr;gap:16px;align-items:start;">

        {{-- Left: Meta (لكن بInputs) --}}
        <div class="a2-card" style="padding:14px;">
          <div style="display:grid;gap:12px;">

            <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
              <div class="a2-hint" style="font-weight:900;">ID</div>
              <div class="a2-fw-900">{{ $post->id }}</div>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">Share Count</label>
              <input class="a2-input" type="number" min="0" name="share_count"
                     value="{{ old('share_count', (int)($post->share_count ?? 0)) }}">
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('Expire At (نهاية الإعلان)') }}</label>
              <input class="a2-input" type="datetime-local" name="expire_at"
                     value="{{ old('expire_at', optional($post->expire_at)->format('Y-m-d\TH:i')) }}">
              <div class="a2-hint" style="margin-top:6px;">
                {{ __('اتركه فارغًا لو لا يوجد انتهاء.') }}
              </div>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('بداية التقديم/المقابلات') }}</label>
              <input class="a2-input" type="datetime-local" name="interview_starts_at"
                     value="{{ old('interview_starts_at', optional($post->interview_starts_at)->format('Y-m-d\TH:i')) }}">
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('التصنيف الأب') }}</label>
              <select class="a2-input" name="category_id">
                <option value="">{{ __('— بدون —') }}</option>
                @foreach($categories as $c)
                  <option value="{{ $c->id }}" @selected(old('category_id', $post->category_id) == $c->id)>{{ $c->name_ar ?: $c->name_en }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('التخصص الفرعي') }}</label>
              <select class="a2-input" name="category_child_id">
                <option value="">{{ __('— بدون —') }}</option>
                @foreach($categoryChildren as $c)
                  <option value="{{ $c->id }}" @selected(old('category_child_id', $post->category_child_id) == $c->id)>{{ $c->name_ar ?: $c->name_en }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('المرتب') }}</label>
              <input class="a2-input" name="salary" value="{{ old('salary', $post->salary) }}" placeholder="{{ __('مثال: يحدد بعد المقابلة') }}">
            </div>

          </div>
        </div>

        {{-- Right: Content (Inputs) --}}
        <div class="a2-card" style="padding:14px;">
          <div style="display:grid;gap:12px;">

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('العنوان') }}</label>
              <input class="a2-input" name="title" value="{{ old('title', $post->title) }}">
            </div>

            {{-- ✅ عمود واحد فقط: body --}}
            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('الوصف') }}</label>
              <textarea class="a2-input" name="body" rows="10"
              style="min-height:140px;white-space:pre-wrap;">{{ old('body', $post->body) }}</textarea>
            </div>

            <div>
              <label class="a2-hint" style="font-weight:900;">{{ __('الشروط المطلوبة') }}</label>
              <textarea class="a2-input" name="requirements" rows="6"
                style="min-height:100px;white-space:pre-wrap;">{{ old('requirements', $post->requirements) }}</textarea>
            </div>

            <div class="a2-form-actions" style="margin-top:6px;">
              <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
              <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.show', ['post'=>$post->id] + $qsKeep) }}">{{ __('إلغاء') }}</a>
            </div>

          </div>
        </div>

      </div>
    </form>

  </div>
</div>

{{-- ✅ Delete Modal --}}
<div id="deleteModal" class="a2-modal" aria-hidden="true" style="display:none;">
  <div class="a2-modal-backdrop" data-close="1"></div>

  <div class="a2-modal-card" role="dialog" aria-modal="true" aria-labelledby="deleteTitle">
    <div class="a2-modal-head">
      <div id="deleteTitle" class="a2-modal-title">{{ __('تأكيد الحذف') }}</div>
      <button type="button" class="a2-modal-x" data-close="1" aria-label="Close">×</button>
    </div>

    <div class="a2-modal-body">
      <div style="margin-bottom:8px;">{{ __('سيتم حذف الوظيفة رقم') }} <b>#{{ $post->id }}</b>.</div>
      <div class="a2-hint">{{ __('لا يمكن التراجع بعد الحذف.') }}</div>
    </div>

    <div class="a2-modal-actions">
      <button type="button" class="a2-btn a2-btn-ghost" data-close="1">{{ __('إلغاء') }}</button>

      <form method="post" action="{{ $deleteUrl }}" style="margin:0;">
        @csrf
        @method('DELETE')
        <button type="submit" class="a2-btn a2-btn-danger" id="btnConfirmDelete">{{ __('حذف') }}</button>
      </form>
    </div>
  </div>
</div>

<style>
  .a2-modal{position:fixed;inset:0;z-index:9999}
  .a2-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
  .a2-modal-card{
    position:relative;
    width:min(520px, calc(100% - 24px));
    margin:80px auto 0;
    background:var(--a2-card,#fff);
    border:1px solid var(--a2-border,#eee);
    border-radius:18px;
    box-shadow:0 20px 60px rgba(0,0,0,.25);
    overflow:hidden;
  }
  .a2-modal-head{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 16px;border-bottom:1px solid var(--a2-border,#eee);
  }
  .a2-modal-title{font-weight:900}
  .a2-modal-x{
    width:36px;height:36px;border-radius:10px;
    border:1px solid var(--a2-border,#eee);
    background:transparent;cursor:pointer;font-size:20px;line-height:1;
  }
  .a2-modal-body{padding:14px 16px}
  .a2-modal-actions{
    display:flex;gap:10px;justify-content:flex-end;
    padding:14px 16px;border-top:1px solid var(--a2-border,#eee)
  }
</style>

<script>
(function(){
  const modal = document.getElementById('deleteModal');
  const btnOpen = document.getElementById('btnOpenDeleteModal');

  function openModal(){
    modal.style.display = 'block';
    modal.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
  }
  function closeModal(){
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  btnOpen?.addEventListener('click', openModal);

  modal?.addEventListener('click', function(e){
    if(e.target && e.target.getAttribute('data-close') === '1') closeModal();
  });

  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape' && modal.style.display === 'block') closeModal();
  });
})();
</script>
@endsection
