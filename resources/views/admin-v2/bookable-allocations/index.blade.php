@extends('admin-v2.layouts.master')

@section('title', 'Bookable Allocations')
@section('topbar_title', 'Bookable Allocations')
@section('body_class', 'admin-v2-bookable-allocations')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">حصص الوحدات القابلة للحجز</h1>
            <div class="a2-page-subtitle">إدارة حصة شركة السياحة أو الشريك من غرف الفندق أو أي وحدة قابلة للحجز.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.bookable-allocations.create') }}" class="a2-btn a2-btn-primary">إنشاء حصة</a>
            <a href="{{ route('admin.business-partnerships.index') }}" class="a2-btn a2-btn-ghost">الشراكات</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">الكل</div>
            <div class="a2-stat-value">{{ number_format($totals['all'] ?? 0) }}</div>
            <div class="a2-stat-note">إجمالي الحصص</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Active</div>
            <div class="a2-stat-value">{{ number_format($totals['active'] ?? 0) }}</div>
            <div class="a2-stat-note">حصص مفعلة</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Paused</div>
            <div class="a2-stat-value">{{ number_format($totals['paused'] ?? 0) }}</div>
            <div class="a2-stat-note">موقوفة مؤقتًا</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Stopped</div>
            <div class="a2-stat-value">{{ number_format($totals['stopped'] ?? 0) }}</div>
            <div class="a2-stat-note">Stop Sale</div>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.bookable-allocations.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="بحث بالفندق / الشريك / الوحدة / ID">

            <select class="a2-select a2-filter-sm" name="partnership_id">
                <option value="0">كل الشراكات</option>
                @foreach($partnerships as $partnership)
                    <option value="{{ $partnership->id }}" {{ (int) $partnershipId === (int) $partnership->id ? 'selected' : '' }}>
                        #{{ $partnership->id }} — {{ optional($partnership->ownerBusiness)->name ?: 'Owner' }} ↔ {{ optional($partnership->partnerBusiness)->name ?: 'Partner' }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="allocation_type">
                <option value="">كل الأنواع</option>
                @foreach(\App\Models\BookableAllocation::allocationTypes() as $key => $label)
                    <option value="{{ $key }}" {{ $allocationType === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="">كل الحالات</option>
                @foreach(\App\Models\BookableAllocation::statuses() as $key => $label)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-allocations.index') }}">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Owner</th>
                        <th>Partner</th>
                        <th>Bookable</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Qty</th>
                        <th>Available</th>
                        <th>Price</th>
                        <th>Period</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>
                                <div class="a2-fw-900">{{ optional($row->ownerBusiness)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $row->owner_business_id }}</div>
                            </td>
                            <td>
                                <div class="a2-fw-900">{{ optional($row->partnerBusiness)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $row->partner_business_id }}</div>
                            </td>
                            <td>
                                <div class="a2-fw-900">{{ optional($row->bookableItem)->display_name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $row->bookable_item_id }}</div>
                            </td>
                            <td><span class="a2-pill a2-pill-gray">{{ $row->allocation_type }}</span></td>
                            <td>
                                <span class="a2-pill {{ $row->status === 'active' ? 'a2-pill-success' : ($row->status === 'stopped' ? 'a2-pill-danger' : 'a2-pill-gray') }}">
                                    {{ $row->status }}
                                </span>
                            </td>
                            <td>{{ (int) $row->quantity_total }}</td>
                            <td>{{ $row->availableQuantity() }}</td>
                            <td>{{ number_format((float) $row->finalPrice(), 2) }} {{ $row->currency }}</td>
                            <td>
                                <div>{{ $row->starts_at ? $row->starts_at->format('Y-m-d') : '—' }}</div>
                                <div class="a2-muted">{{ $row->ends_at ? $row->ends_at->format('Y-m-d') : '—' }}</div>
                            </td>
                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ route('admin.bookable-allocations.edit', $row->id) }}">تعديل</a>

                                    @if($row->status !== 'active')
                                        <form method="POST" action="{{ route('admin.bookable-allocations.activate', $row->id) }}">
                                            @csrf
                                            <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">تفعيل</button>
                                        </form>
                                    @endif

                                    @if($row->status === 'active')
                                        <form method="POST" action="{{ route('admin.bookable-allocations.stop', $row->id) }}">
                                            @csrf
                                            <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">Stop</button>
                                        </form>
                                    @endif

                                    <form method="POST" action="{{ route('admin.bookable-allocations.destroy', $row->id) }}" onsubmit="return confirm('حذف الحصة والعرض المرتبط؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="a2-empty-cell">لا توجد حصص.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-pagination">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
