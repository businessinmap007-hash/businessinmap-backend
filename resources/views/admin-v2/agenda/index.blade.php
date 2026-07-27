@extends('admin-v2.layouts.master')

@section('title','Agenda')
@section('body_class','admin-v2-agenda')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('الجداول الشخصية (إشراف)') }}</h2>
        <div class="a2-hint">
          {{ __('إشراف للقراءة فقط على جداول المستخدمين: المواعيد والحجوزات وجرعات الدواء والمهام. لا يمكن التعديل من هنا.') }}
        </div>
      </div>
    </div>

    <form method="GET" class="a2-filters" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
      <input class="a2-input" type="text" name="q" value="{{ $filters['q'] }}"
             placeholder="{{ __('بحث: المستخدم أو العنوان') }}" />
      <select class="a2-input" name="kind">
        <option value="">{{ __('كل الأنواع') }}</option>
        @foreach($kinds as $k)
          <option value="{{ $k }}" @selected($filters['kind'] === $k)>{{ $k }}</option>
        @endforeach
      </select>
      <select class="a2-input" name="status">
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
            <th>{{ __('المستخدم') }}</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('العنوان') }}</th>
            <th>{{ __('من') }}</th>
            <th>{{ __('إلى') }}</th>
            <th>{{ __('يحجز وقتًا') }}</th>
            <th>{{ __('الحالة') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $item)
            <tr>
              <td>{{ $item->id }}</td>
              <td>{{ optional($item->user)->name ?? '—' }}</td>
              <td>{{ $item->kind }}</td>
              <td>{{ $item->title }}</td>
              <td>{{ optional($item->starts_at)->format('Y-m-d H:i') ?? '—' }}</td>
              <td>{{ optional($item->ends_at)->format('H:i') ?? '—' }}</td>
              <td>{{ $item->blocking ? __('نعم') : '—' }}</td>
              <td>{{ $item->status }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="a2-empty">{{ __('لا توجد عناصر.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
