@extends('admin-v2.layouts.master')
@section('title','Create Service Fee')
@section('body_class','admin-v2-service-fees create')

@section('content')
<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;">
    <div class="a2-title">Create Service Fee</div>
    <div class="a2-hint">إضافة رسوم منصة</div>
  </div>

  <form method="POST" action="{{ route('admin.service_fees.store') }}">
    @csrf
    @include('admin-v2.service-fees._form', ['submitLabel' => 'Create'])
  </form>
</div>
@endsection