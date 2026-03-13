@extends('admin-v2.layouts.master')

@section('title','Create Bookable Item')
@section('body_class','admin-v2-bookable-items')

@section('content')
<form method="POST" action="{{ route('admin.bookable-items.store') }}">
    @csrf
    @include('admin-v2.bookable-items._form', ['submitLabel' => 'Create'])
</form>
@endsection