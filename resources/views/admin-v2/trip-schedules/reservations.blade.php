@extends('admin-v2.layouts.master')

@section('title', 'Trip Reservations')
@section('topbar_title', 'Trip Reservations')
@section('body_class', 'admin-v2-trip-reservations')

@php
    use App\Models\TripReservation;
    use App\Models\TripSchedule;

    $statusLabels = TripReservation::statusLabels();
    $sourceLabels = TripReservation::sourceLabels();
    $modeLabels = TripSchedule::modeLabels();
@endphp

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('حجوزات الرحلات') }}</h1>
            <div class="a2-page-subtitle">{{ __('حجوزات العملاء والحجوزات اليدوية للناقلين (خارج التطبيق).') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.trip-schedules.index') }}" class="a2-btn a2-btn-ghost">{{ __('خطوط التشغيل') }}</a>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.trip-schedules.reservations') }}" class="a2-filterbar">
            <select class="a2-select a2-filter-sm" name="status">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach($statusLabels as $k => $label)
                    <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="source">
                <option value="">{{ __('كل المصادر') }}</option>
                @foreach($sourceLabels as $k => $label)
                    <option value="{{ $k }}" {{ $source === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === (int) $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.trip-schedules.reservations') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('خط التشغيل') }}</th>
                        <th>{{ __('الناقل') }}</th>
                        <th>{{ __('العميل') }}</th>
                        <th>{{ __('المصدر') }}</th>
                        <th>{{ __('الوحدات') }}</th>
                        <th>{{ __('الإجمالي') }}</th>
                        <th>{{ __('العربون المحجوز') }}</th>
                        <th>{{ __('الحالة') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $r)
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td>
                                #{{ $r->trip_schedule_id }}
                                <span class="a2-muted">{{ $modeLabels[optional($r->schedule)->mode] ?? optional($r->schedule)->mode }}</span>
                            </td>
                            <td class="a2-text-right">{{ optional($r->business)->name ?: '#'.$r->business_id }}</td>
                            <td class="a2-text-right">{{ $r->client_id ? (optional($r->client)->name ?: '#'.$r->client_id) : '—' }}</td>
                            <td>
                                <span class="a2-pill {{ $r->source === TripReservation::SOURCE_OFFLINE ? 'a2-pill-gray' : 'a2-pill-success' }}">
                                    {{ $sourceLabels[$r->source] ?? $r->source }}
                                </span>
                            </td>
                            <td>{{ (int) $r->units }}</td>
                            <td>{{ $r->total_price !== null ? number_format((float) $r->total_price, 2) : '—' }}</td>
                            <td>{{ (float) $r->deposit_held > 0 ? number_format((float) $r->deposit_held, 2) : '—' }}</td>
                            <td>
                                <span class="a2-pill {{ $r->status === TripReservation::STATUS_COMPLETED ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                    {{ $statusLabels[$r->status] ?? $r->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="a2-empty-cell">{{ __('لا توجد حجوزات.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $reservations->links() }}</div>
    </div>
</div>
@endsection
