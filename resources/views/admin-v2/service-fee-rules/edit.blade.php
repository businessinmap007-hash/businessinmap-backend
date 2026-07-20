@extends('admin-v2.layouts.master')

@section('title', __('تعديل قاعدة رسوم'))
@section('topbar_title', __('تعديل قاعدة رسوم'))
@section('body_class', 'admin-v2-service-fee-rules')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تعديل القاعدة #') }}{{ $rule->id }}</h1>
            <div class="a2-page-subtitle">{{ __('أي تعديل ينعكس على الرسوم المحسوبة للعمليات التالية فورًا.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.service-fee-rules.index') }}" class="a2-btn a2-btn-ghost">{{ __('رجوع') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.service-fee-rules.update', $rule->id) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.service-fee-rules._form')
    </form>
</div>
@endsection
