@extends('admin-v2.layouts.master')

@section('title','Edit Platform Service')
@section('topbar_title','Edit Platform Service')

@section('content')
<form method="POST" action="{{ route('admin.platform-services.update', $row) }}">
    @csrf
    @method('PUT')
    @include('admin-v2.platform-services._form', ['submitLabel' => 'Update'])
</form>
@endsection