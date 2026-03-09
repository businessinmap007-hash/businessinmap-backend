@extends('admin-v2.layouts.master')

@section('title','Edit Bookable Item')
@section('body_class','admin-v2-bookable-items')

@section('content')
<form method="POST" action="{{ route('admin.bookable-items.update', $row) }}">
    @csrf
    @method('PUT')
    @include('admin-v2.bookable-items._form', ['submitLabel' => 'Update'])
</form>
@endsection