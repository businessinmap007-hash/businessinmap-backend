@extends('business.layouts.master')

@section('title', __('سجل نشاط الموظفين'))

@section('content')
@php
    $capabilityLabels = collect(\App\Support\BusinessCapability::registry())
        ->map(fn ($pair) => $pair[0]);

    $actionLabels = [
        'accepted' => 'قبِل',
        'rejected' => 'رفض',
        'preparing' => 'بدأ التجهيز',
        'ready' => 'جهّز',
        'completed' => 'أكمل',
        'started' => 'بدأ التنفيذ',
        'confirmed' => 'أكّد الاستعداد',
        'item_unavailable' => 'أبلغ عن نفاد صنف',
    ];

    $subjectLabels = [
        \App\Models\Order::class => 'طلب',
        \App\Models\Booking::class => 'حجز',
    ];
@endphp

<div class="a2-page-head">
    <div>
        <h1 class="a2-page-title">{{ __('سجل نشاط الموظفين') }}</h1>
        <div class="a2-page-subtitle">{{ __('من عمل ماذا — لمراجعة الشفت أو أي فترة، لكل موظف على حدة أو للجميع.') }}</div>
    </div>
    <div class="a2-page-actions">
        <a href="{{ route('business.staff.index') }}" class="a2-btn a2-btn-ghost">{{ __('الموظفون') }}</a>
    </div>
</div>

<div class="a2-card a2-card--section">
    <form method="GET" action="{{ route('business.staff.activity') }}" class="a2-filterbar">
        <div class="a2-filter-sm">
            <label class="a2-label" for="from">{{ __('من') }}</label>
            <input type="date" id="from" name="from" class="a2-input" value="{{ $from }}">
        </div>
        <div class="a2-filter-sm">
            <label class="a2-label" for="to">{{ __('إلى') }}</label>
            <input type="date" id="to" name="to" class="a2-input" value="{{ $to }}">
        </div>
        <div class="a2-filter-md">
            <label class="a2-label" for="user_id">{{ __('الموظف') }}</label>
            <select id="user_id" name="user_id" class="a2-select">
                <option value="">{{ __('الجميع') }}</option>
                @foreach($actors as $actor)
                    <option value="{{ $actor->id }}" @selected($selectedUserId === (int) $actor->id)>
                        {{ $actor->name }} @if($actor->id === auth()->id()) ({{ __('صاحب النشاط') }}) @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div class="a2-filter-actions">
            <button type="submit" class="a2-btn a2-btn-primary">{{ __('فلترة') }}</button>
            <a href="{{ route('business.staff.activity') }}" class="a2-btn a2-btn-ghost">{{ __('اليوم') }}</a>
        </div>
    </form>

    <div class="a2-stat-grid a2-mt-16">
        @foreach($summary as $entry)
            <div class="a2-stat-card">
                <div class="a2-stat-label">
                    {{ $entry['user']->name }}
                    @if($entry['user']->id === auth()->id())
                        <span class="a2-pill a2-pill-sub">{{ __('صاحب النشاط') }}</span>
                    @endif
                </div>
                <div class="a2-stat-value">{{ $entry['count'] }}</div>
                <div class="a2-stat-note">{{ __('عملية في هذه الفترة') }}</div>
            </div>
        @endforeach
    </div>

    <div class="a2-table-wrap a2-mt-16">
        <table class="a2-table">
            <thead>
                <tr>
                    <th>{{ __('الوقت') }}</th>
                    <th>{{ __('الموظف') }}</th>
                    <th>{{ __('القسم') }}</th>
                    <th>{{ __('الإجراء') }}</th>
                    <th>{{ __('العنصر') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td dir="ltr">{{ $row->created_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            {{ optional($row->user)->name ?? ('#' . $row->user_id) }}
                            @if((int) $row->user_id === (int) auth()->id())
                                <span class="a2-pill a2-pill-sub">{{ __('صاحب النشاط') }}</span>
                            @endif
                        </td>
                        <td>{{ $capabilityLabels[$row->capability] ?? $row->capability }}</td>
                        <td>{{ $actionLabels[$row->action] ?? $row->action }}</td>
                        <td dir="ltr">{{ ($subjectLabels[$row->subject_type] ?? class_basename($row->subject_type)) }} #{{ $row->subject_id }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="a2-empty">{{ __('لا يوجد نشاط في هذه الفترة.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="a2-mt-16">{{ $rows->links() }}</div>
</div>
@endsection
