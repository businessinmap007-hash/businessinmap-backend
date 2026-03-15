@extends('admin-v2.layouts.master')

@section('title','Bulk Bookable Operations')

@section('content')

<div class="a2-page-head">
    <h1 class="a2-page-title">Bulk Availability & Pricing</h1>
    <div class="a2-page-subtitle">
        تعديل جماعي للوحدات القابلة للحجز
    </div>
</div>

<div class="a2-card" style="padding:16px;margin-bottom:16px;">

<form method="GET">

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

<div>
<label class="a2-label">Business</label>
<select class="a2-input" name="business_id">
<option value="">All</option>

@foreach($businesses as $b)
<option value="{{ $b->id }}">
{{ $b->name }}
</option>
@endforeach

</select>
</div>

<div>
<label class="a2-label">Service</label>

<select class="a2-input" name="service_id">
<option value="">All</option>

@foreach($services as $s)
<option value="{{ $s->id }}">
{{ $s->name_en ?? $s->key }}
</option>
@endforeach

</select>

</div>

</div>

<button class="a2-btn a2-btn-primary" style="margin-top:12px">
Filter
</button>

</form>

</div>

<div class="a2-card" style="padding:16px;margin-bottom:16px;">

<form method="POST" action="{{ route('admin.bookable-items.bulk.block') }}">
@csrf

<h3>Bulk Block</h3>

<select class="a2-input" multiple name="bookable_ids[]">

@foreach($bookables as $b)

<option value="{{ $b->id }}">
{{ $b->title }}
</option>

@endforeach

</select>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">

<input type="date" name="starts_at" class="a2-input" required>

<input type="date" name="ends_at" class="a2-input" required>

</div>

<button class="a2-btn a2-btn-danger" style="margin-top:10px">
Block
</button>

</form>

</div>

<div class="a2-card" style="padding:16px">

<form method="POST" action="{{ route('admin.bookable-items.bulk.price') }}">
@csrf

<h3>Bulk Price Override</h3>

<select class="a2-input" multiple name="bookable_ids[]">

@foreach($bookables as $b)

<option value="{{ $b->id }}">
{{ $b->title }}
</option>

@endforeach

</select>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:10px;">

<input type="date" name="starts_at" class="a2-input">

<input type="date" name="ends_at" class="a2-input">

<input type="number" step="0.01" name="price" class="a2-input">

</div>

<button class="a2-btn a2-btn-primary" style="margin-top:10px">
Apply Price
</button>

</form>

</div>

@endsection
