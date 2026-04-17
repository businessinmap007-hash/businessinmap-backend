@extends('admin-v2.layouts.master')

@section('title','إضافة رسوم خدمة')
@section('body_class','admin-v2-service-fees-create')

@section('content')
<div class="a2-page">

    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة رسوم خدمة</h1>
            <div class="a2-page-subtitle">إنشاء إعداد رسوم جديد (Business + Client)</div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.service-fees.index') }}" class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.service-fees.store') }}">
        @csrf

        @include('admin-v2.service-fees._form')

    </form>

</div>
@endsection