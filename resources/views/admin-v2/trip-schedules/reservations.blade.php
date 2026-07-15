@extends('admin-v2.layouts.master')

@section('title', 'Trip Reservations')
@section('topbar_title', 'Trip Reservations')
@section('body_class', 'admin-v2-trip-reservations')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">حجوزات الرحلات</h1>
            <div class="a2-page-subtitle">حجوزات العملاء والحجوزات اليدوية للناقلين (خارج التطبيق).</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.trip-schedules.index') }}" class="a2-btn a2-btn-ghost">خطوط التشغيل</a>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.trip-schedules.reservations') }}" class="a2-filterbar">
            <select class="a2-select a2-filter-sm" name="status">
                <option value="">كل الحالات</option>
                @foreach(['pending'=>'قيد الانتظار','confirmed'=>'مؤكد','completed'=>'مكتمل','cancelled'=>'ملغي','blocked'=>'حجز يدوي'] as $k=>$label)
                    <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="source">
                <option value="">كل المصادر</option>
                <option value="app" {{ $source === 'app' ? 'selected' : '' }}>التطبيق</option>
                <option value="offline" {{ $source === 'offline' ? 'selected' : '' }}>خارج التطبيق</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === (int) $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.trip-schedules.reservations') }}">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>خط التشغيل</th>
                        <th>الناقل</th>
                        <th>العميل</th>
                        <th>المصدر</th>
                        <th>الوحدات</th>
                        <th>الإجمالي</th>
                        <th>العربون المحجوز</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reservations as $r)
                        <tr>
                            <td>{{ $r->id }}</td>
                            <td>#{{ $r->trip_schedule_id }} <span class="a2-muted">{{ optional($r->schedule)->mode }}</span></td>
                            <td class="a2-text-right">{{ optional($r->business)->name ?: '#'.$r->business_id }}</td>
                            <td class="a2-text-right">{{ $r->client_id ? (optional($r->client)->name ?: '#'.$r->client_id) : '—' }}</td>
                            <td>
                                <span class="a2-pill {{ $r->source === 'offline' ? 'a2-pill-gray' : 'a2-pill-success' }}">
                                    {{ $r->source === 'offline' ? 'خارج التطبيق' : 'التطبيق' }}
                                </span>
                            </td>
                            <td>{{ (int) $r->units }}</td>
                            <td>{{ $r->total_price !== null ? number_format((float) $r->total_price, 2) : '—' }}</td>
                            <td>{{ (float) $r->deposit_held > 0 ? number_format((float) $r->deposit_held, 2) : '—' }}</td>
                            <td><span class="a2-pill a2-pill-gray">{{ $r->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="a2-empty-cell">لا توجد حجوزات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="a2-pagination">{{ $reservations->links() }}</div>
    </div>
</div>
@endsection
