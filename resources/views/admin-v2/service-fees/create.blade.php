@extends('admin-v2.layouts.master')
@section('title','Create Service Fee')
@section('topbar_title','Create Service Fee')

@section('content')
<form method="POST" action="{{ route('admin.service-fees.store') }}">
    @csrf
    @include('admin-v2.service-fees._form', ['submitLabel' => 'Create'])
</form>
@endsection