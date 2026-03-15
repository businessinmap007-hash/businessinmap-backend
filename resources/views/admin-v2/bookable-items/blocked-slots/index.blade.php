@extends('admin-v2.layouts.master')

@section('title', 'Blocked Slots')
@section('body_class', 'admin-v2-bookable-blocked-slots')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item'=>$item])

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">Blocked Slots</h1>
            <div class="a2-page-subtitle">
                {{ $item->title }} — إدارة فترات الغلق
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.index') }}">رجوع</a>
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.calendar', $item) }}">Calendar</a>
            <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookable-items.blocked-slots.create', $item) }}">
                + إضافة غلق
            </a>
        </div>
    </div>

    <div class="a2-card">
        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Reason</th>
                        <th>Starts At</th>
                        <th>Ends At</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($slots as $slot)
                        <tr>
                            <td>{{ $slot->id }}</td>
                            <td>{{ $slot->block_type ?: '-' }}</td>
                            <td>
                                <span class="a2-clip" title="{{ $slot->reason }}">
                                    {{ $slot->reason ?: '-' }}
                                </span>
                            </td>
                            <td dir="ltr">{{ optional($slot->starts_at)->format('Y-m-d H:i') ?: '-' }}</td>
                            <td dir="ltr">{{ optional($slot->ends_at)->format('Y-m-d H:i') ?: '-' }}</td>
                            <td>
                                @if($slot->is_active)
                                    <span class="a2-pill a2-pill-active">Yes</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">No</span>
                                @endif
                            </td>
                            <td>
                                <div class="a2-actions">
                                    <form method="POST"
                                          action="{{ route('admin.bookable-items.blocked-slots.destroy', [$item, $slot]) }}"
                                          onsubmit="return confirm('حذف فترة الغلق؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="a2-btn a2-btn-danger a2-btn-sm" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="a2-empty-cell">لا توجد فترات غلق</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="a2-mt-12">
            {{ $slots->links() }}
        </div>
    </div>
</div>
@endsection
