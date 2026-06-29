@extends('admin-v2.layouts.master')

@section('title', 'Offer Boost Packages')
@section('topbar_title', 'Offer Boost Packages')
@section('body_class', 'admin-v2-offer-boost-packages')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">باقات تمييز العروض</h1>
            <div class="a2-page-subtitle">إدارة باقات الظهور المميز للعروض المدفوعة.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.offer-boost-packages.boost-form') }}" class="a2-btn a2-btn-primary">تفعيل Boost لعرض</a>
            <a href="{{ route('admin.offer-boost-packages.create') }}" class="a2-btn a2-btn-ghost">إنشاء باقة</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card"><div class="a2-stat-label">Packages</div><div class="a2-stat-value">{{ number_format($totals['packages'] ?? 0) }}</div><div class="a2-stat-note">كل الباقات</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Active Packages</div><div class="a2-stat-value">{{ number_format($totals['active_packages'] ?? 0) }}</div><div class="a2-stat-note">باقات فعالة</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Purchases</div><div class="a2-stat-value">{{ number_format($totals['purchases'] ?? 0) }}</div><div class="a2-stat-note">عمليات شراء</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Active Boosts</div><div class="a2-stat-value">{{ number_format($totals['active_purchases'] ?? 0) }}</div><div class="a2-stat-note">Boost فعال</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Revenue</div><div class="a2-stat-value">{{ number_format((float) ($totals['revenue'] ?? 0), 2) }}</div><div class="a2-stat-note">إجمالي رسوم Boost</div></div>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <form method="GET" action="{{ route('admin.offer-boost-packages.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="بحث بالاسم أو key">
            <select class="a2-select a2-filter-sm" name="status">
                <option value="">كل الحالات</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.offer-boost-packages.index') }}">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <h2 class="a2-section-title">الباقات</h2>
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Boost</th>
                        <th>Status</th>
                        <th>Purchases</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($packages as $package)
                        <tr>
                            <td>{{ $package->id }}</td>
                            <td>
                                <div class="a2-fw-900">{{ $package->displayName() }}</div>
                                <div class="a2-muted">{{ $package->key }}</div>
                            </td>
                            <td>{{ number_format((float) $package->price, 2) }} {{ $package->currency }}</td>
                            <td>{{ (int) $package->duration_days }} days</td>
                            <td>
                                <div>{{ number_format((float) $package->boost_score, 4) }}</div>
                                <div class="a2-muted">{{ $package->is_featured ? 'Featured' : 'Ranking only' }}</div>
                            </td>
                            <td><span class="a2-pill {{ $package->is_active ? 'a2-pill-success' : 'a2-pill-gray' }}">{{ $package->is_active ? 'active' : 'inactive' }}</span></td>
                            <td>{{ number_format((int) ($package->purchases_count ?? 0)) }}</td>
                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ route('admin.offer-boost-packages.edit', $package->id) }}">تعديل</a>
                                    <form method="POST" action="{{ route('admin.offer-boost-packages.toggle', $package->id) }}">
                                        @csrf
                                        <button class="a2-btn a2-btn-sm a2-btn-warning" type="submit">Toggle</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="a2-empty-cell">لا توجد باقات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $packages->links() }}</div>
    </div>

    <div class="a2-card a2-card--tight">
        <h2 class="a2-section-title">آخر عمليات شراء Boost</h2>
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Offer</th>
                        <th>Business</th>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Period</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchases as $purchase)
                        <tr>
                            <td>{{ $purchase->id }}</td>
                            <td>#{{ $purchase->offer_id }} — {{ optional($purchase->offer)->title_ar ?: optional($purchase->offer)->title_en }}</td>
                            <td>{{ optional($purchase->business)->name ?: ('#' . $purchase->business_id) }}</td>
                            <td>{{ optional($purchase->package)->displayName() ?: ('#' . $purchase->package_id) }}</td>
                            <td>{{ number_format((float) $purchase->price, 2) }} {{ $purchase->currency }}</td>
                            <td>
                                <div>{{ $purchase->starts_at ? $purchase->starts_at->format('Y-m-d') : '—' }}</div>
                                <div class="a2-muted">{{ $purchase->ends_at ? $purchase->ends_at->format('Y-m-d') : '—' }}</div>
                            </td>
                            <td><span class="a2-pill a2-pill-gray">{{ $purchase->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="a2-empty-cell">لا توجد عمليات شراء Boost.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
