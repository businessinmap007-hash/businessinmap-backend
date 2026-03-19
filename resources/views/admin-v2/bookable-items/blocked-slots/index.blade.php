@extends('admin-v2.layouts.master')

@section('title', 'Blocked Slots')
@section('body_class', 'admin-v2-bookable-blocked-slots')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item' => $item])

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">الفترات المحجوبة</h1>
            <div class="a2-page-subtitle">
                {{ $item->title }} — إدارة فترات الغلق والحجب
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.index') }}">رجوع</a>

            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.calendar', $item) }}">
                التقويم
            </a>

            <a class="a2-btn a2-btn-primary" href="{{ route('admin.bookable-items.blocked-slots.create', $item) }}">
                + إضافة غلق
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="a2-alert a2-alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="a2-alert a2-alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="a2-card a2-card--section">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">ملخص العنصر</div>
                <div class="a2-card-sub">معلومات سريعة عن العنصر الذي يتم إدارة غلقه</div>
            </div>
        </div>

        <div class="a2-form-grid">
            <div><strong>Title:</strong> {{ $item->title ?: '—' }}</div>
            <div><strong>Type:</strong> <span dir="ltr">{{ $item->item_type ?: '—' }}</span></div>
            <div><strong>Code:</strong> <span dir="ltr">{{ $item->code ?: '—' }}</span></div>
            <div><strong>Price:</strong> {{ number_format((float) ($item->price ?? 0), 2) }}</div>
        </div>
    </div>

    <div class="a2-card" style="margin-top:16px;">
        <div class="a2-card-head">
            <div>
                <div class="a2-card-title">قائمة الفترات المحجوبة</div>
                <div class="a2-card-sub">
                    @if(method_exists($slots, 'total'))
                        إجمالي السجلات: {{ $slots->total() }}
                    @else
                        عرض فترات الغلق المسجلة لهذا العنصر
                    @endif
                </div>
            </div>
        </div>

        <div class="a2-table-wrap">
            <table class="a2-table">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th style="width:130px;">Type</th>
                        <th>Reason</th>
                        <th style="width:190px;">Starts At</th>
                        <th style="width:190px;">Ends At</th>
                        <th style="width:100px;">Active</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($slots as $slot)
                        <tr>
                            <td>{{ $slot->id }}</td>

                            <td>
                                <span dir="ltr">{{ $slot->block_type ?: '-' }}</span>
                            </td>

                            <td>
                                <span class="a2-clip" title="{{ $slot->reason }}">
                                    {{ $slot->reason ?: '-' }}
                                </span>
                            </td>

                            <td dir="ltr">
                                {{ optional($slot->starts_at)->format('Y-m-d H:i') ?: '-' }}
                            </td>

                            <td dir="ltr">
                                {{ optional($slot->ends_at)->format('Y-m-d H:i') ?: '-' }}
                            </td>

                            <td>
                                @if($slot->is_active)
                                    <span class="a2-pill a2-pill-active">Yes</span>
                                @else
                                    <span class="a2-pill a2-pill-gray">No</span>
                                @endif
                            </td>

                            <td>
                                <div class="a2-actions">
                                    <a class="a2-btn a2-btn-ghost a2-btn-sm"href="{{ route('admin.bookable-items.blocked-slots.edit', [$item, $slot]) }}"> Edit</a>
                                    <form method="POST"
                                          action="{{ route('admin.bookable-items.blocked-slots.destroy', [$item, $slot]) }}"
                                          onsubmit="return confirm('حذف فترة الغلق؟')"
                                          style="margin:0;">
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

        @if(method_exists($slots, 'links'))
            <div class="a2-mt-12">
                {{ $slots->links() }}
            </div>
        @endif
    </div>
</div>
@endsection