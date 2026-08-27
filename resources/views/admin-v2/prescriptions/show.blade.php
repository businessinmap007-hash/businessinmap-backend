@extends('admin-v2.layouts.master')

@section('title','Prescription')
@section('body_class','admin-v2-prescriptions')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('وصفة') }} #{{ $prescription->id }}</h2>
        <div class="a2-hint">
          {{ __('الطبيب') }}: {{ optional($prescription->doctor)->name ?? '—' }}
          · {{ __('المريض') }}: {{ optional($prescription->patient)->name ?? '—' }}
          · {{ $prescription->status }}
        </div>
      </div>
      <div><a class="a2-btn" href="{{ route('admin.prescriptions.index') }}">{{ __('رجوع') }}</a></div>
    </div>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <tbody>
          <tr><th>{{ __('التشخيص') }}</th><td>{{ $prescription->diagnosis ?: '—' }}</td></tr>
          <tr><th>{{ __('حالة المريض') }}</th><td>{{ $prescription->patient_condition ?: '—' }}</td></tr>
          <tr><th>{{ __('ملاحظات') }}</th><td>{{ $prescription->notes ?: '—' }}</td></tr>
          <tr><th>{{ __('هاتف المريض') }}</th><td>{{ optional($prescription->patient)->phone ?: '—' }}</td></tr>
          <tr><th>{{ __('طريقة التسليم') }}</th><td>{{ $prescription->fulfillment_type ?? '—' }}</td></tr>
          <tr><th>{{ __('عنوان التوصيل') }}</th><td>{{ $prescription->delivery_address ?: '—' }}</td></tr>
          <tr><th>{{ __('صدرت فى') }}</th><td>{{ optional($prescription->issued_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
          <tr><th>{{ __('صُرفت فى') }}</th><td>{{ optional($prescription->dispensed_at)->format('Y-m-d H:i') ?? '—' }}</td></tr>
          @if($prescription->revises_prescription_id)
            <tr><th>{{ __('تعدّل الوصفة') }}</th><td><a href="{{ route('admin.prescriptions.show', $prescription->revises_prescription_id) }}">#{{ $prescription->revises_prescription_id }}</a></td></tr>
          @endif
          @if($prescription->revisedBy->isNotEmpty())
            <tr><th>{{ __('استُبدلت بـ') }}</th><td>
              @foreach($prescription->revisedBy as $r)
                <a href="{{ route('admin.prescriptions.show', $r->id) }}">#{{ $r->id }} ({{ $r->status }})</a>
              @endforeach
            </td></tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('الفاتورة') }}</h3>
    @if($prescription->medicine_total !== null)
      <div class="a2-hint">
        {{ __('الإجمالي') }}: {{ number_format((float) $prescription->medicine_total, 2) }}
        · {{ __('سُعّرت فى') }} {{ optional($prescription->priced_at)->format('Y-m-d H:i') }}
      </div>
    @else
      <div class="a2-empty">{{ __('لم تُسعَّر بعد.') }}</div>
    @endif

    <div class="a2-table-wrap" style="margin-top:8px;">
      <table class="a2-table">
        <thead>
          <tr>
            <th>{{ __('الدواء') }}</th>
            <th>{{ __('الجرعة') }}</th>
            <th>{{ __('الكمية') }}</th>
            <th>{{ __('سعر الوحدة') }}</th>
            <th>{{ __('الكمية المفوترة') }}</th>
            <th>{{ __('إجمالي السطر') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($prescription->items as $i)
            <tr>
              <td>{{ $i->name }}</td>
              <td>{{ $i->dosage ?: '—' }}</td>
              <td>{{ $i->quantity ?: '—' }}</td>
              <td>{{ $i->unit_price !== null ? number_format((float) $i->unit_price, 2) : '—' }}</td>
              <td>{{ $i->billed_quantity ?? '—' }}</td>
              <td>{{ $i->line_total !== null ? number_format((float) $i->line_total, 2) : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('الصيدلية والتوصيل') }}</h3>
    <div class="a2-hint">
      {{ __('الصيدلية') }}: {{ optional($prescription->pharmacy)->name ?? '—' }}
      @if($prescription->deliveryDriver)
        · {{ __('الموصّل') }}: {{ optional($prescription->deliveryDriver->user)->name ?? '#' . $prescription->delivery_driver_id }}
        · {{ __('مرحلة التوصيل') }}: {{ $prescription->delivery_stage ?? '—' }}
      @endif
    </div>
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('أطباء مُشارَكون') }}</h3>
    @if($prescription->shares->isNotEmpty())
      <ul>
        @foreach($prescription->shares as $s)
          <li>{{ optional($s->doctor)->name ?? '#' . $s->doctor_id }}</li>
        @endforeach
      </ul>
    @else
      <div class="a2-empty">{{ __('لم تُشارَك مع أي طبيب آخر.') }}</div>
    @endif
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('صور الوصفة') }}</h3>
    @if($prescription->images->isNotEmpty())
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        @foreach($prescription->images as $img)
          <a href="{{ asset($img->image) }}" target="_blank" rel="noopener">
            <img src="{{ asset($img->image) }}" alt="" style="width:120px;height:120px;object-fit:cover;border-radius:6px;">
          </a>
        @endforeach
      </div>
    @else
      <div class="a2-empty">{{ __('لا توجد صور مرفقة.') }}</div>
    @endif
  </div>
</div>
@endsection
