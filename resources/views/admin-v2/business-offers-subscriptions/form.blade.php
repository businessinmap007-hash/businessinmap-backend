@extends('admin-v2.layouts.master')

@section('title', 'Business Offers Subscription')
@section('topbar_title', 'Business Offers Subscription')
@section('body_class', 'admin-v2-business-offers-subscription')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('اشتراك خدمة العروض التجارية') }}</h1>
            <div class="a2-page-subtitle">{{ __('تفعيل خدمة business_offers للبزنس مع خصم الرسوم الثابتة من المحفظة.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">{{ __('العروض') }}</a>
            <a href="{{ route('admin.wallet-ops.recharge.form') }}" class="a2-btn a2-btn-ghost">{{ __('شحن المحفظة') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card a2-mb-16">
        <form method="GET" action="{{ route('admin.business-offers-subscriptions.form') }}" class="a2-filterbar">
            <select class="a2-select a2-filter-lg" name="business_id" required
                    data-remote-url="{{ route('admin.business-lookup', [], false) }}"
                    data-placeholder="{{ __('اختر البزنس — ابحث بالاسم أو الرقم #') }}">
                <option value="">{{ __('اختر البزنس') }}</option>
                @if($business)
                    <option value="{{ $business->id }}" selected>#{{ $business->id }} — {{ $business->name }}</option>
                @endif
            </select>
            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('عرض الاشتراك') }}</button>
            </div>
        </form>
    </div>

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">Service</div>
            <div class="a2-stat-value">{{ $service ? 'Active' : 'Missing' }}</div>
            <div class="a2-stat-note">business_offers</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Fee</div>
            <div class="a2-stat-value">{{ number_format((float) ($rules['fixed_fee'] ?? 20), 2) }}</div>
            <div class="a2-stat-note">{{ $rules['currency'] ?? 'EGP' }} / {{ (int) ($rules['duration_days'] ?? 30) }} {{ __('يوم') }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Max Offers</div>
            <div class="a2-stat-value">{{ (int) ($rules['max_active_offers'] ?? 5) }}</div>
            <div class="a2-stat-note">{{ __('عروض فعالة') }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Wallet</div>
            <div class="a2-stat-value">{{ $wallet ? number_format((float) $wallet->balance, 2) : '—' }}</div>
            <div class="a2-stat-note">{{ __('رصيد البزنس') }}</div>
        </div>
    </div>

    @if($business)
        <div class="a2-card">
            <h2 class="a2-section-title">{{ $business->name }}</h2>

            <div class="a2-table-wrap a2-mb-16">
                <table class="a2-table">
                    <tbody>
                        <tr>
                            <th>Business ID</th>
                            <td>#{{ $business->id }}</td>
                        </tr>
                        <tr>
                            <th>Current Subscription</th>
                            <td>
                                @if($subscription)
                                    <span class="a2-pill a2-pill-success">Subscribed</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">Not Subscribed</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Usage</th>
                            <td>
                                @if($usage)
                                    {{ $usage['active_offers'] }} / {{ $usage['max_active_offers'] }} active offers
                                    <span class="a2-muted">— remaining {{ $usage['remaining_offers'] }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Wallet Balance</th>
                            <td>{{ $wallet ? number_format((float) $wallet->balance, 2) : '0.00' }} {{ $rules['currency'] ?? 'EGP' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('admin.business-offers-subscriptions.activate') }}" class="a2-form-actions">
                @csrf
                <input type="hidden" name="business_id" value="{{ $business->id }}">

                <label class="a2-checkline">
                    <input type="checkbox" name="charge_wallet" value="1" checked>
                    <span>{{ __('خصم الرسوم من المحفظة الآن') }}</span>
                </label>

                <input class="a2-input" type="text" name="note" placeholder="{{ __('ملاحظة اختيارية') }}">

                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تفعيل / تجديد الاشتراك') }}</button>
            </form>

            @if($subscription)
                <form method="POST" action="{{ route('admin.business-offers-subscriptions.deactivate') }}" class="a2-form-actions">
                    @csrf
                    <input type="hidden" name="business_id" value="{{ $business->id }}">
                    <button class="a2-btn a2-btn-danger" type="submit">{{ __('إيقاف الاشتراك') }}</button>
                </form>
            @endif
        </div>
    @endif
</div>
@endsection
