@extends('admin_v2.layouts.app')
@section('content')
<div class="a2-card" style="padding:14px;">
  <div class="a2-title" style="margin-bottom:10px;">Create Business Service Price</div>
  <form method="POST" action="{{ route('admin.business-service-prices.store') }}">
    @csrf
    @include('admin_v2.business-service-prices._form', ['submitLabel' => 'Create'])
  </form>
</div>
@endsection