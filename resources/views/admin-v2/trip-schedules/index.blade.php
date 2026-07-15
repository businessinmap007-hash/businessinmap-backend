@extends('admin-v2.layouts.master')

@section('title', 'Trip Schedules')
@section('topbar_title', 'Trip Schedules')
@section('body_class', 'admin-v2-trip-schedules')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">خطوط التشغيل (الجدولة)</h1>
            <div class="a2-page-subtitle">إشراف على رحلات الشحن ونقل الركاب والليموزين والتوزيع، محلي ودولي.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.trip-schedules.reservations') }}" class="a2-btn a2-btn-ghost">الحجوزات</a>
        </div>
    </div>

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي الخطوط</div>
            <div class="a2-stat-value">{{ number_format($totals['count'] ?? 0) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">المفعلة</div>
            <div class="a2-stat-value">{{ number_format($totals['active'] ?? 0) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">دولية</div>
            <div class="a2-stat-value">{{ number_format($totals['international'] ?? 0) }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي الحجوزات</div>
            <div class="a2-stat-value">{{ number_format($totals['reservations'] ?? 0) }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.trip-schedules.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="بحث باسم البزنس">

            <select class="a2-select a2-filter-sm" name="mode">
                <option value="">كل الأنماط</option>
                @foreach(['freight'=>'شحن','passenger'=>'ركاب','limousine'=>'ليموزين','distribution'=>'توزيع'] as $k=>$label)
                    <option value="{{ $k }}" {{ $mode === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="scope">
                <option value="">محلي + دولي</option>
                <option value="domestic" {{ $scope === 'domestic' ? 'selected' : '' }}>محلي</option>
                <option value="international" {{ $scope === 'international' ? 'selected' : '' }}>دولي</option>
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="">كل الحالات</option>
                @foreach(['active'=>'مفعل','paused'=>'موقوف','expired'=>'منتهٍ','cancelled'=>'ملغي'] as $k=>$label)
                    <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === (int) $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.trip-schedules.index') }}">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>البزنس</th>
                        <th>النمط</th>
                        <th>المركبة</th>
                        <th>النطاق</th>
                        <th>من → إلى</th>
                        <th>اليوم/التاريخ</th>
                        <th>السعة</th>
                        <th>السعر / العربون</th>
                        <th>حجوزات نشطة</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $s)
                        <tr>
                            <td>{{ $s->id }}</td>
                            <td class="a2-text-right">{{ optional($s->business)->name ?: '#'.$s->business_id }}</td>
                            <td>{{ $s->mode }}{{ $s->is_return_leg ? ' ↩' : '' }}</td>
                            <td>{{ optional($s->vehicleType)->name_ar ?: ($s->vehicle_label ?: '—') }}</td>
                            <td>
                                <span class="a2-pill {{ $s->scope === 'international' ? 'a2-pill-gray' : 'a2-pill-success' }}">
                                    {{ $s->scope === 'international' ? 'دولي' : 'محلي' }}
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
                                @if($s->schedule_pattern === 'weekly')
                                    يوم {{ $s->day_of_week }}
                                @elseif($s->schedule_pattern === 'one_off')
                                    {{ optional($s->trip_date)->toDateString() }}
                                @else
                                    عند الطلب
                                @endif
                                <div class="a2-muted">{{ $s->departure_time }}</div>
                            </td>
                            <td>{{ $s->capacity !== null ? $s->capacity.' '.$s->capacity_unit : '∞' }}</td>
                            <td>{{ $s->price !== null ? number_format((float) $s->price, 2) : '—' }} / {{ $s->deposit_per_unit ? number_format((float) $s->deposit_per_unit, 2) : '—' }}</td>
                            <td>{{ (int) $s->active_reservations_count }}</td>
                            <td>
                                <span class="a2-pill {{ $s->status === 'active' ? 'a2-pill-success' : 'a2-pill-gray' }}">{{ $s->status }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="a2-empty-cell">لا توجد خطوط تشغيل.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $schedules->links() }}</div>
    </div>
</div>
@endsection
