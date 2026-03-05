@extends('admin-v2.layouts.master')
@section('title','Edit Service Fee')
@section('body_class','admin-v2-service-fees edit')

@section('content')
<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;">
    <div class="a2-title">Edit Service Fee</div>
    <div class="a2-hint">#{{ $serviceFee->id }}</div>
  </div>

  <form method="POST" action="{{ route('admin.service_fees.update', $serviceFee) }}">
    @csrf @method('PUT')
    @include('admin-v2.service-fees._form', ['serviceFee' => $serviceFee, 'submitLabel' => 'Update'])
  </form>
</div>
@endsection