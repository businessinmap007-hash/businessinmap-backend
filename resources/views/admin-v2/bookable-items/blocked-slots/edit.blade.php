@extends('admin-v2.layouts.master')

@section('title', 'Edit Blocked Slot')
@section('body_class', 'admin-v2-bookable-blocked-slot-edit')

@section('content')
@include('admin-v2.bookable-items.partials.tabs', ['item' => $item])

<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">تعديل فترة الغلق</h1>
            <div class="a2-page-subtitle">
                {{ $bookableItem->title }} — Slot #{{ $slot->id }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a href="{{ route('admin.bookable-items.calendar', ['bookableItem' => $bookableItem->id]) }}"
               class="a2-btn a2-btn-ghost">
                العودة إلى التقويم
            </a>

            <a href="{{ route('admin.bookable-items.blocked-slots.index', $bookableItem) }}"
               class="a2-btn a2-btn-ghost">
                كل فترات الغلق
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="a2-alert a2-alert-danger" style="margin-bottom:16px;">
            <strong>يوجد أخطاء في البيانات:</strong>
            <ul style="margin:8px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="a2-bookslot-edit-layout">
        <div class="a2-card">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">بيانات فترة الغلق</div>
                    <div class="a2-card-sub">يمكنك تعديل التاريخ والسبب والحالة</div>
                </div>
            </div>

            <form method="POST"
                  action="{{ route('admin.bookable-items.blocked-slots.update', ['bookableItem' => $bookableItem->id, 'slot' => $slot->id]) }}"
                  class="a2-form">
                @csrf
                @method('PUT')

                @include('admin-v2.bookable-items.blocked-slots._form', [
                    'bookableItem' => $bookableItem,
                    'slot' => $slot,
                    'submitLabel' => 'حفظ التعديلات'
                ])
            </form>
        </div>

        <div class="a2-card">
            <div class="a2-card-head">
                <div>
                    <div class="a2-card-title">إجراءات</div>
                    <div class="a2-card-sub">إدارة سريعة للفترة الحالية</div>
                </div>
            </div>

            <div class="a2-stack" style="display:flex;flex-direction:column;gap:12px;">
                <a href="{{ route('admin.bookable-items.calendar', ['bookableItem' => $bookableItem->id]) }}"
                   class="a2-btn a2-btn-primary a2-btn-block">
                    فتح التقويم
                </a>

                <a href="{{ route('admin.bookable-items.blocked-slots.index', $bookableItem) }}"
                   class="a2-btn a2-btn-ghost a2-btn-block">
                    عرض جميع فترات الغلق
                </a>

                <form method="POST"
                      action="{{ route('admin.bookable-items.blocked-slots.destroy', ['bookableItem' => $bookableItem->id, 'slot' => $slot->id]) }}"
                      onsubmit="return confirm('هل أنت متأكد من حذف فترة الغلق؟');">
                    @csrf
                    @method('DELETE')

                    <button type="submit" class="a2-btn a2-btn-danger a2-btn-block">
                        حذف فترة الغلق
                    </button>
                </form>
            </div>

            <div class="a2-divider"></div>

            <div class="a2-kv">
                <div class="a2-kv-row">
                    <span class="a2-kv-key">ID</span>
                    <span class="a2-kv-value">#{{ $slot->id }}</span>
                </div>
                <div class="a2-kv-row">
                    <span class="a2-kv-key">Type</span>
                    <span class="a2-kv-value">{{ $slot->block_type }}</span>
                </div>
                <div class="a2-kv-row">
                    <span class="a2-kv-key">Status</span>
                    <span class="a2-kv-value">
                        @if($slot->is_active)
                            <span class="a2-pill a2-pill-success">Active</span>
                        @else
                            <span class="a2-pill a2-pill-inactive">Inactive</span>
                        @endif
                    </span>
                </div>
                <div class="a2-kv-row">
                    <span class="a2-kv-key">Created</span>
                    <span class="a2-kv-value">{{ optional($slot->created_at)->format('Y-m-d H:i') ?: '—' }}</span>
                </div>
                <div class="a2-kv-row">
                    <span class="a2-kv-key">Updated</span>
                    <span class="a2-kv-value">{{ optional($slot->updated_at)->format('Y-m-d H:i') ?: '—' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>


@endsection