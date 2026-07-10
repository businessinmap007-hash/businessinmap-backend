@extends('admin-v2.layouts.master')

@section('title', 'Edit Business Partnership')
@section('topbar_title', 'Edit Business Partnership')
@section('body_class', 'admin-v2-business-partnerships')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل شراكة #{{ $partnership->id }}</h1>
            <div class="a2-page-subtitle">{{ $partnership->displayName() }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.business-partnerships.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.business-partnerships.update', $partnership->id) }}">
        @csrf
        @method('PUT')
        @include('admin-v2.business-partnerships._form', ['partnership' => $partnership])
    </form>
</div>
@endsection
