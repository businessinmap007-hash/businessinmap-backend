@extends('admin-v2.layouts.master')

@section('title', 'Bookable Items')
@section('body_class', 'admin-v2-bookable-items')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Bookable Items</h1>
            <div class="a2-page-subtitle">
                الغرف / الشقق / الأجنحة / الملاعب / أي عنصر قابل للحجز
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookable-items.create') }}">
                + إضافة
            </a>
        </div>
    </div>

    <div class="a2-card a2-mb-12">
        <form method="GET" action="{{ route('admin.bookable-items.index') }}" class="a2-filterbar">

            <input
                class="a2-input a2-filter-search"
                name="q"
                value="{{ $q ?? '' }}"
                placeholder="title / code / type"
            >

            <select class="a2-select a2-filter-md" name="service_id">
                <option value="">الخدمة كلها</option>
                @foreach($services as $s)
                    <option value="{{ $s->id }}" @selected((int) request('service_id') === (int) $s->id)>
                        {{ $s->name_ar ?? $s->name_en ?? $s->key }} ({{ $s->key }})
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="business_id">
                <option value="">البزنس كله</option>
                @foreach($businesses as $b)
                    <option value="{{ $b->id }}" @selected((int) request('business_id') === (int) $b->id)>
                        {{ $b->name }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="is_active">
                <option value="">الكل</option>
                <option value="1" @selected(request('is_active') === '1')>Yes</option>
                <option value="0" @selected(request('is_active') === '0')>No</option>
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">بحث</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.index') }}">Reset</a>
            </div>
        </form>
    </div>

    <div class="a2-card">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Code</th>
                        <th>Service</th>
                        <th>Business</th>
                        <th>Price</th>
                        <th>Capacity</th>
                        <th>Deposit</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r->id }}</td>

                            <td>
                                <span class="a2-clip" title="{{ $r->title }}">
                                    {{ $r->title }}
                                </span>
                            </td>

                            <td>{{ $r->item_type ?: '-' }}</td>
                            <td>{{ $r->code ?: '-' }}</td>

                            <td>
                                @if($r->service)
                                    <div class="a2-bookable-service-cell">
                                        <div class="a2-fw-800">
                                            {{ $r->service->name_ar ?? $r->service->name_en ?? '-' }}
                                        </div>
                                        <div class="a2-hint">{{ $r->service->key }}</div>
                                    </div>
                                @else
                                    -
                                @endif
                            </td>

                            <td>
                                <span class="a2-clip" title="{{ $r->business->name ?? '-' }}">
                                    {{ $r->business->name ?? '-' }}
                                </span>
                            </td>

                            <td dir="ltr">{{ number_format((float) $r->price, 2) }}</td>
                            <td>{{ $r->capacity ?: '-' }}</td>

                            <td>
                                @if($r->deposit_enabled)
                                    <div class="a2-bookable-deposit-cell">
                                        <span class="a2-pill a2-pill-active">Yes</span>
                                        <span class="a2-hint">{{ (int) $r->deposit_percent }}%</span>
                                    </div>
                                @else
                                    <span class="a2-pill a2-pill-gray">No</span>
                                @endif
                            </td>

                            <td>
                                @if($r->is_active)
                                    <span class="a2-pill a2-pill-active">Yes</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">No</span>
                                @endif
                            </td>

                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-ghost a2-btn-sm" href="{{ route('admin.bookable-items.edit', $r) }}">
                                        Edit
                                    </a>

                                    <form method="POST" action="{{ route('admin.bookable-items.destroy', $r) }}" onsubmit="return confirm('Delete?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-danger a2-btn-sm" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                    <a class="a2-btn a2-btn-ghost"href="{{ route('admin.bookable-items.blocked-slots.index',$r) }}">Availability</a>
                                    <a class="a2-btn a2-btn-ghost"href="{{ route('admin.bookable-items.price-rules.index',$r) }}">Pricing</a>
<a class="a2-btn a2-btn-ghost a2-btn-sm"
   href="{{ route('admin.bookable-items.calendar', $r) }}">
    Calendar
</a>
                                </div>
                                
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="a2-empty-cell">لا توجد بيانات</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-mt-12">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
