@extends('admin-v2.layouts.master')
@section('title','Service Fees')
@section('body_class','admin-v2-service-fees index')

@section('content')
<div class="a2-page">
  <div class="a2-header" style="margin-bottom:12px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <div class="a2-title">Service Fees</div>
      <div class="a2-hint">رسوم المنصة (مثل booking_execution_fee)</div>
    </div>
    <a class="a2-btn a2-btn-primary" href="{{ route('admin.service_fees.create') }}">+ إضافة</a>
  </div>

  <div class="a2-card" style="padding:14px;margin-bottom:12px;">
    <form method="GET" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end;">
      <div>
        <div class="a2-hint">Code</div>
        <input class="a2-input" name="code" value="{{ request('code') }}">
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
          <th>Code</th>
          <th>Service</th>
          <th>Amount</th>
          <th>Active</th>
          <th style="width:220px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $r)
          <tr>
            <td>#{{ $r->id }}</td>
            <td style="font-weight:800;">{{ $r->code }}</td>
            <td>{{ $r->service_id ? ('#'.$r->service_id) : 'ALL' }}</td>
            <td>{{ number_format((float)$r->amount, 2) }}</td>
            <td>{{ (int)$r->is_active ? 'Yes' : 'No' }}</td>
            <td>
              <a class="a2-btn a2-btn-ghost" href="{{ route('admin.service_fees.show', $r) }}">View</a>
              <a class="a2-btn a2-btn-ghost" href="{{ route('admin.service_fees.edit', $r) }}">Edit</a>
              <form method="POST" action="{{ route('admin.service_fees.destroy', $r) }}" style="display:inline"
                    onsubmit="return confirm('Delete?')">
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