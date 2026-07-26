@extends('admin-v2.layouts.master')

@section('title','Project')
@section('body_class','admin-v2-projects')

@section('content')
@php
  $tl = $timeline['tasks'] ?? [];
@endphp
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ $project->title }}</h2>
        <div class="a2-hint">
          {{ optional($project->business)->name ?? '—' }}
          @if($project->reference) · {{ $project->reference }} @endif
          · {{ $project->status }} · {{ $project->visibility }}
        </div>
      </div>
      <div>
        <a class="a2-btn" href="{{ route('admin.projects.index') }}">{{ __('رجوع') }}</a>
      </div>
    </div>

    <div class="a2-hint" style="margin-bottom:10px;">
      {{ __('التقدّم الكلي') }}: <strong>{{ (int) $project->progress }}%</strong>
      · {{ __('مدة المسار') }}: {{ $timeline['project_duration_days'] ?? 0 }} {{ __('يوم') }}
      @if(!empty($timeline['has_cycle']))
        · <span class="a2-badge a2-badge-danger">{{ __('حلقة تبعيات') }}</span>
      @endif
    </div>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('المرحلة') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('التقدّم') }}</th>
            <th>{{ __('البداية المخطّطة') }}</th>
            <th>{{ __('النهاية المخطّطة') }}</th>
            <th>{{ __('على المسار الحرج') }}</th>
            <th>{{ __('متأخّرة') }}</th>
            <th>{{ __('الأدلة') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($project->tasks as $task)
            @php $row = $tl[$task->id] ?? []; @endphp
            <tr>
              <td>{{ $task->id }}</td>
              <td>{{ $task->title }}</td>
              <td>{{ $task->status }}</td>
              <td>{{ (int) $task->progress }}%</td>
              <td>{{ $row['planned_start'] ?? '—' }}</td>
              <td>{{ $row['planned_end'] ?? '—' }}</td>
              <td>@if(!empty($row['is_critical']))<span class="a2-badge">{{ __('نعم') }}</span>@else — @endif</td>
              <td>@if(!empty($row['is_overdue']))<span class="a2-badge a2-badge-danger">{{ __('نعم') }}</span>@else — @endif</td>
              <td>
                @forelse($task->photos as $photo)
                  <a href="{{ asset($photo->image) }}" target="_blank" rel="noopener"
                     title="{{ $photo->source === \App\Models\Image::SOURCE_CAMERA ? __('كاميرا') : __('مرفوعة') }}">
                    <img src="{{ asset($photo->image) }}" alt="" style="height:40px;width:40px;object-fit:cover;border-radius:6px;margin:2px;border:1px solid var(--a2-border,#ddd);" />
                  </a>
                @empty
                  <span class="a2-hint">—</span>
                @endforelse
              </td>
            </tr>
          @empty
            <tr><td colspan="9" class="a2-empty">{{ __('لا توجد مراحل.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="a2-card" style="margin-top:12px;">
    <h3 class="a2-title">{{ __('المتابعون') }}</h3>
    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>{{ __('المستخدم') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('مستوى الوصول') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($project->followers as $follower)
            <tr>
              <td>{{ optional($follower->user)->name ?? ('#' . $follower->user_id) }}</td>
              <td>{{ $follower->status }}</td>
              <td>{{ $follower->access_level }}</td>
            </tr>
          @empty
            <tr><td colspan="3" class="a2-empty">{{ __('لا يوجد متابعون.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
