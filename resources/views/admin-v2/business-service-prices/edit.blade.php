@extends('admin-v2.layouts.master')

@section('title','Edit Business Service Price')
@section('topbar_title','Edit Business Service Price')

@section('content')
<form method="POST" action="{{ route('admin.business_service_prices.update', $row) }}">
    @csrf
    @method('PUT')
    @include('admin-v2.business-service-prices._form', ['submitLabel' => 'Update'])
</form>
@endsection