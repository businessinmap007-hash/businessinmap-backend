@extends('admin-v2.layouts.master')

@section('title','Clinic appointment')
@section('body_class','admin-v2-clinic-appointments')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('موعد') }} #{{ $appointment->id }}</h2>
        <div class="a2-hint">
          {{ __('العيادة') }}: {{ optional($appointment->clinic)->name ?? '—' }}
          · {{ __('المريض') }}: {{ optional($appointment->patient)->name ?? '—' }}
          · {{ $appointment->status }}
        </div>
      </div>
      <div><a class="a2-btn" href="{{ route('admin.clinic-appointments.index') }}">{{ __('رجوع') }}</a></div>
    </div>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <tbody>
          <tr><th>{{ __('الوقت') }}</th><td>{{ optional($appointment->scheduled_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
          <tr><th>{{ __('المدة') }}</th><td>{{ $appointment->duration_minutes }} {{ __('دقيقة') }}</td></tr>
          <tr><th>{{ __('السبب') }}</th><td>{{ $appointment->reason ?: '—' }}</td></tr>
          <tr><th>{{ __('ملاحظات') }}</th><td>{{ $appointment->notes ?: '—' }}</td></tr>
          <tr><th>{{ __('هاتف المريض') }}</th><td>{{ optional($appointment->patient)->phone ?: '—' }}</td></tr>
          <tr><th>{{ __('تذكير قبل يوم') }}</th><td>{{ optional($appointment->reminded_day_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
          <tr><th>{{ __('تذكير قبل ساعتين') }}</th><td>{{ optional($appointment->reminded_soon_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('الوصفة المرتبطة') }}</h3>
    @if($appointment->prescription)
      <div class="a2-hint">
        {{ __('وصفة') }} #{{ $appointment->prescription->id }}
        · {{ $appointment->prescription->status }}
        @if($appointment->prescription->diagnosis) · {{ $appointment->prescription->diagnosis }} @endif
      </div>
    @else
      <div class="a2-empty">{{ __('لم تُكتب وصفة أثناء هذه الزيارة.') }}</div>
    @endif
  </div>
</div>
@endsection
