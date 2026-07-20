@extends('admin-v2.layouts.master')

@section('title', 'Offer Performance')
@section('topbar_title', 'Offer Performance')
@section('body_class', 'admin-v2-offer-performance')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('أداء العروض') }}</h1>
            <div class="a2-page-subtitle">{{ __('متابعة المشاهدات والضغطات والـ leads والتحويلات الخاصة بالعروض التجارية.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">{{ __('العروض') }}</a>
            <a href="{{ route('admin.business-offers-subscriptions.form') }}" class="a2-btn a2-btn-ghost">{{ __('اشتراكات العروض') }}</a>
        </div>
    </div>

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card"><div class="a2-stat-label">Total Events</div><div class="a2-stat-value">{{ number_format($totals['all'] ?? 0) }}</div><div class="a2-stat-note">{{ __('كل الأحداث') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Views</div><div class="a2-stat-value">{{ number_format($totals['views'] ?? 0) }}</div><div class="a2-stat-note">{{ __('مشاهدات') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Clicks</div><div class="a2-stat-value">{{ number_format($totals['clicks'] ?? 0) }}</div><div class="a2-stat-note">{{ __('ضغطات') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Leads</div><div class="a2-stat-value">{{ number_format($totals['leads'] ?? 0) }}</div><div class="a2-stat-note">{{ __('طلبات اهتمام') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Conversions</div><div class="a2-stat-value">{{ number_format($totals['conversions'] ?? 0) }}</div><div class="a2-stat-note">{{ __('تحويلات') }}</div></div>
        <div class="a2-stat-card"><div class="a2-stat-label">Value</div><div class="a2-stat-value">{{ number_format((float) ($totals['value_total'] ?? 0), 2) }}</div><div class="a2-stat-note">{{ __('إجمالي القيمة') }}</div></div>
    </div>

    <div class="a2-card a2-card--tight a2-mb-16">
        <form method="GET" action="{{ route('admin.offer-performance.index') }}" class="a2-filterbar">
            @php $selBizId = (int) ($filters['business_id'] ?? 0); $selBiz = $selBizId ? $businesses->firstWhere('id', $selBizId) : null; @endphp
            <select class="a2-select a2-filter-sm" name="business_id"
                    data-remote-url="{{ route('admin.business-lookup', [], false) }}" data-placeholder="{{ __('كل البزنس — ابحث') }}">
                <option value="">{{ __('كل البزنس') }}</option>
                @if($selBizId)
                    <option value="{{ $selBizId }}" selected>#{{ $selBizId }}@if($selBiz) — {{ $selBiz->name }}@endif</option>
                @endif
            </select>

            <select class="a2-select a2-filter-sm" name="offer_id">
                <option value="">{{ __('كل العروض') }}</option>
                @foreach($offers as $offer)
                    <option value="{{ $offer->id }}" {{ (int) ($filters['offer_id'] ?? 0) === (int) $offer->id ? 'selected' : '' }}>
                        #{{ $offer->id }} — {{ $offer->title_ar ?: ($offer->title_en ?: 'Offer') }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="event_type">
                <option value="">{{ __('كل الأحداث') }}</option>
                @foreach($eventTypes as $type)
                    <option value="{{ $type }}" {{ ($filters['event_type'] ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>

            <input class="a2-input a2-filter-sm" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
            <input class="a2-input a2-filter-sm" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,30,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) ($filters['per_page'] ?? 30) === $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.offer-performance.index') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-grid-2 a2-mb-16">
        <div class="a2-card a2-card--tight">
            <h2 class="a2-section-title">{{ __('تفصيل الأحداث') }}</h2>
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Total</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($eventBreakdown as $row)
                            <tr>
                                <td><span class="a2-pill a2-pill-gray">{{ $row->event_type }}</span></td>
                                <td>{{ number_format((int) $row->total) }}</td>
                                <td>{{ number_format((float) $row->value_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="a2-empty-cell">{{ __('لا توجد أحداث.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="a2-card a2-card--tight">
            <h2 class="a2-section-title">{{ __('أفضل العروض أداءً') }}</h2>
            <div class="a2-table-wrap">
                <table class="a2-table">
                    <thead>
                        <tr>
                            <th>Offer</th>
                            <th>Business</th>
                            <th>Events</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($offerStats as $row)
                            <tr>
                                <td>
                                    <div class="a2-fw-900">#{{ $row->offer_id }}</div>
                                    <div class="a2-muted">{{ optional($row->offer)->title_ar ?: optional($row->offer)->title_en }}</div>
                                </td>
                                <td>{{ optional($row->business)->name ?: ('#' . $row->business_id) }}</td>
                                <td>{{ number_format((int) $row->events_count) }}</td>
                                <td>{{ number_format((float) $row->value_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="a2-empty-cell">{{ __('لا توجد إحصائيات.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <h2 class="a2-section-title">{{ __('آخر الأحداث') }}</h2>
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Event</th>
                        <th>Offer</th>
                        <th>Business</th>
                        <th>User</th>
                        <th>Value</th>
                        <th>Source</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        <tr>
                            <td>{{ $event->id }}</td>
                            <td><span class="a2-pill a2-pill-gray">{{ $event->event_type }}</span></td>
                            <td>
                                <div class="a2-fw-900">#{{ $event->offer_id }}</div>
                                <div class="a2-muted">{{ optional($event->offer)->title_ar ?: optional($event->offer)->title_en }}</div>
                            </td>
                            <td>{{ optional($event->business)->name ?: ('#' . $event->business_id) }}</td>
                            <td>{{ optional($event->user)->name ?: 'Guest' }}</td>
                            <td>{{ $event->value_amount !== null ? number_format((float) $event->value_amount, 2) . ' ' . $event->currency : '—' }}</td>
                            <td>{{ $event->source ?: '—' }}</td>
                            <td>{{ $event->occurred_at ? $event->occurred_at->format('Y-m-d H:i') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="a2-empty-cell">{{ __('لا توجد أحداث.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $events->links() }}</div>
    </div>
</div>
@endsection
