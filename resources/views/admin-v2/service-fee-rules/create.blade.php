@extends('admin-v2.layouts.master')

@section('title', 'قاعدة رسوم جديدة')
@section('topbar_title', 'قاعدة رسوم جديدة')
@section('body_class', 'admin-v2-service-fee-rules')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">قاعدة رسوم جديدة</h1>
            <div class="a2-page-subtitle">تُطبَّق على الرسوم الأساسية بعد حسابها، قبل أي عرض خصم.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.service-fee-rules.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.service-fee-rules.store') }}">
        @csrf
        @include('admin-v2.service-fee-rules._form')
    </form>
</div>
@endsection
