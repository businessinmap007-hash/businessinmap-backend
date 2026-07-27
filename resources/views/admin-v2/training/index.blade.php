@extends('admin-v2.layouts.master')

@section('title','Training plans')
@section('body_class','admin-v2-training')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('خطط التدريب (إشراف)') }}</h2>
        <div class="a2-hint">
          {{ __('إشراف للقراءة فقط على خطط التدريب والتغذية لكل المدرّبين: التمارين، الوجبات، وتقدّم العميل. لا يمكن التعديل، ولا تظهر محادثة المدرّب والمتدرّب هنا (مشفّرة، تُقرأ من مركز المحادثات المخصّص للحكم فقط).') }}
        </div>
      </div>
    </div>

    <form method="GET" class="a2-filters" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
      <input class="a2-input" type="text" name="q" value="{{ $filters['q'] }}"
             placeholder="{{ __('بحث: العنوان أو المدرّب أو العميل') }}" />
      <select class="a2-input" name="status">
        <option value="">{{ __('كل الحالات') }}</option>
        @foreach($statuses as $s)
          <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $s }}</option>
        @endforeach
      </select>
      <button class="a2-btn" type="submit">{{ __('تصفية') }}</button>
    </form>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('الخطة') }}</th>
            <th>{{ __('المدرّب') }}</th>
            <th>{{ __('العميل') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('تمارين') }}</th>
            <th>{{ __('وجبات') }}</th>
            <th>{{ __('تسجيلات') }}</th>
            <th>{{ __('إجراء') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $plan)
            <tr>
              <td>{{ $plan->id }}</td>
              <td>
                {{ $plan->title }}
                @if($plan->goal)<div class="a2-hint">{{ $plan->goal }}</div>@endif
              </td>
              <td>{{ optional($plan->trainer)->name ?? '—' }}</td>
              <td>{{ optional($plan->client)->name ?? '—' }}</td>
              <td>{{ $plan->status }}</td>
              <td>{{ $plan->exercises_count }}</td>
              <td>{{ $plan->meals_count }}</td>
              <td>{{ $plan->progress_logs_count }}</td>
              <td><a class="a2-btn" href="{{ route('admin.training-plans.show', $plan) }}">{{ __('عرض') }}</a></td>
            </tr>
          @empty
            <tr><td colspan="9" class="a2-empty">{{ __('لا توجد خطط تدريب.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
