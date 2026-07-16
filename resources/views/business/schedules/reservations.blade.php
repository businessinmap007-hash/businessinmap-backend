@extends('business.layouts.master')

@section('title', 'حجوزات الرحلات')

@php
    use App\Models\TripReservation;
    use App\Models\TripSchedule;

    $dayLabels = TripSchedule::dayLabels();
    $statusLabels = TripReservation::statusLabels();

    $statusPills = [
        TripReservation::STATUS_PENDING => 'a2-pill-warning',
        TripReservation::STATUS_CONFIRMED => 'a2-pill-success',
        TripReservation::STATUS_COMPLETED => 'a2-pill-success',
        TripReservation::STATUS_CANCELLED => 'a2-pill-gray',
        TripReservation::STATUS_BLOCKED => 'a2-pill-sub',
    ];
@endphp

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">حجوزات الرحلات</h1>
        <div class="a2-page-subtitle">أكّد الحجز ليحتفظ العميل بمكانه، وأكمله بعد الرحلة ليُسجَّل التقييم للطرفين ويُرد العربون.</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.schedules.index') }}" class="a2-btn a2-btn-ghost">خطوط التشغيل</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="a2-card a2-card--tight">
    <form method="GET" action="{{ route('business.schedules.reservations.index') }}" class="a2-filterbar">
        <select class="a2-select a2-filter-sm" name="status">
            <option value="">كل الحالات</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select class="a2-select" name="trip_schedule_id">
            <option value="">كل الخطوط</option>
            @foreach($legs as $leg)
                <option value="{{ $leg->id }}" @selected($scheduleId === (int) $leg->id)>
                    #{{ $leg->id }} — {{ optional($leg->originGovernorate)->name_ar ?: '—' }} → {{ optional($leg->destinationGovernorate)->name_ar ?: '—' }}
                </option>
            @endforeach
        </select>

        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
            <a class="a2-btn a2-btn-ghost" href="{{ route('business.schedules.reservations.index') }}">إعادة ضبط</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>العميل</th>
                    <th>الخط</th>
                    <th>الموعد</th>
                    <th>الوحدات</th>
                    <th>الإجمالي / العربون</th>
                    <th>المصدر</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php $leg = $row->schedule; @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td class="a2-text-right">
                            @if($row->source === TripReservation::SOURCE_OFFLINE)
                                <span class="a2-muted">تعامل خارج التطبيق</span>
                            @else
                                <div class="a2-fw-900">{{ optional($row->client)->name ?: '—' }}</div>
                                <div class="a2-muted">{{ optional($row->client)->phone }}</div>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            @if($leg)
                                #{{ $leg->id }} — {{ optional($leg->originGovernorate)->name_ar ?: '—' }} → {{ optional($leg->destinationGovernorate)->name_ar ?: '—' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($leg && $leg->day_of_week !== null)
                                {{ $dayLabels[$leg->day_of_week] ?? '—' }}
                            @else
                                <span class="a2-muted">—</span>
                            @endif
                            @if($leg && $leg->departure_time)
                                <div class="a2-muted">{{ $leg->departure_time }}</div>
                            @endif
                        </td>
                        <td>{{ $row->units }} {{ optional($leg)->capacity_unit }}</td>
                        <td>
                            {{ $row->total_price !== null ? number_format((float) $row->total_price, 2) : '—' }} {{ $row->currency }}
                            @if((float) $row->deposit_held > 0)
                                <div class="a2-muted">عربون محجوز: {{ number_format((float) $row->deposit_held, 2) }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="a2-pill {{ $row->source === TripReservation::SOURCE_OFFLINE ? 'a2-pill-sub' : 'a2-pill-gray' }}">
                                {{ $row->source === TripReservation::SOURCE_OFFLINE ? 'يدوي' : 'التطبيق' }}
                            </span>
                        </td>
                        <td>
                            <span class="a2-pill {{ $statusPills[$row->status] ?? 'a2-pill-gray' }}">
                                {{ $statusLabels[$row->status] ?? $row->status }}
                            </span>
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions" style="align-items:center;">
                                @if($row->status === TripReservation::STATUS_PENDING)
                                    <form method="POST" action="{{ route('business.schedules.reservations.confirm', $row->id) }}">
                                        @csrf
                                        <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">تأكيد</button>
                                    </form>
                                @endif

                                @if($row->status === TripReservation::STATUS_CONFIRMED)
                                    <form method="POST" action="{{ route('business.schedules.reservations.complete', $row->id) }}" onsubmit="return confirm('إكمال الرحلة؟ سيُسجَّل تقييم ناجح للطرفين ويُرد العربون.');">
                                        @csrf
                                        <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">إكمال</button>
                                    </form>
                                @endif

                                @if(in_array($row->status, [TripReservation::STATUS_PENDING, TripReservation::STATUS_CONFIRMED, TripReservation::STATUS_BLOCKED], true))
                                    <form method="POST" action="{{ route('business.schedules.reservations.reject', $row->id) }}" onsubmit="return confirm('إلغاء هذا الحجز وتحرير السعة؟');">
                                        @csrf
                                        <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">
                                            {{ $row->status === TripReservation::STATUS_BLOCKED ? 'تحرير' : 'رفض' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="a2-empty">لا حجوزات بعد.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="a2-pagination">{{ $rows->links() }}</div>
</div>
@endsection
