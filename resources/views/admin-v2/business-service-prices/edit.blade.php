@extends('admin-v2.layouts.master')
@section('title','Edit Business Service Price')
@section('body_class','admin-v2-business-service-prices edit')

@section('content')
<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;">
    <div class="a2-title">Edit Business Service Price</div>
    <div class="a2-hint">#{{ $row->id }}</div>
  </div>

  <form method="POST" action="{{ route('admin.business_service_prices.update', $row) }}">
    @csrf @method('PUT')
    @include('admin-v2.business-service-prices._form', ['row'=>$row, 'submitLabel' => 'Update'])
  </form>
</div>
@endsection