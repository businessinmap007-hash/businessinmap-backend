@extends('admin-v2.layouts.master')

@section('title','Clinic appointments')
@section('body_class','admin-v2-clinic-appointments')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('مواعيد العيادات (إشراف)') }}</h2>
        <div class="a2-hint">
          {{ __('إشراف للقراءة فقط على مواعيد كل العيادات: الحالة، الوقت، المريض، وهل كُتبت وصفة أثناء الزيارة. لا يمكن التعديل من هنا.') }}
        </div>
      </div>
    </div>

    <form method="GET" class="a2-filters" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
      <input class="a2-input" type="text" name="q" value="{{ $filters['q'] }}"
             placeholder="{{ __('بحث: العيادة أو المريض أو السبب') }}" />
      <select class="a2-input" name="status">
        <option value="">{{ __('كل الحالات') }}</option>
        @foreach($statuses as $s)
          <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $s }}</option>
        @endforeach
      </select>
      <input class="a2-input" type="date" name="date" value="{{ $filters['date'] }}" />
      <button class="a2-btn" type="submit">{{ __('تصفية') }}</button>
    </form>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('الوقت') }}</th>
            <th>{{ __('العيادة') }}</th>
            <th>{{ __('المريض') }}</th>
            <th>{{ __('المدة') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('وصفة') }}</th>
            <th>{{ __('إجراء') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $a)
            <tr>
              <td>{{ $a->id }}</td>
              <td>{{ optional($a->scheduled_at)->format('Y-m-d H:i') ?? '—' }}</td>
              <td>{{ optional($a->clinic)->name ?? '—' }}</td>
              <td>{{ optional($a->patient)->name ?? '—' }}</td>
              <td>{{ $a->duration_minutes }} {{ __('د') }}</td>
              <td>{{ $a->status }}</td>
              <td>{{ $a->prescription ? __('نعم') : '—' }}</td>
              <td><a class="a2-btn" href="{{ route('admin.clinic-appointments.show', $a) }}">{{ __('عرض') }}</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="a2-empty">{{ __('لا توجد مواعيد.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
