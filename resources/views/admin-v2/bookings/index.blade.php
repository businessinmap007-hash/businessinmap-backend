@extends('admin-v2.layouts.master')

@section('title', 'Bookings')
@section('body_class', 'admin-v2 admin-v2-bookings-index admin-v2-bookings-index-compact')

@section('content')
@php
    $items = collect($rows->items());

    $totalRows = $rows->total();

    $activeRows = $items
        ->where('status', \App\Models\Booking::STATUS_IN_PROGRESS)
        ->count();

    $completedRows = $items
        ->where('status', \App\Models\Booking::STATUS_COMPLETED)
        ->count();

    $chargedRows = $items->filter(function ($r) {
        $meta = is_array($r->meta ?? null) ? $r->meta : [];
        return ! empty(data_get($meta, '_execution_fee.charged_at'));
    })->count();

    $withDepositRows = $items->filter(function ($r) {
        $meta = is_array($r->meta ?? null) ? $r->meta : [];
        return (bool) data_get($meta, 'deposit_policy.required', false);
    })->count();

    $qVal = (string) ($q ?? '');
    $statusVal = (string) ($status ?? '');
    $dateVal = (string) ($date ?? '');
    $perPageVal = (int) ($perPage ?? 50);
    $sortVal = (string) ($sort ?? 'starts_at');
    $dirVal = (string) ($dir ?? 'desc');

    $statusPill = function (?string $status) {
        return match ((string) $status) {
            \App\Models\Booking::STATUS_COMPLETED => 'a2-pill-success',
            \App\Models\Booking::STATUS_IN_PROGRESS => 'a2-pill-active',
            \App\Models\Booking::STATUS_CANCELLED,
            \App\Models\Booking::STATUS_REJECTED => 'a2-pill-danger',
            \App\Models\Booking::STATUS_ACCEPTED => 'a2-pill-warning',
            default => 'a2-pill-gray',
        };
    };

    $statusText = function (?string $status, array $statusOptions) {
        return $statusOptions[$status] ?? $status ?? '—';
    };

    $serviceName = function ($booking) {
        return (string) (
            $booking->service?->name_ar
            ?: $booking->service?->name_en
            ?: $booking->service?->key
            ?: ('Service #' . $booking->service_id)
        );
    };

    $shortText = function ($value, int $limit = 22) {
        $value = trim((string) $value);

        return $value !== ''
            ? \Illuminate\Support\Str::limit($value, $limit)
            : '—';
    };
@endphp

<div class="a2-page">

    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الحجوزات</h1>
            <div class="a2-page-subtitle">
                عرض مختصر للحجوزات. التفاصيل الكاملة داخل صفحة عرض الحجز.
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
            <ul class="bk-error-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-stat-grid bk-compact-stats">
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي</div>
            <div class="a2-stat-value">{{ number_format($totalRows) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">قيد التنفيذ</div>
            <div class="a2-stat-value">{{ number_format($activeRows) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">مكتملة</div>
            <div class="a2-stat-value">{{ number_format($completedRows) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">Deposit</div>
            <div class="a2-stat-value">{{ number_format($withDepositRows) }}</div>
        </div>

        <div class="a2-stat-card">
            <div class="a2-stat-label">رسوم مخصومة</div>
            <div class="a2-stat-value">{{ number_format($chargedRows) }}</div>
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
                    value="{{ $qVal }}"
                    placeholder="رقم الحجز / ملاحظات / user_id / business_id / service_id"
                >
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">الحالة</label>
                <select name="status" class="a2-select">
                    <option value="">الكل</option>
                    @foreach($statusOptions as $key => $label)
                        <option value="{{ $key }}" @selected($statusVal === $key)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">التاريخ</label>
                <input type="date" name="date" class="a2-input" value="{{ $dateVal }}">
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">عدد الصفوف</label>
                <select name="per_page" class="a2-select">
                    @foreach([10, 20, 50, 100] as $n)
                        <option value="{{ $n }}" @selected($perPageVal === $n)>
                            {{ $n }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">الترتيب</label>
                <select name="sort" class="a2-select">
                    <option value="starts_at" @selected($sortVal === 'starts_at')>البداية</option>
                    <option value="ends_at" @selected($sortVal === 'ends_at')>النهاية</option>
                    <option value="status" @selected($sortVal === 'status')>الحالة</option>
                    <option value="price" @selected($sortVal === 'price')>السعر</option>
                    <option value="id" @selected($sortVal === 'id')>رقم الحجز</option>
                </select>
            </div>

            <div class="a2-filter-sm">
                <label class="a2-label">الاتجاه</label>
                <select name="dir" class="a2-select">
                    <option value="desc" @selected($dirVal === 'desc')>الأحدث</option>
                    <option value="asc" @selected($dirVal === 'asc')>الأقدم</option>
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
        <div class="bk-list-head">
            <div>
                <div class="a2-section-title">قائمة الحجوزات</div>
                <div class="a2-section-subtitle">
                    القائمة مختصرة. اضغط عرض لمراجعة كل بيانات الحجز.
                </div>
            </div>

            <div class="bk-list-count">
                {{ number_format($rows->firstItem() ?? 0) }}
                -
                {{ number_format($rows->lastItem() ?? 0) }}
                من
                {{ number_format($rows->total()) }}
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table bk-bookings-table bk-bookings-table-compact">
                <thead>
                    <tr>
                        <th class="bk-col-id">#</th>
                        <th class="bk-col-operation">العملية</th>
                        <th class="bk-col-parties">الأطراف</th>
                        <th class="bk-col-date">الموعد</th>
                        <th class="bk-col-finance">الماليات</th>
                        <th class="bk-col-status">الحالة</th>
                        <th class="bk-col-actions">الإجراءات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $item)
                        @php
                            $meta = is_array($item->meta ?? null) ? $item->meta : [];

                            $pricing = is_array(data_get($meta, 'pricing')) ? data_get($meta, 'pricing') : [];
                            $depositPolicy = is_array(data_get($meta, 'deposit_policy')) ? data_get($meta, 'deposit_policy') : [];
                            $executionFee = is_array(data_get($meta, '_execution_fee')) ? data_get($meta, '_execution_fee') : [];
                            $bookableMeta = is_array(data_get($meta, 'bookable_item')) ? data_get($meta, 'bookable_item') : [];

                            $finalPrice = (float) data_get($pricing, 'final_price', $item->price ?? 0);
                            $currency = (string) data_get($pricing, 'currency', 'EGP');
                            $priceSource = (string) data_get($pricing, 'source', '');

                            $depositRequired = (bool) data_get($depositPolicy, 'required', false);
                            $depositAmount = (float) data_get($depositPolicy, 'amount', data_get($depositPolicy, 'hold', 0));

                            $chargedAt = data_get($executionFee, 'charged_at');
                            $clientFee = (float) data_get($executionFee, 'client_amount', 0);
                            $businessFee = (float) data_get($executionFee, 'business_amount', 0);
                            $totalFee = $clientFee + $businessFee;

                            $durationText = '—';

                            if ((int) ($item->duration_value ?? 0) > 0) {
                                $durationUnit = (string) ($item->duration_unit ?? '');

                                $durationLabel = match ($durationUnit) {
                                    'day' => 'يوم',
                                    'hour' => 'ساعة',
                                    'minute' => 'دقيقة',
                                    default => $durationUnit,
                                };

                                $durationText = (int) $item->duration_value . ' ' . $durationLabel;
                            }

                            $bookableTitle = (string) data_get($bookableMeta, 'title', '');
                            $bookableCode = (string) data_get($bookableMeta, 'code', '');

                            if ($bookableTitle === '' && $item->relationLoaded('bookable') && $item->bookable) {
                                $bookableTitle = (string) ($item->bookable->title ?? '');
                                $bookableCode = (string) ($item->bookable->code ?? '');
                            }

                            $statusLabel = $statusText($item->status, $statusOptions);
                        @endphp

                        <tr>
                            <td>
                                <a href="{{ route('admin.bookings.show', $item) }}" class="bk-booking-id">
                                    #{{ $item->id }}
                                </a>

                                <div class="bk-row-muted" dir="ltr">
                                    booking:{{ $item->id }}
                                </div>
                            </td>

                            <td>
                                <div class="bk-row-title">
                                    {{ $shortText($serviceName($item), 24) }}
                                </div>

                                @if($bookableTitle !== '')
                                    <div class="bk-row-sub">
                                        {{ $shortText($bookableTitle, 24) }}
                                        @if($bookableCode !== '')
                                            <span dir="ltr">({{ $bookableCode }})</span>
                                        @endif
                                    </div>
                                @endif

                                @if($priceSource !== '')
                                    <div class="bk-row-muted" dir="ltr">
                                        {{ $priceSource }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div class="bk-row-pair">
                                    <span>طالب</span>
                                    <strong>{{ $shortText($item->user?->name, 24) }}</strong>
                                </div>

                                <div class="bk-row-pair">
                                    <span>مقدم</span>
                                    <strong>{{ $shortText($item->business?->name, 24) }}</strong>
                                </div>

                                <div class="bk-row-muted" dir="ltr">
                                    U#{{ (int) $item->user_id }} / B#{{ (int) $item->business_id }}
                                </div>
                            </td>

                            <td>
                                <div class="bk-row-title" dir="ltr">
                                    {{ optional($item->starts_at)->format('Y-m-d H:i') ?: (($item->date?->format('Y-m-d') ?: '—') . ' ' . ($item->time ?? '')) }}
                                </div>

                                @if($item->ends_at)
                                    <div class="bk-row-sub" dir="ltr">
                                        إلى {{ optional($item->ends_at)->format('Y-m-d H:i') }}
                                    </div>
                                @endif

                                <div class="bk-row-muted">
                                    {{ $durationText }}
                                </div>
                            </td>

                            <td>
                                <div class="bk-row-title">
                                    {{ number_format($finalPrice, 2) }} {{ $currency }}
                                </div>

                                <div class="bk-finance-tags">
                                    @if($depositRequired && $depositAmount > 0)
                                        <span class="a2-pill a2-pill-warning">
                                            Deposit {{ number_format($depositAmount, 0) }}
                                        </span>
                                    @else
                                        <span class="a2-pill a2-pill-gray">
                                            No Deposit
                                        </span>
                                    @endif

                                    @if($chargedAt)
                                        <span class="a2-pill a2-pill-success">
                                            Fee {{ number_format($totalFee, 0) }}
                                        </span>
                                    @else
                                        <span class="a2-pill a2-pill-gray">
                                            Fee later
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <span class="a2-pill {{ $statusPill($item->status) }}">
                                    {{ $statusLabel }}
                                </span>

                                @if($item->status === \App\Models\Booking::STATUS_IN_PROGRESS)
                                    <div class="bk-row-muted">قيد التنفيذ</div>
                                @elseif($item->status === \App\Models\Booking::STATUS_PENDING)
                                    <div class="bk-row-muted">بانتظار الإجراء</div>
                                @endif
                            </td>

                            <td>
                                <div class="bk-table-actions bk-table-actions-compact">
                                    <a href="{{ route('admin.bookings.show', $item) }}" class="a2-btn a2-btn-sm a2-btn-primary">
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
                            <td colspan="7" class="a2-empty-cell">
                                لا توجد حجوزات مطابقة للفلاتر الحالية
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