@extends('admin-v2.layouts.master')

@section('title', 'Service Fee Setup Details')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">تفاصيل إعداد الرسوم</h1>
        <div class="a2-page-subtitle">عرض رسوم البزنس ورسوم العميل لنفس الإعداد</div>
    </div>

    <div class="a2-page-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('admin.service-fees.edit', $groupKey) }}" class="a2-btn a2-btn-primary">تعديل</a>
        <a href="{{ route('admin.service-fees.index', $groupKey) }}" class="a2-btn">رجوع</a>
    </div>
</div>

<div class="a2-card" style="padding:16px;margin-bottom:16px;">
    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;">
        <div>
            <div class="a2-muted">البزنس</div>
            <div style="font-weight:800;">{{ $business ? $business->name : 'Global' }}</div>
        </div>

        <div>
            <div class="a2-muted">الخدمة</div>
            <div style="font-weight:800;">
                {{ $service ? ($service->name_ar ?: $service->name_en) : 'All Services' }}
            </div>
        </div>

        <div>
            <div class="a2-muted">كود الرسم</div>
            <div style="font-weight:800;"><code>{{ $feeCode }}</code></div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;">
    @foreach(['business' => $businessFee, 'client' => $clientFee] as $label => $row)
        <div class="a2-card" style="padding:16px;">
            <div class="a2-title" style="font-size:18px;margin-bottom:12px;">
                {{ $label === 'business' ? 'رسوم البزنس' : 'رسوم العميل' }}
            </div>

            @if($row)
                <div class="a2-table-wrap">
                    <table class="a2-table" style="min-width:0;">
                        <tbody>
                            <tr><th style="width:180px;">نوع الرسم</th><td>{{ $row->fee_type }}</td></tr>
                            <tr><th>طريقة الحساب</th><td>{{ $row->calc_type }}</td></tr>
                            <tr><th>القيمة</th><td>{{ number_format((float)$row->amount, 2) }}</td></tr>
                            <tr><th>الحد الأدنى</th><td>{{ $row->min_amount ?? '-' }}</td></tr>
                            <tr><th>الحد الأقصى</th><td>{{ $row->max_amount ?? '-' }}</td></tr>
                            <tr><th>العملة</th><td>{{ $row->currency }}</td></tr>
                            <tr><th>الأولوية</th><td>{{ $row->priority }}</td></tr>
                            <tr>
                                <th>الحالة</th>
                                <td>
                                    @if($row->is_active)
                                        <span class="a2-pill a2-pill-ok">نشط</span>
                                    @else
                                        <span class="a2-pill a2-pill-trashed">غير نشط</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:12px;">
                    <div class="a2-muted" style="margin-bottom:6px;">Rules JSON</div>
                    <pre style="margin:0;white-space:pre-wrap;word-break:break-word;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px;">{{ !empty($row->rules) ? json_encode($row->rules, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) : '—' }}</pre>
                </div>

                <div style="margin-top:12px;">
                    <div class="a2-muted">ملاحظات</div>
                    <div style="white-space:pre-wrap;">{{ $row->notes ?: '—' }}</div>
                </div>
            @else
                <div class="a2-muted">غير محدد</div>
            @endif
        </div>
    @endforeach
</div>
@endsection