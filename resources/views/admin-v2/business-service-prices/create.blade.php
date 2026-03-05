@extends('admin-v2.layouts.master')
@section('title','Create Business Service Price')
@section('body_class','admin-v2-business-service-prices create')

@section('content')
<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;">
    <div class="a2-title">Create Business Service Price</div>
    <div class="a2-hint">إضافة/تحديد سعر خدمة لبزنس</div>
  </div>

  <form method="POST" action="{{ route('admin.business_service_prices.store') }}">
    @csrf
    @include('admin-v2.business-service-prices._form', ['submitLabel' => 'Create'])
  </form>
</div>
@endsection