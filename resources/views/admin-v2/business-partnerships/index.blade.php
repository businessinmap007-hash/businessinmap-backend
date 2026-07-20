@extends('admin-v2.layouts.master')

@section('title', 'Business Partnerships')
@section('topbar_title', 'Business Partnerships')
@section('body_class', 'admin-v2-business-partnerships')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">{{ __('شراكات البزنس') }}</h1>
            <div class="a2-page-subtitle">{{ __('ربط الفنادق بشركات السياحة أو أي بزنس ببزنس لإدارة الحصص والعروض.') }}</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.business-partnerships.create') }}" class="a2-btn a2-btn-primary">{{ __('إنشاء شراكة') }}</a>
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
            <div class="a2-stat-label">{{ __('الكل') }}</div>
            <div class="a2-stat-value">{{ number_format($totals['all'] ?? 0) }}</div>
            <div class="a2-stat-note">{{ __('إجمالي الشراكات') }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Active</div>
            <div class="a2-stat-value">{{ number_format($totals['active'] ?? 0) }}</div>
            <div class="a2-stat-note">{{ __('شراكات فعالة') }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Pending</div>
            <div class="a2-stat-value">{{ number_format($totals['pending'] ?? 0) }}</div>
            <div class="a2-stat-note">{{ __('بانتظار الموافقة') }}</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Paused</div>
            <div class="a2-stat-value">{{ number_format($totals['paused'] ?? 0) }}</div>
            <div class="a2-stat-note">{{ __('موقوفة مؤقتًا') }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.business-partnerships.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="{{ __('بحث باسم الفندق / الشركة / ID') }}">

            <select class="a2-select a2-filter-sm" name="relationship_type">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach(\App\Models\BusinessPartnership::relationshipTypes() as $key => $label)
                    <option value="{{ $key }}" {{ $relationshipType === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="status">
                <option value="">{{ __('كل الحالات') }}</option>
                @foreach(\App\Models\BusinessPartnership::statuses() as $key => $label)
                    <option value="{{ $key }}" {{ $status === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" {{ (int) $perPage === $n ? 'selected' : '' }}>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">{{ __('تطبيق') }}</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.business-partnerships.index') }}">{{ __('إعادة ضبط') }}</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Owner</th>
                        <th>Partner</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Period</th>
                        <th>Allocations</th>
                        <th>{{ __('إجراءات') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>
                                <div class="a2-fw-900">{{ optional($row->ownerBusiness)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $row->owner_business_id }}</div>
                            </td>
                            <td>
                                <div class="a2-fw-900">{{ optional($row->partnerBusiness)->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $row->partner_business_id }}</div>
                            </td>
                            <td><span class="a2-pill a2-pill-gray">{{ $row->relationship_type }}</span></td>
                            <td>
                                <span class="a2-pill {{ $row->status === 'active' ? 'a2-pill-success' : ($row->status === 'paused' ? 'a2-pill-warning' : 'a2-pill-gray') }}">
                                    {{ $row->status }}
                                </span>
                            </td>
                            <td>
                                <div>{{ $row->starts_at ? $row->starts_at->format('Y-m-d') : '—' }}</div>
                                <div class="a2-muted">{{ $row->ends_at ? $row->ends_at->format('Y-m-d') : '—' }}</div>
                            </td>
                            <td>{{ (int) $row->allocations_count }}</td>
                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-sm a2-btn-ghost" href="{{ route('admin.business-partnerships.edit', $row->id) }}">{{ __('تعديل') }}</a>

                                    @if($row->status !== 'active')
                                        <form method="POST" action="{{ route('admin.business-partnerships.activate', $row->id) }}">
                                            @csrf
                                            <button class="a2-btn a2-btn-sm a2-btn-primary" type="submit">{{ __('تفعيل') }}</button>
                                        </form>
                                    @endif

                                    @if($row->status === 'active')
                                        <form method="POST" action="{{ route('admin.business-partnerships.pause', $row->id) }}">
                                            @csrf
                                            <button class="a2-btn a2-btn-sm a2-btn-warning" type="submit">{{ __('إيقاف') }}</button>
                                        </form>
                                    @endif

                                    @if((int) $row->allocations_count === 0)
                                        <form method="POST" action="{{ route('admin.business-partnerships.destroy', $row->id) }}" onsubmit="return confirm('حذف الشراكة؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="a2-btn a2-btn-sm a2-btn-danger" type="submit">{{ __('حذف') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="a2-empty-cell">{{ __('لا توجد شراكات.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-pagination">
            {{ $rows->links() }}
        </div>
    </div>
</div>
@endsection
