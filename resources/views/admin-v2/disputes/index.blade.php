@extends('admin-v2.layouts.master')

@section('title','Disputes')
@section('body_class','admin-v2-disputes')


@section('content')
@php
  $money = fn($v) => $v === null ? '—' : number_format((float)$v, 2);
@endphp

<div class="a2-page">
  <div class="a2-card">

    <div class="a2-header">
      <div>
        <h2 class="a2-title">النزاعات المفتوحة</h2>
        <div class="a2-hint">Deposits status = dispute</div>
      </div>
      <div class="a2-actionsbar">
        <a class="a2-btn a2-btn-ghost" href="{{ route('admin.disputes.index') }}">الحجوزات</a>
      </div>
    </div>

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Booking</th>
            <th>Total</th>
            <th>Client %</th>
            <th>Business %</th>
            <th>Opened</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $d)
            <tr>
              <td>#{{ $d->id }}</td>
              <td>
                <a class="a2-link" href="{{ url('admin/bookings/'.$d->target_id) }}">
                  Booking #{{ $d->target_id }}
                </a>
              </td>
              <td>{{ $money($d->total_amount) }}</td>
              <td>{{ (int)$d->client_percent }}%</td>
              <td>{{ (int)$d->business_percent }}%</td>
              <td class="a2-muted">
                {{ $d->dispute_opened_at ? $d->dispute_opened_at->format('Y-m-d H:i') : '—' }}
              </td>
              <td>
@if($d->booking_exists)
  <a class="a2-btn a2-btn-ghost" href="{{ url('admin/bookings/'.$d->target_id) }}">فتح الحجز</a>
@else
  <span class="a2-badge a2-badge-danger">الحجز غير موجود</span>
@endif              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="a2-muted" style="text-align:center;padding:14px;">لا توجد نزاعات حالياً</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">
      {{ $rows->links() }}
    </div>

  </div>
</div>
@endsection