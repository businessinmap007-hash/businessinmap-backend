@extends('admin-v2.layouts.master')

@section('title','Dispute rules version')
@section('body_class','admin-v2-dispute-rules')

@section('content')
<div class="a2-page">
    <div class="a2-card" style="padding:14px;">
        <div class="a2-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div class="a2-title" style="font-size:16px;">{{ $version->title }}</div>
                <div class="a2-hint">
                    {{ __('النسخة') }} {{ $version->version }} —
                    {{ optional($version->published_at)->format('Y-m-d H:i') }}
                    @if($version->publishedBy) — {{ $version->publishedBy->name }} @endif
                </div>
            </div>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.dispute-rules.index') }}">{{ __('رجوع') }}</a>
        </div>

        @foreach($version->sections as $section)
            <div style="margin-top:16px;">
                <div style="font-weight:800;margin-bottom:6px;">{{ $section['title'] ?? '' }}</div>
                <ol style="padding-inline-start:20px;line-height:2;">
                    @foreach($section['clauses'] ?? [] as $clause)
                        <li>{{ $clause }}</li>
                    @endforeach
                </ol>
            </div>
        @endforeach

        <div class="a2-hint" style="margin-top:16px;">
            {{ __('هذه النسخة محفوظة كما نُشرت ولا تُعدَّل — من وافق عليها وافق على هذا النص بالضبط.') }}
        </div>
    </div>
</div>
@endsection
