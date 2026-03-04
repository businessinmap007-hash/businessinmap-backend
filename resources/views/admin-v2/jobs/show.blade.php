@extends('admin-v2.layouts.master')

@section('title','Job')
@section('body_class','admin-v2-jobs')

@section('content')
@php
    $qsKeep = $qsKeep ?? request()->only(['q','expire','per_page','sort','dir']);
    $post   = $item ?? $post;
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <h2 class="a2-title">الوظائف</h2>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index', $qsKeep) }}">رجوع</a>
        <a class="a2-btn a2-btn-primary" href="{{ route('admin.jobs.edit', ['post'=>$post->id] + $qsKeep) }}">تعديل</a>
      </div>
    </div>

    @if(session('success'))
      <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
      <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div style="display:grid;grid-template-columns: 320px 1fr;gap:16px;align-items:start;">

      {{-- Meta (بنفس روح index: ID / Shares / Expire) --}}
      <div class="a2-card" style="padding:14px;">
        <div style="display:grid;gap:10px;">

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">ID</div>
            <div class="a2-fw-900">{{ $post->id }}</div>
          </div>

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">Shares</div>
            <div class="a2-fw-900">{{ (int)($post->share_count ?? 0) }}</div>
          </div>

          <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;">
            <div class="a2-hint" style="font-weight:900;">Expire</div>
            <div class="a2-fw-900" dir="ltr">
              {{ $post->expire_at ? \Carbon\Carbon::parse($post->expire_at)->format('Y-m-d') : '—' }}
            </div>
          </div>

        </div>
      </div>

      {{-- Content --}}
      <div class="a2-card" style="padding:14px;">
        <div style="display:grid;gap:12px;">

          <div>
            <label class="a2-hint" style="font-weight:900;">العنوان (AR)</label>
            <div class="a2-input">{{ $post->title_ar ?: '—' }}</div>
          </div>

          <div>
            <label class="a2-hint" style="font-weight:900;">العنوان (EN)</label>
            <div class="a2-input a2-text-left" dir="ltr">{{ $post->title_en ?: '—' }}</div>
          </div>

          <div>
            <label class="a2-hint" style="font-weight:900;">الوصف</label>
            <div
              class="a2-input"
              style="min-height:140px;white-space:pre-wrap;">{{ $post->body ?: '—' }}
            </div>
          </div>


          

        </div>
      </div>

    </div>

  </div>
</div>
@endsection
