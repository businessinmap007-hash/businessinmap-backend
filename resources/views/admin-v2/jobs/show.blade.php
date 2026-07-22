@extends('admin-v2.layouts.master')

@section('title', __('عرض وظيفة'))
@section('body_class','admin-v2-jobs-show')

@section('content')
@php
    $qsKeep = $qsKeep ?? request()->only(['q','expire','per_page','sort','dir']);
    $post   = $item ?? $post;

    $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('Y-m-d') : '—';
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('وظيفة #') }}{{ $post->id }}</h1>
            <div class="a2-page-subtitle a2-clip" title="{{ $post->title }}">{{ $post->title ?: '—' }}</div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index', $qsKeep) }}">{{ __('رجوع') }}</a>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.applicants', ['post'=>$post->id]) }}">{{ __('المتقدمون') }} ({{ $post->applies()->count() }})</a>
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.jobs.edit', ['post'=>$post->id] + $qsKeep) }}">{{ __('تعديل') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card">
        <div class="a2-album-show-grid">

            {{-- Details --}}
            <div class="a2-card a2-card-flat">
                <div class="a2-header"><h3 class="a2-section-title">{{ __('التفاصيل') }}</h3></div>

                <div class="a2-album-details-body">
                    <div>
                        <label class="a2-label">{{ __('العنوان') }}</label>
                        <div class="a2-view-box">{{ $post->title ?: '—' }}</div>
                    </div>

                    <div class="a2-mt-12">
                        <label class="a2-label">{{ __('الوصف') }}</label>
                        <div class="a2-view-box" style="min-height:140px;white-space:pre-wrap;">{{ $post->body ?: '—' }}</div>
                    </div>

                    <div class="a2-mt-12">
                        <label class="a2-label">{{ __('الشروط المطلوبة') }}</label>
                        <div class="a2-view-box" style="min-height:100px;white-space:pre-wrap;">{{ $post->requirements ?: '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- Meta --}}
            <div class="a2-card a2-card-flat">
                <div class="a2-header"><h3 class="a2-section-title">{{ __('البيانات') }}</h3></div>

                <div class="a2-album-details-body">
                    <div class="a2-kv">
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('المُعرّف') }}</div>
                            <div class="a2-kv-val" dir="ltr">{{ $post->id }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('المشاركات') }}</div>
                            <div class="a2-kv-val" dir="ltr">{{ (int) ($post->share_count ?? 0) }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('تاريخ الانتهاء') }}</div>
                            <div class="a2-kv-val" dir="ltr">{{ $fmt($post->expire_at) }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('بداية المقابلات') }}</div>
                            <div class="a2-kv-val" dir="ltr">{{ $fmt($post->interview_starts_at) }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('المرتب') }}</div>
                            <div class="a2-kv-val">{{ $post->salary ?: '—' }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('التصنيف') }}</div>
                            <div class="a2-kv-val">{{ optional($post->category)->name_ar ?: '—' }}</div>
                        </div>
                        <div class="a2-kv-row">
                            <div class="a2-kv-key">{{ __('التخصص') }}</div>
                            <div class="a2-kv-val">{{ optional($post->categoryChild)->name_ar ?: '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
