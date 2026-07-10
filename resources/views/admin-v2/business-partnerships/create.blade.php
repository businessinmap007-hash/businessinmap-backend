@extends('admin-v2.layouts.master')

@section('title', 'Create Business Partnership')
@section('topbar_title', 'Create Business Partnership')
@section('body_class', 'admin-v2-business-partnerships')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إنشاء شراكة بزنس</h1>
            <div class="a2-page-subtitle">ربط مالك أصل مثل فندق مع شريك مثل شركة سياحة أو وكيل.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.business-partnerships.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.business-partnerships.store') }}">
        @csrf
        @include('admin-v2.business-partnerships._form', ['partnership' => $partnership])
    </form>
</div>
@endsection
