@extends('admin-v2.layouts.master')

@section('title', 'Trip Schedules')
@section('topbar_title', 'Trip Schedules')
@section('body_class', 'admin-v2-trip-schedules')

@php
    use App\Models\TripSchedule;

    // Shared with the carrier's own panel, so a mode/status/day never reads one
    // way for the admin and another for the business.
    $modeLabels = TripSchedule::modeLabels();
    $statusLabels = TripSchedule::statusLabels();
    $scopeLabels = TripSchedule::scopeLabels();
    $dayLabels = TripSchedule::dayLabels();
@endphp

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('خطوط التشغيل (الجدولة)') }}</h1>
            <div class="a2-page-subtitle">{{ __('إشراف على رحلات الشحن ونقل الركاب والليموزين والتوزيع، محلي ودولي.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.trip-schedules.reservations') }}" class="a2-btn a2-btn-ghost">{{ __('الحجوزات') }}</a>
        </div>
    </div>

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('إجمالي الخطوط') }}</div>
            <div class="a2-stat-value">{{ number_format($totals['count'] ?? 0) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('المفعلة') }}</div>
            <div class="a2-stat-value">{{ number_format($totals['active'] ?? 0) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('دولية') }}</div>
            <div class="a2-stat-value">{{ number_format($totals['international'] ?? 0) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('إجمالي الحجوزات') }}</div>
            <div class="a2-stat-value">{{ number_format($totals['reservations'] ?? 0) }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.trip-schedules.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="{{ __('بحث باسم البزنس') }}">

            <select class="a2-select a2-filter-sm" name="mode">
                <option value="">{{ __('كل الأنماط') }}</option>
                @foreach($modeLabels as $k => $label)
                    <option value="{{ $k }}" {{ $mode === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="scope">
                <option value="">{{ __('محلي + دولي') }}</option>
                @foreach($scopeLabels as $k => $label)
                    <option value="{{ $k }}" {{ $scope === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach($statusLabels as $k => $label)
                    <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === (int) $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.trip-schedules.index') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('البزنس') }}</th>
                        <th>{{ __('النمط') }}</th>
                        <th>{{ __('المركبة') }}</th>
                        <th>{{ __('النطاق') }}</th>
                        <th>{{ __('من → إلى') }}</th>
                        <th>{{ __('اليوم/التاريخ') }}</th>
                        <th>{{ __('السعة') }}</th>
                        <th>{{ __('السعر / العربون') }}</th>
                        <th>{{ __('حجوزات نشطة') }}</th>
                        <th>{{ __('الحالة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $s)
                        <tr>
                            <td>{{ $s->id }}</td>
                            <td class="a2-text-right">{{ optional($s->business)->name ?: '#'.$s->business_id }}</td>
                            <td>{{ $modeLabels[$s->mode] ?? $s->mode }}{{ $s->is_return_leg ? ' ↩' : '' }}</td>
                            <td>{{ optional($s->vehicleType)->name_ar ?: ($s->vehicle_label ?: '—') }}</td>
                            <td>
                                <span class="a2-pill {{ $s->scope === TripSchedule::SCOPE_INTERNATIONAL ? 'a2-pill-gray' : 'a2-pill-success' }}">
                                    {{ $scopeLabels[$s->scope] ?? $s->scope }}
                                </span>
                            </td>
                            <td class="a2-text-right">
                                @if($s->scope === 'international')
                                    {{ optional($s->originCountry)->name_ar ?: '—' }} → {{ optional($s->destinationCountry)->name_ar ?: '—' }}
                                @else
                                    {{ optional($s->originGovernorate)->name_ar ?: '—' }} → {{ optional($s->destinationGovernorate)->name_ar ?: '—' }}
                                @endif
                            </td>
                            <td>
                                @if($s->schedule_pattern === TripSchedule::PATTERN_WEEKLY)
                                    {{ $dayLabels[$s->day_of_week] ?? '—' }}
                                @elseif($s->schedule_pattern === TripSchedule::PATTERN_ONE_OFF)
                                    {{ optional($s->trip_date)->toDateString() ?: '—' }}
                                @else
                                    {{ __('عند الطلب') }}
                                @endif
                                <div class="a2-muted">{{ $s->departure_time }}</div>
                            </td>
                            <td>{{ $s->capacity !== null ? $s->capacity.' '.$s->capacity_unit : '∞' }}</td>
                            <td>{{ $s->price !== null ? number_format((float) $s->price, 2) : '—' }} / {{ $s->deposit_per_unit ? number_format((float) $s->deposit_per_unit, 2) : '—' }}</td>
                            <td>{{ (int) $s->active_reservations_count }}</td>
                            <td>
                                <span class="a2-pill {{ $s->status === TripSchedule::STATUS_ACTIVE ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                    {{ $statusLabels[$s->status] ?? $s->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="a2-empty-cell">{{ __('لا توجد خطوط تشغيل.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $schedules->links() }}</div>
    </div>
</div>
@endsection
