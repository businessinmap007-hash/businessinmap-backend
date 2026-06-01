@extends('admin-v2.layouts.master')

@section('title', 'إضافة عرض رسوم منصة')
@section('body_class', 'admin-v2 admin-v2-platform-service-fee-promotions')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة عرض رسوم منصة</h1>
            <div class="a2-page-subtitle">
                إنشاء عرض مؤقت لتعديل أو إيقاف رسوم المنصة بدون تغيير القيم الأصلية.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-fee-promotions.index') }}" class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.platform-service-fee-promotions.store') }}" class="a2-stack">
        @csrf

        @include('admin-v2.platform-service-fee-promotions._form')
    </form>
</div>
@endsection