@extends('admin-v2.layouts.master')

@section('title', 'Bookings')
@section('body_class', 'admin-v2 admin-v2-bookings-index')

@section('content')
@php
    $items = collect($rows->items());

    $totalRows = $rows->total();
    $activeRows = $items->where('status', \App\Models\Booking::STATUS_IN_PROGRESS)->count();
    $completedRows = $items->where('status', \App\Models\Booking::STATUS_COMPLETED)->count();
    $avgPrice = $items->avg(fn($r) => (float) ($r->price ?? 0));

    $chargedRows = $items->filter(function ($r) {
        $meta = is_array($r->meta ?? null) ? $r->meta : [];
        return ! empty(data_get($meta, '_execution_fee.charged_at'));
    })->count();
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الحجوزات</h1>
            <div class="a2-page-subtitle">
                إدارة الحجوزات مع الأسعار والديبوزت ورسوم التنفيذ وخصم المحافظ.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookings.create') }}" class="a2-btn a2-btn-primary">
                + إضافة حجز
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="a2-alert a2-alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-stat-grid">
        <div class="a2-stat-card">
            <div class="a2-stat-label">عدد السجلات</div>
            <div class="a2-stat-value">{{ number_format($totalRows) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">قيد التنفيذ</div>
            <div class="a2-stat-value">{{ number_format($activeRows) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">المكتملة</div>
            <div class="a2-stat-value">{{ number_format($completedRows) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">تم خصم رسوم التنفيذ</div>
            <div class="a2-stat-value">{{ number_format($chargedRows) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">متوسط السعر بالصفحة</div>
            <div class="a2-stat-value">{{ number_format((float)$avgPrice, 2) }} EGP</div>
        </div>
    </div>

    <div class="a2-card a2-mt-16">
        <form method="GET" action="{{ route('admin.bookings.index') }}" class="a2-filterbar">
            <div class="a2-filter-search">
                <label class="a2-label">بحث</label>
                <input
                    type="text"
                    name="q"
                    class="a2-input"
                    value="{{ $q }}"
                    placeholder="رقم / ملاحظات / user_id / business_id / service_id"
                >
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">الحالة</label>
                <select name="status" class="a2-select">
                    <option value="">الكل</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">التاريخ</label>
                <input type="date" name="date" class="a2-input" value="{{ $date }}">
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">عدد الصفوف</label>
                <select name="per_page" class="a2-select">
                    @foreach([10,20,50,100] as $n)
                        <option value="{{ $n }}" @selected((int)$perPage === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">الترتيب</label>
                <select name="sort" class="a2-select">
                    <option value="starts_at" @selected($sort === 'starts_at')>starts_at</option>
                    <option value="ends_at" @selected($sort === 'ends_at')>ends_at</option>
                    <option value="status" @selected($sort === 'status')>status</option>
                    <option value="id" @selected($sort === 'id')>id</option>
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">الاتجاه</label>
                <select name="dir" class="a2-select">
                    <option value="desc" @selected(($dir ?? 'desc') === 'desc')>DESC</option>
                    <option value="asc" @selected(($dir ?? 'desc') === 'asc')>ASC</option>
                </select>
            </div>

            <div class="a2-filter-actions">
                <a href="{{ route('admin.bookings.index') }}" class="a2-btn a2-btn-ghost">
                    إعادة ضبط
                </a>
                <button type="submit" class="a2-btn a2-btn-primary">
                    تصفية
                </button>
            </div>
        </form>
    </div>

    <div class="a2-card a2-mt-16">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th style="min-width:70px;">#</th>
                        <th style="min-width:160px;">العميل</th>
                        <th style="min-width:170px;">البزنس</th>
                        <th style="min-width:150px;">الخدمة</th>
                        <th style="min-width:130px;">السعر</th>
                        <th style="min-width:120px;">الديبوزت</th>
                        <th style="min-width:160px;">رسوم التنفيذ</th>
                        <th style="min-width:140px;">التاريخ</th>
                        <th style="min-width:120px;">الحالة</th>
                        <th style="min-width:170px;">الإجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $item)
                        @php
                            $meta = is_array($item->meta ?? null) ? $item->meta : [];

                            $pricing = is_array($meta['pricing'] ?? null) ? $meta['pricing'] : [];
                            $depositPolicy = is_array($meta['deposit_policy'] ?? null) ? $meta['deposit_policy'] : [];
                            $executionFee = is_array($meta['_execution_fee'] ?? null) ? $meta['_execution_fee'] : [];

                            $finalPrice = (float)($pricing['final_price'] ?? $item->price ?? 0);
                            $originalPrice = (float)($pricing['original_price'] ?? $finalPrice);
                            $discountAmount = (float)($pricing['discount_amount'] ?? 0);
                            $discountPercent = (int)($pricing['discount_percent'] ?? 0);
                            $currency = (string)($pricing['currency'] ?? 'EGP');

                            $depositAmount = (float)($depositPolicy['amount'] ?? $depositPolicy['hold'] ?? 0);

                            $clientFee = (float)($executionFee['client_amount'] ?? 0);
                            $businessFee = (float)($executionFee['business_amount'] ?? 0);
                            $chargedAt = $executionFee['charged_at'] ?? null;

                            $statusLabel = $statusOptions[$item->status] ?? $item->status;
                        @endphp

                        <tr>
                            <td>
                                <div class="a2-fw-900">#{{ $item->id }}</div>
                            </td>

                            <td class="a2-text-right">
                                <div class="a2-fw-900">{{ $item->user->name ?? '-' }}</div>

                                @if(!empty($item->user?->phone))
                                    <div class="a2-muted a2-mt-8">{{ $item->user->phone }}</div>
                                @endif
                            </td>

                            <td class="a2-text-right">
                                <div class="a2-fw-900">{{ $item->business->name ?? '-' }}</div>

                                @if(!empty($item->business?->phone))
                                    <div class="a2-muted a2-mt-8">{{ $item->business->phone }}</div>
                                @endif

                                @if(!empty($item->business?->category_child_id))
                                    <div class="a2-muted a2-mt-8">
                                        Child: {{ $item->business->category_child_id }}
                                    </div>
                                @endif
                            </td>

                            <td class="a2-text-right">
                                <div class="a2-fw-900">
                                    {{ $item->service->name_ar ?? $item->service->name_en ?? '-' }}
                                </div>

                                @if(!empty($item->service?->key))
                                    <div class="a2-muted a2-mt-8" dir="ltr">
                                        {{ $item->service->key }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div class="a2-fw-900">
                                    {{ number_format($finalPrice, 2) }} {{ $currency }}
                                </div>

                                @if($discountAmount > 0)
                                    <div class="a2-muted a2-mt-8">
                                        قبل الخصم: {{ number_format($originalPrice, 2) }}
                                    </div>
                                    <div class="a2-muted">
                                        خصم {{ $discountPercent }}%
                                    </div>
                                @endif
                            </td>

                            <td>
                                @if($depositAmount > 0)
                                    <div class="a2-fw-900">
                                        {{ number_format($depositAmount, 2) }} {{ $currency }}
                                    </div>
                                    <div class="a2-muted a2-mt-8">
                                        {{ (int)($depositPolicy['configured_percent'] ?? 0) }}%
                                    </div>
                                @else
                                    <span class="a2-pill a2-pill-gray">لا يوجد</span>
                                @endif
                            </td>

                            <td>
                                @if($chargedAt)
                                    <span class="a2-pill a2-pill-success">Charged</span>
                                    <div class="a2-muted a2-mt-8">
                                        Client: {{ number_format($clientFee, 2) }}
                                    </div>
                                    <div class="a2-muted">
                                        Business: {{ number_format($businessFee, 2) }}
                                    </div>
                                @else
                                    <span class="a2-pill a2-pill-gray">Not charged</span>
                                    <div class="a2-muted a2-mt-8">
                                        يتم الخصم عند in_progress
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div class="a2-fw-900">
                                    {{ optional($item->starts_at)->format('Y-m-d H:i') ?: (($item->date?->format('Y-m-d') ?: '') . ' ' . ($item->time ?? '')) }}
                                </div>

                                @if($item->ends_at)
                                    <div class="a2-muted a2-mt-8">
                                        إلى: {{ optional($item->ends_at)->format('Y-m-d H:i') }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                @if($item->status === \App\Models\Booking::STATUS_COMPLETED)
                                    <span class="a2-pill a2-pill-success">Completed</span>
                                @elseif($item->status === \App\Models\Booking::STATUS_IN_PROGRESS)
                                    <span class="a2-pill a2-pill-active">In Progress</span>
                                @elseif($item->status === \App\Models\Booking::STATUS_CANCELLED)
                                    <span class="a2-pill a2-pill-danger">Cancelled</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">{{ $statusLabel }}</span>
                                @endif
                            </td>

                            <td>
                                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                    <a href="{{ route('admin.bookings.show', $item) }}" class="a2-btn a2-btn-sm a2-btn-ghost">
                                        عرض
                                    </a>

                                    <a href="{{ route('admin.bookings.edit', $item) }}" class="a2-btn a2-btn-sm a2-btn-ghost">
                                        تعديل
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.bookings.destroy', $item) }}"
                                        onsubmit="return confirm('هل أنت متأكد من حذف الحجز؟');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="a2-btn a2-btn-sm a2-btn-danger">
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="a2-empty-cell">
                                لا توجد بيانات
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-mt-16">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection