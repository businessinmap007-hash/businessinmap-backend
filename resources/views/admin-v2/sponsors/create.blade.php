@extends('admin-v2.layouts.master')

@section('title', __('إضافة إعلان'))
@section('body_class','admin-v2-sponsors')

@section('content')
@php
  $backUrl = route('admin.sponsors.index');
@endphp

<div class="a2-page">

  <div class="a2-card">

    <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <div>
        <h2 class="a2-title" style="margin:0;">{{ __('إضافة إعلان') }}</h2>
        <div class="a2-hint">{{ __('الصورة مطلوبة. يبدأ الإعلان متوقفًا حتى يتم تفعيله من شاشة التعديل.') }}</div>
      </div>

      <div class="a2-actionsbar" style="display:flex;gap:10px;justify-content:flex-start;align-items:center;">
        <a class="a2-btn a2-btn-ghost" href="{{ $backUrl }}">{{ __('رجوع') }}</a>
      </div>
    </div>

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-grid" style="display:grid;grid-template-columns: 1fr 360px;gap:16px;align-items:start;">

      {{-- Form --}}
      <div>
        <form method="post" action="{{ route('admin.sponsors.store') }}" enctype="multipart/form-data">
          @csrf

          <div class="spon-form">
            @include('admin-v2.sponsors._form', ['sponsor' => $sponsor])
          </div>

          <div class="a2-actionsbar" style="margin-top:14px;display:flex;gap:10px;">
            <button class="a2-btn a2-btn-primary" type="submit">{{ __('حفظ') }}</button>
            <a class="a2-btn a2-btn-ghost" href="{{ $backUrl }}">{{ __('إلغاء') }}</a>
          </div>
        </form>
      </div>

      {{-- There is nothing to preview yet: the record has no image, no id and
           no activation state until it is saved. --}}
      <aside>
        <div class="a2-card" style="padding:14px;">
          <div class="a2-hint" style="margin-bottom:10px;">{{ __('المعاينة') }}</div>
          <div class="a2-empty-cell">{{ __('ستظهر المعاينة بعد الحفظ.') }}</div>
        </div>
      </aside>
    </div>

  </div>
</div>

<style>
/* Responsive */
@media (max-width: 1100px){
  .a2-grid{grid-template-columns: 1fr !important;}
}

/* Same form alignment as the edit screen: label right, field left. */
.spon-form{max-width: 720px;}
.spon-form .spon-row{
  display:grid;
  grid-template-columns: 170px 1fr;
  gap:12px;
  align-items:center;
  margin-bottom:12px;
}
.spon-form .spon-label{
  font-weight:800;
  color:var(--a2-text,#101828);
  text-align:right;
  line-height:1.2;
}
.spon-form .spon-help{
  font-size:12px;
  color:var(--a2-muted,#667085);
  margin-top:4px;
  font-weight:600;
}

.spon-form input[type="text"],
.spon-form input[type="number"],
.spon-form input[type="datetime-local"],
.spon-form input[type="file"],
.spon-form select,
.spon-form textarea{
  width:100%;
  min-height:44px;
  padding:10px 12px;
  border:1px solid var(--a2-border-2,#e6e8ee);
  border-radius:14px;
  background:#fff;
  outline:none;
  box-sizing:border-box;
}

.spon-form input[type="file"]{padding:8px 10px}

.spon-form input[type="datetime-local"]{
  direction:ltr;
  text-align:left;
}

@media (max-width: 700px){
  .spon-form .spon-row{grid-template-columns: 1fr;}
  .spon-form .spon-label{text-align:right}
}
</style>

@endsection
