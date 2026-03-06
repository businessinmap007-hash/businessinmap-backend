@extends('admin-v2.layouts.master')
@section('title','Create Platform Service') 
@section('body_class','admin-v2-platform-services create')

@section('content')
<div class="a2-card" style="padding:14px;">
  <div class="a2-title" style="margin-bottom:10px;">Create Platform Service</div>
  <form method="POST" action="{{ route('admin.platform-services.store') }}">
    @csrf
    @include('admin-v2.platform-services._form', ['submitLabel' => 'Create'])
  </form>
</div>
@endsection