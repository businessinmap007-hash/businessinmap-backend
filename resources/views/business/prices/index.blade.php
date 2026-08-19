@extends('business.layouts.master')

@section('title', __('أسعاري'))

@section('content')
@php
    $displayName = function ($s) {
        return $s ? ($s->name_ar ?: ($s->name_en ?: $s->key)) : '—';
    };
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('أسعاري') }}</h1>
        <div class="a2-page-subtitle">{{ __('سعر كل نوع تقدّمه — يخصّك أنت فقط.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.prices.create') }}" class="a2-btn a2-btn-primary">{{ __('إضافة سعر') }}</a>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($childId <= 0)
    <div class="a2-alert a2-alert-warning">{{ __('حسابك غير مرتبط بقسم فرعي بعد.') }}</div>
@endif

<div class="a2-card a2-card--soft a2-mb-16">
    <form method="GET" action="{{ route('business.prices.index') }}" class="a2-filterbar">
        <div class="a2-filter-md">
            <label class="a2-label">{{ __('الخدمة') }}</label>
            <select class="a2-select" name="service_id">
                <option value="0">{{ __('كل الخدمات') }}</option>
                @foreach($services as $service)
                    <option value="{{ $service->id }}" @selected((int) $serviceId === (int) $service->id)>
                        {{ $displayName($service) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="a2-filter-actions">
            <button class="a2-btn a2-btn-primary" type="submit">{{ __('تصفية') }}</button>
            <a href="{{ route('business.prices.index') }}" class="a2-btn a2-btn-ghost">{{ __('إعادة') }}</a>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('الخدمة') }}</th>
                    <th>{{ __('النوع') }}</th>
                    <th>{{ __('السعر') }}</th>
                    <th>{{ __('الخصم') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th class="a2-text-right">{{ __('إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $displayName($row->service) }}</td>
                        <td>
                            <div dir="ltr">{{ $row->bookable_item_type }}</div>
                            @php $offering = $row->offeringLabel(); @endphp
                            @if($offering)
                                <div class="a2-fw-900">{{ $offering }}</div>
                            @endif
                        </td>
                        <td class="a2-fw-900">
                            @php
                                /**
                                 * ما يدفعه العميل، لا ما كُتب فى خانة السعر.
                                 *
                                 * الخانةُ لا تُقرأ إلا فى الوضع العادى: «مجانية»
                                 * تُلغيها، و«رسوم حجز» تستبدلها بمبلغ الرسوم،
                                 * و«حد أدنى» بالحد. فكان الصفُّ يقول «٢٠ · حد
                                 * أدنى» والحدُّ صفرٌ — عشرون معروضةٌ وصفرٌ يُحصَّل.
                                 */
                                $modeLabels = ['free' => __('مجانية'), 'reservation_fee' => __('رسوم حجز'), 'minimum_charge' => __('حد أدنى')];
                                $mode = (string) ($row->charge_mode ?? 'standard');
                                $base = $row->resolveBaseCharge();
                                $ignoresPrice = $mode !== 'standard' && (float) $row->price > 0;

                                // ومدى ما تضيفه المُوصِّفات المسعَّرة على الوحدة.
                                $adds = $row->offeringOptions
                                    ->where('role', \App\Models\OfferingOption::ROLE_MODIFIER)
                                    ->map(fn ($m) => $m->appliedTo($base))
                                    ->filter(fn ($v) => $v !== 0.0);
                            @endphp

                            {{ number_format($base, 2) }} {{ $row->currency ?: 'EGP' }}

                            @if($adds->isNotEmpty())
                                <div class="a2-muted a2-mt-8" style="font-weight:400">
                                    {{ __('حتى') }} {{ number_format(max($base + $adds->sum(), 0), 2) }}
                                    <span class="a2-pill a2-pill-sub">{{ __('حسب اختيار العميل') }}</span>
                                </div>
                            @endif

                            @if(isset($modeLabels[$mode]))
                                <div class="a2-muted a2-mt-8">
                                    <span class="a2-pill a2-pill-sub">{{ $modeLabels[$mode] }}</span>
                                    @if((float) $row->charge_amount > 0)
                                        {{ number_format((float) $row->charge_amount, 2) }}
                                    @endif
                                </div>
                            @endif

                            @if($ignoresPrice)
                                <div class="a2-alert a2-alert-warning a2-mt-8" style="font-weight:400">
                                    {{ __('كتبت :price فى خانة السعر، ونمط التحصيل لا يقرأها — المحصَّل :base.', [
                                        'price' => number_format((float) $row->price, 2),
                                        'base' => number_format($base, 2),
                                    ]) }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if((int) $row->discount_enabled === 1)
                                <span class="a2-pill a2-pill-success">{{ (int) $row->discount_percent }}%</span>
                            @else
                                <span class="a2-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($row->is_active)
                                <span class="a2-pill a2-pill-success">{{ __('مفعّل') }}</span>
                            @else
                                <span class="a2-pill a2-pill-gray">{{ __('موقوف') }}</span>
                            @endif
                        </td>
                        <td class="a2-text-right">
                            <div class="a2-inline-actions">
                                <a href="{{ route('business.prices.edit', $row->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('تعديل') }}</a>
                                <form method="POST" action="{{ route('business.prices.destroy', $row->id) }}" onsubmit="return confirm('{{ __('حذف هذا السعر؟') }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="a2-empty">{{ __('لا توجد أسعار بعد. أضف سعرًا لكل نوع تقدّمه.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($rows, 'links'))
        <div class="a2-pagination">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
