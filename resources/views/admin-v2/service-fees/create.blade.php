@extends('admin-v2.layouts.master')

@section('title', 'Create Service Fee')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">إضافة إعداد رسوم جديد</h1>
        <div class="a2-page-subtitle">إنشاء رسوم البزنس ورسوم العميل في نفس الإعداد</div>
    </div>

    <div class="a2-page-actions" style="display:flex;gap:8px;">
        <a href="{{ route('admin.service-fees.index', $groupKey) }}" class="a2-btn">رجوع</a>
    </div>
</div>

<form method="POST" action="{{ route('admin.service-fees.store', $groupKey) }}">
    @csrf

    @include('admin-v2.service-fees._form', [
    'businessFee' => $businessFee,
    'clientFee' => $clientFee,
    'groupKey' => null,
    'submitLabel' => 'حفظ الإعداد'
])
</form>
@endsection