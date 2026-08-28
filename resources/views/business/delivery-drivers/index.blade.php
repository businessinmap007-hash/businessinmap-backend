@extends('business.layouts.master')

@section('title', __('موصّليّ'))

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('موصّليّ') }}</h1>
        <div class="a2-page-subtitle">{{ __('فريق التوصيل الخاص بنشاطك — من شاغر ومن معه طلب، وحالة كل طلب لحظة بلحظة.') }}</div>
    </div>
</div>

@if(session('success'))
    <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="a2-alert a2-alert-danger">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<div class="a2-card a2-card--section">
    <div class="a2-card-head">
        <div>
            <div class="a2-card-title">{{ __('إضافة موصّل') }}</div>
            <div class="a2-card-sub">{{ __('برقم هاتفه — يجب أن يكون له حساب مسجَّل بالفعل في التطبيق.') }}</div>
        </div>
    </div>
    <form method="POST" action="{{ route('business.delivery-drivers.store') }}">
        @csrf
        <div class="a2-form-grid" style="grid-template-columns:1fr 1fr auto;align-items:end;gap:12px;">
            <div class="a2-form-group">
                <label class="a2-label" for="phone">{{ __('رقم هاتف الموصّل') }} <span class="a2-danger">*</span></label>
                <input class="a2-input" id="phone" name="phone" value="{{ old('phone') }}" dir="ltr" placeholder="01xxxxxxxxx" required>
            </div>
            <div class="a2-form-group">
                <label class="a2-label" for="vehicle_label">{{ __('الوسيلة (اختياري)') }}</label>
                <input class="a2-input" id="vehicle_label" name="vehicle_label" value="{{ old('vehicle_label') }}" placeholder="{{ __('موتوسيكل، دراجة...') }}">
            </div>
            <div class="a2-form-group">
                <button type="submit" class="a2-btn a2-btn-primary">{{ __('إضافة') }}</button>
            </div>
        </div>
    </form>
</div>

<div class="a2-card">
    <div class="a2-table-wrap">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('الموصّل') }}</th>
                    <th>{{ __('الوسيلة') }}</th>
                    <th>{{ __('الحالة') }}</th>
                    <th>{{ __('خط سيره الحالي') }}</th>
                    <th>{{ __('اليوم') }}</th>
                    <th class="a2-text-right">{{ __('إجراءات') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drivers as $driver)
                    <tr>
                        <td>
                            <div class="a2-fw-900">{{ $driver['name'] ?? __('بدون اسم') }}</div>
                            <div class="a2-muted" dir="ltr">{{ $driver['phone'] }}</div>
                        </td>
                        <td>{{ $driver['vehicle_label'] ?: '—' }}</td>
                        <td>
                            @if(! $driver['is_active'])
                                <span class="a2-pill a2-pill-gray">{{ __('موقوف') }}</span>
                            @elseif($driver['busy'])
                                <span class="a2-pill a2-pill-warning">{{ __('مشغول') }} ({{ $driver['active_order_count'] }})</span>
                            @else
                                <span class="a2-pill a2-pill-success">{{ __('شاغر') }}</span>
                            @endif
                        </td>
                        <td>
                            @if(empty($driver['active_orders']))
                                <span class="a2-muted">{{ __('لا يوجد طلب حالياً') }}</span>
                            @else
                                <div style="display:flex;flex-direction:column;gap:6px;">
                                    @foreach($driver['active_orders'] as $order)
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <span class="a2-pill {{ $order['stage'] === 'picked_up' ? 'a2-pill-warning' : 'a2-pill-gray' }}">
                                                #{{ $order['order_id'] }} —
                                                {{ $order['stage'] === 'picked_up' ? __('في الطريق') : __('توجّه للاستلام') }}
                                            </span>
                                            <span class="a2-muted">{{ $order['address'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>
                            <span class="a2-pill a2-pill-success">{{ __('تم التسليم') }}: {{ $driver['delivered_today'] }}</span>
                        </td>
                        <td class="a2-text-right">
                            <form method="POST" action="{{ route('business.delivery-drivers.update', $driver['id']) }}">
                                @csrf
                                @method('PUT')
                                @if($driver['is_active'])
                                    <input type="hidden" name="is_active" value="0">
                                    <button class="a2-btn a2-btn-sm a2-btn-ghost" type="submit">{{ __('إيقاف') }}</button>
                                @else
                                    <input type="hidden" name="is_active" value="1">
                                    <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">{{ __('تفعيل') }}</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="a2-empty">{{ __('لا يوجد موصّلون بعد. أضف موصّلاً برقم هاتفه.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
