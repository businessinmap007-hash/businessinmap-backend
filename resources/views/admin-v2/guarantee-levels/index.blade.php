@extends('admin-v2.layouts.master')

@section('title', 'Guarantee Levels')
@section('topbar_title', 'Guarantee Levels')
@section('body_class', 'admin-v2-guarantee-levels')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('مستويات الضمان') }}</h1>
            <div class="a2-page-subtitle">{{ __('إدارة مستويات الضمان، قدرة التغطية، الرصيد المطلوب، وشروط التأهيل.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.guarantee-levels.create') }}" class="a2-btn a2-btn-primary">{{ __('إنشاء مستوى ضمان') }}</a>
            <a href="{{ route('admin.guarantees.index') }}" class="a2-btn a2-btn-ghost">{{ __('ضمانات المستخدمين') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('إجمالي المستويات') }}</div>
            <div class="a2-stat-value">{{ number_format($totals['count'] ?? 0) }}</div>
            <div class="a2-stat-note">{{ __('كل مستويات الضمان') }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">{{ __('المفعلة') }}</div>
            <div class="a2-stat-value">{{ number_format($totals['active'] ?? 0) }}</div>
            <div class="a2-stat-note">{{ __('متاحة للترقية والتفعيل') }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Client</div>
            <div class="a2-stat-value">{{ number_format($totals['client'] ?? 0) }}</div>
            <div class="a2-stat-note">{{ __('مستويات العملاء') }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Business</div>
            <div class="a2-stat-value">{{ number_format($totals['business'] ?? 0) }}</div>
            <div class="a2-stat-note">{{ __('مستويات أصحاب الأعمال') }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.guarantee-levels.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="{{ __('بحث بالكود / الاسم / رقم المستوى') }}">

            <select class="a2-select a2-filter-sm" name="target_type">
                <option value="">{{ __('كل الأنواع') }}</option>
                <option value="client" {{ $targetType === 'client' ? 'selected' : '' }}>Client</option>
                <option value="business" {{ $targetType === 'business' ? 'selected' : '' }}>Business</option>
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="">{{ __('كل الحالات') }}</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>{{ __('مفعل') }}</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>{{ __('معطل') }}</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === (int) $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.guarantee-levels.index') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>{{ __('الاسم') }}</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Locked</th>
                        <th>Pending Coverage</th>
                        <th>Active Coverage</th>
                        <th>Ops / Score</th>
                        <th>Disputes / Cancel</th>
                        <th>Linked</th>
                        <th>{{ __('إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($levels as $level)
                        <tr>
                            <td>{{ $level->id }}</td>
                            <td><span class="a2-pill a2-pill-gray">{{ $level->code }}</span></td>
                            <td class="a2-text-right">
                                <div class="a2-fw-900">{{ $level->display_name }}</div>
                                <div class="a2-muted">{{ $level->name_en ?: '—' }}</div>
                            </td>
                            <td>{{ $level->target_type }}</td>
                            <td>
                                <span class="a2-pill {{ $level->is_active ? 'a2-pill-success' : 'a2-pill-danger' }}">
                                    {{ $level->is_active ? 'مفعل' : 'معطل' }}
                                </span>
                            </td>
                            <td>{{ (int) $level->priority }}</td>
                            <td>{{ number_format((float) $level->required_locked_amount, 2) }}</td>
                            <td>{{ number_format((float) $level->pending_coverage_amount, 2) }}</td>
                            <td>{{ number_format((float) $level->active_coverage_amount, 2) }}</td>
                            <td>{{ (int) $level->required_completed_operations }} / {{ number_format((float) $level->required_trust_score, 2) }}</td>
                            <td>{{ $level->max_lost_disputes === null ? '—' : (int) $level->max_lost_disputes }} / {{ $level->max_late_cancellations === null ? '—' : (int) $level->max_late_cancellations }}</td>
                            <td>{{ (int) $level->purchased_guarantees_count }} / {{ (int) $level->effective_guarantees_count }}</td>
                            <td>
                                <div class="a2-actions">
                                    <a href="{{ route('admin.guarantee-levels.edit', $level->id) }}" class="a2-btn a2-btn-sm a2-btn-ghost">{{ __('تعديل') }}</a>

                                    <form method="POST" action="{{ route('admin.guarantee-levels.toggle', $level->id) }}">
                                        @csrf
                                        <button type="submit" class="a2-btn a2-btn-sm {{ $level->is_active ? 'a2-btn-danger' : 'a2-btn-success' }}">
                                            {{ $level->is_active ? 'تعطيل' : 'تفعيل' }}
                                        </button>
                                    </form>

                                    @if((int) $level->purchased_guarantees_count === 0 && (int) $level->effective_guarantees_count === 0)
                                        <form method="POST" action="{{ route('admin.guarantee-levels.destroy', $level->id) }}" onsubmit="return confirm('حذف مستوى الضمان؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="a2-btn a2-btn-sm a2-btn-danger">{{ __('حذف') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="a2-empty-cell">{{ __('لا توجد مستويات ضمان.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-pagination">
            {{ $levels->links() }}
        </div>
    </div>
</div>
@endsection
