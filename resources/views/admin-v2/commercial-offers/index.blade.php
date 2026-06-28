@extends('admin-v2.layouts.master')

@section('title', 'Commercial Offers')
@section('topbar_title', 'Commercial Offers')
@section('body_class', 'admin-v2-commercial-offers')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">العروض التجارية</h1>
            <div class="a2-page-subtitle">عروض عامة للبزنس تدخل في محرك مقارنة الأسعار والحملات التسويقية.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.commercial-offers.create') }}" class="a2-btn a2-btn-primary">إنشاء عرض</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card"><div class="a2-stat-label">الكل</div><div class="a2-stat-value">{{ number_format($totals['all'] ?? 0) }}</div><div class="a2-stat-note">كل العروض</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Active</div><div class="a2-stat-value">{{ number_format($totals['active'] ?? 0) }}</div><div class="a2-stat-note">عروض فعالة</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Paused</div><div class="a2-stat-value">{{ number_format($totals['paused'] ?? 0) }}</div><div class="a2-stat-note">موقوفة</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Promotions</div><div class="a2-stat-value">{{ number_format($totals['promotions'] ?? 0) }}</div><div class="a2-stat-note">عروض تسويقية</div></div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.commercial-offers.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="بحث بالعرض / البزنس / ID">

            <select class="a2-select a2-filter-sm" name="offerable_type">
                <option value="">كل الأنواع</option>
                @foreach($offerableTypes as $type)
                    <option value="{{ $type }}" {{ $offerableType === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="source_type">
                <option value="">كل المصادر</option>
                @foreach($sourceTypes as $type)
                    <option value="{{ $type }}" {{ $sourceType === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="">كل الحالات</option>
                @foreach($statuses as $st)
                    <option value="{{ $st }}" {{ $status === $st ? 'selected' : '' }}>{{ $st }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="seller_business_id">
                <option value="0">كل البزنس</option>
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}" {{ (int) $sellerId === (int) $business->id ? 'selected' : '' }}>#{{ $business->id }} — {{ $business->name }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.commercial-offers.index') }}">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>العرض</th>
                        <th>Seller</th>
                        <th>Type</th>
                        <th>Source</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Period</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>
                                <div class="a2-fw-900">{{ $row->displayTitle() }}</div>
                                <div class="a2-muted">{{ $row->offerable_type }} #{{ $row->offerable_id }}</div>
                            </td>
                            <td>
                                <div class="a2-fw-900">{{ optional($row->sellerBusiness)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $row->seller_business_id }}</div>
                            </td>
                            <td><span class="a2-pill a2-pill-gray">{{ $row->offerable_type }}</span></td>
                            <td><span class="a2-pill a2-pill-gray">{{ $row->source_type }}</span></td>
                            <td>{{ number_format((float) $row->final_price, 2) }} {{ $row->currency }}</td>
                            <td><span class="a2-pill {{ $row->status === 'active' ? 'a2-pill-success' : 'a2-pill-gray' }}">{{ $row->status }}</span></td>
                            <td>
                                <div>{{ $row->starts_at ? $row->starts_at->format('Y-m-d') : '—' }}</div>
                                <div class="a2-muted">{{ $row->ends_at ? $row->ends_at->format('Y-m-d') : '—' }}</div>
                            </td>
                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ route('admin.commercial-offers.edit', $row->id) }}">تعديل</a>
                                    <form method="POST" action="{{ route('admin.commercial-offers.toggle', $row->id) }}">
                                        @csrf
                                        <button class="a2-btn a2-btn-sm a2-btn-warning" type="submit">Toggle</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.commercial-offers.destroy', $row->id) }}" onsubmit="return confirm('حذف العرض؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="a2-empty-cell">لا توجد عروض.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
