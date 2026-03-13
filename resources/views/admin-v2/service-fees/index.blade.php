@extends('admin-v2.layouts.master')

@section('title', 'Service Fees')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">إعدادات رسوم الخدمات</h1>
        <div class="a2-page-subtitle">إدارة إعدادات الرسوم للطرفين حسب البزنس والخدمة وكود الرسم</div>
    </div>

    <div class="a2-page-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('admin.service-fees.create') }}" class="a2-btn a2-btn-primary">إضافة إعداد جديد</a>
    </div>
</div>

<div class="a2-card" style="padding:14px;margin-bottom:14px;">
    <form method="GET" action="{{ route('admin.service-fees.index') }}">
        <div class="a2-toolbar" style="margin:0;">
            <div class="a2-filters" style="width:100%;">
                <input type="text" name="q" class="a2-input" placeholder="بحث عام..." value="{{ $q ?? request('q') }}" style="min-width:220px;">

                <select name="business_id" class=" js-a2-searchable" style="min-width:210px;">
                    <option value="">كل البزنس</option>
                    <option value="global" @selected(($businessId ?? request('business_id')) === 'global')>Global</option>
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}" @selected((string)($businessId ?? request('business_id')) === (string)$business->id)>
                            {{ $business->name }}@if(!empty($business->code)) ({{ $business->code }}) @endif
                        </option>
                    @endforeach
                </select>

                <select name="service_id" class=" js-a2-searchable" style="min-width:210px;">
                    <option value="">كل الخدمات</option>
                    <option value="global" @selected(($serviceId ?? request('service_id')) === 'global')>All Services</option>
                    @foreach($services as $service)
                        <option value="{{ $service->id }}" @selected((string)($serviceId ?? request('service_id')) === (string)$service->id)>
                            {{ $service->name_ar ?: $service->name_en }}
                            @if(!empty($service->key)) ({{ $service->key }}) @endif
                        </option>
                    @endforeach
                </select>

                <select name="fee_code" class=" js-a2-searchable" style="min-width:210px;">
                    <option value="">كل أكواد الرسوم</option>
                    @foreach($feeCodeOptions as $value => $label)
                        <option value="{{ $value }}" @selected((string)($feeCode ?? request('fee_code')) === (string)$value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <select name="is_active" class=" js-a2-searchable" style="min-width:160px;">
                    <option value="">كل الحالات</option>
                    <option value="1" @selected((string)($isActive ?? request('is_active')) === '1')>نشط</option>
                    <option value="0" @selected((string)($isActive ?? request('is_active')) === '0')>غير نشط</option>
                </select>

                <select name="per_page" class=" js-a2-searchable" style="min-width:130px;">
                    @foreach([10,20,50,100] as $n)
                        <option value="{{ $n }}" @selected((int)($perPage ?? request('per_page', 50)) === $n)>
                            {{ $n }} / صفحة
                        </option>
                    @endforeach
                </select>

                <button type="submit" class="a2-btn a2-btn-primary">فلترة</button>
                <a href="{{ route('admin.service-fees.index') }}" class="a2-btn">إعادة ضبط</a>
            </div>
        </div>
    </form>
</div>

<div class="a2-card" style="padding:14px;">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th style="width:70px;">#</th>
                    <th>البزنس</th>
                    <th>الخدمة</th>
                    <th>كود الرسم</th>
                    <th>صفوف الإعداد</th>
                    <th>Business</th>
                    <th>Client</th>
                    <th>نشط</th>
                    <th>آخر تحديث</th>
                    <th style="width:260px;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php
                        $groupKey = [
                            'business_id' => $row->business_id,
                            'service_id'  => $row->service_id,
                            'fee_code'    => $row->fee_code,
                        ];

                        $businessName = 'Global';
                        if(!is_null($row->business_id)){
                            $b = $businesses->firstWhere('id', $row->business_id);
                            $businessName = $b ? $b->name : ('#'.$row->business_id);
                        }

                        $serviceName = 'All Services';
                        if(!is_null($row->service_id)){
                            $s = $services->firstWhere('id', $row->service_id);
                            $serviceName = $s ? ($s->name_ar ?: $s->name_en) : ('#'.$row->service_id);
                        }
                    @endphp

                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $businessName }}</td>
                        <td>{{ $serviceName }}</td>
                        <td><code>{{ $row->fee_code }}</code></td>
                        <td>{{ $row->rows_count }}</td>
                        <td>
                            @if((int)$row->has_business_fee > 0)
                                <span class="a2-pill a2-pill-ok">موجود</span>
                            @else
                                <span class="a2-pill a2-pill-trashed">—</span>
                            @endif
                        </td>
                        <td>
                            @if((int)$row->has_client_fee > 0)
                                <span class="a2-pill a2-pill-ok">موجود</span>
                            @else
                                <span class="a2-pill a2-pill-trashed">—</span>
                            @endif
                        </td>
                        <td>
                            @if((int)$row->active_count > 0)
                                <span class="a2-pill a2-pill-ok">نشط</span>
                            @else
                                <span class="a2-pill a2-pill-trashed">غير نشط</span>
                            @endif
                        </td>
                        <td>{{ \Illuminate\Support\Carbon::parse($row->updated_at)->format('Y-m-d H:i') }}</td>
                        <td>
                            <div class="a2-actions">
                                <a href="{{ route('admin.service-fees.show', $groupKey) }}" class="a2-link">عرض</a>
                                <a href="{{ route('admin.service-fees.edit', $groupKey) }}" class="a2-link">تعديل</a>

                                <form method="POST" action="{{ route('admin.service-fees.toggleActive', $groupKey) }}" style="display:inline;">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="a2-link" style="background:none;border:none;padding:0;cursor:pointer;">
                                        تبديل الحالة
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('admin.service-fees.destroy', $groupKey) }}"
                                      onsubmit="return confirm('هل أنت متأكد من حذف إعداد الرسوم بالكامل؟');" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="a2-link a2-link-danger" style="background:none;border:none;padding:0;cursor:pointer;">
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="a2-empty-cell">لا توجد إعدادات رسوم حالياً.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($rows, 'links'))
        <div style="margin-top:12px;">
            {{ $rows->links() }}
        </div>
    @endif
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-a2-searchable').forEach(function (el) {
        if (el.tomselect) return;

        new TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            placeholder: 'ابحث أو اختر...',
            maxOptions: 500,
            closeAfterSelect: true,
            searchField: ['text'],
            dropdownParent: 'body',
            render: {
                no_results: function(data, escape) {
                    return '<div class="no-results" style="padding:10px 12px;">لا توجد نتائج</div>';
                }
            }
        });
    });
});
</script>

<style>
.ts-wrapper.single .ts-control,
.ts-wrapper.multi .ts-control{
    min-height: 40px;
    border: 1px solid var(--a2-border-2);
    border-radius: 12px;
    padding: 7px 12px;
    box-shadow: none;
    background: #fff;
    font-size: 14px;
}
.ts-wrapper.focus .ts-control{
    border-color: #c7d2fe;
    box-shadow: 0 0 0 4px rgba(99,102,241,.12);
}
.ts-dropdown{
    border: 1px solid var(--a2-border-2);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 12px 30px rgba(16,24,40,.10);
    z-index: 9999;
}
.ts-dropdown .option,
.ts-dropdown .create{
    padding: 10px 12px;
    font-size: 14px;
}
</style>
@endsection