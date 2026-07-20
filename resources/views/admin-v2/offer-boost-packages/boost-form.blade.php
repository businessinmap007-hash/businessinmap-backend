@extends('admin-v2.layouts.master')

@section('title', 'Activate Offer Boost')
@section('topbar_title', 'Activate Offer Boost')
@section('body_class', 'admin-v2-offer-boost-activate')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('تفعيل Boost لعرض') }}</h1>
            <div class="a2-page-subtitle">{{ __('اختيار عرض وباقة، ثم خصم قيمة الباقة من محفظة البزنس صاحب العرض.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.offer-boost-packages.index') }}" class="a2-btn a2-btn-ghost">{{ __('باقات Boost') }}</a>
            <a href="{{ route('admin.commercial-offers.index') }}" class="a2-btn a2-btn-ghost">{{ __('العروض') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-card a2-mb-16">
        <form method="GET" action="{{ route('admin.offer-boost-packages.boost-form') }}" class="a2-filterbar">
            <select class="a2-select a2-filter-search" name="offer_id">
                <option value="0">{{ __('اختر عرضًا') }}</option>
                @foreach($offers as $row)
                    <option value="{{ $row->id }}" {{ (int) $offerId === (int) $row->id ? 'selected' : '' }}>
                        #{{ $row->id }} — {{ $row->title_ar ?: ($row->title_en ?: 'Offer') }} — {{ optional($row->sellerBusiness)->name }}
                    </option>
                @endforeach
            </select>
            <button class="a2-btn a2-btn-primary" type="submit">{{ __('عرض التفاصيل') }}</button>
        </form>
    </div>

    @if($offer)
        <div class="a2-grid-2">
            <div class="a2-card">
                <h2 class="a2-section-title">{{ __('العرض المحدد') }}</h2>
                <div class="a2-info-list">
                    <div><span class="a2-muted">Offer</span><strong>#{{ $offer->id }} — {{ $offer->displayTitle() }}</strong></div>
                    <div><span class="a2-muted">Business</span><strong>{{ optional($offer->sellerBusiness)->name ?: ('#' . $offer->seller_business_id) }}</strong></div>
                    <div><span class="a2-muted">Price</span><strong>{{ number_format((float) $offer->final_price, 2) }} {{ $offer->currency }}</strong></div>
                    <div><span class="a2-muted">Audience</span><strong>{{ $offer->audience_type }}</strong></div>
                    <div><span class="a2-muted">Status</span><strong>{{ $offer->status }}</strong></div>
                    <div><span class="a2-muted">Boost</span><strong>{{ $offer->isBoosted() ? 'Boosted' : 'Not boosted' }}</strong></div>
                    <div><span class="a2-muted">Featured Until</span><strong>{{ $offer->featured_until ? $offer->featured_until->format('Y-m-d H:i') : '—' }}</strong></div>
                </div>
            </div>

            <div class="a2-card">
                <h2 class="a2-section-title">{{ __('تفعيل الباقة') }}</h2>
                <form method="POST" action="{{ route('admin.offer-boost-packages.activate') }}">
                    @csrf
                    <input type="hidden" name="offer_id" value="{{ $offer->id }}">

                    <div class="a2-field">
                        <label class="a2-label">Boost Package</label>
                        <select class="a2-select" name="package_id" required>
                            <option value="">{{ __('اختر الباقة') }}</option>
                            @foreach($packages as $package)
                                <option value="{{ $package->id }}">
                                    {{ $package->displayName() }} — {{ number_format((float) $package->price, 2) }} {{ $package->currency }} — {{ $package->duration_days }} days
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button class="a2-btn a2-btn-primary" type="submit">{{ __('تفعيل وخصم الرسوم') }}</button>
                </form>
            </div>
        </div>
    @else
        <div class="a2-card">
            <div class="a2-empty-cell">{{ __('اختر عرضًا أولًا لتفعيل Boost.') }}</div>
        </div>
    @endif
</div>
@endsection
