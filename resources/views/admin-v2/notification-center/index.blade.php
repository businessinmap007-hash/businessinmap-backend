@extends('admin-v2.layouts.master')

@section('title', 'Notification Center')
@section('topbar_title', 'Notification Center')
@section('body_class', 'admin-v2-notification-center')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مركز الإشعارات') }}</h1>
            <div class="a2-page-subtitle">{{ __('مركز عام لإشعارات التطبيق: عروض، حجوزات، محفظة، ضمان، نزاعات، نظام، وخدمات مفعلة من Platform Services.') }}</div>
        </div>
        <div class="a2-page-actions">
            <form method="POST" action="{{ route('admin.notification-center.sync-offers') }}">
                @csrf
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('مزامنة إشعارات العروض') }}</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card"><div class="a2-stat-label">All</div><div class="a2-stat-value">{{ number_format($totals['all'] ?? 0) }}</div><div class="a2-stat-note">{{ __('كل الإشعارات') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Unread</div><div class="a2-stat-value">{{ number_format($totals['unread'] ?? 0) }}</div><div class="a2-stat-note">{{ __('غير مقروء') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Read</div><div class="a2-stat-value">{{ number_format($totals['read'] ?? 0) }}</div><div class="a2-stat-note">{{ __('مقروء') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Archived</div><div class="a2-stat-value">{{ number_format($totals['archived'] ?? 0) }}</div><div class="a2-stat-note">{{ __('مؤرشف') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Offers</div><div class="a2-stat-value">{{ number_format($totals['offers'] ?? 0) }}</div><div class="a2-stat-note">{{ __('إشعارات عروض') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Urgent</div><div class="a2-stat-value">{{ number_format($totals['urgent'] ?? 0) }}</div><div class="a2-stat-note">{{ __('أولوية عاجلة') }}</div></div>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <form method="GET" action="{{ route('admin.notification-center.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('بحث بالعنوان / النص / المستخدم / ID') }}">

            <select class="a2-select a2-filter-sm" name="user_id">
                <option value="">{{ __('كل المستخدمين') }}</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" {{ (int) ($filters['user_id'] ?? 0) === (int) $user->id ? 'selected' : '' }}>
                        #{{ $user->id }} — {{ $user->name }} — {{ $user->type }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="type">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach(($typeOptions ?? []) as $key => $option)
                    <option value="{{ $key }}" {{ ($filters['type'] ?? '') === $key ? 'selected' : '' }}>
                        {{ $option['label_ar'] ?? $key }} — {{ $key }}
                        @if(($option['source'] ?? 'core') === 'platform_service')
                            [service]
                        @endif
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="priority">
                <option value="">{{ __('كل الأولويات') }}</option>
                @foreach($priorities as $priority)
                    <option value="{{ $priority }}" {{ ($filters['priority'] ?? '') === $priority ? 'selected' : '' }}>{{ $priority }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,30,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) ($filters['per_page'] ?? 30) === $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.notification-center.index') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Notification</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Source</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>
                                <div class="a2-fw-900">{{ optional($row->user)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $row->user_id }} — {{ optional($row->user)->type }}</div>
                            </td>
                            <td>
                                <div class="a2-fw-900">{{ $row->displayTitle() }}</div>
                                <div class="a2-muted">{{ \Illuminate\Support\Str::limit($row->displayBody(), 90) }}</div>
                            </td>
                            <td><span class="a2-pill a2-pill-gray">{{ $row->type }}</span></td>
                            <td><span class="a2-pill a2-pill-gray">{{ $row->priority }}</span></td>
                            <td><span class="a2-pill {{ $row->status === 'unread' ? 'a2-pill-success' : 'a2-pill-gray' }}">{{ $row->status }}</span></td>
                            <td>
                                <div>{{ $row->action_type ?: '—' }}</div>
                                <div class="a2-muted">{{ $row->action_url ?: '—' }}</div>
                            </td>
                            <td>
                                <div>{{ $row->source_type ?: '—' }}</div>
                                <div class="a2-muted">{{ $row->source_id ? ('#' . $row->source_id) : '—' }}</div>
                            </td>
                            <td>
                                <div>{{ $row->created_at ? $row->created_at->format('Y-m-d H:i') : '—' }}</div>
                                <div class="a2-muted">{{ $row->read_at ? ('Read: ' . $row->read_at->format('Y-m-d H:i')) : '' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="a2-empty-cell">{{ __('لا توجد إشعارات.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
