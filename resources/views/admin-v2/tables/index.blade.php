@extends('admin-v2.layouts.master')

@section('title','Restaurant Tables')
@section('body_class','admin-v2-tables')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('طاولات المطاعم') }}</h2>
        <div class="a2-hint">{{ __('رموز QR للطاولات (BIM-13.3) — عرض على مستوى المنصّة') }}</div>
      </div>
    </div>

    <form method="GET" class="a2-filterbar">
      <input class="a2-input a2-filter-search" type="text" name="q" value="{{ $q }}" placeholder="{{ __('بحث بالاسم أو المطعم') }}">
      <div class="a2-filter-actions">
        <button class="a2-btn a2-btn-primary" type="submit">{{ __('بحث') }}</button>
      </div>
    </form>

    <div class="a2-tablewrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('المطعم') }}</th>
            <th>{{ __('الطاولة') }}</th>
            <th>{{ __('الطلبات') }}</th>
            <th>{{ __('الحالة') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($tables as $t)
            <tr>
              <td>{{ $t->id }}</td>
              <td>{{ $t->business->name ?? ('#'.$t->business_id) }}</td>
              <td>{{ $t->label ?: '—' }}</td>
              <td>{{ $t->orders_count }}</td>
              <td>
                @if($t->is_active)
                  <span class="a2-badge a2-badge-ok">{{ __('مفعّلة') }}</span>
                @else
                  <span class="a2-badge a2-badge-muted">{{ __('موقوفة') }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="a2-empty">{{ __('لا توجد طاولات.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="a2-pager">{{ $tables->links() }}</div>
  </div>
</div>
@endsection
