@extends('admin-v2.layouts.master')
@section('title','Service Fees')
@section('body_class','admin-v2-service-fees index')
@section('content')
<div class="a2-card" style="padding:14px;margin-bottom:12px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <div style="font-size:20px;font-weight:800;">Service Fees</div>
            <div class="a2-hint">رسوم التنفيذ لكل خدمة مثل booking_execution_fee</div>
        </div>

        <a class="a2-btn a2-btn-primary" href="{{ route('admin.service-fees.create') }}">
            + إضافة
        </a>
    </div>
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
                <option value="">الكل</option>
                @foreach($services as $s)
                    <option value="{{ $s->id }}" @selected((string)request('service_id') === (string)$s->id)>
                        {{ $s->id }} - {{ $s->name_ar ?? $s->name_en }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <div class="a2-hint">Active</div>
            <select class="a2-input" name="is_active">
                <option value="">الكل</option>
                <option value="1" @selected(request('is_active') === '1')>Yes</option>
                <option value="0" @selected(request('is_active') === '0')>No</option>
            </select>
        </div>

        <div style="display:flex;gap:8px;">
            <button class="a2-btn a2-btn-dark" type="submit">بحث</button>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.service-fees.index') }}">Reset</a>
        </div>
    </form>
</div>

<div class="a2-card" style="padding:14px;">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Service</th>
                    <th>Business</th>
                    <th>Price</th>
                    <th>Active</th>
                    <th>Fee Type</th>
                    <th>Fee Value</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r->id }}</td>
                    <td>
                        {{ $r->service->name_ar ?? $r->service->name_en ?? '-' }}
                    </td>
                    <td>
                        {{ $r->business->name ?? '-' }}
                        @if(!empty($r->business->code))
                            <div class="a2-hint">{{ $r->business->code }}</div>
                        @endif
                    </td>
                    <td>{{ $r->price }}</td>
                    <td>
                        @if($r->is_active)
                            <span class="a2-badge a2-badge-success">Yes</span>
                        @else
                            <span class="a2-badge">No</span>
                        @endif
                    </td>
                    <td>{{ $r->fee_type ?: '-' }}</td>
                    <td>{{ $r->fee_value ?: '-' }}</td>
                    <td>
                        <a class="a2-btn a2-btn-sm" href="{{ route('admin.service-fees.edit', $r) }}">Edit</a>

                        <form method="POST" action="{{ route('admin.service-fees.destroy', $r) }}" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit"
                                    onclick="return confirm('Delete?')">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align:center;">لا توجد بيانات</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:10px;">
        {{ $rows->links() }}
    </div>
</div>
@endsection