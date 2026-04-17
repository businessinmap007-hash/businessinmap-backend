@extends('admin-v2.layouts.master')

@section('title','تعديل رسوم خدمة')
@section('body_class','admin-v2-service-fees-edit')

@section('content')
<div class="a2-page">

    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل رسوم الخدمة</h1>
            <div class="a2-page-subtitle">
                تعديل إعداد الرسوم الحالي
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.service-fees.index') }}" class="a2-btn a2-btn-ghost">
                رجوع
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.service-fees.update') }}">
        @csrf

        {{-- group key --}}
        <input type="hidden" name="business_id" value="{{ $groupKey['business_id'] }}">
        <input type="hidden" name="child_id" value="{{ $groupKey['child_id'] }}">
        <input type="hidden" name="service_id" value="{{ $groupKey['service_id'] }}">
        <input type="hidden" name="fee_code" value="{{ $groupKey['fee_code'] }}">

        @include('admin-v2.service-fees._form', [
            'submitLabel' => 'تحديث'
        ])

    </form>

</div>
@endsection