@extends('admin-v2.layouts.master')

@section('title', 'Edit Business Service Price')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تعديل خدمة البزنس #{{ $row->id }}</h1>
        <div class="a2-page-subtitle">تعديل السعر وإعدادات الديبوزت</div>
    </div>
</div>

<form method="POST" action="{{ route('admin.business_service_prices.update', ['row' => $row->id]) }}">
    @csrf
    @method('PUT')

    @include('admin-v2.business-service-prices._form', [
        'isEdit' => true
    ])
</form>
@endsection