@extends('admin-v2.layouts.master')

@section('title', 'Bookings')

@section('content')
@php
    $totalRows = $rows->total();
    $activeRows = collect($rows->items())->where('status', \App\Models\Booking::STATUS_IN_PROGRESS)->count();
    $completedRows = collect($rows->items())->where('status', \App\Models\Booking::STATUS_COMPLETED)->count();
    $avgPrice = collect($rows->items())->avg(fn($r) => (float) ($r->price ?? 0));
@endphp

<div class="a2-page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
    <div>
        <h1 class="a2-page-title" style="margin:0;">الحجوزات</h1>
        <div class="a2-page-subtitle" style="margin-top:6px;">
            إدارة الحجوزات مع الأسعار والديبوزت وحالة التنفيذ
        </div>
    </div>

    <div class="a2-page-actions">
        <a href="{{ route('admin.bookings.create') }}" class="a2-btn a2-btn-primary">
            + إضافة حجز
        </a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success" style="margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

<div class="bk-stats-grid">
    <div class="a2-card bk-stat-card stat-blue">
        <div class="stat-icon">📋</div>
        <div class="stat-content">
            <div class="bk-stat-label">عدد السجلات</div>
            <div class="bk-stat-value">{{ number_format($totalRows) }}</div>
        </div>
    </div>

    <div class="a2-card bk-stat-card stat-green">
        <div class="stat-icon">⚙️</div>
        <div class="stat-content">
            <div class="bk-stat-label">قيد التنفيذ</div>
            <div class="bk-stat-value">{{ number_format($activeRows) }}</div>
        </div>
    </div>

    <div class="a2-card bk-stat-card stat-orange">
        <div class="stat-icon">✅</div>
        <div class="stat-content">
            <div class="bk-stat-label">المكتملة</div>
            <div class="bk-stat-value">{{ number_format($completedRows) }}</div>
        </div>
    </div>

    <div class="a2-card bk-stat-card stat-purple">
        <div class="stat-icon">💳</div>
        <div class="stat-content">
            <div class="bk-stat-label">متوسط السعر</div>
            <div class="bk-stat-value">{{ number_format((float)$avgPrice, 2) }} EGP</div>
        </div>
    </div>
</div>

<div class="a2-card bk-filter-card">
    <form method="GET" action="{{ route('admin.bookings.index') }}">
        <div class="bk-filter-grid">
            <div class="bk-field">
                <label class="a2-label">بحث</label>
                <input type="text" name="q" class="a2-input" value="{{ $q }}" placeholder="رقم / ملاحظات / user_id / business_id / service_id">
            </div>

            <div class="bk-field">
                <label class="a2-label">الحالة</label>
                <select name="status" class="a2-select">
                    <option value="">الكل</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="bk-field">
                <label class="a2-label">التاريخ</label>
                <input type="date" name="date" class="a2-input" value="{{ $date }}">
            </div>

            <div class="bk-field">
                <label class="a2-label">عدد الصفوف</label>
                <select name="per_page" class="a2-select">
                    @foreach([10,20,50,100] as $n)
                        <option value="{{ $n }}" @selected((int)$perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>

            <div class="bk-field">
                <label class="a2-label">الترتيب</label>
                <select name="sort" class="a2-select">
                    <option value="starts_at" @selected($sort==='starts_at')>starts_at</option>
                    <option value="ends_at" @selected($sort==='ends_at')>ends_at</option>
                    <option value="status" @selected($sort==='status')>status</option>
                    <option value="id" @selected($sort==='id')>id</option>
                </select>
            </div>
        </div>

        <div class="bk-filter-actions">
            <a href="{{ route('admin.bookings.index') }}" class="a2-btn">إعادة ضبط</a>
            <button type="submit" class="a2-btn a2-btn-primary">تصفية</button>
        </div>
    </form>
</div>

<div class="a2-card bk-table-card">
    <div class="table-responsive">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>العميل</th>
                    <th>البزنس</th>
                    <th>الخدمة</th>
                    <th>السعر</th>
                    <th>الديبوزت</th>
                    <th>التاريخ</th>
                    <th>الحالة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $item)
                    @php
                        $pricing = is_array($item->meta['pricing'] ?? null) ? $item->meta['pricing'] : [];
                        $depositPolicy = is_array($item->meta['deposit_policy'] ?? null) ? $item->meta['deposit_policy'] : [];
                        $finalPrice = (float)($pricing['final_price'] ?? $item->price ?? 0);
                        $originalPrice = (float)($pricing['original_price'] ?? $finalPrice);
                        $discountAmount = (float)($pricing['discount_amount'] ?? 0);
                        $discountPercent = (int)($pricing['discount_percent'] ?? 0);
                        $depositAmount = (float)($depositPolicy['amount'] ?? $depositPolicy['hold'] ?? 0);
                    @endphp
                    <tr>
                        <td>#{{ $item->id }}</td>

                        <td>
                            <div class="bk-main-text">{{ $item->user->name ?? '-' }}</div>
                            @if(!empty($item->user?->phone))
                                <div class="bk-sub-text">{{ $item->user->phone }}</div>
                            @endif
                        </td>

                        <td>
                            <div class="bk-main-text">{{ $item->business->name ?? '-' }}</div>
                            @if(!empty($item->business?->phone))
                                <div class="bk-sub-text">{{ $item->business->phone }}</div>
                            @endif
                        </td>

                        <td>
                            <div class="bk-main-text">{{ $item->service->name_ar ?? $item->service->name_en ?? '-' }}</div>
                            @if(!empty($item->service?->key))
                                <div class="bk-sub-text">{{ $item->service->key }}</div>
                            @endif
                        </td>

                        <td>
                            <div class="bk-money">{{ number_format($finalPrice, 2) }} EGP</div>
                            @if($discountAmount > 0)
                                <div class="bk-sub-text">
                                    قبل الخصم: {{ number_format($originalPrice, 2) }} —
                                    خصم {{ $discountPercent }}%
                                </div>
                            @endif
                        </td>

                        <td>
                            @if($depositAmount > 0)
                                <div class="bk-money">{{ number_format($depositAmount, 2) }} EGP</div>
                                <div class="bk-sub-text">
                                    {{ (int)($depositPolicy['configured_percent'] ?? 0) }}%
                                </div>
                            @else
                                <span class="a2-badge">لا يوجد</span>
                            @endif
                        </td>

                        <td>
                            <div class="bk-main-text">
                                {{ optional($item->starts_at)->format('Y-m-d H:i') ?: ($item->date?->format('Y-m-d') . ' ' . ($item->time ?? '')) }}
                            </div>
                        </td>

                        <td>
                            @if($item->status === \App\Models\Booking::STATUS_COMPLETED)
                                <span class="a2-badge a2-badge-success">Completed</span>
                            @elseif($item->status === \App\Models\Booking::STATUS_IN_PROGRESS)
                                <span class="a2-badge">In Progress</span>
                            @elseif($item->status === \App\Models\Booking::STATUS_CANCELLED)
                                <span class="a2-badge a2-badge-danger">Cancelled</span>
                            @else
                                <span class="a2-badge">{{ $statusOptions[$item->status] ?? $item->status }}</span>
                            @endif
                        </td>

                        <td>
                            <div class="bk-actions">
                                <a href="{{ route('admin.bookings.show', $item) }}" class="a2-btn a2-btn-sm">عرض</a>
                                <a href="{{ route('admin.bookings.edit', $item) }}" class="a2-btn a2-btn-sm">تعديل</a>

                                <form method="POST" action="{{ route('admin.bookings.destroy', $item) }}" onsubmit="return confirm('هل أنت متأكد من حذف الحجز؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="a2-btn a2-btn-sm a2-btn-danger">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center">لا توجد بيانات</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="padding:14px;">
        {{ $rows->links() }}
    </div>
</div>

<style>
.bk-stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}
.bk-stat-card{padding:14px;min-height:120px;display:flex;align-items:center;gap:12px;border-radius:16px}
.stat-icon{width:52px;height:52px;min-width:52px;display:flex;align-items:center;justify-content:center;font-size:22px;border-radius:14px;background:#f3f4f6}
.stat-content{min-width:0;flex:1}
.bk-stat-label{font-size:13px;color:#6b7280;margin-bottom:6px;line-height:1.4}
.bk-stat-value{font-size:18px;font-weight:800;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.stat-blue .stat-icon{background:#e0f2fe}
.stat-green .stat-icon{background:#dcfce7}
.stat-orange .stat-icon{background:#ffedd5}
.stat-purple .stat-icon{background:#f3e8ff}

.bk-filter-card{padding:16px;margin-bottom:16px}
.bk-filter-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;align-items:end}
.bk-field{min-width:0}
.bk-filter-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px;flex-wrap:wrap}

.bk-table-card{padding:0;overflow:hidden}
.bk-main-text{font-weight:800}
.bk-sub-text{font-size:12px;color:#6b7280;margin-top:2px}
.bk-money{font-weight:800;white-space:nowrap}
.bk-actions{display:flex;gap:6px;flex-wrap:wrap}
.bk-actions form{display:inline-block}

@media (max-width: 1200px){
    .bk-stats-grid,.bk-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media (max-width: 700px){
    .bk-stats-grid,.bk-filter-grid{grid-template-columns:1fr}
}
</style>
@endsection