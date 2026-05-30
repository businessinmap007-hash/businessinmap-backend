@extends('admin-v2.layouts.master')

@section('title', 'Create Booking')
@section('body_class', 'admin-v2 admin-v2-bookings-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة حجز جديد</h1>
            <div class="a2-page-subtitle">
                طالب الحجز قد يكون عميلًا عاديًا أو بزنس، ومقدم الخدمة يجب أن يكون بزنس مفعل.
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookings.index') }}" class="a2-btn a2-btn-ghost">
                رجوع
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
            <div class="a2-fw-900 a2-mb-8">يوجد أخطاء في بيانات الحجز:</div>

            <ul style="margin:0;padding-inline-start:18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.bookings.store') }}">
        @csrf

        @include('admin-v2.bookings._form', [
            'isEdit' => false,
            'booking' => new \App\Models\Booking(),
            'statusOptions' => $statusOptions ?? \App\Models\Booking::statusOptions(),
            'services' => $services ?? collect(),
            'businesses' => $businesses ?? collect(),
            'clients' => $clients ?? collect(),
            'businessServicePrices' => $businessServicePrices ?? collect(),
        ])
    </form>
</div>
@endsection