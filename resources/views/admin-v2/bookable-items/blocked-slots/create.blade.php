@extends('admin-v2.layouts.master')

@section('title', 'Create Blocked Slot')
@section('body_class', 'admin-v2-bookable-blocked-slot-create')

@section('content')
<div class="a2-page">
    <div class="a2-page-head">
        <div>
            <h1 class="a2-page-title">إضافة فترة غلق</h1>
            <div class="a2-page-subtitle">
                {{ $item->title }}
            </div>
        </div>

        <div class="a2-page-actions">
            <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.blocked-slots.index', $item) }}">
                رجوع
            </a>
        </div>
    </div>

    <div class="a2-card a2-page-narrow">
        @if ($errors->any())
            <div class="a2-alert a2-alert-danger a2-mb-12">
                <div class="a2-fw-900 a2-mb-8">يوجد أخطاء</div>
                <ul class="a2-errors-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.bookable-items.blocked-slots.store', $item) }}">
            @csrf

            <div class="a2-form-grid">
                <div class="a2-form-group">
                    <label class="a2-label">Type</label>
                    <select class="a2-select" name="block_type" required>
                        <option value="manual" @selected(old('block_type') === 'manual')>manual</option>
                        <option value="maintenance" @selected(old('block_type') === 'maintenance')>maintenance</option>
                        <option value="holiday" @selected(old('block_type') === 'holiday')>holiday</option>
                        <option value="admin" @selected(old('block_type') === 'admin')>admin</option>
                    </select>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Reason</label>
                    <input class="a2-input" name="reason" value="{{ old('reason') }}" placeholder="سبب الغلق">
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Starts At</label>
                    <input class="a2-input" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required>
                </div>

                <div class="a2-form-group">
                    <label class="a2-label">Ends At</label>
                    <input class="a2-input" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required>
                </div>

                <div class="a2-form-group a2-field-full">
                    <label class="a2-label">Notes</label>
                    <textarea class="a2-textarea" name="notes" rows="4">{{ old('notes') }}</textarea>
                </div>
            </div>

            <div class="a2-actionsbar a2-mt-16">
                <button class="a2-btn a2-btn-primary" type="submit">حفظ</button>
                <a class="a2-btn a2-btn-ghost" href="{{ route('admin.bookable-items.blocked-slots.index', $item) }}">إلغاء</a>
            </div>
        </form>
    </div>
</div>
@endsection
