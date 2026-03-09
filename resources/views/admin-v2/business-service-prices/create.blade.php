@extends('admin-v2.layouts.master')

@section('title','Create Business Service Price')
@section('topbar_title','Create Business Service Price')

@section('content')
<form method="POST" action="{{ route('admin.business_service_prices.store') }}">
    @csrf
    @include('admin-v2.business-service-prices._form', ['submitLabel' => 'Create'])
</form>
@endsection