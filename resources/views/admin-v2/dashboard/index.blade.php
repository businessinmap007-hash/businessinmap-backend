@extends('admin-v2.layouts.master')

@section('title', 'Dashboard')
@section('body_class', 'admin-v2 admin-v2-dashboard')

@section('content')
@php
    use Illuminate\Support\Facades\Route;

    $n = fn($v) => number_format((float)($v ?? 0), 0);
    $m = fn($v) => number_format((float)($v ?? 0), 2);

    $stats = $stats ?? [];
    $bookingStats = $bookingStats ?? [];
    $walletStats = $walletStats ?? [];

    $latestBookings = collect($latestBookings ?? []);
    $latestWalletTransactions = collect($latestWalletTransactions ?? []);

    $openDisputesCount = (int)($openDisputesCount ?? ($stats['open_disputes'] ?? 0));
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">لوحة التحكم</h1>
            <div class="a2-page-subtitle">
                ملخص سريع لحالة المستخدمين، الخدمات، الحجوزات، المحافظ، ورسوم التنفيذ.
            </div>
        </div>

        <div class="a2-page-actions">
            @if(Route::has('admin.bookings.index'))
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookings.index') }}">الحجوزات</a>
            @endif

            @if(Route::has('admin.wallet-transactions.index'))
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.wallet-transactions.index') }}">المحفظة</a>
            @endif

            @if(Route::has('admin.platform-services.index'))
                <a class="a2-btn a2-btn-primary" href="{{ route('admin.platform-services.index') }}">Platform Services</a>
            @endif
        </div>
    </div>

    @if($openDisputesCount > 0)
        <div class="a2-alert a2-alert-warning">
            يوجد {{ $openDisputesCount }} نزاع مفتوح يحتاج مراجعة.
            @if(Route::has('admin.disputes.index'))
                <a class="a2-link" href="{{ route('admin.disputes.index') }}">عرض النزاعات</a>
            @endif
        </div>
    @endif

    <div class="a2-stat-grid">
        <div class="a2-stat-card">
            <div class="a2-stat-label">Users</div>
            <div class="a2-stat-value">{{ $n($stats['users'] ?? 0) }}</div>
            <div class="a2-stat-note">
                Business: {{ $n($stats['businesses'] ?? 0) }} /
                Clients: {{ $n($stats['clients'] ?? 0) }}
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Categories</div>
            <div class="a2-stat-value">{{ $n($stats['categories'] ?? 0) }}</div>
            <div class="a2-stat-note">
                Children: {{ $n($stats['category_children'] ?? 0) }}
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Platform Services</div>
            <div class="a2-stat-value">{{ $n($stats['platform_services'] ?? 0) }}</div>
            <div class="a2-stat-note">
                Service Fee Rows: {{ $n($stats['category_child_service_fees'] ?? 0) }}
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Business Prices</div>
            <div class="a2-stat-value">{{ $n($stats['business_service_prices'] ?? 0) }}</div>
            <div class="a2-stat-note">
                أسعار الخدمات الخاصة بالبزنس
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Bookings</div>
            <div class="a2-stat-value">{{ $n($stats['bookings'] ?? 0) }}</div>
            <div class="a2-stat-note">
                In Progress: {{ $n($bookingStats['in_progress'] ?? 0) }}
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Open Disputes</div>
            <div class="a2-stat-value">{{ $n($openDisputesCount) }}</div>
            <div class="a2-stat-note">
                Deposits with status dispute
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Wallet Transactions</div>
            <div class="a2-stat-value">{{ $n($stats['wallet_transactions'] ?? 0) }}</div>
            <div class="a2-stat-note">
                إجمالي حركات المحافظ
            </div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Platform Fees</div>
            <div class="a2-stat-value">{{ $m($walletStats['platform_fees'] ?? 0) }}</div>
            <div class="a2-stat-note">
                Completed platform_fee
            </div>
        </div>
    </div>

    <div class="a2-card-grid-2 a2-mt-16">
        <div class="a2-card">
            <div class="a2-header">
                <div>
                    <h2 class="a2-section-title a2-mb-0">حالة الحجوزات</h2>
                    <div class="a2-section-subtitle">توزيع سريع حسب status</div>
                </div>

                @if(Route::has('admin.bookings.index'))
                    <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ route('admin.bookings.index') }}">عرض الكل</a>
                @endif
            </div>

            <div class="a2-kv-grid">
                @foreach([
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'in_progress' => 'In Progress',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ] as $key => $label)
                    <div class="a2-kv-box">
                        <span>{{ $label }}</span>
                        <strong>{{ $n($bookingStats[$key] ?? 0) }}</strong>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="a2-card">
            <div class="a2-header">
                <div>
                    <h2 class="a2-section-title a2-mb-0">ملخص المحفظة</h2>
                    <div class="a2-section-subtitle">Completed IN / OUT / Platform Fees</div>
                </div>

                @if(Route::has('admin.wallet-transactions.index'))
                    <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ route('admin.wallet-transactions.index') }}">عرض المحفظة</a>
                @endif
            </div>

            <div class="a2-kv-grid">
                <div class="a2-kv-box">
                    <span>Total IN</span>
                    <strong>{{ $m($walletStats['in_total'] ?? 0) }}</strong>
                </div>

                <div class="a2-kv-box">
                    <span>Total OUT</span>
                    <strong>{{ $m($walletStats['out_total'] ?? 0) }}</strong>
                </div>

                <div class="a2-kv-box">
                    <span>Platform Fees</span>
                    <strong>{{ $m($walletStats['platform_fees'] ?? 0) }}</strong>
                </div>

                <div class="a2-kv-box">
                    <span>Net</span>
                    <strong>{{ $m(($walletStats['in_total'] ?? 0) - ($walletStats['out_total'] ?? 0)) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="a2-card-grid-2 a2-mt-16">
        <div class="a2-card">
            <div class="a2-header">
                <div>
                    <h2 class="a2-section-title a2-mb-0">آخر الحجوزات</h2>
                    <div class="a2-section-subtitle">آخر 8 حجوزات حسب ID</div>
                </div>
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Created</th>
                        <th>View</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($latestBookings as $booking)
                        <tr>
                            <td class="a2-fw-900">#{{ $booking->id }}</td>
                            <td>
                                <span class="a2-pill a2-pill-gray">{{ $booking->status ?: '—' }}</span>
                            </td>
                            <td>{{ $m($booking->total_price ?? 0) }}</td>
                            <td dir="ltr">{{ optional($booking->created_at)->format('Y-m-d H:i') ?: '—' }}</td>
                            <td>
                                @if(Route::has('admin.bookings.show'))
                                    <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ route('admin.bookings.show', $booking->id) }}">
                                        عرض
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="a2-empty-cell">لا توجد حجوزات</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="a2-card">
            <div class="a2-header">
                <div>
                    <h2 class="a2-section-title a2-mb-0">آخر معاملات المحفظة</h2>
                    <div class="a2-section-subtitle">آخر 8 معاملات حسب ID</div>
                </div>
            </div>

            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Dir</th>
                        <th>Amount</th>
                        <th>View</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($latestWalletTransactions as $tx)
                        <tr>
                            <td class="a2-fw-900">#{{ $tx->id }}</td>
                            <td>
                                <span class="a2-pill a2-pill-gray">{{ $tx->type ?: '—' }}</span>
                            </td>
                            <td>
                                @if($tx->direction === 'in')
                                    <span class="a2-pill a2-pill-success">IN</span>
                                @else
                                    <span class="a2-pill a2-pill-danger">OUT</span>
                                @endif
                            </td>
                            <td>{{ $m($tx->amount ?? 0) }}</td>
                            <td>
                                @if(Route::has('admin.wallet-transactions.show'))
                                    <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ route('admin.wallet-transactions.show', $tx->id) }}">
                                        عرض
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="a2-empty-cell">لا توجد معاملات</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($openDisputesCount > 0)
        <div class="a2-card a2-mt-16">
            <div class="a2-header">
                <div>
                    <h2 class="a2-section-title a2-mb-0">النزاعات المفتوحة</h2>
                    <div class="a2-section-subtitle">يوجد نزاعات تحتاج مراجعة فورية</div>
                </div>

                @if(Route::has('admin.disputes.index'))
                    <a class="a2-btn a2-btn-danger" href="{{ route('admin.disputes.index') }}">
                        عرض النزاعات المفتوحة
                    </a>
                @endif
            </div>

            <div class="a2-alert a2-alert-warning">
                راجع النزاعات قبل تنفيذ release أو refund على الودائع المرتبطة بالحجوزات.
            </div>
        </div>
    @endif
</div>
@endsection