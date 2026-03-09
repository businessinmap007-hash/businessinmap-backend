@extends('admin-v2.layouts.master')


@section('title','Business Service Prices')
@section('body_class','admin-v2-business-service-prices')
@section('topbar_title','Business Service Prices')

@section('content')
<div class="a2-page">

    <div class="a2-card" style="padding:14px;margin-bottom:12px;">
        <div class="a2-header" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div>
                <div class="a2-title">Business Service Prices</div>
                <div class="a2-hint">سعر كل خدمة لكل بزنس + إعدادات الديبوزت</div>
            </div>

            <a class="a2-btn a2-btn-primary" href="{{ route('admin.business_service_prices.create') }}">
                + إضافة
            </a>
        </div>
    </div>

    <div class="a2-card" style="padding:14px;margin-bottom:12px;">
        <form method="GET" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:10px;align-items:end;">
            <div>
                <label class="a2-label">Service</label>
                <select class="a2-input" name="platform_service_id">
                    <option value="">الكل</option>
                    @foreach($services as $s)
                        <option value="{{ $s->id }}" @selected((int)request('platform_service_id') === (int)$s->id)>
                            {{ $s->name_ar ?? $s->name_en ?? $s->key }} ({{ $s->key }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="a2-label">Business</label>
                <select class="a2-input" name="business_id">
                    <option value="">الكل</option>
                    @foreach($businesses as $b)
                        <option value="{{ $b->id }}" @selected((int)request('business_id') === (int)$b->id)>
                            {{ $b->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="a2-label">Active</label>
                <select class="a2-input" name="is_active">
                    <option value="">الكل</option>
                    <option value="1" @selected(request('is_active') === '1')>Yes</option>
                    <option value="0" @selected(request('is_active') === '0')>No</option>
                </select>
            </div>

            <div></div>

            <div style="display:flex;gap:8px;">
                <button class="a2-btn a2-btn-primary" type="submit">بحث</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.business_service_prices.index') }}">Reset</a>
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
                        <th>Deposit Enabled</th>
                        <th>Deposit %</th>
                        <th>Max Allowed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td>{{ $r->id }}</td>

                        <td>
                            @if($r->service)
                                <div style="font-weight:700;">
                                    {{ $r->service->name_ar ?? $r->service->name_en ?? '-' }}
                                </div>
                                <div class="a2-hint">{{ $r->service->key }}</div>
                            @else
                                —
                            @endif
                        </td>

                        <td>{{ $r->business->name ?? '—' }}</td>

                        <td>{{ number_format((float)$r->price, 2) }}</td>

                        <td>
                            @if($r->is_active)
                                <span class="a2-badge a2-badge-success">Yes</span>
                            @else
                                <span class="a2-badge a2-badge-muted">No</span>
                            @endif
                        </td>

                        <td>
                            @if($r->deposit_enabled)
                                <span class="a2-badge a2-badge-success">Yes</span>
                            @else
                                <span class="a2-badge a2-badge-muted">No</span>
                            @endif
                        </td>

                        <td>{{ (int) $r->deposit_percent }}%</td>

                        <td>{{ (int) data_get($r, 'service.max_deposit_percent', 0) }}%</td>

                        <td class="a2-actions">
                            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.business_service_prices.edit', $r) }}">Edit</a>

                            <form method="POST" action="{{ route('admin.business_service_prices.destroy', $r) }}" style="display:inline;" onsubmit="return confirm('Delete?')">
                                @csrf
                                @method('DELETE')
                                <button class="a2-btn a2-btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;">لا توجد بيانات</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:12px;">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
