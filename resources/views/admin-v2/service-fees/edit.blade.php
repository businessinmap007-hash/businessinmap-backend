@extends('admin-v2.layouts.master')

@section('title', 'Edit Service Fee')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تعديل إعداد الرسوم</h1>
        <div class="a2-page-subtitle">تحديث رسوم البزنس ورسوم العميل</div>
    </div>

    <div class="a2-page-actions" style="display:flex;gap:8px;">
        <a href="{{ route('admin.service-fees.show', $groupKey) }}" class="a2-btn">عرض</a>
        <a href="{{ route('admin.service-fees.index', $groupKey) }}" class="a2-btn">رجوع</a>
    </div>
</div>

<form method="POST" action="{{ route('admin.service-fees.update', $groupKey) }}">
    @csrf
    @method('PUT')

   @include('admin-v2.service-fees._form', [
    'businessFee' => $businessFee,
    'clientFee' => $clientFee,
    'groupKey' => $groupKey,
    'submitLabel' => 'حفظ التعديلات'
])
</form>
@endsection