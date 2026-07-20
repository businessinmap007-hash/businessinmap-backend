@extends('admin-v2.layouts.master')

@section('title', 'Offer Follows')
@section('topbar_title', 'Offer Follows')
@section('body_class', 'admin-v2-offer-follows')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('متابعات العروض والاستهداف') }}</h1>
            <div class="a2-page-subtitle">{{ __('تحليل ما يتابعه المستخدمون والبزنسات، والكلمات والمنتجات الأكثر استهدافًا.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">{{ __('العروض') }}</a>
            <a href="{{ route('admin.offer-performance.index') }}" class="a2-btn a2-btn-ghost">{{ __('أداء العروض') }}</a>
        </div>
    </div>

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card"><div class="a2-stat-label">Follows</div><div class="a2-stat-value">{{ number_format($totals['follows'] ?? 0) }}</div><div class="a2-stat-note">{{ __('كل المتابعات') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Active</div><div class="a2-stat-value">{{ number_format($totals['active_follows'] ?? 0) }}</div><div class="a2-stat-note">{{ __('متابعات فعالة') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Keywords</div><div class="a2-stat-value">{{ number_format($totals['keywords'] ?? 0) }}</div><div class="a2-stat-note">{{ __('متابعة كلمات') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">B2B</div><div class="a2-stat-value">{{ number_format($totals['b2b'] ?? 0) }}</div><div class="a2-stat-note">{{ __('استهداف بزنس') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">B2C</div><div class="a2-stat-value">{{ number_format($totals['b2c'] ?? 0) }}</div><div class="a2-stat-note">{{ __('استهداف عملاء') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Notifications</div><div class="a2-stat-value">{{ number_format($totals['notifications'] ?? 0) }}</div><div class="a2-stat-note">{{ __('إشعارات Matching') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Unread</div><div class="a2-stat-value">{{ number_format($totals['unread_notifications'] ?? 0) }}</div><div class="a2-stat-note">{{ __('غير مقروء') }}</div></div>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <form method="GET" action="{{ route('admin.offer-follows.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('بحث بالاسم / keyword / id') }}">

            <select class="a2-select a2-filter-sm" name="user_id">
                <option value="">{{ __('كل المستخدمين') }}</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ (int) ($filters['user_id'] ?? 0) === (int) $user->id ? 'selected' : '' }}>
                        #{{ $user->id }} — {{ $user->name }} — {{ $user->type }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="user_type">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach(['client', 'business', 'admin'] as $type)
                    <option value="{{ $type }}" {{ ($filters['user_type'] ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="followable_type">
                <option value="">{{ __('كل المتابعات') }}</option>
                @foreach($followableTypes as $type)
                    <option value="{{ $type }}" {{ ($filters['followable_type'] ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="audience_type">
                <option value="">{{ __('كل الجمهور') }}</option>
                @foreach($audienceTypes as $type)
                    <option value="{{ $type }}" {{ ($filters['audience_type'] ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="is_active">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="1" {{ ($filters['is_active'] ?? '') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ ($filters['is_active'] ?? '') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,30,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) ($filters['per_page'] ?? 30) === $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.offer-follows.index') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-grid-3 a2-mb-16">
        <div class="a2-card a2-card--tight">
            <h2 class="a2-section-title">{{ __('أكثر Keywords متابعة') }}</h2>
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead><tr><th>Keyword</th><th>Total</th></tr></thead>
                    <tbody>
                        @forelse($topKeywords as $row)
                            <tr><td>{{ $row->keyword }}</td><td>{{ number_format((int) $row->total) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="a2-empty-cell">{{ __('لا توجد keywords.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="a2-card a2-card--tight">
            <h2 class="a2-section-title">{{ __('أكثر عناصر متابعة') }}</h2>
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead><tr><th>Type</th><th>ID</th><th>Total</th></tr></thead>
                    <tbody>
                        @forelse($topFollowables as $row)
                            <tr>
                                <td><span class="a2-pill a2-pill-gray">{{ $row->followable_type }}</span></td>
                                <td>#{{ $row->followable_id }}</td>
                                <td>{{ number_format((int) $row->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="a2-empty-cell">{{ __('لا توجد عناصر.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="a2-card a2-card--tight">
            <h2 class="a2-section-title">{{ __('أكثر أقسام فرعية متابعة') }}</h2>
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead><tr><th>Child ID</th><th>Total</th></tr></thead>
                    <tbody>
                        @forelse($topCategoryChildren as $row)
                            <tr><td>#{{ $row->category_child_id }}</td><td>{{ number_format((int) $row->total) }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="a2-empty-cell">{{ __('لا توجد أقسام.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <h2 class="a2-section-title">{{ __('قائمة المتابعات') }}</h2>
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Follow</th>
                        <th>Keyword</th>
                        <th>Category</th>
                        <th>Audience</th>
                        <th>Price Range</th>
                        <th>Status</th>
                        <th>Notifications</th>
                        <th>Last Matched</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($follows as $follow)
                        <tr>
                            <td>{{ $follow->id }}</td>
                            <td>
                                <div class="a2-fw-900">{{ optional($follow->user)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $follow->user_id }} — {{ optional($follow->user)->type }}</div>
                            </td>
                            <td>
                                <span class="a2-pill a2-pill-gray">{{ $follow->followable_type }}</span>
                                <div class="a2-muted">#{{ $follow->followable_id }}</div>
                            </td>
                            <td>{{ $follow->keyword ?: '—' }}</td>
                            <td>
                                <div>Root: {{ $follow->category_id ?: '—' }}</div>
                                <div class="a2-muted">Child: {{ $follow->category_child_id ?: '—' }}</div>
                            </td>
                            <td><span class="a2-pill a2-pill-gray">{{ $follow->audience_type }}</span></td>
                            <td>
                                <div>{{ $follow->min_price !== null ? number_format((float) $follow->min_price, 2) : '—' }}</div>
                                <div class="a2-muted">{{ $follow->max_price !== null ? number_format((float) $follow->max_price, 2) : '—' }}</div>
                            </td>
                            <td><span class="a2-pill {{ $follow->is_active ? 'a2-pill-success' : 'a2-pill-gray' }}">{{ $follow->is_active ? 'active' : 'inactive' }}</span></td>
                            <td>{{ number_format((int) ($follow->notifications_count ?? 0)) }}</td>
                            <td>{{ $follow->last_matched_at ? $follow->last_matched_at->format('Y-m-d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="a2-empty-cell">{{ __('لا توجد متابعات.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $follows->links() }}</div>
    </div>

    <div class="a2-card a2-card--tight">
        <h2 class="a2-section-title">{{ __('آخر إشعارات المتابعة') }}</h2>
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Match</th>
                        <th>Offer</th>
                        <th>Seller</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                        <tr>
                            <td>{{ $notification->id }}</td>
                            <td>{{ optional($notification->user)->name ?: ('#' . $notification->user_id) }}</td>
                            <td>
                                <span class="a2-pill a2-pill-gray">{{ $notification->match_type }}</span>
                                <div class="a2-muted">{{ optional($notification->follow)->keyword ?: optional($notification->follow)->followable_type }}</div>
                            </td>
                            <td>
                                #{{ $notification->offer_id }} — {{ optional($notification->offer)->title_ar ?: optional($notification->offer)->title_en }}
                                <div class="a2-muted">{{ optional($notification->offer)->audience_type }}</div>
                            </td>
                            <td>{{ optional(optional($notification->offer)->sellerBusiness)->name ?: '—' }}</td>
                            <td>{{ number_format((float) $notification->match_score, 4) }}</td>
                            <td><span class="a2-pill a2-pill-gray">{{ $notification->status }}</span></td>
                            <td>{{ $notification->created_at ? $notification->created_at->format('Y-m-d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="a2-empty-cell">{{ __('لا توجد إشعارات.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
