@extends('admin-v2.layouts.master')
@section('title','Edit Service Fee')
@section('topbar_title','Edit Service Fee')

@section('content')
<form method="POST" action="{{ route('admin.service-fees.update', $row) }}">
    @csrf
    @method('PUT')
    @include('admin-v2.service-fees._form', ['submitLabel' => 'Update'])
</form>
@endsection