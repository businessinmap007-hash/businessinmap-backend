@extends('business.layouts.master')

@section('title', 'الرئيسية')
@section('body_class', 'business-panel-dashboard')

@section('content')
<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">أهلًا، {{ $user->name }}</h1>
        <div class="a2-page-subtitle">
            هذه لوحتك الخاصة — كل ما تراه هنا يخص نشاطك أنت فقط.
        </div>
    </div>
</div>

<div class="a2-stat-grid" style="margin-top:16px;">
    <div class="a2-stat-card">
        <div class="a2-stat-label">وحداتي القابلة للحجز</div>
        <div class="a2-stat-value">{{ $stats['bookable_items'] }}</div>
    </div>

    <div class="a2-stat-card">
        <div class="a2-stat-label">المفعّلة منها</div>
        <div class="a2-stat-value">{{ $stats['active_items'] }}</div>
    </div>

    <div class="a2-stat-card">
        <div class="a2-stat-label">أسعاري</div>
        <div class="a2-stat-value">{{ $stats['prices'] }}</div>
    </div>

    <div class="a2-stat-card">
        <div class="a2-stat-label">حجوزاتي</div>
        <div class="a2-stat-value">{{ $stats['bookings'] }}</div>
    </div>
</div>

<div class="a2-card a2-card--soft" style="margin-top:16px;">
    <div class="a2-section-title">الخطوات القادمة</div>
    <div class="a2-section-subtitle">
        قريبًا: إدارة الأنواع التي تقدّمها، تسعيرها، وإضافة وحداتك الفعلية — كل ذلك من هنا في خطوات بسيطة.
    </div>
</div>
@endsection
