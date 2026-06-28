@extends('admin-v2.layouts.master')

@section('title', 'Wallet Overview')
@section('topbar_title', 'Wallet Overview')
@section('body_class', 'admin-v2-wallet-overview')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">أرصدة المحافظ</h1>
            <div class="a2-page-subtitle">عرض رصيد كل عميل أو بزنس داخل المحفظة.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.wallet-ops.recharge.form') }}" class="a2-btn a2-btn-primary">شحن محفظة</a>
            <a href="{{ route('admin.wallet-transactions.index') }}" class="a2-btn a2-btn-ghost">حركات المحافظ</a>
        </div>
    </div>

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card"><div class="a2-stat-label">Wallets</div><div class="a2-stat-value">{{ number_format($totals['wallets'] ?? 0) }}</div><div class="a2-stat-note">كل المحافظ</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Active</div><div class="a2-stat-value">{{ number_format($totals['active'] ?? 0) }}</div><div class="a2-stat-note">نشطة</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Available</div><div class="a2-stat-value">{{ number_format((float) ($totals['balance'] ?? 0), 2) }}</div><div class="a2-stat-note">رصيد متاح</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Locked</div><div class="a2-stat-value">{{ number_format((float) ($totals['locked'] ?? 0), 2) }}</div><div class="a2-stat-note">رصيد محجوز</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Total In</div><div class="a2-stat-value">{{ number_format((float) ($totals['total_in'] ?? 0), 2) }}</div><div class="a2-stat-note">إجمالي الداخل</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Total Out</div><div class="a2-stat-value">{{ number_format((float) ($totals['total_out'] ?? 0), 2) }}</div><div class="a2-stat-note">إجمالي الخارج</div></div>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <form method="GET" action="{{ route('admin.wallet-overview.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="بحث بالاسم / الهاتف / الإيميل / ID">

            <select class="a2-select a2-filter-sm" name="type">
                <option value="">كل الأنواع</option>
                @foreach(['client' => 'Client', 'business' => 'Business', 'admin' => 'Admin'] as $key => $label)
                    <option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="wallet_status">
                <option value="">كل الحالات</option>
                <option value="active" {{ $walletStatus === 'active' ? 'selected' : '' }}>active</option>
                <option value="blocked" {{ $walletStatus === 'blocked' ? 'selected' : '' }}>blocked</option>
            </select>

            <select class="a2-select a2-filter-sm" name="amount_filter">
                <option value="">كل الأرصدة</option>
                <option value="with_amount" {{ $amountFilter === 'with_amount' ? 'selected' : '' }}>به رصيد</option>
                <option value="zero" {{ $amountFilter === 'zero' ? 'selected' : '' }}>رصيد صفر</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([20,50,100,200] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-overview.index') }}">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>Wallet</th>
                        <th>User</th>
                        <th>Type</th>
                        <th>Available</th>
                        <th>Locked</th>
                        <th>Total</th>
                        <th>Total In</th>
                        <th>Total Out</th>
                        <th>Status</th>
                        <th>Transactions</th>
                        <th>Last Activity</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $wallet)
                        @php
                            $available = (float) ($wallet->balance ?? 0);
                            $locked = (float) ($wallet->locked_balance ?? 0);
                            $total = $available + $locked;
                            $user = $wallet->user;
                        @endphp
                        <tr>
                            <td>#{{ $wallet->id }}</td>
                            <td>
                                <div class="a2-fw-900">{{ optional($user)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $wallet->user_id }} — {{ optional($user)->phone ?: optional($user)->email }}</div>
                            </td>
                            <td><span class="a2-pill a2-pill-gray">{{ optional($user)->type ?: '—' }}</span></td>
                            <td>{{ number_format($available, 2) }}</td>
                            <td>{{ number_format($locked, 2) }}</td>
                            <td class="a2-fw-900">{{ number_format($total, 2) }}</td>
                            <td>{{ number_format((float) ($wallet->total_in ?? 0), 2) }}</td>
                            <td>{{ number_format((float) ($wallet->total_out ?? 0), 2) }}</td>
                            <td><span class="a2-pill {{ $wallet->status === 'active' ? 'a2-pill-success' : 'a2-pill-gray' }}">{{ $wallet->status }}</span></td>
                            <td>
                                <a class="a2-link" href="{{ route('admin.wallet-transactions.index', ['user_id' => $wallet->user_id]) }}">
                                    {{ number_format((int) ($wallet->transactions_count ?? 0)) }}
                                </a>
                            </td>
                            <td>{{ $wallet->last_activity_at ? $wallet->last_activity_at->format('Y-m-d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="a2-empty-cell">لا توجد محافظ.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
