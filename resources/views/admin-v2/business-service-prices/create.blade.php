@extends('admin-v2.layouts.master')

@section('title', 'Create Business Service Price')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">إضافة خدمة للبزنس</h1>
        <div class="a2-page-subtitle">ربط البزنس بخدمة من المنصة وتحديد السعر والديبوزت</div>
    </div>
</div>

<form method="POST" action="{{ route('admin.business_service_prices.store') }}">
    @csrf

    @include('admin-v2.business-service-prices._form', [
        'isEdit' => false
    ])
</form>
@endsection