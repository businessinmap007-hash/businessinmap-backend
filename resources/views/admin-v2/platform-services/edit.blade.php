@extends('admin_v2.layouts.app')
@section('content')
<div class="a2-card" style="padding:14px;">
  <div class="a2-title" style="margin-bottom:10px;">Edit Platform Service</div>
  <form method="POST" action="{{ route('admin.platform-services.update', $row) }}">
    @csrf @method('PUT')
    @include('admin_v2.platform-services._form', ['submitLabel' => 'Update'])
  </form>
</div>
@endsection