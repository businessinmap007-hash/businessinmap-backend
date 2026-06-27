@extends('admin-v2.layouts.master')

@section('title', 'Guarantees')
@section('topbar_title', 'Guarantee Engine')
@section('body_class', 'admin-v2-guarantees')

@section('content')
@php
    $statusClass = function (?string $status) {
        return match ((string) $status) {
            'active' => 'a2-pill-success',
            'pending_operations' => 'a2-pill-warning',
            'underfunded' => 'a2-pill-warning',
            'suspended', 'cancelled' => 'a2-pill-danger',
            default => 'a2-pill-gray',
        };
    };

    $statusLabel = function (?string $status) {
        return match ((string) $status) {
            'active' => 'نشط',
            'pending_operations' => 'بانتظار عمليات',
            'underfunded' => 'رصيد ضمان ناقص',
            'suspended' => 'معلق',
            'cancelled' => 'ملغي',
            'downgraded' => 'تم التخفيض',
            default => $status ?: '—',
        };
    };
@endphp

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">محرك الضمان</h1>
            <div class="a2-page-subtitle">متابعة ضمانات العملاء والأعمال، مستويات التغطية، الرصيد المجمد، Grace وExpiration.</div>
        </div>
        <div class="a2-page-actions">
            <a href="{{ route('admin.wallet-ops.recharge.form') }}" class="a2-btn a2-btn-primary">شحن محفظة / ترقية ضمان</a>
        </div>
    </div>

    <div class="a2-stat-grid a2-mb-16">
        <div class="a2-stat-card">
            <div class="a2-stat-label">إجمالي الضمانات</div>
            <div class="a2-stat-value">{{ number_format($totals['count']) }}</div>
            <div class="a2-stat-note">حسب الفلاتر الحالية</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">نشط / Pending</div>
            <div class="a2-stat-value">{{ number_format($totals['active']) }} / {{ number_format($totals['pending']) }}</div>
            <div class="a2-stat-note">تغطية كاملة أو مؤقتة</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">Underfunded / Suspended</div>
            <div class="a2-stat-value">{{ number_format($totals['underfunded']) }} / {{ number_format($totals['suspended']) }}</div>
            <div class="a2-stat-note">تحتاج مراجعة أو انتظار Grace</div>
        </div>
        <div class="a2-stat-card">
            <div class="a2-stat-label">الرصيد المجمد / التغطية</div>
            <div class="a2-stat-value">{{ number_format($totals['locked_sum'], 2) }}</div>
            <div class="a2-stat-note">Coverage: {{ number_format($totals['coverage_sum'], 2) }} | Used: {{ number_format($totals['used_sum'], 2) }}</div>
        </div>
    </div>

    <div class="a2-card a2-card--tight">
        <form method="GET" action="{{ route('admin.guarantees.index') }}" class="a2-filterbar">
            <input class="a2-input a2-filter-search" type="search" name="q" value="{{ $q }}" placeholder="بحث بالاسم / الهاتف / البريد / رقم الضمان / المستوى">

            <select class="a2-select a2-filter-md" name="status">
                <option value="">كل الحالات</option>
                @foreach(['active' => 'نشط', 'pending_operations' => 'بانتظار عمليات', 'underfunded' => 'Underfunded', 'suspended' => 'معلق', 'cancelled' => 'ملغي'] as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-sm" name="target_type">
                <option value="">كل الأنواع</option>
                <option value="client" @selected($targetType === 'client')>Client</option>
                <option value="business" @selected($targetType === 'business')>Business</option>
            </select>

            <select class="a2-select a2-filter-md" name="level_id">
                <option value="0">كل المستويات</option>
                @foreach($levels as $level)
                    <option value="{{ $level->id }}" @selected((int) $levelId === (int) $level->id)>
                        {{ $level->display_name }} — {{ $level->target_type }}
                    </option>
                @endforeach
            </select>

            <select class="a2-select a2-filter-md" name="expires">
                <option value="">Expiration</option>
                <option value="has_expiration" @selected($expires === 'has_expiration')>له تاريخ انتهاء</option>
                <option value="expired" @selected($expires === 'expired')>منتهي</option>
                <option value="missing" @selected($expires === 'missing')>بدون تاريخ</option>
            </select>

            <select class="a2-select a2-filter-sm" name="per_page">
                @foreach([10,20,50,100] as $n)
                    <option value="{{ $n }}" @selected((int) $perPage === $n)>{{ $n }}</option>
                @endforeach
            </select>

            <div class="a2-filter-actions">
                <button class="a2-btn a2-btn-primary" type="submit">تطبيق</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.guarantees.index') }}">إعادة ضبط</a>
            </div>
        </form>
    </div>

    <div class="a2-card a2-card--tight">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المستخدم</th>
                        <th>النوع</th>
                        <th>الحالة</th>
                        <th>Purchased</th>
                        <th>Effective</th>
                        <th>Locked</th>
                        <th>Coverage</th>
                        <th>Used</th>
                        <th>Score</th>
                        <th>Ops</th>
                        <th>Grace</th>
                        <th>آخر تحديث</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($guarantees as $guarantee)
                        <tr>
                            <td>{{ $guarantee->id }}</td>
                            <td class="a2-text-right">
                                <div class="a2-fw-900 a2-clip a2-clip--name">{{ $guarantee->user?->name ?: '—' }}</div>
                                <div class="a2-muted">#{{ $guarantee->user_id }}</div>
                            </td>
                            <td><span class="a2-pill a2-pill-gray">{{ $guarantee->target_type }}</span></td>
                            <td><span class="a2-pill {{ $statusClass($guarantee->status) }}">{{ $statusLabel($guarantee->status) }}</span></td>
                            <td>{{ $guarantee->purchasedLevel?->display_name ?: '—' }}</td>
                            <td>{{ $guarantee->effectiveLevel?->display_name ?: '—' }}</td>
                            <td>{{ number_format((float) $guarantee->locked_amount, 2) }}</td>
                            <td>{{ number_format((float) $guarantee->current_coverage_amount, 2) }}</td>
                            <td>{{ number_format((float) $guarantee->used_coverage_amount, 2) }}</td>
                            <td>{{ number_format((float) $guarantee->trust_score, 2) }}</td>
                            <td>{{ (int) $guarantee->completed_operations_count }}</td>
                            <td>{{ $guarantee->grace_until ? $guarantee->grace_until->format('Y-m-d H:i') : '—' }}</td>
                            <td>{{ $guarantee->updated_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                <div class="a2-actions">
                                    <a href="{{ route('admin.guarantees.show', $guarantee) }}" class="a2-btn a2-btn-sm a2-btn-ghost">عرض</a>
                                    @if($guarantee->user)
                                        <a href="{{ route('admin.users.show', $guarantee->user) }}" class="a2-btn a2-btn-sm a2-btn-ghost">المستخدم</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="a2-empty-cell">لا توجد ضمانات مطابقة للفلاتر.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
