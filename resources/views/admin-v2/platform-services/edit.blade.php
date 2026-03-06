@extends('admin-v2.layouts.master')
@section('title','Edit Platform Service')
@section('body_class','admin-v2-platform-services edit')

@section('content')
<div class="a2-card" style="padding:14px;">
  <div class="a2-title" style="margin-bottom:10px;">Edit Platform Service</div>
  <form method="POST" action="{{ route('admin.platform-services.update', $row) }}">
    @csrf @method('PUT')
    @include('admin-v2.platform-services._form', ['submitLabel' => 'Update'])
  </form>
</div>
@endsection