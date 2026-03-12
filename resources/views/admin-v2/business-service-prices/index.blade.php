@extends('admin-v2.layouts.master')

@section('title', 'Business Service Prices')

@section('content')
<div class="a2-page-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
    <div>
        <h1 class="a2-page-title" style="margin:0;">خدمات البزنس والأسعار</h1>
        <div class="a2-page-subtitle" style="margin-top:6px;">
            إدارة الأسعار والخصومات والديبوزت لكل بزنس وخدمة
        </div>
    </div>

    <div class="a2-page-actions">
        <a href="{{ route('admin.business_service_prices.create') }}" class="a2-btn a2-btn-primary">
            + إضافة خدمة للبزنس
        </a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success" style="margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

{{-- Stats --}}
<div class="bsp-stats-grid">

    <div class="a2-card bsp-stat-card stat-blue">
        <div class="stat-icon">📊</div>
        <div class="stat-content">
            <div class="bsp-stat-label">عدد السجلات</div>
            <div class="bsp-stat-value">{{ number_format($stats['total_rows']) }}</div>
        </div>
    </div>

    <div class="a2-card bsp-stat-card stat-green">
        <div class="stat-icon">✅</div>
        <div class="stat-content">
            <div class="bsp-stat-label">عدد السجلات النشطة</div>
            <div class="bsp-stat-value">{{ number_format($stats['active_rows']) }}</div>
        </div>
    </div>

    <div class="a2-card bsp-stat-card stat-orange">
        <div class="stat-icon">💰</div>
        <div class="stat-content">
            <div class="bsp-stat-label">السجلات التي بها Deposit</div>
            <div class="bsp-stat-value">{{ number_format($stats['deposit_rows']) }}</div>
        </div>
    </div>

    <div class="a2-card bsp-stat-card stat-purple">
        <div class="stat-icon">💳</div>
        <div class="stat-content">
            <div class="bsp-stat-label">متوسط الأسعار</div>
            <div class="bsp-stat-value">
                {{ number_format((float)$stats['avg_price'],2) }} EGP
            </div>
        </div>
    </div>
    <div class="a2-card bsp-stat-card stat-blue">
<div class="stat-icon">🏢</div>
<div>
<div class="bsp-stat-label">عدد البزنس</div>
<div class="bsp-stat-value">{{ $stats['business_count'] }}</div>
</div>
</div>

<div class="a2-card bsp-stat-card stat-green">
<div class="stat-icon">🛠</div>
<div>
<div class="bsp-stat-label">عدد الخدمات</div>
<div class="bsp-stat-value">{{ $stats['services_count'] }}</div>
</div>
</div>

<div class="a2-card bsp-stat-card stat-orange">
<div class="stat-icon">⬆</div>
<div>
<div class="bsp-stat-label">أعلى سعر</div>
<div class="bsp-stat-value">
EGP {{ number_format($stats['max_price'],2) }}
</div>
</div>
</div>

<div class="a2-card bsp-stat-card stat-purple">
<div class="stat-icon">⬇</div>
<div>
<div class="bsp-stat-label">أقل سعر</div>
<div class="bsp-stat-value">
EGP {{ number_format($stats['min_price'],2) }}
</div>
</div>
</div>

</div>

{{-- Filters --}}
<div class="a2-card bsp-filter-card">
    <form method="GET" action="{{ route('admin.business_service_prices.index') }}">
        <div class="bsp-filter-grid">
            <div class="bsp-field">
                <label class="a2-label">بحث باسم البزنس</label>
                <input
                    type="text"
                    name="q_business"
                    class="a2-input"
                    value="{{ $qBusiness ?? '' }}"
                    placeholder="اسم البزنس"
                >
            </div>

            <div class="bsp-field">
                <label class="a2-label">بحث باسم الخدمة</label>
                <input
                    type="text"
                    name="q_service"
                    class="a2-input"
                    value="{{ $qService ?? '' }}"
                    placeholder="اسم الخدمة"
                >
            </div>

            <div class="bsp-field">
                <label class="a2-label">الخدمة</label>
                <select name="service_id" class="a2-select">
                    <option value="">الكل</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected((int)$serviceId === (int)$service->id)>
                            {{ $service->name_ar ?: $service->name_en }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="bsp-field">
                <label class="a2-label">البزنس</label>
                <select name="business_id" class="a2-select">
                    <option value="">الكل</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}" @selected((int)$businessId === (int)$business->id)>
                            {{ $business->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="bsp-field">
                <label class="a2-label">الحالة</label>
                <select name="is_active" class="a2-select">
                    <option value="">الكل</option>
                    <option value="1" @selected((string)$isActive === '1')>نشط</option>
                    <option value="0" @selected((string)$isActive === '0')>غير نشط</option>
                </select>
            </div>
        </div>

        <div class="bsp-filter-actions">
            <a href="{{ route('admin.business_service_prices.index') }}" class="a2-btn">إعادة ضبط</a>
            <button type="submit" class="a2-btn a2-btn-primary">تصفية</button>
        </div>
    </form>
</div>

{{-- Table --}}
<div class="a2-card bsp-table-card">
    <div class="table-responsive">
        <table class="a2-table">
            <thead>
                <tr>
                    <th style="width:70px;">#</th>
                    <th>البزنس</th>
                    <th>الخدمة</th>
                    <th>السعر قبل الخصم</th>
                    <th>الخصم</th>
                    <th>السعر بعد الخصم</th>
                    <th>الديبوزت</th>
                    <th>المتبقي</th>
                    <th>الحالة</th>
                    <th style="width:170px;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $item)
                    <tr>
                        <td>#{{ $item->id }}</td>

                        <td>
                            <div class="bsp-main-text">
                                {{ $item->business->name ?? '-' }}
                            </div>
                        </td>

                        <td>
                            <div class="bsp-main-text">
                                {{ $item->service->name_ar ?? $item->service->name_en ?? '-' }}
                            </div>
                            @if(!empty($item->service?->key))
                                <div class="bsp-sub-text">
                                    {{ $item->service->key }}
                                </div>
                            @endif
                        </td>

                        <td>
                            <div class="bsp-money">
                                {{ number_format((float)$item->price, 2) }} EGP
                            </div>
                        </td>

                        <td>
                            @if($item->discount_enabled)
                                <div class="bsp-money">
                                    {{ number_format((float)$item->discount_amount, 2) }} EGP
                                </div>
                                <div class="bsp-sub-text">{{ (int)$item->discount_percent }}%</div>
                            @else
                                <span class="a2-badge">لا يوجد</span>
                            @endif
                        </td>

                        <td>
                            <div class="bsp-money">
                                {{ number_format((float)$item->price_after_discount, 2) }} EGP
                            </div>
                        </td>

                        <td>
                            @if($item->deposit_enabled)
                                <div class="bsp-money">
                                    {{ number_format((float)$item->deposit_amount, 2) }} EGP
                                </div>
                                <div class="bsp-sub-text">{{ (int)$item->deposit_percent }}%</div>
                            @else
                                <span class="a2-badge">لا يوجد</span>
                            @endif
                        </td>

                        <td>
                            <div class="bsp-money">
                                {{ number_format((float)$item->remaining_amount, 2) }} EGP
                            </div>
                        </td>

                        <td>
                            @if($item->is_active)
                                <span class="a2-badge a2-badge-success">نشط</span>
                            @else
                                <span class="a2-badge a2-badge-danger">غير نشط</span>
                            @endif
                        </td>

                        <td>
                            <div class="bsp-actions">
                                <a href="{{ route('admin.business_service_prices.edit', ['row' => $item->id]) }}"
                                   class="a2-btn a2-btn-sm">
                                    تعديل
                                </a>

                                <form method="POST"
                                      action="{{ route('admin.business_service_prices.destroy', ['row' => $item->id]) }}"
                                      onsubmit="return confirm('هل أنت متأكد من حذف السجل؟');">
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
                        <td colspan="10" class="text-center">لا توجد بيانات</td>
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
.bsp-stats-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px;
    margin-bottom:14px;
}

.bsp-stat-card{
    padding:12px 14px;
    min-height:70px;
    display:flex;
    flex-direction:column;
    justify-content:center;
}

.bsp-stat-label{
    color:#6b7280;
    font-size:12px;
    margin-bottom:4px;
}

.bsp-stat-value{
    font-size:22px;
    font-weight:400;
    line-height:1.1;
}
.bsp-stat-card{
    border:1px solid #e5e7eb;
    border-radius:12px;
}
.bsp-filter-card{
    padding:16px;
    margin-bottom:16px;
}
.bsp-filter-grid{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:14px;
    align-items:end;
}
.bsp-field{
    min-width:0;
}
.bsp-field .a2-input,
.bsp-field .a2-select{
    width:100%;
}
.bsp-filter-actions{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    margin-top:14px;
    flex-wrap:wrap;
}
.bsp-table-card{
    padding:0;
    overflow:hidden;
}
.bsp-main-text{
    font-weight:800;
}
.bsp-sub-text{
    font-size:12px;
    color:#6b7280;
    margin-top:2px;
}
.bsp-money{
    font-weight:800;
    white-space:nowrap;
}
.bsp-actions{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}
.bsp-actions form{
    display:inline-block;
}

@media (max-width: 1400px){
    .bsp-filter-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
    .bsp-stats-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media (max-width: 900px){
    .bsp-filter-grid,
    .bsp-stats-grid{
        grid-template-columns:1fr;
    }
    .bsp-stat-value{
        font-size:26px;
    }
}
.bsp-stats-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:12px;
    margin-bottom:18px;
}

.bsp-stat-card{
    padding:14px;
    display:flex;
    align-items:center;
    gap:12px;
    border-radius:12px;
}

.stat-icon{
    width:42px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:20px;
    border-radius:10px;
    background:#f3f4f6;
}

.stat-content{
    display:flex;
    flex-direction:column;
}

.bsp-stat-label{
    font-size:12px;
    color:#6b7280;
    margin-bottom:4px;
}

.bsp-stat-value{
    font-size:20px;
    font-weight:800;
}

/* Colors */

.stat-blue .stat-icon{
    background:#e0f2fe;
}

.stat-green .stat-icon{
    background:#dcfce7;
}

.stat-orange .stat-icon{
    background:#ffedd5;
}

.stat-purple .stat-icon{
    background:#f3e8ff;
}
</style>
@endsection