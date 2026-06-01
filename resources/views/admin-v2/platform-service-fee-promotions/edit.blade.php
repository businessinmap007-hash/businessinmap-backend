@extends('admin-v2.layouts.master')

@section('title', 'تعديل عرض رسوم منصة')
@section('body_class', 'admin-v2 admin-v2-platform-service-fee-promotions')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل عرض رسوم منصة</h1>
            <div class="a2-page-subtitle">
                {{ $promotion->name }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.platform-service-fee-promotions.index') }}" class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.platform-service-fee-promotions.update', $promotion) }}" class="a2-stack">
        @csrf
        @method('PUT')

        @include('admin-v2.platform-service-fee-promotions._form')
    </form>
</div>
@endsection