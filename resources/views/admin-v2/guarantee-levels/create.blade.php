@extends('admin-v2.layouts.master')

@section('title', 'Create Guarantee Level')
@section('topbar_title', 'Create Guarantee Level')
@section('body_class', 'admin-v2-guarantee-levels')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إنشاء مستوى ضمان</h1>
            <div class="a2-page-subtitle">إضافة مستوى جديد للعميل أو صاحب العمل مع تحديد قدرة التغطية وشروط التأهيل.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.guarantee-levels.index') }}" class="a2-btn a2-btn-ghost">رجوع للمستويات</a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.guarantee-levels.store') }}">
        @csrf
        @include('admin-v2.guarantee-levels._form', ['level' => $level])
    </form>
</div>
@endsection
