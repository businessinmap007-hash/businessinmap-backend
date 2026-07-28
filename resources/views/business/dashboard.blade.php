@extends('business.layouts.master')

@section('title', __('الرئيسية'))
@section('body_class', 'business-panel-dashboard')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('أهلًا، :name', ['name' => $user->name]) }}</h1>
        <div class="a2-page-subtitle">
            {{ __('هذه لوحتك الخاصة — كل ما تراه هنا يخص نشاطك أنت فقط.') }}
        </div>
    </div>
</div>

<div class="a2-stat-grid" style="margin-top:16px;">
    <a href="{{ route('business.bookable-items.index') }}" class="a2-stat-card" style="text-decoration:none;color:inherit;">
        <div class="a2-stat-label">{{ __('وحداتي القابلة للحجز') }}</div>
        <div class="a2-stat-value">{{ $stats['bookable_items'] }}</div>
    </a>

    <div class="a2-stat-card">
        <div class="a2-stat-label">{{ __('المفعّلة منها') }}</div>
        <div class="a2-stat-value">{{ $stats['active_items'] }}</div>
    </div>

    <a href="{{ route('business.prices.index') }}" class="a2-stat-card" style="text-decoration:none;color:inherit;">
        <div class="a2-stat-label">{{ __('أسعاري') }}</div>
        <div class="a2-stat-value">{{ $stats['prices'] }}</div>
    </a>

    <a href="{{ route('business.menu.index') }}" class="a2-stat-card" style="text-decoration:none;color:inherit;">
        <div class="a2-stat-label">{{ __('أصناف المنيو') }}</div>
        <div class="a2-stat-value">{{ $stats['menu_items'] }}</div>
    </a>

    <a href="{{ route('business.bookings.index') }}" class="a2-stat-card" style="text-decoration:none;color:inherit;">
        <div class="a2-stat-label">{{ __('حجوزاتي') }}</div>
        <div class="a2-stat-value">{{ $stats['bookings'] }}</div>
    </a>
</div>

<div class="a2-card a2-card--soft" style="margin-top:16px;">
    <div class="a2-section-title">{{ __('الخطوات القادمة') }}</div>
    <div class="a2-section-subtitle">
        {{ __('قريبًا: إدارة الأنواع التي تقدّمها، تسعيرها، وإضافة وحداتك الفعلية — كل ذلك من هنا في خطوات بسيطة.') }}
    </div>
</div>
@endsection
