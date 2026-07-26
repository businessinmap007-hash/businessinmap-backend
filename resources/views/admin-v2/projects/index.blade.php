@extends('admin-v2.layouts.master')

@section('title','Projects')
@section('body_class','admin-v2-projects')

@section('content')
<div class="a2-page">
  <div class="a2-card">
    <div class="a2-header">
      <div>
        <h2 class="a2-title">{{ __('مشاريع الأعمال (إشراف)') }}</h2>
        <div class="a2-hint">
          {{ __('إشراف للقراءة فقط على خرائط المشاريع الزمنية لكل الأنشطة (التصنيع/الإنشاءات): المراحل، نِسب الإكمال، وأدلة الكاميرا. لا يمكن التعديل من هنا.') }}
        </div>
      </div>
    </div>

    <form method="GET" class="a2-filters" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">
      <input class="a2-input" type="text" name="q" value="{{ $filters['q'] }}"
             placeholder="{{ __('بحث: العنوان أو المرجع أو النشاط') }}" />
      <select class="a2-input" name="status">
        <option value="">{{ __('كل الحالات') }}</option>
        @foreach($statuses as $s)
          <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ $s }}</option>
        @endforeach
      </select>
      <select class="a2-input" name="visibility">
        <option value="">{{ __('كل مستويات الظهور') }}</option>
        @foreach($visibilities as $v)
          <option value="{{ $v }}" @selected($filters['visibility'] === $v)>{{ $v }}</option>
        @endforeach
      </select>
      <button class="a2-btn" type="submit">{{ __('تصفية') }}</button>
    </form>

    <div class="a2-table-wrap">
      <table class="a2-table">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('المشروع') }}</th>
            <th>{{ __('النشاط') }}</th>
            <th>{{ __('الحالة') }}</th>
            <th>{{ __('الظهور') }}</th>
            <th>{{ __('التقدّم') }}</th>
            <th>{{ __('المهام') }}</th>
            <th>{{ __('المتابعون') }}</th>
            <th>{{ __('إجراء') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $project)
            <tr>
              <td>{{ $project->id }}</td>
              <td>
                {{ $project->title }}
                @if($project->reference)<div class="a2-hint">{{ $project->reference }}</div>@endif
              </td>
              <td>{{ optional($project->business)->name ?? '—' }}</td>
              <td>{{ $project->status }}</td>
              <td>{{ $project->visibility }}</td>
              <td>{{ (int) $project->progress }}%</td>
              <td>{{ $project->tasks_count }}</td>
              <td>{{ $project->followers_count }}</td>
              <td>
                <a class="a2-btn" href="{{ route('admin.projects.show', $project) }}">{{ __('عرض') }}</a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="a2-empty">{{ __('لا توجد مشاريع.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div style="margin-top:12px;">{{ $rows->links() }}</div>
  </div>
</div>
@endsection
