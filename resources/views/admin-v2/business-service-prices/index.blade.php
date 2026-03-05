@extends('admin-v2.layouts.master')
@section('title','Business Service Prices')
@section('body_class','admin-v2-business-service-prices index')

@section('content')
<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <div class="a2-title">Business Service Prices</div>
      <div class="a2-hint">تحديد سعر كل خدمة لكل بزنس (Admin يتحكم)</div>
    </div>
    <a class="a2-btn a2-btn-primary" href="{{ route('admin.business_service_prices.create') }}">+ إضافة</a>
  </div>

  <div class="a2-card" style="padding:14px;margin-bottom:12px;">
    <form method="GET" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end;">
      <div>
        <div class="a2-hint">Business</div>
        <select class="a2-input" name="business_id">
          <option value="">All</option>
          @foreach($businesses as $b)
            <option value="{{ $b->id }}" @selected((string)request('business_id')===(string)$b->id)>
              #{{ $b->id }} — {{ $b->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="a2-hint">Service</div>
        <select class="a2-input" name="service_id">
          <option value="">All</option>
          @foreach($services as $s)
            <option value="{{ $s->id }}" @selected((string)request('service_id')===(string)$s->id)>
              #{{ $s->id }} — {{ $s->name_ar ?? $s->name_en }}
            </option>
          @endforeach
        </select>
      </div>
      <div>
        <div class="a2-hint">Active</div>
        <select class="a2-input" name="is_active">
          <option value="">All</option>
          <option value="1" @selected(request('is_active')==='1')>Yes</option>
          <option value="0" @selected(request('is_active')==='0')>No</option>
        </select>
      </div>
      <div>
        <button class="a2-btn a2-btn-ghost" type="submit">بحث</button>
      </div>
    </form>
  </div>

  <div class="a2-card" style="padding:0;overflow:auto;">
    <table class="a2-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Business</th>
          <th>Service</th>
          <th>Price</th>
          <th>Active</th>
          <th style="width:210px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td>#{{ $r->id }}</td>
            <td>#{{ $r->business_id }} — {{ $r->business->name ?? '' }}</td>
            <td>#{{ $r->service_id }} — {{ $r->service->name_ar ?? $r->service->name_en ?? '' }}</td>
            <td style="font-weight:800;">{{ number_format((float)$r->price, 2) }}</td>
            <td>{{ $r->is_active ? 'Yes':'No' }}</td>
            <td>
              <a class="a2-btn a2-btn-ghost" href="{{ route('admin.business_service_prices.edit', $r) }}">Edit</a>
              <form method="POST" action="{{ route('admin.business_service_prices.destroy', $r) }}"
                    style="display:inline" onsubmit="return confirm('Delete?')">
                @csrf @method('DELETE')
                <button class="a2-btn a2-btn-danger" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" style="padding:14px;">No rows</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div style="margin-top:12px;">
    {{ $rows->links() }}
  </div>
</div>
@endsection