@extends('admin_v2.layouts.app')
@section('content')
<div class="a2-card" style="padding:14px;">
  <div class="a2-title" style="margin-bottom:10px;">Edit Business Service Price</div>
  <form method="POST" action="{{ route('admin.business-service-prices.update', $row) }}">
    @csrf @method('PUT')
    @include('admin_v2.business-service-prices._form', ['submitLabel' => 'Update'])
  </form>
</div>
@endsection