@extends('admin-v2.layouts.master')
@section('title','Create Platform Service')
@section('topbar_title','Create Platform Service')

@section('content')
<form method="POST" action="{{ route('admin.platform-services.store') }}">
    @csrf
    @include('admin-v2.platform-services._form', ['submitLabel' => 'Create'])
</form>
@endsection