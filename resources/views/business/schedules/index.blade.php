@extends('business.layouts.master')

@section('title', 'خطوط التشغيل')

@php
    use App\Models\TripSchedule;

    $modeLabels = TripSchedule::modeLabels();
    $statusLabels = TripSchedule::statusLabels();
    $scopeLabels = TripSchedule::scopeLabels();
    $dayLabels = TripSchedule::dayLabels();
@endphp

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">خطوط التشغيل</h1>
        <div class="a2-page-subtitle">انشر الطريق الذي تسير فيه ويومه، ليظهر لمن يبحث عن «من يسافر من … إلى … يوم …».</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.schedules.reservations.index') }}" class="a2-btn a2-btn-ghost">الحجوزات</a>
        <a href="{{ route('business.schedules.create') }}" class="a2-btn a2-btn-primary">نشر خط جديد</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

<div class="a2-card a2-card--tight">
    <form method="GET" action="{{ route('business.schedules.index') }}" class="a2-filterbar">
        <select class="a2-select a2-filter-sm" name="mode">
            <option value="">كل الأنماط</option>
            @foreach($modeLabels as $key => $label)
                <option value="{{ $key }}" @selected($mode === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <select class="a2-select a2-filter-sm" name="status">
            <option value="">كل الحالات</option>
            @foreach($statusLabels as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
        </select>

        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
            <a class="a2-btn a2-btn-ghost" href="{{ route('business.schedules.index') }}">إعادة ضبط</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>النمط</th>
                    <th>المركبة</th>
                    <th>من → إلى</th>
                    <th>اليوم / الموعد</th>
                    <th>المتاح</th>
                    <th>السعر / العربون</th>
                    <th>الحالة</th>
                    <th class="a2-text-right">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $left = $remaining[$row->id] ?? null;
                        $isIntl = $row->scope === TripSchedule::SCOPE_INTERNATIONAL;
                    @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>
                            {{ $modeLabels[$row->mode] ?? $row->mode }}
                            @if($row->is_return_leg)
                                <span class="a2-pill a2-pill-sub" title="رحلة عودة">عودة ↩</span>
                            @endif
                        </td>
                        <td>{{ optional($row->vehicleType)->name_ar ?: ($row->vehicle_label ?: '—') }}</td>
                        <td class="a2-text-right">
                            @if($isIntl)
                                {{ optional($row->originCountry)->name_ar ?: '—' }} → {{ optional($row->destinationCountry)->name_ar ?: '—' }}
                                <span class="a2-pill a2-pill-gray">{{ $scopeLabels[TripSchedule::SCOPE_INTERNATIONAL] ?? '' }}</span>
                            @else
                                {{ optional($row->originGovernorate)->name_ar ?: '—' }} → {{ optional($row->destinationGovernorate)->name_ar ?: '—' }}
                            @endif
                        </td>
                        <td>
                            @if($row->schedule_pattern === TripSchedule::PATTERN_WEEKLY)
                                {{ $dayLabels[$row->day_of_week] ?? '—' }}
                            @elseif($row->schedule_pattern === TripSchedule::PATTERN_ONE_OFF)
                                {{ optional($row->trip_date)->toDateString() ?: '—' }}
                            @else
                                عند الطلب
                            @endif
                            @if($row->departure_time)
                                <div class="a2-muted">{{ $row->departure_time }}</div>
                            @endif
                        </td>
                        <td>
                            @if($row->capacity === null)
                                <span class="a2-muted">غير محدودة</span>
                            @else
                                <span class="a2-fw-900">{{ $left ?? $row->capacity }}</span>
                                <span class="a2-muted">/ {{ $row->capacity }} {{ $row->capacity_unit }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $row->price !== null ? number_format((float) $row->price, 2) : '—' }}
                            <span class="a2-muted">/ {{ $row->deposit_per_unit ? number_format((float) $row->deposit_per_unit, 2) : '—' }}</span>
                        </td>
                        <td>
                            <span class="a2-pill {{ $row->status === TripSchedule::STATUS_ACTIVE ? 'a2-pill-success' : 'a2-pill-gray' }}">
                                {{ $statusLabels[$row->status] ?? $row->status }}
                            </span>
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions" style="align-items:center;">
                                <a href="{{ route('business.schedules.edit', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">تعديل</a>

                                @if($row->capacity !== null)
                                    <form method="POST" action="{{ route('business.schedules.block', $row->id) }}" style="display:flex;gap:6px;align-items:center;" title="حجز مقاعد بِعتها خارج التطبيق">
                                        @csrf
                                        <input class="a2-input" name="units" type="number" min="1" value="1" style="width:64px;padding:7px 9px;" required>
                                        <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">حجز يدوي</button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('business.schedules.destroy', $row->id) }}" onsubmit="return confirm('حذف خط التشغيل؟ لن يظهر بعدها في البحث.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="a2-empty">لا خطوط تشغيل بعد. انشر خطاً ليجدك العملاء في البحث.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="a2-pagination">{{ $rows->links() }}</div>
</div>
@endsection
