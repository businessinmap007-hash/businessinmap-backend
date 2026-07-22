@extends('admin-v2.layouts.master')

@section('title', __('متقدمو الوظيفة'))
@section('body_class','admin-v2-jobs-applicants')

@section('content')
@php
    $post = $item;
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('المتقدمون') }} <span class="a2-pill a2-pill-gray">{{ $applicants->total() }}</span></h1>
            <div class="a2-page-subtitle a2-clip" title="{{ $post->title }}">{{ $post->title ?: ('#'.$post->id) }}</div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.show', ['post'=>$post->id]) }}">{{ __('رجوع للوظيفة') }}</a>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.jobs.index') }}">{{ __('كل الوظائف') }}</a>
        </div>
    </div>

    <div class="a2-card">
        <div class="a2-hint" style="margin-bottom:12px;">
            {{ __('عرض فقط — للإدارة. المُعلن (صاحب العمل) وحده يرى هذه التفاصيل من التطبيق، والجمهور يرى عدد المتقدمين فقط.') }}
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:70px;">#</th>
                        <th>{{ __('الاسم') }}</th>
                        <th>{{ __('الهاتف') }}</th>
                        <th>{{ __('البريد') }}</th>
                        <th>{{ __('تاريخ التقديم') }}</th>
                        <th>{{ __('الحالة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applicants as $a)
                        <tr>
                            <td dir="ltr">{{ $a->id }}</td>
                            <td>{{ optional($a->user)->name ?: '—' }}</td>
                            <td dir="ltr">{{ optional($a->user)->phone ?: '—' }}</td>
                            <td dir="ltr">{{ optional($a->user)->email ?: '—' }}</td>
                            <td dir="ltr">{{ optional($a->created_at)->format('Y-m-d H:i') }}</td>
                            <td>
                                @if($a->approved_at)
                                    <span class="a2-pill a2-pill-active">{{ __('معتمد') }}</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">{{ __('قيد المراجعة') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="a2-empty-cell">{{ __('لا يوجد متقدمون بعد.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px;">
            {{ $applicants->links() }}
        </div>
    </div>
</div>
@endsection
