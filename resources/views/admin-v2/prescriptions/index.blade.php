@extends('admin-v2.layouts.master')

@section('title','Prescriptions')
@section('body_class','admin-v2-prescriptions')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('الوصفات الطبية (إشراف)') }}</h2>
        <div class="a2-hint">
          {{ __('إشراف للقراءة فقط: كل وصفة، من كتبها، لمن، وأي صيدلية تصرفها — الحالة، الفاتورة، والتوصيل. لا تعديل من هنا؛ التعديل حصريًا للطبيب الأصلي.') }}
        </div>
      </div>
    </div>

    <form method="GET" class="a2-filters" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
      <input class="a2-input" type="text" name="q" value="{{ $filters['q'] }}"
             placeholder="{{ __('بحث: الطبيب أو المريض أو الصيدلية') }}" />
      <select class="a2-input" name="status">
        <option value="">{{ __('كل الحالات') }}</option>
        @foreach($statuses as $s)
          <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $s }}</option>
        @endforeach
      </select>
      <select class="a2-input" name="fulfillment">
        <option value="">{{ __('كل طرق التسليم') }}</option>
        @foreach($fulfillments as $f)
          <option value="{{ $f }}" @selected($filters['fulfillment'] === $f)>{{ $f }}</option>
        @endforeach
      </select>
      <button class="a2-btn" type="submit">{{ __('تصفية') }}</button>
    </form>

    <div class="a2-statgrid">
      @foreach(\App\Models\Prescription::STATUSES as $s)
        <div class="a2-stat">
          <div class="a2-stat-label">{{ $s }}</div>
          <div class="a2-stat-value">{{ (int) optional($summary->get($s))->c }}</div>
        </div>
      @endforeach
    </div>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('الطبيب') }}</th>
            <th>{{ __('المريض') }}</th>
            <th>{{ __('الصيدلية') }}</th>
            <th>{{ __('التسليم') }}</th>
            <th>{{ __('الفاتورة') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('إجراء') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $p)
            <tr>
              <td>{{ $p->id }}</td>
              <td>{{ optional($p->doctor)->name ?? '—' }}</td>
              <td>{{ optional($p->patient)->name ?? '—' }}</td>
              <td>{{ optional($p->pharmacy)->name ?? '—' }}</td>
              <td>{{ $p->fulfillment_type ?? '—' }}</td>
              <td>{{ $p->medicine_total !== null ? number_format((float) $p->medicine_total, 2) : '—' }}</td>
              <td>{{ $p->status }}</td>
              <td><a class="a2-btn" href="{{ route('admin.prescriptions.show', $p) }}">{{ __('عرض') }}</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="a2-empty">{{ __('لا توجد وصفات.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
