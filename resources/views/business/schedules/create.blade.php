@extends('business.layouts.master')

@section('title', 'نشر خط تشغيل')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">نشر خط تشغيل</h1>
        <div class="a2-page-subtitle">حدّد الطريق واليوم والسعة، وسيظهر خطك لمن يبحث عن هذا المسار.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.schedules.index') }}" class="a2-btn a2-btn-ghost">رجوع</a>
    </div>
</div>

<form method="POST" action="{{ route('business.schedules.store') }}">
    @csrf
    @include('business.schedules._form')
</form>
@endsection
