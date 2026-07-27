@extends('admin-v2.layouts.master')

@section('title','Training plan')
@section('body_class','admin-v2-training')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ $plan->title }}</h2>
        <div class="a2-hint">
          {{ __('المدرّب') }}: {{ optional($plan->trainer)->name ?? '—' }}
          · {{ __('العميل') }}: {{ optional($plan->client)->name ?? '—' }}
          · {{ $plan->status }}
          @if($plan->goal) · {{ $plan->goal }} @endif
        </div>
      </div>
      <div><a class="a2-btn" href="{{ route('admin.training-plans.index') }}">{{ __('رجوع') }}</a></div>
    </div>

    <div class="a2-hint" style="margin-bottom:10px;">
      {{ __('التزام الأسبوع') }} ({{ $summary['from'] }} → {{ $summary['to'] }}):
      <strong>{{ $summary['adherence_percent'] === null ? '—' : $summary['adherence_percent'] . '%' }}</strong>
      · {{ __('الجولات') }}: {{ $summary['completed_rounds'] }}/{{ $summary['weekly_target_rounds'] }}
      · {{ __('أيام النشاط') }}: {{ $summary['active_days'] }}
      · {{ __('تسجيلات التقدّم') }}: {{ $summary['progress']['check_ins'] }}
    </div>
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('التمارين') }}</h3>
    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>{{ __('التمرين') }}</th>
            <th>{{ __('اليوم') }}</th>
            <th>{{ __('المجموعات') }}</th>
            <th>{{ __('التكرارات') }}</th>
            <th>{{ __('راحة (ث)') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($plan->exercises as $e)
            <tr>
              <td>{{ $e->name }}</td>
              <td>{{ $e->day_of_week !== null ? $e->day_of_week : '—' }}</td>
              <td>{{ $e->sets ?? '—' }}</td>
              <td>{{ $e->reps ?? '—' }}</td>
              <td>{{ $e->rest_seconds ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="a2-empty">{{ __('لا توجد تمارين.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('الوجبات') }}</h3>
    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>{{ __('الوجبة') }}</th>
            <th>{{ __('النوع') }}</th>
            <th>{{ __('السعرات') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($plan->meals as $m)
            <tr>
              <td>{{ $m->name }}</td>
              <td>{{ $m->meal_type }}</td>
              <td>{{ $m->calories ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="a2-empty">{{ __('لا توجد وجبات.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('سجلّ التقدّم') }}</h3>
    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>{{ __('التاريخ') }}</th>
            <th>{{ __('الوزن') }}</th>
            <th>{{ __('ملاحظات') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($plan->progressLogs as $log)
            <tr>
              <td>{{ optional($log->logged_on)->format('Y-m-d') }}</td>
              <td>{{ $log->weight ?? '—' }}</td>
              <td>{{ $log->notes ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="a2-empty">{{ __('لا توجد تسجيلات.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
